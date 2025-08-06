<?php
session_start();
header('Content-Type: application/json');

// Inclure le fichier de connexion à la base de données.
require_once __DIR__ . '/../db_connect.php';

$response = ['success' => false, 'message' => ''];

// 1. Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    $response['message'] = 'Accès non autorisé. Vous devez être connecté en tant qu\'administrateur.';
    echo json_encode($response);
    exit();
}

// Récupérer les données de la requête POST
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (!isset($input['book_id']) || !is_numeric($input['book_id'])) {
    $response['message'] = 'ID du livre manquant ou invalide.';
    $response['input'] = $input; // Inclure les données d'entrée pour le débogage
    echo json_encode($response);
    exit();
}

if (!isset($input['file_type']) || !in_array($input['file_type'], ['cover', 'electronic'])) {
    $response['message'] = 'Type de fichier invalide. Doit être "cover" ou "electronic".';
    echo json_encode($response);
    exit();
}

$book_id = (int)$input['book_id'];
$file_type = $input['file_type']; // 'cover' ou 'electronic'

try {
    global $pdo;
    $pdo->beginTransaction();

    $file_id_column = ($file_type === 'cover') ? 'cover_image_id' : 'file_id';

    // 2. Récupérer l'ID du fichier et son chemin depuis la table 'livres' et 'fichiers'
    $stmt_get_file_info = $pdo->prepare("
        SELECT f.id AS file_id, f.chemin AS file_path
        FROM livres l
        JOIN fichiers f ON l.{$file_id_column} = f.id
        WHERE l.id = :book_id
    ");
    $stmt_get_file_info->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt_get_file_info->execute();
    $file_info = $stmt_get_file_info->fetch(PDO::FETCH_ASSOC);

    if (!$file_info) {
        $pdo->rollBack();
        $response['message'] = 'Aucun fichier associé trouvé pour ce livre et ce type.';
        echo json_encode($response);
        exit();
    }

    $file_id_to_delete = $file_info['file_id'];
    $file_path_on_server = __DIR__ . '/../..' . $file_info['file_path']; // Chemin absolu sur le serveur

    // 3. Supprimer le fichier physique du serveur
    if (file_exists($file_path_on_server)) {
        if (!unlink($file_path_on_server)) {
            $pdo->rollBack();
            $response['message'] = 'Impossible de supprimer le fichier physique du serveur.';
            echo json_encode($response);
            exit();
        }
    } else {
        // Le fichier n'existe pas physiquement, mais nous continuons pour nettoyer la base de données
        error_log("Fichier physique non trouvé pour suppression: " . $file_path_on_server);
    }

    // 4. Mettre à jour la colonne dans la table 'livres' à NULL
    $stmt_update_livre = $pdo->prepare("UPDATE livres SET {$file_id_column} = NULL WHERE id = :book_id");
    $stmt_update_livre->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    if (!$stmt_update_livre->execute()) {
        $pdo->rollBack();
        $response['message'] = 'Erreur lors de la mise à jour de la référence dans la table livres.';
        echo json_encode($response);
        exit();
    }

    // 5. Supprimer l'entrée du fichier de la table 'fichiers'
    $stmt_delete_file = $pdo->prepare("DELETE FROM fichiers WHERE id = :file_id");
    $stmt_delete_file->bindParam(':file_id', $file_id_to_delete, PDO::PARAM_INT);
    if (!$stmt_delete_file->execute()) {
        $pdo->rollBack();
        $response['message'] = 'Erreur lors de la suppression de l\'entrée du fichier dans la base de données.';
        echo json_encode($response);
        exit();
    }

    $pdo->commit();
    $response['success'] = true;
    $response['message'] = 'Fichier supprimé avec succès.';

} catch (PDOException $e) {
    $pdo->rollBack();
    $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
} catch (Exception $e) {
    $pdo->rollBack();
    $response['message'] = 'Une erreur inattendue est survenue : ' . $e->getMessage();
}

echo json_encode($response);
?>
