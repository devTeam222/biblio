<?php
// Si l'utilisateur n'est pas authentifié ou n'est pas un administrateur, rediriger vers la page de connexion
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.html'); // Rediriger vers la page de connexion
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Administrateur - Ma Bibliothèque</title>
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

        /* Overlay pour les messages modaux */
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="min-h-screen flex flex-col">
    <!-- En-tête de la page -->
    <header class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center mb-4 md:mb-0 gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-shield-user-icon lucide-shield-user">
                        <path
                            d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
                        <path d="M6.376 18.91a6 6 0 0 1 11.249.003" />
                        <circle cx="12" cy="11" r="4" />
                    </svg>
                    <h1 class="text-2xl md:text-3xl font-bold">Tableau de Bord Admin</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2 text-white bg-indigo-700/50 px-3 py-1 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                            fill="currentColor" fill-rule="evenOdd" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-bell-icon lucide-bell">
                            <path d="M10.268 21a2 2 0 0 0 3.464 0" />
                            <path
                                d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" />
                        </svg>
                        <span id="notificationCount" class="bg-red-500 text-xs rounded-full h-5 w-5 flex items-center justify-center">0</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="text-right hidden md:block">
                            <p id="userNameDisplay" class="font-medium"></p>
                            <p class="text-xs text-indigo-200">Administrateur</p>
                        </div>
                        <img loading="lazy" src="https://placehold.co/40x40/FFFFFF/000000?text=ADM" alt="Admin"
                            class="rounded-full border-2 border-white">
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Barre de navigation Admin -->
    <nav class="bg-white shadow-sm">
        <div class="container mx-auto p-4">
            <div class="container mx-auto flex flex-wrap justify-center md:justify-start gap-4 px-4" id="mainNav">
                <!-- Les liens seront injectés ici par JavaScript -->
            </div>
        </div>
    </nav>

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
                <div class="stat-card bg-white rounded-xl shadow p-6 flex items-center space-x-4 border-l-4 border-purple-500">
                    <div class="bg-purple-100 p-3 rounded-full text-purple-600">
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
                            <button class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded">7 jours</button>
                            <button class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">30 jours</button>
                            <button class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">Tout</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="activityChart"></canvas>
                    </div>
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
                        class="mt-4 w-full text-sm text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
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
            <!-- Troisième ligne avec journal des activités et actions rapides -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Journal des Activités -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow p-6 dashboard-card">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Journal des Activités</h3>
                    <div class="space-y-4">
                        <ul id="recentActivityList" class="space-y-3">
                            <li class="activity-item">
                                <div class="flex items-start">
                                    <div class="bg-indigo-100 p-2 rounded-full mr-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round"
                                            class="animate-spin text-indigo-600 lucide lucide-refresh-cw-icon lucide-refresh-cw">
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
                        class="mt-4 text-sm text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1">
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
                        <button class="quick-action-btn bg-purple-50 text-purple-700 hover:bg-purple-100 flex items-center gap-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-book-open-check-icon lucide-book-open-check">
                                <path d="M12 21V7" />
                                <path d="m16 12 2 2 4-4" />
                                <path
                                    d="M22 6V4a1 1 0 0 0-1-1h-5a4 4 0 0 0-4 4 4 4 0 0 0-4-4H3a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h6a3 3 0 0 1 3 3 3 3 0 0 1 3-3h6a1 1 0 0 0 1-1v-1.3" />
                            </svg>
                            <span>Gérer les emprunts</span>
                        </button>
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
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="bg-gray-800 text-white py-4 shadow-inner">
        <div class="container mx-auto px-4 text-center text-sm">
            <p>&copy; 2025 Système de Gestion de Bibliothèque. Tous droits réservés. Réalisé par Ochokom.</p>
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
