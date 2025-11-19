<?php
// api/map/get_shapefiles.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Permettre l'accès CORS depuis la page de visualisation

require_once __DIR__ . '/../db_connect.php'; // Remonter d'un niveau pour trouver db_connect.php

try {
    // 1. Récupérer le paramètre area_ids de l'URL (ex: ?area_ids=1,5,12)
    $area_ids_param = $_GET['area_ids'] ?? null;
    
    // 2. Initialiser les variables de la requête
    $where_clause = "";
    $params = [];
    
    if (!empty($area_ids_param)) {
        // Nettoyer et valider les IDs (doivent être des nombres séparés par des virgules)
        $ids = array_filter(array_map('intval', explode(',', $area_ids_param)));
        
        if (!empty($ids)) {
            // Créer les placeholders pour la clause IN (:id1, :id2, ...)
            $placeholders = [];
            foreach ($ids as $index => $id) {
                $placeholder = ":id" . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $id;
            }
            // Ajouter la clause WHERE pour filtrer par ID
            $where_clause = " AND sa.id IN (" . implode(', ', $placeholders) . ")";
        }
    }

    // 3. Construction de la requête SQL complète
    // La clause `WHERE` initiale concernant le type de fichier est conservée.
    $sql = "
        SELECT 
            sa.id AS study_area_id, 
            sa.name AS study_area_name, 
            sa.description,
            f.chemin AS shapefile_url,
            f.nom AS file_name
        FROM 
            study_areas sa
        JOIN 
            fichiers f ON sa.shapefile_id = f.id
        WHERE
            (f.type LIKE 'application/zip' OR f.nom LIKE '%.zip')
            {$where_clause} -- Ajout de la nouvelle clause WHERE optionnelle
        ORDER BY 
            sa.id DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    
    // 4. Exécuter la requête avec les paramètres (si présents)
    $stmt->execute($params);
    $shapefiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true, 
        "data" => $shapefiles,
        "count" => count($shapefiles)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("DB Error in get_shapefiles.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Erreur interne du serveur lors de la récupération des fichiers."]);
}
?>