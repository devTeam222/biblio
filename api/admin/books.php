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
$input = $_POST;

try {
    // Function to get or create academic year ID
    function getOrCreateAcademicYearId($pdo, $academicYearString) {
        // Validate format YYYY-YYYY
        if (!preg_match('/^(\d{4})-(\d{4})$/', $academicYearString, $matches)) {
            return ["success" => false, "message" => "Format d'année académique invalide. Utilisez YYYY-YYYY."];
        }
        $startYear = (int)$matches[1];
        $endYear = (int)$matches[2];

        // Validate end year is start year + 1
        if ($endYear !== $startYear + 1) {
            return ["success" => false, "message" => "L'année de fin doit être l'année de début + 1."];
        }

        // Check if academic year exists
        $stmt = $pdo->prepare("SELECT id FROM academic_year WHERE start = ? AND \"end\" = ?");
        $stmt->execute([$startYear, $endYear]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return ["success" => true, "id" => $result['id']];
        } else {
            // Create new academic year
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
            $stmt = $pdo->query("
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
                    ay.start || '-' || ay.\"end\" AS annee_academique
                FROM livres l
                JOIN auteurs a ON l.auteur_id = a.id
                LEFT JOIN fichiers f ON l.cover_image_id = f.id
                LEFT JOIN academic_year ay ON l.annee_id = ay.id
                ORDER BY l.date_publication DESC;
            ");
            $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "data" => $books]);
            break;

        case 'details':
            $id = intval($_GET['id']) ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID de livre manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("
                SELECT 
                    l.id, l.titre, l.isbn, l.descr, l.disponible, l.emplacement,
                    a.id AS auteur_id, a.nom AS auteur_nom,
                    ay.start || '-' || ay.\"end\" AS annee_academique
                FROM livres l
                JOIN auteurs a ON l.auteur_id = a.id
                LEFT JOIN academic_year ay ON l.annee_id = ay.id
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
            $titre = $_POST['titre'] ?? null;
            $auteur_id = $_POST['auteur_id'] ?? null;
            $emplacement = $_POST['emplacement'] ?? null;
            $description = $_POST['description'] ?? null;
            $annee_academique = $_POST['annee_academique'] ?? null; // New field
            $disponible = true; // Par défaut disponible

            if (!$titre || !$auteur_id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Titre et auteur sont requis."]);
                exit();
            }

            $annee_id = null;
            if ($annee_academique) {
                $yearResult = getOrCreateAcademicYearId($pdo, $annee_academique);
                if (!$yearResult['success']) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => $yearResult['message']]);
                    exit();
                }
                $annee_id = $yearResult['id'];
            }

            $stmt = $pdo->prepare("INSERT INTO livres (titre, auteur_id, emplacement, descr, disponible, annee_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$titre, $auteur_id, $emplacement, $description, $disponible, $annee_id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Erreur lors de l'ajout du livre."]);
                exit();
            }
            echo json_encode(["success" => true, "message" => "Livre ajouté."]);
            break;

        case 'update':
            $id = $_POST['id'] ?? null;
            $titre = $_POST['titre'] ?? null;
            $auteur_id = $_POST['auteur_id'] ?? null;
            $emplacement = $_POST['emplacement'] ?? null;
            $description = $_POST['description'] ?? null;
            $annee_academique = $_POST['annee_academique'] ?? null; // New field

            if (!$id || !$titre || !$auteur_id) {
                echo json_encode(["success" => false, "message" => "ID, titre et auteur sont requis.", "error" => "Missing required fields", "input" => [
                    'id' => $id,
                    'titre' => $titre,
                    'auteur_id' => $auteur_id,
                    'emplacement'=> $emplacement,
                    'description' => $description,
                    'annee_academique' => $annee_academique
                ], "post" => $_POST]);
                exit();
            }

            $annee_id = null;
            if ($annee_academique) {
                $yearResult = getOrCreateAcademicYearId($pdo, $annee_academique);
                if (!$yearResult['success']) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => $yearResult['message']]);
                    exit();
                }
                $annee_id = $yearResult['id'];
            }

            $stmt = $pdo->prepare("UPDATE livres SET titre = ?, auteur_id = ?, emplacement = ?, descr = ?, annee_id = ? WHERE id = ?");
            $stmt->execute([$titre, $auteur_id, $emplacement, $description, $annee_id, $id]);
            if ($stmt->rowCount() === 0) {
                echo json_encode(["success" => false, "message" => "Erreur lors de la mise à jour du livre."]);
                exit();
            }
            echo json_encode(["success" => true, "message" => "Livre mis à jour."]);
            break;

        case 'delete':
            $id = intval($_GET['id']) ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID de livre manquant."]);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM livres WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Livre supprimé."]);
            break;

        default:
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Action invalide."]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erreur de base de données", "error"=> $e->getMessage()]);
}
?>
