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

require_once __DIR__ . '/../db_connect.php';

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
            $search = isset($_GET['search']) ? htmlspecialchars(trim($_GET['search'])) : '';
            $page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : 1;
            $limit = isset($_GET['limit']) ? filter_var($_GET['limit'], FILTER_VALIDATE_INT) : 10;

            if ($page < 1) $page = 1;
            if ($limit < 1) $limit = 10;
            $offset = ($page - 1) * $limit;

            // Prepare WHERE clause for search
            $whereClause = '';
            $params = [];
            if (!empty($search)) {
                $whereClause .= ' WHERE a.nom ILIKE ? OR u.nom ILIKE ? OR u.email ILIKE ? OR u.role ILIKE ? OR a.biographie ILIKE ? ';
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $sql = "SELECT a.id AS authorId, a.nom AS pseudo, a.biographie, u.nom AS nom_complet, u.id AS userId 
                FROM auteurs a
                LEFT JOIN users u ON u.id = a.user_id
                $whereClause
                ORDER BY a.nom ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

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
            $id = trim(htmlspecialchars($id, ENT_QUOTES, 'UTF-8'));
            // Préparation de la requête pour obtenir les détails de l'auteur dans la table auteurs et users
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
            $nom = trim(htmlspecialchars($input['nom_complet'])) ?? null;
            $pseudo = trim(htmlspecialchars($input['pseudo'])) ?? $nom;
            $biographie = trim(htmlspecialchars($input['biographie'])) ?? null;

            if (!$nom) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Nom de l'auteur est requis."]);
                exit();
            }
            $sql = "INSERT INTO users (nom, `role`) VALUES (?, 'author')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nom]);
            $userId = $pdo->lastInsertId();

            $sql = "INSERT INTO auteurs (nom, biographie, user_id) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$pseudo, $biographie, $userId]);
            echo json_encode(["success" => true, "message" => "Auteur ajouté avec succès."]);
            break;

        case 'update':
            $id = trim(htmlspecialchars($input['id'])) ?? null;
            $nom = trim(htmlspecialchars($input['nom_complet'])) ?? null;
            $pseudo = trim(htmlspecialchars($input['pseudo'])) ?? $nom;
            $biographie = trim(htmlspecialchars($input['biographie'])) ?? null;

            if (!$id || !$nom) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID et nom de l'auteur sont requis."]);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE auteurs SET nom = ?, biographie = ? WHERE id = ?");
            $stmt->execute([$pseudo, $biographie, $id]);
            $stmt = $pdo->prepare("UPDATE users SET nom = ? WHERE id = (SELECT user_id FROM auteurs WHERE id = ?)");
            $stmt->execute([$nom, $id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Auteur mis à jour."]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Aucune mise à jour effectuée. Auteur inexistant ou données inchangées."]);
            }
            break;
        case 'delete':
            $id = htmlspecialchars($_GET['id']) ?? null;
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
