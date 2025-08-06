<?php
session_start();
header('Content-Type: application/json');

// Inclure le fichier de connexion à la base de données.
// Le chemin est corrigé pour correspondre à la structure où db_connect.php est dans le répertoire parent.
require_once __DIR__ . '/../db_connect.php';

$response = ['success' => false, 'message' => ''];

// 1. Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    $response['message'] = 'Accès non autorisé. Vous devez être connecté en tant qu\'administrateur.';
    echo json_encode($response);
    exit();
}

// 2. Vérifier si l'ID du livre est fourni et est un nombre valide
if (!isset($_POST['book_id']) || !is_numeric($_POST['book_id'])) {
    $response['message'] = 'ID du livre manquant ou invalide.';
    echo json_encode($response);
    exit();
}

$book_id = (int)$_POST['book_id'];

// 3. Vérifier si un fichier a été uploadé et s'il n'y a pas d'erreur
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Aucun fichier uploadé ou erreur lors de l\'upload.';
    // Gérer les erreurs d'upload spécifiques
    switch ($_FILES['file']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $response['message'] = 'Le fichier est trop volumineux.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $response['message'] = 'Le fichier n\'a été que partiellement uploadé.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $response['message'] = 'Aucun fichier n\'a été sélectionné.';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $response['message'] = 'Dossier temporaire manquant.';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $response['message'] = 'Échec de l\'écriture du fichier sur le disque.';
            break;
        case UPLOAD_ERR_EXTENSION:
            $response['message'] = 'Une extension PHP a arrêté l\'upload du fichier.';
            break;
    }
    echo json_encode($response);
    exit();
}

$file = $_FILES['file'];
// Définir le répertoire de destination pour les images de couverture
$upload_dir = __DIR__ . '/../../public/uploads/covers/'; 

// 4. Créer le répertoire si il n'existe pas
if (!is_dir($upload_dir)) {
    // Tente de créer le répertoire avec des permissions 0777 (lecture/écriture/exécution pour tous)
    // Le 'true' final permet la création récursive des répertoires parents
    if (!mkdir($upload_dir, 0777, true)) {
        $response['message'] = 'Impossible de créer le répertoire d\'upload.';
        echo json_encode($response);
        exit();
    }
}

// 5. Valider le type de fichier (images seulement)
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowed_types)) {
    $response['message'] = 'Type de fichier non autorisé. Seules les images (JPEG, PNG, GIF, WebP) sont acceptées.';
    echo json_encode($response);
    exit();
}

// 6. Générer un nom de fichier unique pour éviter les conflits
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_file_name = uniqid('cover_', true) . '.' . $file_extension;
$destination_path = $upload_dir . $new_file_name;
$public_url = '/public/uploads/covers/' . $new_file_name; // URL publique pour accéder à l'image depuis le navigateur

// 7. Déplacer le fichier uploadé du répertoire temporaire vers la destination finale
if (move_uploaded_file($file['tmp_name'], $destination_path)) {
    try {
        // Utiliser la variable $pdo déjà définie par db_connect.php
        global $pdo; 

        // Début de la transaction pour assurer l'intégrité des données
        $pdo->beginTransaction();

        // 8. Insérer les informations du fichier dans la table 'fichiers'
        $stmt_insert_file = $pdo->prepare(
            "INSERT INTO fichiers (nom, chemin, type, taille, date_telechargement) 
             VALUES (:nom, :chemin, :type, :taille, :date_telechargement)"
        );
        $stmt_insert_file->bindParam(':nom', $file['name']);
        $stmt_insert_file->bindParam(':chemin', $public_url); // Stocker l'URL publique comme chemin
        $stmt_insert_file->bindParam(':type', $file['type']);
        $stmt_insert_file->bindParam(':taille', $file['size'], PDO::PARAM_INT);
        $current_timestamp = time(); // Obtenir le timestamp Unix actuel
        $stmt_insert_file->bindParam(':date_telechargement', $current_timestamp, PDO::PARAM_INT);

        if ($stmt_insert_file->execute()) {
            $file_id = $pdo->lastInsertId(); // Récupérer l'ID du fichier nouvellement inséré

            // 9. Mettre à jour le champ 'cover_image_id' dans la table 'livres'
            $stmt_update_livre = $pdo->prepare("UPDATE livres SET cover_image_id = :cover_image_id WHERE id = :book_id");
            $stmt_update_livre->bindParam(':cover_image_id', $file_id, PDO::PARAM_INT);
            $stmt_update_livre->bindParam(':book_id', $book_id, PDO::PARAM_INT);

            if ($stmt_update_livre->execute()) {
                $pdo->commit(); // Valider la transaction
                $response['success'] = true;
                $response['message'] = 'Image de couverture uploadée et mise à jour avec succès.';
                $response['cover_image_url'] = $public_url; // Retourner la nouvelle URL pour rafraîchir le client
            } else {
                $pdo->rollBack(); // Annuler la transaction en cas d'échec de la mise à jour du livre
                unlink($destination_path); // Supprimer le fichier uploadé
                $response['message'] = 'Erreur lors de la mise à jour de la référence de l\'image de couverture dans la base de données.';
            }
        } else {
            $pdo->rollBack(); // Annuler la transaction en cas d'échec de l'insertion du fichier
            unlink($destination_path); // Supprimer le fichier uploadé
            $response['message'] = 'Erreur lors de l\'insertion des informations du fichier dans la base de données.';
        }
    } catch (PDOException $e) {
        $pdo->rollBack(); // Annuler la transaction en cas d'exception
        unlink($destination_path); // Supprimer le fichier uploadé
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Erreur lors du déplacement du fichier uploadé. Vérifiez les permissions du dossier d\'upload.';
}

echo json_encode($response);
?>
