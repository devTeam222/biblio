<?php
// api/admin/lists.php
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

require_once __DIR__.'/../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Accès non autorisé."]);
    exit();
}

$type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? ''; // Paramètre de recherche envoyé par le frontend
$limit = filter_var($_GET['limit'] ?? 9999, FILTER_VALIDATE_INT); // Par défaut, une grande limite pour les dropdowns

try {
    $whereClause = '';
    $params = [];
    $searchTerm = '%' . $search . '%'; // Préparer le terme de recherche

    switch ($type) {
        case 'authors':
            $sql = "SELECT id AS authorid, nom FROM auteurs";
            if (!empty($search)) {
                $whereClause = " WHERE nom ILIKE ?";
                $params[] = $searchTerm;
            }
            $sql .= $whereClause . " ORDER BY nom ASC";
            break;
        case 'departement':
            $sql = "SELECT id, nom AS name FROM departement";
            if (!empty($search)) {
                $whereClause = " WHERE nom ILIKE ?";
                $params[] = $searchTerm;
            }
            $sql .= $whereClause . " ORDER BY nom ASC";
            break;
        case 'academic_years':
            // Concaténer les années pour la recherche
            $sql = "SELECT id, start || '-' || \"end\" AS annee_academique FROM academic_year";
            if (!empty($search)) {
                $whereClause = " WHERE (start || '-' || \"end\") ILIKE ?";
                $params[] = $searchTerm;
            }
            $sql .= $whereClause . " ORDER BY start DESC";
            break;
        case 'study_areas':
            $sql = "SELECT id, name FROM study_areas";
            if (!empty($search)) {
                $whereClause = " WHERE name ILIKE ?";
                $params[] = $searchTerm;
            }
            $sql .= $whereClause . " ORDER BY name ASC";
            break;
        case 'readers': // Ajout pour le dropdown des lecteurs dans les abonnements
            $sql = "SELECT l.id AS id, u.nom AS nom, u.email AS email FROM lecteurs l JOIN users u ON l.user_id = u.id";
            if (!empty($search)) {
                $whereClause = " WHERE u.nom ILIKE ? OR u.email ILIKE ?";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            $sql .= $whereClause . " ORDER BY u.nom ASC";
            break;
        default:
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Type de liste invalide."]);
            exit(); // Sortir pour éviter une exécution ultérieure
    }

    // Ajouter la limite si nécessaire (bien que DropdownSearch demande 9999 pour une liste complète)
    $sql .= " LIMIT " . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $data]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erreur de base de données: " . $e->getMessage()]);
}
?>
