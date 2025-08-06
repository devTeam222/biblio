<?php
// api/loans/current.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../db_connect.php';
session_start();
// Simule l'ID de l'utilisateur connecté.
// En production, ceci viendrait d'une session sécurisée ou d'un token JWT.
$current_user_id = $_SESSION['user_id'] ?? null; // ID de l'utilisateur (de la table 'users')
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


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Récupère les emprunts non rendus de l'utilisateur, avec les détails du livre (titre, auteur)
        $stmt = $pdo->prepare("
            SELECT
                e.id,
                e.lecteur_id,
                e.livre_id,
                e.date_emprunt,
                e.date_retour,
                e.rendu,
                l.titre,
                a.nom AS auteur,
                f.chemin AS cover_url
            FROM
                emprunts e
            JOIN
                livres l ON e.livre_id = l.id
            JOIN
                auteurs a ON l.auteur_id = a.id
            LEFT JOIN
                fichiers f ON l.cover_image_id = f.id
            WHERE
                e.lecteur_id = :lecteur_id
            ORDER BY
                e.date_retour ASC;

        ");
        $stmt->bindParam(':lecteur_id', $current_lecteur_id, PDO::PARAM_INT);
        $stmt->execute();
        $loans = $stmt->fetchAll();

        echo json_encode(["success" => true, "data" => $loans]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(["success" => false, "message" => "Erreur lors de la récupération des emprunts: " . $e->getMessage()]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["success" => false, "message" => "Méthode non autorisée."]);
}
