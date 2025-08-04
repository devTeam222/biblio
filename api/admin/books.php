<?php
// api/admin/books.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__.'/../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Accès non autorisé."]);
    exit();
}

$action = $_GET['action'] ?? ''; 
$input = $_POST;

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query("
                SELECT l.id, l.titre, l.isbn, l.descr, l.disponible, l.emplacement , a.id AS auteur_id, a.nom AS auteur_nom
                FROM livres l
                JOIN auteurs a ON l.auteur_id = a.id
                ORDER BY l.date_publication DESC
            ");
            $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "data" => $books]);
            break;

        case 'details':
            $id = intval($_GET['id']) ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID de livre manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("
                SELECT l.id, l.titre, l.isbn, l.descr, l.disponible, a.id AS auteur_id, a.nom AS auteur_nom
                FROM livres l
                JOIN auteurs a ON l.auteur_id = a.id
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
            break;

        case 'add':
            $titre = $_POST['titre'] ?? null;
            $auteur_id = $_POST['auteur_id'] ?? null;
            $isbn = $_POST['isbn'] ?? null;
            $description = $_POST['description'] ?? null;
            $disponible = true; // Par défaut disponible

            if (!$titre || !$auteur_id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Titre et auteur sont requis."]);
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO livres (titre, auteur_id, isbn, descr, disponible) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$titre, $auteur_id, $isbn, $description, $disponible]);
            if ($stmt->rowCount() === 0) {
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Erreur lors de l'ajout du livre."]);
                exit();
            }
            echo json_encode(["success" => true, "message" => "Livre ajouté."]);
            break;

        case 'update':
            $id = $_POST['id'] ?? null;
            $titre = $_POST['titre'] ?? null;
            $auteur_id = $_POST['auteur_id'] ?? null;
            $isbn = $_POST['isbn'] ?? null;
            $description = $_POST['description'] ?? null;

            if (!$id || !$titre || !$auteur_id) {
                echo json_encode(["success" => false, "message" => "ID, titre et auteur sont requis.", "error" => "Missing required fields", "input" => [
                    'id' => $id,
                    'titre' => $titre,
                    'auteur_id' => $auteur_id,
                    'isbn' => $isbn,
                    'description' => $description
                ], "post" => $_POST]);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE livres SET titre = ?, auteur_id = ?, isbn = ?, descr = ? WHERE id = ?");
            $stmt->execute([$titre, $auteur_id, $isbn, $description, $id]);
            if ($stmt->rowCount() === 0) {
                echo json_encode(["success" => false, "message" => "Erreur lors de la mise à jour du livre."]);
                exit();
            }
            echo json_encode(["success" => true, "message" => "Livre mis à jour."]);
            break;

        case 'delete':
            $id = intval($_GET['id']) ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID de livre manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM livres WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Livre supprimé."]);
            break;

        default:
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Action invalide."]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erreur de base de données", "error"=> $e->getMessage()]);
}
?>