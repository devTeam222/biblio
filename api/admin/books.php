<?php
// api/admin/books.php
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
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    // Function to get or create academic year ID (existing function)
    function getOrCreateAcademicYearId($pdo, $academicYearString) {
        if (!preg_match('/^(\d{4})-(\d{4})$/', $academicYearString, $matches)) {
            return ["success" => false, "message" => "Format d'année académique invalide. Utilisez YYYY-YYYY. $academicYearString", "input" => $academicYearString];
        }
        $startYear = (int)$matches[1];
        $endYear = (int)$matches[2];

        if ($endYear !== $startYear + 1) {
            return ["success" => false, "message" => "L'année de fin doit être l'année de début + 1."];
        }

        $stmt = $pdo->prepare("SELECT id FROM academic_year WHERE start = ? AND \"end\" = ?");
        $stmt->execute([$startYear, $endYear]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return ["success" => true, "id" => $result['id']];
        } else {
            // Check if user is trying to add a year in the future too far
            $currentYear = date('Y');
            if ($startYear > ($currentYear + 2)) { // Limit creation to current year + 2 (e.g., 2025 -> 2027-2028)
                return ["success" => false, "message" => "Impossible d'ajouter une année académique trop éloignée dans le futur."];
            }


            $stmt = $pdo->prepare("INSERT INTO academic_year (start, \"end\") VALUES (?, ?)");
            $stmt->execute([$startYear, $endYear]);
            if ($stmt->rowCount() > 0) {
                return ["success" => true, "id" => $pdo->lastInsertId()];
            } else {
                return ["success" => false, "message" => "Erreur lors de la création de l'année académique."];
            }
        }
    }


    switch ($action) {
        case 'list':
            // Get search and pagination parameters
            $search = htmlspecialchars(trim($_GET['search'] ?? ''));
            $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
            $limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT);

            if ($page < 1) $page = 1;
            if ($limit < 1) $limit = 10;
            $offset = ($page - 1) * $limit;

            $whereClause = '';
            $params = [];
            if (!empty($search)) {
                $whereClause .= ' WHERE (l.titre ILIKE ? OR a.nom ILIKE ? OR l.isbn ILIKE ? OR ay.start || \'-\' || ay."end" ILIKE ? OR sa.name ILIKE ?)';
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $countStmt = $pdo->prepare("
                SELECT COUNT(l.id)
                FROM livres l
                LEFT JOIN auteurs a ON l.auteur_id = a.id
                LEFT JOIN academic_year ay ON l.annee_id = ay.id
                LEFT JOIN study_areas sa ON l.study_area_id = sa.id
                " . $whereClause
            );
            $countStmt->execute($params);
            $totalCount = $countStmt->fetchColumn();

            $sql = "
                SELECT 
                    l.id,
                    l.titre,
                    l.isbn,
                    l.descr,
                    l.disponible,
                    l.emplacement,
                    a.id AS auteur_id,
                    a.nom AS auteur_nom,
                    f.chemin AS cover_url,
                    ay.start || '-' || ay.\"end\" AS annee_academique,
                    sa.id AS study_area_id,
                    sa.name AS study_area_name,
                    sa.latitude AS study_area_latitude,
                    sa.longitude AS study_area_longitude
                FROM livres l
                JOIN auteurs a ON l.auteur_id = a.id
                LEFT JOIN fichiers f ON l.cover_image_id = f.id
                LEFT JOIN academic_year ay ON l.annee_id = ay.id
                LEFT JOIN study_areas sa ON l.study_area_id = sa.id
                " . $whereClause . "
                ORDER BY l.titre ASC
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $pdo->prepare($sql);
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "success" => true,
                "data" => $books,
                "total" => $totalCount
            ]);
            break;

        case 'details':
            $id = intval($_GET['id'] ?? null);
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID de livre manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("
                SELECT 
                    l.id, 
                    l.titre, 
                    l.isbn, 
                    l.descr, 
                    l.disponible, 
                    l.emplacement,
                    l.auteur_id,
                    l.departement_id,
                    l.annee_id,
                    l.study_area_id, -- Nouvelle colonne
                    a.nom AS auteur_nom,
                    c.nom AS departement_nom,
                    ay.start || '-' || ay.\"end\" AS annee_academique,
                    sa.name AS study_area_name, -- Nom de la zone d'étude
                    sa.latitude AS study_area_latitude,
                    sa.longitude AS study_area_longitude
                FROM livres l
                JOIN auteurs a ON l.auteur_id = a.id
                LEFT JOIN departement c ON l.departement_id = c.id
                LEFT JOIN academic_year ay ON l.annee_id = ay.id
                LEFT JOIN study_areas sa ON l.study_area_id = sa.id -- Nouvelle jointure
                WHERE l.id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($book) {
                echo json_encode(["success" => true, "data" => $book]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Livre non trouvé."]);
            }
            break;

        case 'add':
            $titre = $input['titre'] ?? null;
            $auteur_id = intval($input['auteur_id'] ?? 0);
            $emplacement = $input['emplacement'] ?? null;
            $isbn = $input['isbn'] ?? null;
            $descr = $input['descr'] ?? null;
            $disponible = filter_var($input['disponible'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $departement_id = intval($input['departement_id'] ?? 0);
            $annee_academique_str = $input['annee_academique'] ?? null; // Utilisez le nom original
            $study_area_id = intval($input['study_area_id'] ?? 0); // Nouveau champ

            if (empty($titre) || empty($auteur_id)) {
                echo json_encode(["success" => false, "message" => "Titre et auteur sont requis."]);
                exit();
            }

            $annee_id = null;
            if (!empty($annee_academique_str)) { // Vérifiez si la chaîne n'est pas vide
                $yearResult = getOrCreateAcademicYearId($pdo, $annee_academique_str);
                if (!$yearResult['success']) {
                    // NE PAS envoyer http_response_code(400); ici
                    echo json_encode(["success" => false, "message" => $yearResult['message']]);
                    exit(); // Sortir avec la réponse JSON d'erreur
                }
                $annee_id = $yearResult['id'];
            }

            $stmt = $pdo->prepare("INSERT INTO livres (titre, auteur_id, emplacement, isbn, descr, disponible, departement_id, annee_id, study_area_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $titre, 
                $auteur_id, 
                $emplacement, 
                $isbn, 
                $descr, 
                $disponible, 
                $departement_id > 0 ? $departement_id : null, 
                $annee_id, // Utilisez annee_id qui peut être null
                $study_area_id > 0 ? $study_area_id : null // Enregistrement de la zone d'étude
            ]);
            echo json_encode(["success" => true, "message" => "Livre ajouté avec succès.", "id" => $pdo->lastInsertId()]);
            break;

        case 'update':
            $id = intval($input['id'] ?? null);
            $titre = $input['titre'] ?? null;
            $auteur_id = intval($input['auteur_id'] ?? 0);
            $emplacement = $input['emplacement'] ?? null;
            $isbn = $input['isbn'] ?? null;
            $descr = $input['descr'] ?? null;
            $disponible = filter_var($input['disponible'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $departement_id = intval($input['departement_id'] ?? 0);
            $annee_academique_str = $input['annee_academique'] ?? null; // Utilisez le nom original
            $study_area_id = intval($input['study_area_id'] ?? 0); // Nouveau champ

            if (!$id || empty($titre) || empty($auteur_id)) {
                echo json_encode(["success" => false, "message" => "ID, titre et auteur sont requis."]);
                exit();
            }

            $annee_id = null;
            if (!empty($annee_academique_str)) { // Vérifiez si la chaîne n'est pas vide
                $yearResult = getOrCreateAcademicYearId($pdo, $annee_academique_str);
                if (!$yearResult['success']) {
                    // NE PAS envoyer http_response_code(400); ici
                    echo json_encode(["success" => false, "message" => $yearResult['message']]);
                    exit(); // Sortir avec la réponse JSON d'erreur
                }
                $annee_id = $yearResult['id'];
            }

            $stmt = $pdo->prepare("UPDATE livres SET titre = ?, auteur_id = ?, emplacement = ?, isbn = ?, descr = ?, disponible = ?, departement_id = ?, annee_id = ?, study_area_id = ? WHERE id = ?");
            $stmt->execute([
                $titre, 
                $auteur_id, 
                $emplacement, 
                $isbn, 
                $descr, 
                $disponible, 
                $departement_id > 0 ? $departement_id : null, 
                $annee_id, // Utilisez annee_id qui peut être null
                $study_area_id > 0 ? $study_area_id : null, // Mise à jour de la zone d'étude
                $id
            ]);
            if ($stmt->rowCount() === 0) {
                // Si rowCount est 0, cela peut signifier qu'aucune ligne n'a été affectée (pas de changement ou ID non trouvé)
                // Dans le cas d'une mise à jour, cela n'est pas nécessairement une erreur si les données sont identiques
                // Mais pour un débogage, on peut le laisser ainsi ou ajouter une vérification plus spécifique
            }
            echo json_encode(["success" => true, "message" => "Livre mis à jour."]);
            break;

        case 'delete':
            $id = intval($_GET['id']) ?? null;
            if (!$id) {
                echo json_encode(["success" => false, "message" => "ID de livre manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM livres WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Livre supprimé."]);
            break;

        default:
            echo json_encode(["success" => false, "message" => "Action invalide."]);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Erreur de base de données: " . $e->getMessage()]);
}
?>
