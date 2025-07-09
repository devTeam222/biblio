<?php
// api/admin/dashboard.php

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

require_once '../db_connect.php';

// Vérifier si l'utilisateur est authentifié et a le rôle 'admin'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403); // Forbidden
    echo json_encode(["success" => false, "message" => "Accès non autorisé. Réservé aux administrateurs."]);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'stats':
            // Statistiques globales
            $total_books_stmt = $pdo->query("SELECT COUNT(*) FROM livres");
            $total_books = $total_books_stmt->fetchColumn();

            $available_books_stmt = $pdo->query("SELECT COUNT(*) FROM livres WHERE disponible = TRUE");
            $available_books = $available_books_stmt->fetchColumn();

            $total_users_stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $total_users = $total_users_stmt->fetchColumn();

            $current_loans_stmt = $pdo->query("SELECT COUNT(*) FROM emprunts WHERE rendu = FALSE");
            $current_loans_count = $current_loans_stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_books' => (int)$total_books,
                    'available_books' => (int)$available_books,
                    'total_users' => (int)$total_users,
                    'current_loans_count' => (int)$current_loans_count
                ]
            ]);
            break;

        case 'overdue_loans':
            // Emprunts en retard
            $current_timestamp = time();
            $stmt = $pdo->prepare("
                SELECT 
                    e.id AS loan_id,
                    l.titre,
                    a.nom AS auteur,
                    u.nom AS lecteur_nom,
                    e.date_emprunt,
                    e.date_retour
                FROM emprunts e
                JOIN livres l ON e.livre_id = l.id
                JOIN lecteurs le ON e.lecteur_id = le.id
                JOIN users u ON le.user_id = u.id
                JOIN auteurs a ON l.auteur_id = a.id
                WHERE e.rendu = FALSE AND e.date_retour < :current_timestamp
                ORDER BY e.date_retour ASC
            ");
            $stmt->bindParam(':current_timestamp', $current_timestamp, PDO::PARAM_INT);
            $stmt->execute();
            $overdue_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $overdue_loans
            ]);
            break;

        case 'recent_activity':
            // Activité récente (par exemple, 10 derniers emprunts ou retours)
            // Pour simplifier, nous allons chercher les 10 derniers emprunts/retours
            $stmt = $pdo->query("
                SELECT 
                    e.id AS loan_id,
                    l.titre,
                    u.nom AS user_name,
                    e.date_emprunt,
                    e.date_retour,
                    e.rendu
                FROM emprunts e
                JOIN livres l ON e.livre_id = l.id
                JOIN lecteurs le ON e.lecteur_id = le.id
                JOIN users u ON le.user_id = u.id
                ORDER BY e.date_emprunt DESC, e.date_retour DESC
                LIMIT 10
            ");
            $recent_activities_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $recent_activities = [];
            foreach ($recent_activities_raw as $activity) {
                if ($activity['rendu']) {
                    $description = "Livre '{$activity['titre']}' rendu par {$activity['user_name']}.";
                    $timestamp = $activity['date_retour']; // Ou un champ 'date_rendu' si vous l'ajoutez
                } else {
                    $description = "Livre '{$activity['titre']}' emprunté par {$activity['user_name']}.";
                    $timestamp = $activity['date_emprunt'];
                }
                $recent_activities[] = ['description' => $description, 'timestamp' => (int)$timestamp];
            }

            echo json_encode([
                'success' => true,
                'data' => $recent_activities
            ]);
            break;

        default:
            http_response_code(400); // Bad Request
            echo json_encode(["success" => false, "message" => "Action non spécifiée ou invalide."]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données lors de la récupération des données du tableau de bord.',
        'error' => $e->getMessage()
    ]);
}
?>
