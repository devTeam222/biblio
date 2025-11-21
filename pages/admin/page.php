<?php
// Si l'utilisateur n'est pas authentifié ou n'est pas un administrateur, rediriger vers la page de connexion
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: /login'); // Rediriger vers la page de connexion
    exit();
}
if ($_SESSION['user_role'] !== 'admin') {
    send_error_page(403, "Page reservée aux administrateurs");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Administrateur - GéoLib</title>
    <link rel="stylesheet" href="/app/css/modal.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        .dashboard-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .stat-card {
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: scale(1.03);
        }

        .activity-item {
            position: relative;
            padding-left: 1.5rem;
        }

        .activity-item:before {
            content: "";
            position: absolute;
            left: 0;
            top: 0.5rem;
            height: 0.5rem;
            width: 0.5rem;
            border-radius: 50%;
            background-color: #4f46e5;
        }

        #overdueLoansList li,
        #recentActivityList li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        #overdueLoansList li:last-child,
        #recentActivityList li:last-child {
            border-bottom: none;
        }

        .chart-container {
            height: 250px;
            /* Ajustez si nécessaire */
        }

        .quick-action-btn {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            justify-content: center;
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
        <div class="flex flex-col space-y-6">
            <!-- Section de bienvenue et stats rapides -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Carte Statistiques Livres -->
                <div class="stat-card bg-white rounded-xl shadow p-6 flex items-center space-x-4 border-l-4 border-blue-500">
                    <div class="bg-blue-100 p-3 rounded-full text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-book-text-icon lucide-book-text">
                            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20" />
                            <path d="M8 11h8" />
                            <path d="M8 7h6" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Total Livres</p>
                        <h3 id="totalBooks" class="text-2xl font-bold text-gray-800">...</h3>
                    </div>
                </div>

                <!-- Carte Livres Disponibles -->
                <div class="stat-card bg-white rounded-xl shadow p-6 flex items-center space-x-4 border-l-4 border-green-500">
                    <div class="bg-green-100 p-3 rounded-full text-green-600 ">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-book-open-icon lucide-book-open">
                            <path d="M12 7v14" />
                            <path
                                d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Livres Disponibles</p>
                        <h3 id="availableBooks" class="text-2xl font-bold text-gray-800">...</h3>
                    </div>
                </div>

                <!-- Carte Utilisateurs -->
                <div class="stat-card bg-white rounded-xl shadow p-6 flex items-center space-x-4 border-l-4 border-blue-500">
                    <div class="bg-blue-100 p-3 rounded-full text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-users-round-icon lucide-users-round">
                            <path d="M18 21a8 8 0 0 0-16 0" />
                            <circle cx="10" cy="8" r="5" />
                            <path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Total Utilisateurs</p>
                        <h3 id="totalUsers" class="text-2xl font-bold text-gray-800">...</h3>
                    </div>
                </div>

                <!-- Carte Emprunts -->
                <div class="stat-card bg-white rounded-xl shadow p-6 flex items-center space-x-4 border-l-4 border-orange-500">
                    <div class="bg-orange-100 p-3 rounded-full text-orange-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-arrow-left-right-icon lucide-arrow-left-right">
                            <path d="M8 3 4 7l4 4" />
                            <path d="M4 7h16" />
                            <path d="m16 21 4-4-4-4" />
                            <path d="M20 17H4" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Emprunts Actuels</p>
                        <h3 id="currentLoansCount" class="text-2xl font-bold text-gray-800">...</h3>
                    </div>
                </div>
            </div>

            <!-- Deuxième ligne avec graphique et emprunts en retard -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Section Graphique -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow p-6 dashboard-card">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-800">Activité Récente</h3>
                        <div class="flex space-x-2">
                            <button class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">7 jours</button>
                            <button class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">30 jours</button>
                            <button class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">Tout</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>

                
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow p-6 dashboard-card">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Actions Rapides</h3>
                    <div class="space-y-3">
                        <button class="quick-action-btn bg-blue-50 text-blue-700 hover:bg-blue-100 flex items-center gap-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-plus-icon lucide-plus">
                                <path d="M5 12h14" />
                                <path d="M12 5v14" />
                            </svg>
                            <span>Ajouter un nouveau livre</span>
                        </button>
                        <button class="quick-action-btn bg-green-50 text-green-700 hover:bg-green-100 flex items-center gap-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-user-round-plus-icon lucide-user-round-plus">
                                <path d="M2 21a8 8 0 0 1 13.292-6" />
                                <circle cx="10" cy="8" r="5" />
                                <path d="M19 16v6" />
                                <path d="M22 19h-6" />
                            </svg>
                            <span>Ajouter un auteur</span>
                        </button>
                        <a href="/admin/loans" class="quick-action-btn bg-blue-50 text-blue-700 hover:bg-blue-100 flex items-center gap-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-book-open-check-icon lucide-book-open-check">
                                <path d="M12 21V7" />
                                <path d="m16 12 2 2 4-4" />
                                <path
                                    d="M22 6V4a1 1 0 0 0-1-1h-5a4 4 0 0 0-4 4 4 4 0 0 0-4-4H3a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h6a3 3 0 0 1 3 3 3 3 0 0 1 3-3h6a1 1 0 0 0 1-1v-1.3" />
                            </svg>
                            <span>Gérer les emprunts</span>
                        </a>
                        <button class="quick-action-btn bg-orange-50 text-orange-700 hover:bg-orange-100 flex items-center gap-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-settings-icon lucide-settings">
                                <path
                                    d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <span>Paramètres du système</span>
                        </button>
                    </div>

                    <div class="mt-6">
                        <h4 class="font-medium text-gray-700 mb-2">Statut du système</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>Base de données</span>
                                <span class="text-green-600 font-medium flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="lucide lucide-circle-check-icon lucide-circle-check">
                                        <circle cx="12" cy="12" r="10" />
                                        <path d="m9 12 2 2 4-4" />
                                    </svg>
                                    <span>Connectée</span>
                                </span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span>Dernière sauvegarde</span>
                                <span id="lastBackup" class="text-gray-600">Aujourd'hui, 03:45</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span>Version</span>
                                <span class="text-gray-600">v2.4.1</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- Troisième ligne avec journal des activités et actions rapides -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Journal des Activités -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow p-6 dashboard-card">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Journal des Activités</h3>
                    <div class="space-y-4">
                        <ul id="recentActivityList" class="space-y-3">
                            <li class="activity-item">
                                <div class="flex items-start">
                                    <div class="bg-green-100 p-2 rounded-full mr-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round"
                                            class="animate-spin text-green-600 lucide lucide-refresh-cw-icon lucide-refresh-cw">
                                            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
                                            <path d="M21 3v5h-5" />
                                            <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" />
                                            <path d="M8 16H3v5" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Chargement des activités...</p>
                                        <p class="text-xs text-gray-400">À l'instant</p>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <button id="loadMoreActivities"
                        class="mt-4 text-sm text-green-600 hover:text-green-800 font-medium flex items-center gap-1">
                        <span>Charger plus d'activités</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-circle-plus-icon lucide-circle-plus">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M8 12h8" />
                            <path d="M12 8v8" />
                        </svg>
                    </button>
                </div>
                
                <!-- Emprunts en retard -->
                <div class="bg-white rounded-xl shadow p-6 dashboard-card">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-800">Emprunts en Retard</h3>
                        <span id="overdueCount"
                            class="bg-red-500 text-white text-xs font-medium px-2 py-1 rounded-full">0</span>
                    </div>
                    <div class="overflow-y-auto max-h-64">
                        <ul id="overdueLoansList" class="divide-y divide-gray-100">
                            <li class="py-3 flex items-center justify-between">
                                <span>Chargement...</span>
                            </li>
                        </ul>
                    </div>
                    <button id="viewAllOverdueBtn"
                        class="mt-4 w-full text-sm text-green-600 hover:text-green-800 font-medium flex items-center">
                        <span>Voir tous les emprunts en retard</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-arrow-right-icon lucide-arrow-right">
                            <path d="M5 12h14" />
                            <path d="m12 5 7 7-7 7" />
                        </svg>
                    </button>
                </div>
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

    <!-- Conteneur pour les modales -->
    <div id="modalContainer"></div>

    <!-- Chart.js pour les graphiques -->
    <script src="/app/js/chart.js"></script>

    <!-- Importation des modules JS -->
    <script type="module" src="/app/js/auth/admin.js"></script>
</body>

</html>
