<?php

require_once __DIR__.'/../db_connect.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || !isset($_SESSION['user_name']) || empty($_SESSION['user_name']) || !isset($_SESSION['user_role']) || empty($_SESSION['user_role'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Utilisateur non connecté.',
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => 'Utilisateur connecté.',
    'user'=>[
        'id'=> $_SESSION['user_id'],
        'name'=> $_SESSION['user_name'],
        'email'=> $_SESSION['user_email'] ?? '', // Assurez-vous que l'email est défini dans la session
        'role'=> $_SESSION['user_role'],
    ],
    'roles' => explode(',', $_SESSION['user_role']), // Si le rôle est une chaîne, le convertir en tableau
]);

exit();
