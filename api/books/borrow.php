<?php
// api/books/borrow.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__.'/../db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || !isset($_SESSION['user_name']) || empty($_SESSION['user_name']) || !isset($_SESSION['user_role']) || empty($_SESSION['user_role'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Connectez-vous ou créer un compte.',
    ]);
    exit();
}

$current_user_id = $_SESSION['user_id']; // ID de l'utilisateur (de la table 'users')
$current_lecteur_id = null; // Sera récupéré à partir de $current_user_id
$subscription_end_date = null; // Date de fin de l'abonnement du lecteur

try {
    // Récupérer le lecteur_id et la date de fin de l'abonnement basé sur le user_id authentifié
    $stmt = $pdo->prepare("
        SELECT 
            l.id AS lecteur_id,
            MAX(a.date_fin) AS last_subscription_end_date,
            a.statut AS subscription_status
        FROM lecteurs l 
        LEFT JOIN abonnements a ON l.id = a.lecteur_id AND a.statut = 'actif'
        WHERE l.user_id = :user_id
        GROUP BY l.id, a.statut
    ");
    $stmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $lecteur_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lecteur_data) {
        $current_lecteur_id = $lecteur_data['lecteur_id'];
        $subscription_end_date = $lecteur_data['last_subscription_end_date'];
        $subscription_status = $lecteur_data['subscription_status'];
    } else {
        http_response_code(403); // Forbidden
        echo json_encode(["success" => false, "message" => "L'utilisateur n'est pas enregistré comme lecteur."]);
        exit();
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erreur lors de la vérification du lecteur ou de l'abonnement: " . $e->getMessage()]);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $book_id = isset($_POST['bookId']) ? $_POST['bookId'] : null;

    if ($book_id === null || !is_numeric($book_id)) {
        echo json_encode(["success" => false, "message" => "ID du livre manquant ou invalide." ]);
        return;
    }

    try {
        // 1. Vérifier si le livre est disponible
        $stmt = $pdo->prepare("SELECT disponible FROM livres WHERE id = :book_id");
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->execute();
        $book = $stmt->fetch();

        if (!$book || !$book['disponible']) {
            http_response_code(409); // Conflict
            echo json_encode(["success" => false, "message" => "Ce livre n'est pas disponible pour l'emprunt."]);
            return;
        }

        $date_emprunt = time(); // Timestamp Unix actuel
        $default_return_period_seconds = 15 * 24 * 3600; // 15 jours en secondes
        $suggested_date_retour = $date_emprunt + $default_return_period_seconds;

        // Vérifier l'abonnement du lecteur
        if ($subscription_end_date === null || $subscription_end_date < $date_emprunt) {
            // Pas d'abonnement actif ou abonnement expiré
            echo json_encode([
                "success" => false,
                "message" => "Vous n'avez pas d'abonnement actif ou votre abonnement est expiré. Veuillez contacter un administrateur pour renouveler votre abonnement.",
                "action" => "contact_admin"
            ]);
            return;
        }

        // Calculer la date de retour : 15 jours ou fin de l'abonnement si moins de 2 semaines
        $two_weeks_in_seconds = 14 * 24 * 3600;
        
        if (($subscription_end_date - $date_emprunt) < $two_weeks_in_seconds) {
            // Si la durée restante de l'abonnement est inférieure à 2 semaines
            $date_retour = $subscription_end_date;
            echo json_encode([
                "success" => true, 
                "message" => "Livre emprunté. La date de retour a été ajustée à la fin de votre abonnement.",
                "return_date_adjusted" => true
            ]);
        } else {
            // Sinon, la date de retour est 15 jours après l'emprunt
            $date_retour = $suggested_date_retour;
            echo json_encode([
                "success" => true, 
                "message" => "Livre emprunté avec succès!"
            ]);
        }

        // 2. Insérer un nouvel enregistrement d'emprunt
        $stmt = $pdo->prepare("INSERT INTO emprunts (lecteur_id, livre_id, date_emprunt, date_retour, rendu) VALUES (:lecteur_id, :livre_id, :date_emprunt, :date_retour, FALSE)");
        $stmt->bindParam(':lecteur_id', $current_lecteur_id, PDO::PARAM_INT);
        $stmt->bindParam(':livre_id', $book_id, PDO::PARAM_INT);
        $stmt->bindParam(':date_emprunt', $date_emprunt, PDO::PARAM_INT);
        $stmt->bindParam(':date_retour', $date_retour, PDO::PARAM_INT);
        $stmt->execute();

        // 3. Mettre à jour le statut de disponibilité du livre
        $stmt = $pdo->prepare("UPDATE livres SET disponible = FALSE WHERE id = :book_id");
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->execute();

    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(["success" => false, "message" => "Erreur lors de l'emprunt du livre: " . $e->getMessage()]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["success" => false, "message" => "Méthode non autorisée."]);
}
?>
