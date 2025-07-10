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

require_once '../db_connect.php';

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
                SELECT l.id, l.titre, l.isbn, l.descr, l.disponible, a.id AS auteur_id, a.nom AS auteur_nom
                FROM livres l
                JOIN auteurs a ON l.auteur_id = a.id
                ORDER BY l.titre ASC
            ");
            $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "data" => $books]);
            break;

        case 'details':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID de livre manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("
                SELECT l.id, l.titre, l.isbn, l.description, l.disponible, a.id AS auteur_id, a.nom AS auteur_nom
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
            $titre = $input['titre'] ?? null;
            $auteur_id = $input['auteur_id'] ?? null;
            $isbn = $input['isbn'] ?? null;
            $description = $input['description'] ?? null;
            $disponible = $input['disponible'] ?? 1; // Par défaut disponible

            if (!$titre || !$auteur_id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Titre et auteur sont requis."]);
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO livres (titre, auteur_id, isbn, description, disponible) VALUES (:titre, :auteur_id, :isbn, :description, :disponible)");
            $stmt->bindParam(':titre', $titre);
            $stmt->bindParam(':auteur_id', $auteur_id, PDO::PARAM_INT);
            $stmt->bindParam(':isbn', $isbn);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':disponible', $disponible, PDO::PARAM_BOOL);
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Livre ajouté."]);
            break;

        case 'update':
            $id = $input['id'] ?? null;
            $titre = $input['titre'] ?? null;
            $auteur_id = $input['auteur_id'] ?? null;
            $isbn = $input['isbn'] ?? null;
            $description = $input['description'] ?? null;
            $disponible = $input['disponible'] ?? null;

            if (!$id || !$titre || !$auteur_id || !isset($disponible)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID, titre, auteur et disponibilité sont requis."]);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE livres SET titre = :titre, auteur_id = :auteur_id, isbn = :isbn, description = :description, disponible = :disponible WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':titre', $titre);
            $stmt->bindParam(':auteur_id', $auteur_id, PDO::PARAM_INT);
            $stmt->bindParam(':isbn', $isbn);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':disponible', $disponible, PDO::PARAM_BOOL);
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Livre mis à jour."]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
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