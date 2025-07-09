<?php
// api/books/trending.php

require_once '../db_connect.php'; // connexion PDO PostgreSQL

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Permet les requêtes depuis n'importe quel domaine (pour le développement)
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gère les requêtes OPTIONS (pré-vol CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Récupère les livres disponibles, avec les noms de l'auteur et de la catégorie,
    // ainsi que le chemin de l'image de couverture.
    // Les livres sont triés par date de publication (les plus récents en premier) et limités à 8.
    $stmt = $pdo->query("
        SELECT 
            livres.id,
            livres.titre,
            auteurs.nom AS auteur,
            categories.nom AS categorie,
            categories.id AS categorie_id,
            fichiers.chemin AS cover,
            livres.date_publication,
            livres.disponible
        FROM livres
        JOIN auteurs ON livres.auteur_id = auteurs.id
        JOIN categories ON livres.categorie_id = categories.id
        LEFT JOIN fichiers ON livres.cover_image_id = fichiers.id
        WHERE livres.disponible = TRUE
        ORDER BY livres.date_publication DESC
        LIMIT 8
    ");
    $livres = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $livres
    ]);
} catch (PDOException $e) {
    // En cas d'erreur de base de données, retourne un code 500 et un message d'erreur JSON
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des livres tendances.',
        'error' => $e->getMessage()
    ]);
}
?>
