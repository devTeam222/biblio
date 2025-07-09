<?php
// api/auth/register.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? null;
    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;

    if (empty($name) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Veuillez remplir tous les champs."]);
        exit();
    }

    // Validation simple de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Format d'email invalide."]);
        exit();
    }

    try {
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            http_response_code(409); // Conflict
            echo json_encode(["success" => false, "message" => "Cet email est déjà enregistré."]);
            exit();
        }

        // Hacher le mot de passe
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Commencer une transaction pour s'assurer que les deux insertions réussissent
        $pdo->beginTransaction();

        // Insérer le nouvel utilisateur
        $stmt_user = $pdo->prepare("INSERT INTO users (nom, email, mot_de_passe, role, date_inscription) VALUES (?, ?, ?, ?, ?)");
        $date_inscription = time();
        $stmt_user->execute([$name, $email, $hashed_password, 'user', $date_inscription]);
        $user_id = $pdo->lastInsertId(); // Pour PostgreSQL, spécifiez le nom de la séquence

        // Insérer une entrée correspondante dans la table lecteurs
        $stmt_lecteur = $pdo->prepare("INSERT INTO lecteurs (user_id, type, date_inscription) VALUES (:user_id, 'abonne', :date_inscription)");
        $stmt_lecteur->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_lecteur->bindParam(':date_inscription', $date_inscription, PDO::PARAM_INT);
        $stmt_lecteur->execute();

        $pdo->commit(); // Confirmer la transaction

        $stmt = $pdo->prepare("SELECT id, nom, email, mot_de_passe, role FROM users WHERE id = :id");
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        if (!$user) {
            http_response_code(404); // Not Found
            echo json_encode(["success" => false, "message" => "Utilisateur non trouvé après l'inscription."]);
            exit();
        }
        // Authentifier l'utilisateur après l'inscription
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nom'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];

        echo json_encode(["success" => true, "message" => "Inscription réussie !"]);

    } catch (PDOException $e) {
        $pdo->rollBack(); // Annuler la transaction en cas d'erreur
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Erreur lors de l'inscription" , "error"=> $e->getMessage()]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["success" => false, "message" => "Méthode non autorisée."]);
}
?>
