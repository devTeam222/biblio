<?php
// api/profile.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__.'/db_connect.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(["success" => false, "message" => "Accès non autorisé. Veuillez vous connecter."]);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    switch ($action) {
        case 'details':
            // Récupérer les détails de l'utilisateur
            $stmt_user = $pdo->prepare("SELECT id, nom, email, role, bio, date_naissance FROM users WHERE id = :user_id");
            $stmt_user->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
            $stmt_user->execute();
            $user_details = $stmt_user->fetch(PDO::FETCH_ASSOC);

            if (!$user_details) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Utilisateur non trouvé."]);
                exit();
            }

            // Récupérer l'ID du lecteur associé (s'il existe)
            $lecteur_id = null;
            $stmt_lecteur = $pdo->prepare("SELECT id FROM lecteurs WHERE user_id = :user_id");
            $stmt_lecteur->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
            $stmt_lecteur->execute();
            $lecteur_data = $stmt_lecteur->fetch(PDO::FETCH_ASSOC);
            if ($lecteur_data) {
                $lecteur_id = $lecteur_data['id'];
            }

            $loan_history = [];
            $subscription_history = [];

            if ($lecteur_id) {
                // Récupérer l'historique des emprunts (rendus ou non)
                $stmt_loans = $pdo->prepare("
                    SELECT 
                        e.id AS emprunt_id, 
                        l.titre AS livre_titre, 
                        e.date_emprunt, 
                        e.date_retour, 
                        e.rendu
                    FROM emprunts e
                    JOIN livres l ON e.livre_id = l.id
                    WHERE e.lecteur_id = :lecteur_id
                    ORDER BY e.date_emprunt DESC
                ");
                $stmt_loans->bindParam(':lecteur_id', $lecteur_id, PDO::PARAM_INT);
                $stmt_loans->execute();
                $loan_history = $stmt_loans->fetchAll(PDO::FETCH_ASSOC);

                // Récupérer l'historique des abonnements
                $stmt_subscriptions = $pdo->prepare("
                    SELECT 
                        id AS abonnement_id, 
                        date_debut, 
                        date_fin, 
                        statut
                    FROM abonnements
                    WHERE lecteur_id = :lecteur_id
                    ORDER BY date_debut DESC
                ");
                $stmt_subscriptions->bindParam(':lecteur_id', $lecteur_id, PDO::PARAM_INT);
                $stmt_subscriptions->execute();
                $subscription_history = $stmt_subscriptions->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode([
                "success" => true,
                "data" => [
                    "user" => $user_details,
                    "loan_history" => $loan_history,
                    "subscription_history" => $subscription_history
                ]
            ]);
            break;

        case 'update':
            $nom = $input['nom'] ?? null;
            $bio = $input['bio'] ?? null;
            $date_naissance = $input['date_naissance'] ?? null;
            $password = $input['password'] ?? null;

            if (!$nom) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Le nom est requis."]);
                exit();
            }

            $sql = "UPDATE users SET nom = :nom, bio = :bio, date_naissance = :date_naissance";
            $params = [
                ':nom' => $nom,
                ':bio' => $bio,
                ':date_naissance' => $date_naissance ? strtotime($date_naissance) : null,
                ':user_id' => $current_user_id
            ];

            if ($password) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql .= ", mot_de_passe = :password";
                $params[':password'] = $hashed_password;
            }

            $sql .= " WHERE id = :user_id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() > 0) {
                // Mettre à jour la session si le nom a été modifié
                $_SESSION['user_name'] = $nom;
                echo json_encode(["success" => true, "message" => "Profil mis à jour avec succès."]);
            } else {
                echo json_encode(["success" => false, "message" => "Aucune modification apportée ou erreur lors de la mise à jour."]);
            }
            break;

        case 'contact_admin':
            $message = $input['message'] ?? null;
            if (empty($message)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Le message ne peut pas être vide."]);
                exit();
            }

            // Ici, vous implémenteriez la logique réelle pour envoyer le message à l'admin.
            // Par exemple, l'enregistrer dans une table de base de données 'notifications'
            // ou envoyer un email (nécessite une configuration SMTP).
            // Pour cette démo, nous allons juste simuler le succès.

            // Exemple de simulation d'enregistrement dans une table de notifications
            // Assurez-vous d'avoir une table 'notifications' avec des colonnes comme
            // 'lecteur_id', 'message', 'date_envoi', 'lu'
            
            // Récupérer l'ID du lecteur
            $lecteur_id = null;
            $stmt_lecteur = $pdo->prepare("SELECT id FROM lecteurs WHERE user_id = :user_id");
            $stmt_lecteur->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
            $stmt_lecteur->execute();
            $lecteur_data = $stmt_lecteur->fetch(PDO::FETCH_ASSOC);
            if ($lecteur_data) {
                $lecteur_id = $lecteur_data['id'];
            }

            if ($lecteur_id) {
                $stmt_insert_notification = $pdo->prepare("INSERT INTO notifications (lecteur_id, message, date_envoi, lu) VALUES (:lecteur_id, :message, :date_envoi, FALSE)");
                $stmt_insert_notification->bindParam(':lecteur_id', $lecteur_id, PDO::PARAM_INT);
                $stmt_insert_notification->bindParam(':message', $message, PDO::PARAM_STR);
                $current_time = time();
                $stmt_insert_notification->bindParam(':date_envoi', $current_time, PDO::PARAM_INT);
                $stmt_insert_notification->execute();
                echo json_encode(["success" => true, "message" => "Votre message a été envoyé à l'administrateur."]);
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Impossible d'envoyer le message : lecteur non identifié."]);
            }
            
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
