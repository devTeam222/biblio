<?php
session_start();
// Pas de Content-Type: application/json ici car nous allons servir un fichier
// header('Content-Type: application/json');

// Inclure le fichier de connexion à la base de données.
require_once __DIR__ . '/../db_connect.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo 'Accès non autorisé. Veuillez vous connecter.';
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'guest';

// Récupérer les paramètres de l'URL
$file_id = intval($_GET['file_id'] ?? 0);
$book_id = intval($_GET['book_id'] ?? 0);

if (!$file_id || !$book_id) {
    http_response_code(400); // Bad Request
    echo 'Paramètres de téléchargement manquants ou invalides.';
    exit();
}

try {
    global $pdo;

    // 1. Vérifier si le fichier_id est bien associé au livre_id dans la table livres
    $stmt_check_association = $pdo->prepare("
        SELECT 
            l.id AS livre_id, 
            l.file_id AS electronic_file_id, 
            l.cover_image_id AS cover_file_id,
            f.chemin AS file_path,
            f.nom AS file_name,
            f.type AS file_mime_type,
            f.taille AS file_size
        FROM livres l
        JOIN fichiers f ON f.id = :file_id
        WHERE l.id = :book_id AND (l.file_id = :file_id OR l.cover_image_id = :file_id)
    ");
    $stmt_check_association->bindParam(':file_id', $file_id, PDO::PARAM_INT);
    $stmt_check_association->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt_check_association->execute();
    $file_info = $stmt_check_association->fetch(PDO::FETCH_ASSOC);

    if (!$file_info) {
        http_response_code(404); // Not Found
        echo 'Fichier ou livre non trouvé, ou association incorrecte.';
        exit();
    }

    $is_electronic_file = ($file_info['electronic_file_id'] == $file_id);
    $is_cover_file = ($file_info['cover_file_id'] == $file_id);

    // 2. Vérifier les permissions de l'utilisateur
    $allowed_to_download = false;

    if ($user_role === 'admin') {
        $allowed_to_download = true; // Les administrateurs peuvent tout télécharger
    } elseif ($user_role === 'user' && $is_electronic_file) {
        // Pour les fichiers électroniques, vérifier si le lecteur a un abonnement actif
        $stmt_get_lecteur_id = $pdo->prepare("SELECT id FROM lecteurs WHERE user_id = :user_id");
        $stmt_get_lecteur_id->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_get_lecteur_id->execute();
        $lecteur_id = $stmt_get_lecteur_id->fetchColumn();

        if ($lecteur_id) {
            $current_timestamp = time();
            $stmt_check_subscription = $pdo->prepare("
                SELECT COUNT(*) 
                FROM abonnements 
                WHERE lecteur_id = :lecteur_id 
                AND statut = 'actif' 
                AND date_fin >= :current_timestamp
            ");
            $stmt_check_subscription->bindParam(':lecteur_id', $lecteur_id, PDO::PARAM_INT);
            $stmt_check_subscription->bindParam(':current_timestamp', $current_timestamp, PDO::PARAM_INT);
            $stmt_check_subscription->execute();
            $has_active_subscription = $stmt_check_subscription->fetchColumn() > 0;

            if ($has_active_subscription) {
                $allowed_to_download = true;
            }
        }
    } elseif ($is_cover_file) {
        // Les images de couverture sont généralement publiques ou accessibles à tous les connectés
        // Si vous voulez restreindre, ajoutez une logique ici. Pour l'instant, on les considère accessibles
        $allowed_to_download = true; 
    }

    if (!$allowed_to_download) {
        http_response_code(403); // Forbidden
        echo 'Accès refusé. Vous n\'avez pas les permissions nécessaires pour télécharger ce fichier.';
        exit();
    }

    // 3. Préparer le chemin absolu du fichier sur le serveur
    $file_path_on_server = __DIR__ . '/../..' . $file_info['file_path'];

    // Vérifier si le fichier existe physiquement
    if (!file_exists($file_path_on_server)) {
        http_response_code(404); // Not Found
        echo 'Le fichier demandé n\'existe pas sur le serveur.';
        exit();
    }

    // 4. Définir les en-têtes pour le téléchargement
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $file_info['file_mime_type']);
    header('Content-Disposition: attachment; filename="' . basename($file_info['file_name']) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $file_info['file_size']);

    // 5. Lire et envoyer le fichier
    readfile($file_path_on_server);
    exit();

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo 'Erreur de base de données : ' . $e->getMessage();
    exit();
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo 'Une erreur inattendue est survenue : ' . $e->getMessage();
    exit();
}
?>
