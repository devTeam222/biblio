<?php
// api/admin/loans.php
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
                SELECT 
                    e.id AS loan_id,
                    l.titre,
                    a.nom AS auteur,
                    u.nom AS lecteur_nom,
                    e.date_emprunt,
                    e.date_retour,
                    e.rendu
                FROM emprunts e
                JOIN livres l ON e.livre_id = l.id
                JOIN lecteurs le ON e.lecteur_id = le.id
                JOIN users u ON le.user_id = u.id
                JOIN auteurs a ON l.auteur_id = a.id
                ORDER BY e.date_emprunt DESC
            ");
            $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "data" => $loans]);
            break;

        case 'return':
            $loan_id = $input['loan_id'] ?? null;
            if (!$loan_id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID d'emprunt manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("UPDATE emprunts SET rendu = TRUE, date_retour = :current_timestamp WHERE id = :loan_id");
            $current_timestamp = time(); // Timestamp Unix
            $stmt->bindParam(':current_timestamp', $current_timestamp, PDO::PARAM_INT);
            $stmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Emprunt marqué comme retourné."]);
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
