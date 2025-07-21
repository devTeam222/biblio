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

require_once __DIR__.'/../db_connect.php';

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
            // Activité récente avec pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;
            $offset = ($page - 1) * $per_page;

            $stmt = $pdo->prepare("
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
                LIMIT :per_page OFFSET :offset
            ");
            $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $raw_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $recent_activities = [];
            foreach ($raw_activities as $activity) {
                $type = $activity['rendu'] ? 'return' : 'loan'; // Déduire le type
                $description = $activity['rendu'] ? "Livre '{$activity['titre']}' rendu par {$activity['user_name']}." : "Livre '{$activity['titre']}' emprunté par {$activity['user_name']}.";
                $timestamp = $activity['rendu'] ? $activity['date_retour'] : $activity['date_emprunt']; // Utiliser la date de retour si rendu

                $recent_activities[] = [
                    'description' => $description,
                    'timestamp' => (int)$timestamp,
                    'type' => $type // Ajouter le type
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $recent_activities
            ]);
            break;

        case 'chart_data':
            // Données pour le graphique d'activité (emprunts/retours par jour)
            $days_range = isset($_GET['days']) ? (int)$_GET['days'] : 7; // Par défaut, 7 jours
            $start_timestamp = strtotime("-{$days_range} days", time());

            $stmt = $pdo->prepare("
                SELECT 
                    TO_CHAR(TO_TIMESTAMP(date_emprunt), 'YYYY-MM-DD') AS activity_date,
                    COUNT(*) AS total_loans,
                    SUM(CASE WHEN rendu = TRUE THEN 1 ELSE 0 END) AS total_returns
                FROM emprunts
                WHERE date_emprunt >= :start_timestamp OR date_retour >= :start_timestamp
                GROUP BY activity_date
                ORDER BY activity_date ASC
            ");
            $stmt->bindParam(':start_timestamp', $start_timestamp, PDO::PARAM_INT);
            $stmt->execute();
            $daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $labels = [];
            $loan_data = [];
            $return_data = [];

            // Initialiser toutes les dates du range avec 0
            for ($i = 0; $i < $days_range; $i++) {
                $dayrange = $days_range - 1 - $i; // Inverser l'index pour avoir les dates récentes en premier
                $date = date('Y-m-d', strtotime("-$dayrange days"));
                // Convertir en temps unix pour la compatibilité
                $unix_timestamp = strtotime($date);
                $labels[] = $unix_timestamp; // Ex: Lun 01 Jan
                $loan_data[$date] = 0;
                $return_data[$date] = 0;
            }

            // Remplir avec les données réelles
            foreach ($daily_data as $row) {
                $date_key = $row['activity_date'];
                if (isset($loan_data[$date_key])) { // S'assurer que la date est dans le range
                    $loan_data[$date_key] = (int)$row['total_loans'];
                    $return_data[$date_key] = (int)$row['total_returns'];
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'loan_data' => array_values($loan_data), // Convertir en tableau indexé
                    'return_data' => array_values($return_data) // Convertir en tableau indexé
                ]
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
