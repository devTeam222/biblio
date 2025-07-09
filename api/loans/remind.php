<?php
// api/loans/remind.php

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
        'message' => 'Utilisateur non connecté.',
    ]);
    exit();
}
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
    $loan_id = $_POST['loanId'] ?? null;

    if ($loan_id === null || !is_numeric($loan_id)) {
        http_response_code(400); // Bad Request
        echo json_encode(["success" => false, "message" => "ID de l'emprunt manquant ou invalide pour le rappel."]);
        return;
    }

    try {
        // Vérifier si l'emprunt existe, appartient à l'utilisateur actuel et n'est pas déjà rendu
        $stmt = $pdo->prepare("SELECT rendu FROM emprunts WHERE id = :loan_id AND lecteur_id = :lecteur_id");
        $stmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
        $stmt->bindParam(':lecteur_id', $current_lecteur_id, PDO::PARAM_INT);
        $stmt->execute();
        $loan = $stmt->fetch();

        if (!$loan) {
            http_response_code(404); // Not Found ou Forbidden si l'emprunt n'appartient pas à l'utilisateur
            echo json_encode(["success" => false, "message" => "Emprunt non trouvé ou vous n'avez pas l'autorisation."]);
            return;
        }
        if ($loan['rendu']) {
            http_response_code(409); // Conflict
            echo json_encode(["success" => false, "message" => "Cet emprunt a déjà été rendu."]);
            return;
        }

        // Ici, vous intégreriez la logique réelle d'envoi de rappel
        // Par exemple: envoi d'un e-mail, ajout à une file d'attente de notifications, etc.
        echo json_encode(["success" => true, "message" => "Rappel de retour envoyé pour l'emprunt ID " . $loan_id . "."]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(["success" => false, "message" => "Erreur lors de l'envoi du rappel: " . $e->getMessage()]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["success" => false, "message" => "Méthode non autorisée."]);
}
