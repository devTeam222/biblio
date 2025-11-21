<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les Livres & Zones d'étude - GéoLib</title>
    <link rel="stylesheet" href="/app/css/modal.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
        }

        tr:hover {
            background-color: #f8fafc;
        }

        .action-button {
            padding: 8px 12px;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: background-color 0.2s ease-in-out;
        }

        .tab-button {
            padding: 10px 20px;
            border-radius: 0.5rem 0.5rem 0 0;
            font-weight: 500;
            transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            text-decoration: none; /* Add for anchor tags */
        }

        .tab-button.active {
            background-color: #ffffff;
            color: #4f46e5;
            border-color: #4f46e5;
        }
    </style>
    <!-- Tailwind CSS CDN -->
    <script src="/app/js/tailwind.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-center mb-4">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4 md:mb-0">Gestion</h2>
                <div class="flex flex-col md:flex-row gap-4 w-full md:w-2/3 lg:w-1/2">
                    <input type="text" id="entitySearchInput" placeholder="Rechercher..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <button id="addEntityBtn"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 flex items-center justify-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-plus-icon lucide-plus">
                            <path d="M5 12h14" />
                            <path d="M12 5v14" />
                        </svg>
                        Ajouter
                    </button>
                </div>
            </div>

            <!-- Onglets de navigation -->
            <div class="flex border-b border-gray-200 mb-4">
                <button id="booksTabBtn" class="tab-button active bg-white text-green-700 border-green-500">Livres</button>
                <button id="studyAreasTabBtn" class="tab-button bg-gray-100 text-gray-700">Zones d'étude</button>
            </div>

            <!-- Contenu des onglets -->
            <div id="booksContent" class="tab-content">
                <div class="table-container">
                    <table id="booksTable" class="min-w-full bg-white rounded-md overflow-hidden">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Titre</th>
                                <th>Auteur</th>
                                <th>Emplacement</th>
                                <th>Année académique</th>
                                <th>Zone d'étude</th>
                                <th>Disponible</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Les livres seront chargés ici par JavaScript -->
                            <tr>
                                <td colspan="8" class="text-center py-4 text-gray-500">Chargement des livres...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="studyAreasContent" class="tab-content hidden">
                <div class="table-container overflow-x-auto">
                    <table id="studyAreasTable" class="min-w-full bg-white rounded-md overflow-hidden w-full border-collapse">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Localisation</th>
                                <th>Créé le</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="studyAreasTableBody">
                            <!-- Les zones d'étude seront chargées ici par JavaScript -->
                            <tr>
                                <td colspan="6" class="text-center py-4 text-gray-500">Chargement des zones d'étude...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="studyAreasPagination" class="flex justify-between items-center mt-4">
                    <button id="studyAreasPrevPageBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">Précédent</button>
                    <span id="studyAreasPageInfo" class="text-sm text-gray-700"></span>
                    <button id="studyAreasNextPageBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">Suivant</button>
                </div>
            </div>

        </div>
    </main>

    <!-- Pied de page (Copie du tableau de bord admin) -->
    <footer class="bg-gray-800 text-white py-4 shadow-inner">
        <div class="container mx-auto px-4 text-center text-sm">
            <p>&copy; 2025 Base des données Dimart Géosciences contient ouvrages scientifiques publiée à la mention géosciences/faculté des sciences et technologie/unikin realisé par Ochokom et dirigé par le professeur Didier Yina.</p>
            <p class="mt-1 text-gray-400">Dernière mise à jour: <span id="lastUpdateTime">maintenant</span></p>
        </div>
    </footer>

    <!-- Modale Ajouter/Modifier Livre -->
    <div id="bookModal" class="modal-overlay hidden">
        <div class="modal-content">
            <h3 id="bookModalTitle" class="text-xl font-semibold text-gray-800 mb-4">Ajouter un nouveau livre</h3>
            <div id="stepIndicatorsContainer" class="flex justify-center mb-6 space-x-2">
                <!-- Indicators will be generated by JS -->
            </div>
            <form id="bookForm" class="space-y-4">
                <input type="hidden" id="bookId">
                
                <!-- STEP 1: Informations de base -->
                <div id="bookFormStep1" class="space-y-4">
                    <div>
                        <label for="bookTitle" class="block text-sm font-medium text-gray-700 text-left">Titre</label>
                        <input type="text" id="bookTitle"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"
                            required>
                    </div>
                    <div>
                        <label for="bookAuthor" class="block text-sm font-medium text-gray-700 text-left">Auteur</label>
                        <!-- Remplacé par un DIV qui sera le conteneur pour le composant DropdownSearch -->
                        <div id="bookAuthorSelectContainer" class="mt-1 block w-full"></div>
                    </div>
                    <div>
                        <label for="bookCategory" class="block text-sm font-medium text-gray-700 text-left">Département</label>
                        <!-- Remplacé par un DIV qui sera le conteneur pour le composant DropdownSearch -->
                        <div id="bookCategorySelectContainer" class="mt-1 block w-full"></div>
                    </div>
                </div>

                <!-- STEP 2: Détails académiques et géographiques -->
                <div id="bookFormStep2" class="space-y-4 hidden">
                    <div>
                        <label for="bookAcademicYear" class="block text-sm font-medium text-gray-700 text-left">Année académique</label>
                        <!-- Remplacé par un DIV qui sera le conteneur pour le composant DropdownSearch -->
                        <div id="bookAcademicYearSelectContainer" class="mt-1 block w-full"></div>
                    </div>
                    <div>
                        <label for="bookStudyArea" class="block text-sm font-medium text-gray-700 text-left">Zone d'étude</label>
                        <!-- Remplacé par un DIV qui sera le conteneur pour le composant DropdownSearch -->
                        <div id="bookStudyAreaSelectContainer" class="mt-1 block w-full"></div>
                    </div>
                    <div>
                        <label for="bookISBN" class="block text-sm font-medium text-gray-700 text-left">ISBN</label>
                        <input type="text" id="bookISBN"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>

                <!-- STEP 3: Autres détails -->
                <div id="bookFormStep3" class="space-y-4 hidden">
                    <div>
                        <label for="bookDescription"
                            class="block text-sm font-medium text-gray-700 text-left">Description</label>
                        <textarea id="bookDescription" rows="3"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>
                    <div>
                        <label for="bookLocation" class="block text-sm font-medium text-gray-700 text-left">Emplacement</label>
                        <input type="text" id="bookLocation"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="bookAvailable"
                            class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                        <label for="bookAvailable" class="ml-2 block text-sm text-gray-900">Disponible</label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 max-sm:flex-wrap max-sm:justify-between mt-6">
                    <button type="button" id="cancelBookModalBtn"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Annuler</button>
                    <button type="button" id="prevStepBtn"
                        class="px-4 py-2 bg-green-200 text-green-800 rounded-md hover:bg-green-300 hidden">Précédent</button>
                    <button type="button" id="nextStepBtn"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Suivant</button>
                    <button type="submit" id="submitBookBtn"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 hidden">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modale Ajouter/Modifier Zone d'étude -->
    <div id="studyAreaModal" class="modal-overlay hidden">
        <div class="modal-content">
            <h3 id="studyAreaModalTitle" class="text-xl font-semibold text-gray-800 mb-4">Ajouter une nouvelle zone d'étude</h3>
            <form id="studyAreaForm" class="space-y-4" method="post">
                <input type="hidden" id="studyAreaId">
                <div>
                    <label for="studyAreaName" class="block text-sm font-medium text-gray-700 text-left">Nom de la zone</label>
                    <input type="text" id="studyAreaName"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"
                        required>
                </div>
                <div>
                    <label for="studyAreaDescription" class="block text-sm font-medium text-gray-700 text-left">Description</label>
                    <textarea id="studyAreaDescription" rows="3"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"></textarea>
                </div>
                <div>
                    <label for="studyAreaLatitude" class="block text-sm font-medium text-gray-700 text-left">Latitude (optionnel)</label>
                    <input type="number" step="any" id="studyAreaLatitude"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="studyAreaLongitude" class="block text-sm font-medium text-gray-700 text-left">Longitude (optionnel)</label>
                    <input type="number" step="any" id="studyAreaLongitude"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelStudyAreaModalBtn"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Annuler</button>
                    <button type="submit"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Modale de Confirmation Personnalisée -->
    <div id="confirmationModal" class="modal-overlay hidden">
        <div class="modal-content text-center">
            <p class="mb-4 text-gray-700 font-medium">Voulez-vous vraiment supprimer cet élément ?</p>
            <div class="flex justify-center space-x-3">
                <button id="cancelDeleteBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Annuler</button>
                <button id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Supprimer</button>
            </div>
        </div>
    </div>

    <!-- Conteneur pour les modales -->
    <div id="modalContainer"></div>
    <script type="module" src="/app/js/auth/admin/books.js"></script>
</body>

</html>
