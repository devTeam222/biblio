<?php
// api/admin/subscriptions.php
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
                $whereClause .= ' WHERE u.nom ILIKE ? OR u.email ILIKE ? OR s.statut ILIKE ?';
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $countStmt = $pdo->prepare("SELECT COUNT(s.id) 
                                        FROM abonnements s
                                        JOIN lecteurs l ON s.lecteur_id = l.id
                                        JOIN users u ON l.user_id = u.id " . $whereClause);
            $countStmt->execute($params);
            $totalCount = $countStmt->fetchColumn();

            $sql = "SELECT 
                        s.id AS subscription_id, 
                        s.date_debut, 
                        s.date_fin, 
                        s.statut,
                        u.id AS user_id,
                        u.nom AS user_name,
                        u.email AS user_email
                    FROM abonnements s
                    JOIN lecteurs l ON s.lecteur_id = l.id
                    JOIN users u ON l.user_id = u.id " 
                    . $whereClause . " ORDER BY s.date_debut DESC LIMIT ? OFFSET ?";
            
            $stmt = $pdo->prepare($sql);
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "success" => true,
                "data" => $subscriptions,
                "total" => $totalCount
            ]);
            break;

        case 'details':
            $id = intval($_GET['id']) ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID d'abonnement manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("SELECT 
                                        s.id AS subscription_id, 
                                        s.lecteur_id, 
                                        s.date_debut, 
                                        s.date_fin, 
                                        s.statut,
                                        u.nom AS user_name,
                                        u.email AS user_email
                                    FROM abonnements s
                                    JOIN lecteurs l ON s.lecteur_id = l.id
                                    JOIN users u ON l.user_id = u.id
                                    WHERE s.id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($subscription) {
                echo json_encode(["success" => true, "data" => $subscription]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Abonnement non trouvé."]);
            }
            break;

        case 'add':
            $lecteur_id = $input['lecteur_id'] ?? null;
            $date_debut = $input['date_debut'] ?? null;
            $date_fin = $input['date_fin'] ?? null;
            $statut = $input['statut'] ?? 'actif';

            if (!$lecteur_id || !$date_debut || !$date_fin) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID lecteur, date de début et date de fin sont requis."]);
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO abonnements (lecteur_id, date_debut, date_fin, statut) VALUES (?, ?, ?, ?)");
            $stmt->execute([$lecteur_id, $date_debut, $date_fin, $statut]);
            echo json_encode(["success" => true, "message" => "Abonnement ajouté avec succès.", "id" => $pdo->lastInsertId()]);
            break;

        case 'update':
            $id = $input['id'] ?? null;
            $lecteur_id = $input['lecteur_id'] ?? null;
            $date_debut = $input['date_debut'] ?? null;
            $date_fin = $input['date_fin'] ?? null;
            $statut = $input['statut'] ?? null;

            if (!$id || !$lecteur_id || !$date_debut || !$date_fin || !$statut) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID, ID lecteur, date de début, date de fin et statut sont requis."]);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE abonnements SET lecteur_id = ?, date_debut = ?, date_fin = ?, statut = ? WHERE id = ?");
            $stmt->execute([$lecteur_id, $date_debut, $date_fin, $statut, $id]);
            echo json_encode(["success" => true, "message" => "Abonnement mis à jour."]);
            break;

        case 'delete':
            $id = intval($_GET['id']) ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID d'abonnement manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM abonnements WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Abonnement supprimé."]);
            break;

        // Action pour lister les lecteurs (pour le dropdown dans le formulaire d'abonnement)
        case 'list_readers':
            $stmt = $pdo->query("SELECT l.id AS lecteur_id, u.nom AS user_name, u.email AS user_email FROM lecteurs l JOIN users u ON l.user_id = u.id ORDER BY u.nom ASC");
            $readers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "data" => $readers]);
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
