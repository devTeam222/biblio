<?php
session_start();
// Pas de redirection ici, la page est publique

// Inclure la connexion à la base de données
require_once __DIR__ . '/../../app/db.php';

if (!isset($_GET["id"])) {
    send_error_page(400, "ID de livre manquant.", "L'identifiant du livre n'a pas été fourni dans l'URL.");
}

$id = intval($_GET["id"]); // Assurez-vous que l'ID est un entier

// Tenter de récupérer les détails du livre pour obtenir le titre
$bookTitleForPage = null; // Renommé pour clarté, car c'est le titre pour la page, pas seulement pour les erreurs
try {
    $stmt = $pdo->prepare("SELECT titre FROM livres WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($book) {
        $bookTitleForPage = $book['titre'];
    } else {
        // Si le livre n'est pas trouvé, afficher une page 404 avec le titre si disponible
        send_error_page(404, "Livre non trouvé.", "Le livre avec l'ID {$id} n'existe pas.", $bookTitleForPage);
    }
} catch (PDOException $e) {
    // Gérer les erreurs de base de données lors de la récupération du titre
    send_error_page(500, "Erreur interne du serveur.", "Problème de base de données lors de la récupération du titre du livre: " . $e->getMessage(), $bookTitleForPage);
}

// Si nous arrivons ici, l'ID est valide et le titre a été récupéré (ou est null si non trouvé)
// Le reste de la page HTML sera généré par JavaScript
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $bookTitleForPage ? htmlspecialchars($bookTitleForPage) : 'Détails du Livre'; ?> - GéoLib</title>
    <!-- Tailwind CSS CDN -->
    <script src="/app/js/tailwind.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/app/css/modal.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        .card {
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1.5rem;
        }

        .nav-link {
            transition: all 0.2s ease-in-out;
        }

        .nav-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .book-cover-detail {
            width: 100%;
            max-width: 300px;
            /* Max width for detail cover */
            height: auto;
            object-fit: contain;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        /* Style pour le spinner de chargement */
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-left: 8px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body class="flex flex-col min-h-screen">
    <!-- En-tête de la page (Style Admin) -->
    <header class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center mb-4 md:mb-0 gap-1">
                    <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5s3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18s-3.332.477-4.5 1.253" />
                    </svg>
                    <h1 class="text-2xl md:text-3xl font-bold">GéoLib</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right hidden md:block">
                        <p id="userNameDisplay" class="font-medium">Bienvenue, Lecteur !</p>
                        <p class="text-xs text-indigo-200" id="userRoleDisplay"></p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Barre de navigation (Style Admin) -->
    <nav class="bg-white shadow-sm">
        <div class="container mx-auto p-4">
            <div class="container mx-auto flex flex-wrap justify-center md:justify-start gap-4 px-4" id="mainNav">
                <!-- Les liens seront injectés ici par JavaScript -->
            </div>
        </div>
    </nav>

    <!-- Contenu principal -->
    <main class="flex-grow container mx-auto px-4 py-6">
        <div id="bookDetailsContainer" class="bg-white rounded-xl shadow p-6 mb-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Détails de l'ouvrage <span id="bookTitleDisplay"></span></h2>
            <div id="bookDetailContent" class="flex flex-col md:flex-row gap-8 items-start">
                <!-- Les détails du livre seront chargés ici par JavaScript -->
                <p class="text-center w-full text-gray-500">Chargement des détails de l'ouvrage...</p>
            </div>

            <!-- Section de modification des fichiers (visible pour les admins) -->
            <div id="adminFileManagement" class="hidden mt-8 pt-6 border-t border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Gestion des fichiers de l'ouvrage</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Gestion de l'image de couverture -->
                    <div class="card p-4">
                        <h4 class="font-semibold text-gray-700 mb-3">Image de couverture</h4>
                        <div id="currentCoverImage" class="mb-4">
                            <!-- L'image actuelle sera chargée ici -->
                        </div>
                        <input type="file" id="coverImageInput" accept="image/*" class="block w-full text-sm text-gray-500
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-full file:border-0
                            file:text-sm file:font-semibold
                            file:bg-indigo-50 file:text-indigo-700
                            file:cursor-pointer
                            hover:file:bg-indigo-100 mb-3" />
                        <button id="deleteCoverBtn" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            <span class="loader hidden"></span> Supprimer la couverture
                        </button>
                    </div>

                    <!-- Gestion du fichier électronique -->
                    <div class="card p-4">
                        <h4 class="font-semibold text-gray-700 mb-3">Fichier électronique</h4>
                        <div id="currentElectronicFile" class="mb-4">
                            <!-- Le fichier actuel sera chargé ici -->
                        </div>
                        <input type="file" id="electronicFileInput" accept=".pdf,.epub,.doc,.docx" class="block w-full text-sm text-gray-500
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-full file:border-0
                            file:text-sm file:font-semibold
                            file:bg-indigo-50 file:text-indigo-700
                            file:cursor-pointer
                            hover:file:bg-indigo-100 mb-3" />
                        <button id="deleteElectronicBtn" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            <span class="loader hidden"></span> Supprimer le fichier
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Pied de page (Style Admin) -->
    <footer class="bg-gray-800 text-white py-4 shadow-inner">
        <div class="container mx-auto px-4 text-center text-sm">
            <p>&copy; 2025 Base des données Dimart Géosciences contient ouvrages scientifiques publiée à la mention géosciences/faculté des sciences et technologie/unikin realisé par Ochokom et dirigé par le professeur Didier Yina.</p>
        </div>
    </footer>
    <!-- Conteneur pour les modales -->
    <div id="modalContainer"></div>
    <script type="module" src="/app/js/book-details.js"></script>
</body>

</html>
