<?php
// api/file/upload_shapefile.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../db_connect.php';

$response = ['success' => false, 'message' => ''];

// 1. Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    $response['message'] = 'Accès non autorisé. Vous devez être connecté en tant qu\'administrateur.';
    echo json_encode($response);
    exit();
}

// 2. Vérifier si un fichier a été uploadé et s'il n'y a pas d'erreur
if (!isset($_FILES['shapefile']) || $_FILES['shapefile']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Aucun fichier uploadé ou erreur lors de l\'upload.';
    echo json_encode($response);
    exit();
}

$file = $_FILES['shapefile'];
// Définir le répertoire de destination pour les shapefiles
$upload_dir = __DIR__ . '/../../public/uploads/shapefiles/'; 

// 3. Créer le répertoire si il n'existe pas
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        $response['message'] = 'Impossible de créer le répertoire d\'upload.';
        echo json_encode($response);
        exit();
    }
}

// 4. Valider le type de fichier (doit être un ZIP, car un shapefile est généralement un ensemble de fichiers zippés)
$allowed_mime_types = [
    'application/zip', 
    'application/x-zip-compressed', 
];

// Vérifiez également l'extension pour plus de sécurité
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);

if (!in_array($file['type'], $allowed_mime_types) && strtolower($file_extension) !== 'zip') {
    $response['message'] = 'Type de fichier non autorisé. Seuls les fichiers ZIP sont acceptés pour les shapefiles.';
    echo json_encode($response);
    exit();
}

// 5. Générer un nom de fichier unique et définir le chemin
$new_file_name = uniqid("shapefile_") .'.'. $file_extension;
$destination_path = $upload_dir . $new_file_name;
$public_url = '/public/uploads/shapefiles/' . $new_file_name; // URL publique

// 6. Déplacer le fichier uploadé
if (move_uploaded_file($file['tmp_name'], $destination_path)) {
    try {
        global $pdo; 

        // Début de la transaction
        $pdo->beginTransaction();

        // 7. Insérer les informations du fichier dans la table 'fichiers'
        $stmt_insert_file = $pdo->prepare(
            "INSERT INTO fichiers (nom, chemin, type, taille, date_telechargement) 
             VALUES (:nom, :chemin, :type, :taille, :date_telechargement)"
        );
        $stmt_insert_file->bindParam(':nom', $file['name']);
        $stmt_insert_file->bindParam(':chemin', $public_url); // Chemin public
        $stmt_insert_file->bindParam(':type', $file['type']);
        $stmt_insert_file->bindParam(':taille', $file['size'], PDO::PARAM_INT);
        $current_timestamp = time(); 
        $stmt_insert_file->bindParam(':date_telechargement', $current_timestamp, PDO::PARAM_INT);

        if ($stmt_insert_file->execute()) {
            $file_id = $pdo->lastInsertId(); // Récupérer l'ID
            $pdo->commit(); // Valider la transaction

            $response['success'] = true;
            $response['message'] = 'Shapefile uploadé avec succès.';
            $response['file_id'] = $file_id; // Renvoyer l'ID pour la création de la zone d'étude
            $response['file_url'] = $public_url;
        } else {
            $pdo->rollBack(); 
            unlink($destination_path);
            $response['message'] = 'Erreur lors de l\'insertion du fichier dans la base de données.';
        }
    } catch (PDOException $e) {
        $pdo->rollBack(); 
        unlink($destination_path);
        $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Erreur lors du déplacement du fichier uploadé.';
}

echo json_encode($response);
?>