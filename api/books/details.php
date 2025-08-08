<?php
// api/books/details.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../db_connect.php';

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "ID de livre manquant."]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            l.id, 
            l.titre, 
            l.descr, 
            l.disponible, 
            l.emplacement,
            l.date_publication,
            l.file_id, -- Ajout de l'ID du fichier électronique
            a.nom AS auteur_nom,
            c.nom AS categorie_nom,
            f_cover.chemin AS cover_image_url,
            f_electronic.chemin AS electronic_file_url, -- Chemin du fichier électronique
            ay.start || '-' || ay.\"end\" AS annee_academique -- Ajout de l'année académique
        FROM livres l
        JOIN auteurs a ON l.auteur_id = a.id
        LEFT JOIN categories c ON l.categorie_id = c.id
        LEFT JOIN fichiers f_cover ON l.cover_image_id = f_cover.id
        LEFT JOIN fichiers f_electronic ON l.file_id = f_electronic.id -- Jointure pour le fichier électronique
        LEFT JOIN academic_year ay ON l.annee_id = ay.id -- Jointure pour l'année académique
        WHERE l.id = :id
    ");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($book) {
        echo json_encode(["success" => true, "data" => $book]);
    } else {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Livre non trouvé."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erreur de base de données", "error" => $e->getMessage()]);
}
?>
