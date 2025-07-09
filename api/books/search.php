<?php
// api/books/search.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db_connect.php';

$query_param = $_GET['query'] ?? ''; // Terme de recherche

try {
    $search_term = '%' . $query_param . '%';
    // Recherche de livres par titre, nom d'auteur ou nom de catégorie
    $stmt = $pdo->prepare("
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
        WHERE 
            livres.titre ILIKE :search_term OR 
            auteurs.nom ILIKE :search_term OR 
            categories.nom ILIKE :search_term
        ORDER BY livres.titre ASC -- Ordre alphabétique par titre pour la recherche
    ");
    $stmt->bindParam(':search_term', $search_term);
    $stmt->execute();
    $books = $stmt->fetchAll();
    echo json_encode(["success" => true, "data" => $books]);
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["success" => false, "message" => "Erreur lors de la recherche des livres: " . $e->getMessage()]);
}
?>
