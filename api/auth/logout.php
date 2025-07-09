<?php
// api/auth/logout.php

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Détruire toutes les variables de session
    $_SESSION = [];

    // Si vous utilisez des cookies de session, supprimez-les également
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Finalement, détruire la session
    session_destroy();

    echo json_encode(["success" => true, "message" => "Déconnexion réussie."]);
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["success" => false, "message" => "Erreur inconnue.", "code" => 405]);
}
?>
