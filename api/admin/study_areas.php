<?php
// api/admin/study_areas.php
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

require_once __DIR__.'/../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Accès non autorisé."]);
    exit();
}

$action = $_GET['action'] ?? '';
$input = $_POST;
$search = isset($_GET['search']) ? htmlspecialchars(trim($_GET['search'])) : '';

try {
    switch ($action) {
        case 'create':
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $geojson_data = $input['geojson'] ?? null;
            
            if (empty($name) || strlen($name) < 3) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Le nom de la zone d'étude est requis."]);
                exit();
            }

            $shapefile_id = null;
            
            // Si GeoJSON est fourni, sauvegarder le fichier
            if ($geojson_data) {
                try {
                    // Valider que c'est du JSON valide
                    $geojson = json_decode($geojson_data, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("GeoJSON invalide: " . json_last_error_msg());
                    }
                    
                    // Vérifier que c'est un FeatureCollection valide
                    if (!isset($geojson['type']) || $geojson['type'] !== 'FeatureCollection') {
                        throw new Exception("Le GeoJSON doit être de type FeatureCollection");
                    }
                    
                    // Créer le répertoire de destination pour les GeoJSON
                    $upload_dir = __DIR__ . '/../../public/uploads/geojson/';
                    if (!is_dir($upload_dir)) {
                        if (!mkdir($upload_dir, 0777, true)) {
                            throw new Exception("Impossible de créer le répertoire d'upload.");
                        }
                    }
                    
                    // Générer un nom de fichier unique
                    $filename = uniqid("geojson_") . '.geojson';
                    $filepath = $upload_dir . $filename;
                    $public_url = '/public/uploads/geojson/' . $filename;
                    
                    // Sauvegarder le fichier GeoJSON
                    if (file_put_contents($filepath, $geojson_data) === false) {
                        throw new Exception("Erreur lors de l'écriture du fichier GeoJSON.");
                    }
                    
                    // Insérer l'entrée dans la table fichiers
                    $stmt_file = $pdo->prepare("
                        INSERT INTO fichiers (nom, chemin, type, taille, date_telechargement) 
                        VALUES (:nom, :chemin, :type, :taille, :date_telechargement)
                    ");
                    
                    $file_name = $name . '.geojson';
                    $file_type = 'application/geo+json';
                    $file_size = filesize($filepath);
                    $current_timestamp = time();
                    
                    $stmt_file->bindParam(':nom', $file_name);
                    $stmt_file->bindParam(':chemin', $public_url);
                    $stmt_file->bindParam(':type', $file_type);
                    $stmt_file->bindParam(':taille', $file_size, PDO::PARAM_INT);
                    $stmt_file->bindParam(':date_telechargement', $current_timestamp, PDO::PARAM_INT);
                    
                    if (!$stmt_file->execute()) {
                        unlink($filepath); // Supprimer le fichier en cas d'erreur
                        throw new Exception("Erreur lors de l'insertion du fichier dans la base de données.");
                    }
                    
                    $shapefile_id = $pdo->lastInsertId();
                } catch (Exception $e) {
                    echo json_encode(["success" => false, "message" => $e->getMessage()]);
                    exit();
                }
            }
            
            // Insérer la zone d'étude
            $stmt = $pdo->prepare("
                INSERT INTO public.study_areas (name, description, shapefile_id, created_at, updated_at) 
                VALUES (:name, :description, :shapefile_id, :created_at, :updated_at)
            ");

            $current_timestamp = time();
            
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':shapefile_id', $shapefile_id, $shapefile_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':created_at', $current_timestamp, PDO::PARAM_INT);
            $stmt->bindParam(':updated_at', $current_timestamp, PDO::PARAM_INT);
            
            $stmt->execute();
            $new_id = $pdo->lastInsertId();

            echo json_encode([
                "success" => true, 
                "message" => "Zone d'étude créée avec succès.", 
                "id" => $new_id
            ]);
            break;
        case 'list':
            $search = htmlspecialchars(trim($search)) ?? '';
            $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
            $limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT);

            if ($page < 1) $page = 1;
            if ($limit < 1) $limit = 10;
            $offset = ($page - 1) * $limit;

            $whereClause = '';
            $params = [];
            if (!empty($search)) {
                $whereClause .= ' WHERE name ILIKE ? OR description ILIKE ?';
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $countStmt = $pdo->prepare("SELECT COUNT(id) FROM study_areas" . $whereClause);
            $countStmt->execute($params);
            $totalCount = $countStmt->fetchColumn();

            $sql = "SELECT 
                    sa.id, 
                    sa.name, 
                    sa.description, 
                    sa.shapefile_id, 
                    sa.created_at,
                    f.chemin AS shapefile_url 
                FROM 
                    study_areas sa
                LEFT JOIN 
                    fichiers f ON sa.shapefile_id = f.id" 
                    . $whereClause . " ORDER BY name ASC LIMIT ? OFFSET ?";
            
            $stmt = $pdo->prepare($sql);
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $study_areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "success" => true,
                "data" => $study_areas,
                "total" => $totalCount
            ]);
            break;

        case 'details':
            $id = intval($_GET['id'] ?? null);
            if (!$id) {
                echo json_encode(["success" => false, "message" => "ID de zone d'étude manquant."]);
                exit();
            }
            // Correction: Ajout de 'updated_at' et suppression de la virgule en trop
            $stmt = $pdo->prepare("SELECT id, name, description, shapefile_id, created_at, updated_at FROM study_areas WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $study_area = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($study_area) {
                echo json_encode(["success" => true, "data" => $study_area]);
            } else {
                echo json_encode(["success" => false, "message" => "Zone d'étude non trouvée."]);
            }
            break;

        case 'add':
            // Nouvelle implémentation utilisant shapefile_id
            $name = $input['name'] ?? null;
            $description = $input['description'] ?? null;
            // Récupère l'ID du fichier Shapefile uploadé
            $shapefile_id = filter_var($input['shapefile_id'] ?? null, FILTER_VALIDATE_INT);
            $shapefile_id = ($shapefile_id === false) ? null : $shapefile_id;

            if (empty($name)) {
                echo json_encode(["success" => false, "message" => "Le nom de la zone d'étude est requis."]);
                exit();
            }

            // Vérifiez si le shapefile_id est valide s'il est fourni
            if ($shapefile_id) {
                $check_file = $pdo->prepare("SELECT id FROM fichiers WHERE id = ?");
                $check_file->execute([$shapefile_id]);
                if (!$check_file->fetch()) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "ID de fichier shapefile invalide."]);
                    exit();
                }
            }

            // Insertion dans study_areas
            $stmt = $pdo->prepare("INSERT INTO study_areas (name, description, shapefile_id, created_at, updated_at) 
                                   VALUES (?, ?, ?, ?, ?)");
            $current_time = time();
            // L'ordre des paramètres est : name, description, shapefile_id, created_at, updated_at
            $stmt->execute([$name, $description, $shapefile_id, $current_time, $current_time]);

            echo json_encode(["success" => true, "message" => "Zone d'étude ajoutée avec succès.", "id" => $pdo->lastInsertId()]);
            break;

        case 'update':
            // Nouvelle implémentation utilisant shapefile_id
            $id = $input['id'] ?? null;
            $name = $input['name'] ?? null;
            $description = $input['description'] ?? null;
            // Récupère l'ID du fichier Shapefile si mis à jour
            $shapefile_id = filter_var($input['shapefile_id'] ?? null, FILTER_VALIDATE_INT);
            $shapefile_id = ($shapefile_id === false) ? null : $shapefile_id;

            if (!$id || empty($name)) {
                echo json_encode(["success" => false, "message" => "ID et nom de la zone d'étude sont requis."]);
                exit();
            }
            
            // Si un shapefile_id est fourni, vérifiez sa validité
            if ($shapefile_id) {
                $check_file = $pdo->prepare("SELECT id FROM fichiers WHERE id = ?");
                $check_file->execute([$shapefile_id]);
                if (!$check_file->fetch()) {
                    echo json_encode(["success" => false, "message" => "ID de fichier shapefile invalide."]);
                    exit();
                }
            }
            
            // Mise à jour de study_areas
            $stmt = $pdo->prepare("UPDATE study_areas SET name = ?, description = ?, shapefile_id = ?, updated_at = ? WHERE id = ?");
            $current_time = time();
            // L'ordre des paramètres est : name, description, shapefile_id, updated_at, id
            $stmt->execute([$name, $description, $shapefile_id, $current_time, $id]);
            echo json_encode(["success" => true, "message" => "Zone d'étude mise à jour."]);
            break;

        case 'delete':
            $id = intval($_GET['id'] ?? null);
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID de zone d'étude manquant."]);
                exit();
            }
            // OPTIONNEL: Récupérer l'ID du shapefile avant de supprimer pour le supprimer du disque (nécessite une gestion de suppression de fichier sécurisée)
            $stmt_get_file_id = $pdo->prepare("SELECT shapefile_id FROM study_areas WHERE id = :id");
            $stmt_get_file_id->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt_get_file_id->execute();
            $shapefile_data = $stmt_get_file_id->fetch(PDO::FETCH_ASSOC);
            $shapefile_id_to_delete = $shapefile_data['shapefile_id'] ?? null;


            $stmt = $pdo->prepare("DELETE FROM study_areas WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            // OPTIONNEL: Supprimer l'entrée du fichier dans la table fichiers après la suppression de la zone d'étude
            if ($shapefile_id_to_delete) {
                 // Note: La suppression du fichier physique sur le disque n'est pas implémentée ici pour des raisons de sécurité et de complexité (gestion des dépendances).
                 // Si vous supprimez ici, assurez-vous qu'aucun autre objet (ex: un autre livre) ne référence ce fichier.
                 $stmt_delete_file = $pdo->prepare("DELETE FROM fichiers WHERE id = :file_id");
                 $stmt_delete_file->bindParam(':file_id', $shapefile_id_to_delete, PDO::PARAM_INT);
                 $stmt_delete_file->execute();
            }

            echo json_encode(["success" => true, "message" => "Zone d'étude supprimée."]);
            break;

        default:
            echo json_encode(["success" => false, "message" => "Action invalide."]);
            break;
    }
} catch (PDOException $e) {
    // Afficher l'erreur PDO pour le débogage, mais la logguer en production
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>