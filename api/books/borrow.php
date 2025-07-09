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

require_once '../db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || !isset($_SESSION['user_name']) || empty($_SESSION['user_name']) || !isset($_SESSION['user_role']) || empty($_SESSION['user_role'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Connectez-vous ou créer un compte.',
    ]);
    exit();
}

// Simule l'ID de l'utilisateur connecté.
// En production, ceci viendrait d'une session sécurisée ou d'un token JWT.
// Pour cette démo, nous allons le fixer à un ID de lecteur existant pour les tests.
// Vous devrez implémenter une logique pour obtenir le lecteur_id à partir du user_id authentifié.
$current_user_id = $_SESSION['user_id']; // ID de l'utilisateur (de la table 'users')
$current_lecteur_id = null; // Sera récupéré à partir de $current_user_id

// Récupérer le lecteur_id basé sur le user_id authentifié
try {
    $stmt = $pdo->prepare("SELECT id FROM lecteurs WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $lecteur = $stmt->fetch();
    if ($lecteur) {
        $current_lecteur_id = $lecteur['id'];
    } else {
        http_response_code(403); // Forbidden
        echo json_encode(["success" => false, "message" => "L'utilisateur n'est pas enregistré comme lecteur."]);
        exit();
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erreur lors de la vérification du lecteur: " . $e->getMessage()]);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $book_id = isset($_POST['bookId']) ? $_POST['bookId'] : null;

    if ($book_id === null || !is_numeric($book_id)) {

        // http_response_code(400); // Bad Request
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

        // 2. Insérer un nouvel enregistrement d'emprunt
        $date_emprunt = time(); // Timestamp Unix actuel
        $date_retour = $date_emprunt + 15*24*3600; // Timestamp Unix pour 15 jours plus tard

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

        echo json_encode(["success" => true, "message" => "Livre emprunté avec succès!"]);

    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(["success" => false, "message" => "Erreur lors de l'emprunt du livre: " . $e->getMessage()]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["success" => false, "message" => "Méthode non autorisée."]);
}
?>
