<?php
// api/users/profile_unified.php
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

require_once __DIR__ . '/../db_connect.php';

// Les actions GET (get, loan_history, subscription_history) ne nécessitent pas toujours d'authentification pour les vues publiques
// Les actions POST (update, change_password, send_admin_message) nécessitent une authentification.

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    switch ($action) {
        case 'get':
            $user_id_param = intval($_GET['userid'] ?? 0); // L'ID du profil demandé
            $current_logged_in_user_id = $_SESSION['user_id'] ?? 0;
            $is_admin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');

            // Si aucun userid n'est spécifié, et l'utilisateur est connecté,
            // alors il demande son propre profil.
            if ($user_id_param === 0 && $current_logged_in_user_id > 0) {
                $user_id_param = $current_logged_in_user_id;
            } elseif ($user_id_param === 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID utilisateur manquant."]);
                exit();
            }
            
            // Vérifier si la demande est pour le profil de l'utilisateur connecté ou si l'utilisateur est admin
            $is_viewing_own_profile = ($current_logged_in_user_id === $user_id_param);


            // Récupérer les données de l'utilisateur
            $stmt = $pdo->prepare("SELECT u.id, u.nom, u.email, u.bio, u.date_naissance, u.role, a.pseudo, a.biographie as author_biographie, a.id as author_db_id
                                    FROM users u
                                    LEFT JOIN auteurs a ON u.id = a.user_id
                                    WHERE u.id = :id");
            $stmt->bindParam(':id', $user_id_param, PDO::PARAM_INT);
            $stmt->execute();
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user_data) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Utilisateur non trouvé."]);
                exit();
            }

            $response_data = [
                'user' => [
                    'id' => $user_data['id'],
                    'nom' => $user_data['nom'],
                    // L'email peut être privé, à décider. Pour l'instant, on l'affiche pour le propriétaire ou l'admin
                    'email' => ($is_viewing_own_profile || $is_admin) ? $user_data['email'] : 'privé', 
                    'bio' => $user_data['bio'],
                    'date_naissance' => $user_data['date_naissance'],
                    'role' => $user_data['role']
                ],
                'author' => null,
                'loan_history' => [],
                'subscription_history' => []
            ];

            // Si l'utilisateur est un auteur, récupérer les données de l'auteur
            if ($user_data['role'] === 'author' && $user_data['pseudo']) {
                $response_data['author'] = [
                    'id' => $user_data['author_db_id'],
                    'pseudo' => $user_data['pseudo'],
                    'biographie' => $user_data['author_biographie']
                ];
            }

            // Récupérer l'historique des emprunts et abonnements uniquement si c'est le propriétaire ou un administrateur
            if ($is_viewing_own_profile || $is_admin) {
                // Trouver le lecteur_id associé
                $stmt_lecteur = $pdo->prepare("SELECT id FROM lecteurs WHERE user_id = :user_id");
                $stmt_lecteur->bindParam(':user_id', $user_id_param, PDO::PARAM_INT);
                $stmt_lecteur->execute();
                $lecteur_id = $stmt_lecteur->fetchColumn();

                if ($lecteur_id) {
                    // Historique des emprunts
                    $stmt_loans = $pdo->prepare("SELECT e.date_emprunt, e.date_retour, e.rendu, l.titre AS livre_titre, l.id AS livre_id
                                                FROM emprunts e
                                                JOIN livres l ON e.livre_id = l.id
                                                WHERE e.lecteur_id = :lecteur_id
                                                ORDER BY e.date_emprunt DESC");
                    $stmt_loans->bindParam(':lecteur_id', $lecteur_id, PDO::PARAM_INT);
                    $stmt_loans->execute();
                    $response_data['loan_history'] = $stmt_loans->fetchAll(PDO::FETCH_ASSOC);

                    // Historique des abonnements
                    $stmt_subscriptions = $pdo->prepare("SELECT s.id AS abonnement_id, s.date_debut, s.date_fin, s.statut
                                                        FROM abonnements s
                                                        WHERE s.lecteur_id = :lecteur_id
                                                        ORDER BY s.date_debut DESC");
                    $stmt_subscriptions->bindParam(':lecteur_id', $lecteur_id, PDO::PARAM_INT);
                    $stmt_subscriptions->execute();
                    $response_data['subscription_history'] = $stmt_subscriptions->fetchAll(PDO::FETCH_ASSOC);
                }
            }

            echo json_encode(["success" => true, "data" => $response_data]);
            break;

        case 'update':
            // L'authentification est requise pour mettre à jour un profil
            if (!$current_logged_in_user_id) {
                http_response_code(401);
                echo json_encode(["success" => false, "message" => "Non autorisé. Veuillez vous connecter."]);
                exit();
            }

            $user_nom = $input['nom'] ?? null;
            $user_bio = $input['bio'] ?? null;
            $user_date_naissance = isset($input['date_naissance']) && $input['date_naissance'] !== '' ? strtotime($input['date_naissance']) : null;
            $user_role = $input['role'] ?? null; 

            if (empty($user_nom)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Le nom est requis."]);
                exit();
            }

            // Mettre à jour les informations de l'utilisateur
            $stmt = $pdo->prepare("UPDATE users SET nom = :nom, bio = :bio, date_naissance = :date_naissance WHERE id = :id");
            $stmt->bindParam(':nom', $user_nom);
            $stmt->bindParam(':bio', $user_bio);
            $stmt->bindParam(':date_naissance', $user_date_naissance, PDO::PARAM_INT);
            $stmt->bindParam(':id', $current_logged_in_user_id, PDO::PARAM_INT);
            $stmt->execute();

            // Mettre à jour la session si le nom a changé
            if (isset($_SESSION['user_name']) && $_SESSION['user_name'] !== $user_nom) {
                $_SESSION['user_name'] = $user_nom;
            }

            // Si l'utilisateur est un auteur, mettre à jour ou créer les informations de l'auteur
            if ($user_role === 'author') {
                $author_pseudo = $input['author_pseudo'] ?? null;
                $author_biographie = $input['author_biographie'] ?? null;

                if (empty($author_pseudo)) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Le pseudonyme de l'auteur est requis."]);
                    exit();
                }

                // Vérifier si l'entrée auteur existe
                $stmt_check_author = $pdo->prepare("SELECT id FROM auteurs WHERE user_id = :user_id");
                $stmt_check_author->bindParam(':user_id', $current_logged_in_user_id, PDO::PARAM_INT);
                $stmt_check_author->execute();
                $existing_author = $stmt_check_author->fetch(PDO::FETCH_ASSOC);

                if ($existing_author) {
                    // Mettre à jour l'auteur existant
                    $stmt_update_author = $pdo->prepare("UPDATE auteurs SET pseudo = :pseudo, biographie = :biographie WHERE user_id = :user_id");
                    $stmt_update_author->bindParam(':pseudo', $author_pseudo);
                    $stmt_update_author->bindParam(':biographie', $author_biographie);
                    $stmt_update_author->bindParam(':user_id', $current_logged_in_user_id, PDO::PARAM_INT);
                    $stmt_update_author->execute();
                } else {
                    // Créer une nouvelle entrée auteur
                    $stmt_insert_author = $pdo->prepare("INSERT INTO auteurs (user_id, nom, pseudo, biographie) VALUES (:user_id, :nom, :pseudo, :biographie)");
                    $stmt_insert_author->bindParam(':user_id', $current_logged_in_user_id, PDO::PARAM_INT);
                    $stmt_insert_author->bindParam(':nom', $user_nom); // Utiliser le nom de l'utilisateur comme nom par défaut pour l'auteur
                    $stmt_insert_author->bindParam(':pseudo', $author_pseudo);
                    $stmt_insert_author->bindParam(':biographie', $author_biographie);
                    $stmt_insert_author->execute();
                }
            }

            echo json_encode(["success" => true, "message" => "Profil mis à jour avec succès."]);
            break;

        case 'change_password':
            // L'authentification est requise pour changer le mot de passe
            if (!$current_logged_in_user_id) {
                http_response_code(401);
                echo json_encode(["success" => false, "message" => "Non autorisé. Veuillez vous connecter."]);
                exit();
            }

            $current_password = $input['current_password'] ?? null;
            $new_password = $input['new_password'] ?? null;

            if (empty($current_password) || empty($new_password)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Tous les champs de mot de passe sont requis."]);
                exit();
            }
            if (strlen($new_password) < 6) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Le nouveau mot de passe doit contenir au moins 6 caractères."]);
                exit();
            }

            // Vérifier le mot de passe actuel de l'utilisateur
            $stmt = $pdo->prepare("SELECT mot_de_passe FROM users WHERE id = :id");
            $stmt->bindParam(':id', $current_logged_in_user_id, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($current_password, $user['mot_de_passe'])) {
                http_response_code(401);
                echo json_encode(["success" => false, "message" => "Le mot de passe actuel est incorrect."]);
                exit();
            }

            // Hasher et mettre à jour le nouveau mot de passe
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET mot_de_passe = :new_password WHERE id = :id");
            $stmt->bindParam(':new_password', $hashed_password);
            $stmt->bindParam(':id', $current_logged_in_user_id, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode(["success" => true, "message" => "Mot de passe mis à jour avec succès."]);
            break;

        case 'send_admin_message':
            // L'authentification est requise pour envoyer un message
            if (!$current_logged_in_user_id) {
                http_response_code(401);
                echo json_encode(["success" => false, "message" => "Non autorisé. Veuillez vous connecter."]);
                exit();
            }

            $message_content = $input['message'] ?? null;

            if (empty($message_content)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Le message ne peut pas être vide."]);
                exit();
            }

            // Récupérer le lecteur_id de l'utilisateur actuel
            $stmt_lecteur = $pdo->prepare("SELECT id FROM lecteurs WHERE user_id = :user_id");
            $stmt_lecteur->bindParam(':user_id', $current_logged_in_user_id, PDO::PARAM_INT);
            $stmt_lecteur->execute();
            $lecteur_id = $stmt_lecteur->fetchColumn();

            // Ici, vous devriez avoir un mécanisme pour identifier l'ID lecteur de l'administrateur
            // Pour l'exemple, nous allons insérer cela pour un ID lecteur factice (ex: ID 1 pour un admin)
            // En production, vous feriez une recherche réelle de l'admin
            $admin_lecteur_id = 1; // ID de lecteur de l'administrateur (À ADAPTER à votre DB)

            $notification_message = "Message de l'utilisateur " . htmlspecialchars($_SESSION['user_name'] ?? 'ID ' . $current_logged_in_user_id) . " (ID Lecteur: " . ($lecteur_id ?? 'N/A') . ") : " . $message_content;

            $stmt_insert = $pdo->prepare("INSERT INTO notifications (lecteur_id, message, date_notification, lu) VALUES (:lecteur_id, :message, :date_notification, FALSE)");
            $stmt_insert->bindParam(':lecteur_id', $admin_lecteur_id, PDO::PARAM_INT); 
            $stmt_insert->bindParam(':message', $notification_message);
            $stmt_insert->bindValue(':date_notification', time(), PDO::PARAM_INT);
            $stmt_insert->execute();

            echo json_encode(["success" => true, "message" => "Message envoyé à l'administrateur."]);
            break;

        default:
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Action invalide."]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Unified Profile API Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Erreur de base de données : " . $e->getMessage()]);
}
