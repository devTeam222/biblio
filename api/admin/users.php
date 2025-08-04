<?php
// api/admin/users.php
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
            // Get search and pagination parameters
            $search = htmlspecialchars(trim($_GET['search'])) ?? '';
            $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
            $limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT);

            if ($page < 1) $page = 1;
            if ($limit < 1) $limit = 10;
            $offset = ($page - 1) * $limit;

            // Prepare WHERE clause for search
            $whereClause = '';
            $params = [];
            if (!empty($search)) {
                $whereClause .= ' WHERE nom ILIKE ? OR email ILIKE ? OR role ILIKE ?';
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            // Get total count of filtered items
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users " . $whereClause);
            $countStmt->execute($params);
            $totalCount = $countStmt->fetchColumn();

            // Get paginated and filtered data
            $sql = "SELECT id, nom, email, role FROM users " . $whereClause . " ORDER BY nom ASC LIMIT ? OFFSET ?";
            $stmt = $pdo->prepare($sql);
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "success" => true,
                "data" => $users,
                "total" => $totalCount
            ]);
            break;

        case 'details':
            $id = intval($_GET['id']) ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID utilisateur manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("SELECT id, nom, email, role FROM users WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                echo json_encode(["success" => true, "data" => $user]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Utilisateur non trouvé."]);
            }
            break;

        case 'add':
            $nom = trim(htmlspecialchars($input['nom'])) ?? null;
            $email = trim(htmlspecialchars($input['email'])) ?? null;
            $validEmail = filter_var($email, FILTER_VALIDATE_EMAIL);
            if ($email && !$validEmail) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Email invalide."]);
                exit();
            }
            $password = $input['password'] ?? null;
            $role = $input['role'] ?? 'user';
            if (!$nom || !$email || !$password) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Nom, email et mot de passe sont requis."]);
                exit();
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (nom, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nom, $email, $hashed_password, $role]);
            echo json_encode(["success" => true, "message" => "Utilisateur ajouté avec succès.", "id" => $pdo->lastInsertId()]);
            break;

        case 'update':

            $possibleRoles = ['user', 'admin', 'author'];

            $id = intval($input['id']) ?? null;
            $nom = trim(htmlspecialchars($input['nom'])) ?? null;
            $role = in_array(trim($input['role']), $possibleRoles) ? $input['role'] : null;
            $password = $input['password'] ?? null;

            if (!$id || !$nom || !$role) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID, nom et rôle de l'utilisateur sont requis."]);
                exit();
            }

            $sql = "UPDATE users SET nom = :nom, role = :role";
            if ($password) {
                $sql .= ", password = :password";
            }
            $sql .= " WHERE id = :id";
            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':role', $role);
            if ($password) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt->bindParam(':password', $hashed_password);
            }
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Utilisateur mis à jour."]);
            break;

        case 'delete':
            $id = intval($_GET['id']) ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID utilisateur manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Utilisateur supprimé."]);
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

