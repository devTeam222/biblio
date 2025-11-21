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
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .card {
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1.5rem;
        }

        .nav-link {
            padding: 10px 20px;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
            cursor: pointer;
        }

        .book-cover {
            width: 100%;
            height: 200px;
            /* Fixed height for covers */
            object-fit: cover;
            border-radius: 0.5rem;
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
            /* Adjusted margin */
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Overlay pour les messages modaux (déjà dans modal.css mais inclus ici pour visibilité) */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
    </style>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: {
                            750: '#2d3748',
                            850: '#1a202c',
                            950: '#0f172a'
                        }
                    },
                    zIndex: {
                        'nav': '1050',
                    }
                }
            }
        }
    </script>
</head>

<body class="flex flex-col min-h-screen bg-slate-200 dark:bg-slate-950">
    <!-- HEADER -->
    <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 shadow-sm z-[1100] shrink-0 relative">
        <!-- Logo & Titre -->
        <a class="flex items-center gap-3 dark:text-white" href="/">
            <div class="bg-blue-600 text-white p-1.5 rounded-lg shadow-sm">
                <i data-lucide="map" class="w-6 h-6"></i>
            </div>
            <h1 class="text-lg font-bold tracking-tight hidden sm:block">GeoLib <span class="text-slate-400 font-normal text-sm">Accueil</span></h1>
            <h1 class="text-lg font-bold tracking-tight sm:hidden">GeoLib</h1>
        </a>
        
        <!-- Menu de Navigation -->
        <div id="nav-menu" class="hidden md:flex flex-col md:flex-row absolute md:static top-16 left-0 w-full md:w-auto bg-white dark:bg-slate-900 md:bg-transparent shadow-xl md:shadow-none border-b md:border-0 border-slate-200 dark:border-slate-700 p-4 md:p-0 gap-4 md:gap-2 items-stretch md:items-center z-nav transition-all duration-200 ease-in-out">
            <!-- Apparence + Utilisateur -->
            <div class="flex flex-col md:flex-row md:items-center md:gap-2 gap-3">
                <div class="flex items-center justify-between md:justify-start">
                    <span class="md:hidden text-sm font-bold text-slate-500 uppercase tracking-wider">Apparence</span>
                    <div class="relative group">
                        <button id="btnDarkModeToggle" class="p-2 mr-1 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 transition-colors">
                            <i id="darkModeIcon" data-lucide="moon" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <nav class="md:flex flex-col md:flex-row gap-2" id="mainNav">
                        <a href="/" class="inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-transparent bg-blue-500 text-white shadow-md transition-colors">
                            <i data-lucide="home" class="w-5 h-5"></i>
                            <span class="hidden md:inline">Accueil</span>
                        </a>
                        <a href="/" class="inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-transparent hover:border-slate-200 dark:hover:border-slate-700 text-sm font-semibold text-slate-600 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                            <i data-lucide="home" class="w-5 h-5"></i>
                            <span class="hidden md:inline">Accueil</span>
                        </a>
                    </nav>
                    
                    <span id="userNameChip" class="text-xs font-semibold text-slate-400 hidden md:inline">Invité</span>
                    <div class="relative ml-2" id="profileContainer">
                    <!-- Statut/Nom de l'utilisateur (optionnel, affiché avant l'avatar sur desktop) -->

                    <!-- Bouton Avatar qui ouvre le menu -->
                    <button id="userAvatar" aria-expanded="false" aria-controls="userDropdown" class="w-9 h-9 rounded-full border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-200 bg-white dark:bg-slate-800 shadow-sm transition-colors hover:border-blue-500 dark:hover:border-blue-500">
                        <i data-lucide="user-round" class="w-4 h-4"></i>
                    </button>

                    <!-- Menu Popover (initialement caché) -->
                    <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl py-1 hidden z-20">
                        <div class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300 border-b dark:border-slate-700 font-medium truncate" id="dropdownUserName">
                            Invité
                        </div>
                        
                        <!-- Bouton Profil -->
                        <a href="/profile" id="profileButton" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <i data-lucide="settings" class="w-4 h-4"></i>
                            Mon Compte
                        </a>
                        
                        <!-- Bouton Déconnexion (Affiché uniquement si connecté, géré par JS) -->
                        <button id="logoutButton" class="flex items-center gap-2 w-full text-left px-3 py-2 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            Déconnexion
                        </button>
                    </div>
                </div>
                </div>
            </div>           
        </div>
        
        <!-- Bouton Hamburger (Mobile) -->
        <button id="btnMobileMenu" class="md:hidden p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 focus:outline-none">
            <i id="menuIcon" data-lucide="menu" class="w-6 h-6"></i>
        </button>
    </header>

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
                            file:bg-green-50 file:text-green-700
                            file:cursor-pointer
                            hover:file:bg-green-100 mb-3" />
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
                            file:bg-green-50 file:text-green-700
                            file:cursor-pointer
                            hover:file:bg-green-100 mb-3" />
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
