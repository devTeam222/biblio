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
            $stmt = $pdo->query("SELECT id, nom, email, role FROM users ORDER BY nom ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "data" => $users]);
            break;

        case 'details':
            $id = $_GET['id'] ?? null;
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
            $nom = $input['nom'] ?? null;
            $email = $input['email'] ?? null;
            $password = $input['password'] ?? null;
            $role = $input['role'] ?? 'user';

            if (!$nom || !$email || !$password) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Nom, email et mot de passe sont requis."]);
                exit();
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (nom, email, password, role) VALUES (:nom, :email, :password, :role)");
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':role', $role);
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Utilisateur ajouté."]);
            break;

        case 'update':
            $id = $input['id'] ?? null;
            $nom = $input['nom'] ?? null;
            $password = $input['password'] ?? null; // Optionnel
            $role = $input['role'] ?? null;

            if (!$id || !$nom || !$email || !$role) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID, nom, email et rôle sont requis."]);
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
            $id = $_GET['id'] ?? null;
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
?>