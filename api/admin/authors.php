<?php
// api/admin/authors.php
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
            $stmt = $pdo->query("SELECT id, nom, biographie FROM auteurs ORDER BY nom ASC");
            $authors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "data" => $authors]);
            break;

        case 'details':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID auteur manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("SELECT id, nom, biographie FROM auteurs WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $author = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($author) {
                echo json_encode(["success" => true, "data" => $author]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Auteur non trouvé."]);
            }
            break;

        case 'add':
            $nom = $input['nom'] ?? null;
            $biographie = $input['biographie'] ?? null;

            if (!$nom) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Nom de l'auteur est requis."]);
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO auteurs (nom, biographie) VALUES (:nom, :biographie)");
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':biographie', $biographie);
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Auteur ajouté."]);
            break;

        case 'update':
            $id = $input['id'] ?? null;
            $nom = $input['nom'] ?? null;
            $biographie = $input['biographie'] ?? null;

            if (!$id || !$nom) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID et nom de l'auteur sont requis."]);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE auteurs SET nom = ?, biographie = ? WHERE id = ?");
            $stmt->execute([$nom, $biographie, $id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Auteur mis à jour."]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Aucune mise à jour effectuée. Auteur inexistant ou données inchangées."]);
            }
            break;


        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID auteur manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM auteurs WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Auteur supprimé."]);
            break;

        default:
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Action invalide."]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erreur de base de données: " . $e->getMessage()]);
}
