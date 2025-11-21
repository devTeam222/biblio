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
    <title>Gérer les Abonnements - GéoLib</title>
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
                <h2 class="text-2xl font-semibold text-gray-800 mb-4 md:mb-0">Liste des Abonnements</h2>
                <div class="flex flex-col md:flex-row gap-4 w-full md:w-2/3 lg:w-1/2">
                    <input type="text" id="subscriptionSearchInput" placeholder="Rechercher par lecteur..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <button id="addSubscriptionBtn"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 flex items-center justify-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-plus-icon lucide-plus">
                            <path d="M5 12h14" />
                            <path d="M12 5v14" />
                        </svg>
                        Ajouter un abonnement
                    </button>
                </div>
            </div>

            <div class="table-container overflow-x-auto">
                <table id="subscriptionsTable" class="min-w-full bg-white rounded-md overflow-hidden w-full border-collapse">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Lecteur</th>
                            <th>Date Début</th>
                            <th>Date Fin</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Les abonnements seront chargés ici par JavaScript -->
                        <tr>
                            <td colspan="6" class="text-center py-4 text-gray-500">Chargement des abonnements...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="subscriptionsPagination" class="flex justify-between items-center mt-4">
                <button id="subscriptionsPrevPageBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">Précédent</button>
                <span id="subscriptionsPageInfo" class="text-sm text-gray-700"></span>
                <button id="subscriptionsNextPageBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">Suivant</button>
            </div>
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="bg-gray-800 text-white py-4 shadow-inner">
        <div class="container mx-auto px-4 text-center text-sm">
            <p>&copy; 2025 Base des données Dimart Géosciences contient ouvrages scientifiques publiée à la mention géosciences/faculté des sciences et technologie/unikin realisé par Ochokom et dirigé par le professeur Didier Yina.</p>
            <p class="mt-1 text-gray-400">Dernière mise à jour: <span id="lastUpdateTime">maintenant</span></p>
        </div>
    </footer>

    <!-- Modale Ajouter/Modifier Abonnement -->
    <div id="subscriptionModal" class="modal-overlay hidden">
        <div class="modal-content">
            <h3 id="subscriptionModalTitle" class="text-xl font-semibold text-gray-800 mb-4">Ajouter un nouvel abonnement</h3>
            <form id="subscriptionForm" class="space-y-4" method="post">
                <input type="hidden" id="subscriptionId">
                <div>
                    <label for="subscriptionReader" class="block text-sm font-medium text-gray-700 text-left">Lecteur</label>
                    <select id="subscriptionReader"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"
                        required>
                        <!-- Options will be loaded by JavaScript -->
                    </select>
                </div>
                <div>
                    <label for="subscriptionStartDate" class="block text-sm font-medium text-gray-700 text-left">Date de début</label>
                    <input type="date" id="subscriptionStartDate"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"
                        required>
                </div>
                <div>
                    <label for="subscriptionEndDate" class="block text-sm font-medium text-gray-700 text-left">Date de fin</label>
                    <input type="date" id="subscriptionEndDate"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"
                        required>
                </div>
                <div>
                    <label for="subscriptionStatus" class="block text-sm font-medium text-gray-700 text-left">Statut</label>
                    <select id="subscriptionStatus"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"
                        required>
                        <option value="actif">Actif</option>
                        <option value="expire">Expiré</option>
                        <option value="annule">Annulé</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelSubscriptionModalBtn"
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
    <script type="module" src="/app/js/auth/admin/subscriptions.js"></script>
</body>
</html>
