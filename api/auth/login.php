<?php
// api/auth/login.php

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
    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Veuillez fournir l'email et le mot de passe."]);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT id, nom, email, mot_de_passe, role FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            // Authentification réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nom'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            // Envoyer un status de redirection
            http_response_code(300);
            // Envoyer la page de redirection en fonction du role
            $page = $user['role'] === 'admin' ? 'dashboard' : 'profile';

            echo json_encode([
                "success" => true,
                "message" => "Connexion réussie !",
                "redirect" => $page // Indiquer la page de redirection
            ]);
        } else {
            http_response_code(401); // Unauthorized
            echo json_encode(["success" => false, "message" => "Email ou mot de passe incorrect."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Erreur lors de la connexion", "error"=>  $e->getMessage()]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["success" => false, "message" => "Erreur inconue.", "error"=> "Methode non autorisée."]);
}
?>