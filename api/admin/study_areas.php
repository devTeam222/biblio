<?php
// api/admin/study_areas.php
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
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    switch ($action) {
        case 'list':
            $search = htmlspecialchars(trim($_GET['search'])) ?? '';
            $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
            $limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT);

            if ($page < 1) $page = 1;
            if ($limit < 1) $limit = 10;
            $offset = ($page - 1) * $limit;

            $whereClause = '';
            $params = [];
            if (!empty($search)) {
                $whereClause .= ' WHERE name ILIKE ? OR description ILIKE ?';
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $countStmt = $pdo->prepare("SELECT COUNT(id) FROM study_areas" . $whereClause);
            $countStmt->execute($params);
            $totalCount = $countStmt->fetchColumn();

            $sql = "SELECT id, name, description, latitude, longitude, created_at FROM study_areas" 
                    . $whereClause . " ORDER BY name ASC LIMIT ? OFFSET ?";
            
            $stmt = $pdo->prepare($sql);
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $study_areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "success" => true,
                "data" => $study_areas,
                "total" => $totalCount
            ]);
            break;

        case 'details':
            $id = intval($_GET['id'] ?? null);
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID de zone d'étude manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("SELECT id, name, description, latitude, longitude FROM study_areas WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $study_area = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($study_area) {
                echo json_encode(["success" => true, "data" => $study_area]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Zone d'étude non trouvée."]);
            }
            break;

        case 'add':
            $name = $input['name'] ?? null;
            $description = $input['description'] ?? null;
            // Correction ici: Convertir explicitement false (si input est vide) en null
            $latitude = filter_var($input['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
            $latitude = ($latitude === false) ? null : $latitude;

            $longitude = filter_var($input['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
            $longitude = ($longitude === false) ? null : $longitude;

            if (empty($name)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Le nom de la zone d'étude est requis."]);
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO study_areas (name, description, latitude, longitude, created_at) VALUES (?, ?, ?, ?, ?)");
            $current_time = time();
            $stmt->execute([$name, $description, $latitude, $longitude, $current_time]);
            echo json_encode(["success" => true, "message" => "Zone d'étude ajoutée avec succès.", "id" => $pdo->lastInsertId()]);
            break;

        case 'update':
            $id = $input['id'] ?? null;
            $name = $input['name'] ?? null;
            $description = $input['description'] ?? null;
            // Correction ici: Convertir explicitement false (si input est vide) en null
            $latitude = filter_var($input['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
            $latitude = ($latitude === false) ? null : $latitude;

            $longitude = filter_var($input['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
            $longitude = ($longitude === false) ? null : $longitude;

            if (!$id || empty($name)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID et nom de la zone d'étude sont requis."]);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE study_areas SET name = ?, description = ?, latitude = ?, longitude = ?, updated_at = ? WHERE id = ?");
            $current_time = time();
            $stmt->execute([$name, $description, $latitude, $longitude, $current_time, $id]);
            echo json_encode(["success" => true, "message" => "Zone d'étude mise à jour."]);
            break;

        case 'delete':
            $id = intval($_GET['id'] ?? null);
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID de zone d'étude manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM study_areas WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Zone d'étude supprimée."]);
            break;

        default:
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Action invalide."]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
