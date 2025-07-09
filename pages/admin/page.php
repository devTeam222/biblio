<?php
// Si l'utilisateur n'est pas authentifié ou n'est pas un administrateur, rediriger vers la page de connexion
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login'); // Rediriger vers la page de connexion
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Administrateur - Ma Bibliothèque</title>
    <!-- Tailwind CSS CDN -->
    <script src="/app/js/tailwind.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        #overdueLoansList li, #recentActivityList li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        #overdueLoansList li:last-child, #recentActivityList li:last-child {
            border-bottom: none;
        }
        
        .chart-container {
            height: 250px;
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
    </style>
</head>

<body class="min-h-screen flex flex-col">
    <!-- En-tête de la page -->
    <header class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center mb-4 md:mb-0">
                    <i class="fas fa-user-shield text-2xl mr-3"></i>
                    <h1 class="text-2xl md:text-3xl font-bold">Tableau de Bord Admin</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="hidden md:flex items-center space-x-2 bg-indigo-700/50 px-3 py-1 rounded-full">
                        <i class="fas fa-bell"></i>
                        <span id="notificationCount" class="bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">0</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="text-right hidden md:block">
                            <p id="userNameDisplay" class="font-medium"></p>
                            <p class="text-xs text-indigo-200">Administrateur</p>
                        </div>
                        <img loading="lazy" src="https://placehold.co/40x40/FFFFFF/000000?text=ADM" alt="Admin" class="rounded-full border-2 border-white">
                        <button id="logoutButton" class="text-white hover:text-indigo-200 transition-colors">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Barre de navigation Admin -->
    <nav class="bg-white shadow-sm">
        <div class="container mx-auto px-4">
            <div class="flex overflow-x-auto py-3 space-x-6" id="mainNav">
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
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-book text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Total Livres</p>
                        <h3 id="totalBooks" class="text-2xl font-bold text-gray-800">...</h3>
                    </div>
                </div>
                
                <!-- Carte Livres Disponibles -->
                <div class="stat-card bg-white rounded-xl shadow p-6 flex items-center space-x-4 border-l-4 border-green-500">
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-book-open text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Livres Disponibles</p>
                        <h3 id="availableBooks" class="text-2xl font-bold text-gray-800">...</h3>
                    </div>
                </div>
                
                <!-- Carte Utilisateurs -->
                <div class="stat-card bg-white rounded-xl shadow p-6 flex items-center space-x-4 border-l-4 border-purple-500">
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i class="fas fa-users text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Total Utilisateurs</p>
                        <h3 id="totalUsers" class="text-2xl font-bold text-gray-800">...</h3>
                    </div>
                </div>
                
                <!-- Carte Emprunts -->
                <div class="stat-card bg-white rounded-xl shadow p-6 flex items-center space-x-4 border-l-4 border-orange-500">
                    <div class="bg-orange-100 p-3 rounded-full">
                        <i class="fas fa-exchange-alt text-orange-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Emprunts Actuels</p>
                        <h3 id="currentLoansCount" class="text-2xl font-bold text-gray-800">...</h3>
                    </div>
                </div>
            </div>

            <!-- Deuxième ligne avec graphique et emprunts en retard -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Section Graphique (placeholder) -->
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
                        <span id="overdueCount" class="bg-red-500 text-white text-xs font-medium px-2 py-1 rounded-full">0</span>
                    </div>
                    <div class="overflow-y-auto max-h-64">
                        <ul id="overdueLoansList" class="divide-y divide-gray-100">
                            <li class="py-3 flex items-center justify-between">
                                <span>Chargement...</span>
                            </li>
                        </ul>
                    </div>
                    <button id="viewAllOverdueBtn" class="mt-4 w-full text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        Voir tous les emprunts en retard <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </div>
            </div>

            <!-- Troisième ligne avec activité récente et autres éléments -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Activité Récente -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow p-6 dashboard-card">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Journal des Activités</h3>
                    <div class="space-y-4">
                        <ul id="recentActivityList" class="space-y-3">
                            <li class="activity-item">
                                <div class="flex items-start">
                                    <div class="bg-indigo-100 p-2 rounded-full mr-3">
                                        <i class="fas fa-sync-alt text-indigo-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Chargement des activités...</p>
                                        <p class="text-xs text-gray-400">il y a quelques secondes</p>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <button id="loadMoreActivities" class="mt-4 text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        Charger plus d'activités <i class="fas fa-plus-circle ml-1"></i>
                    </button>
                </div>
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow p-6 dashboard-card">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Actions Rapides</h3>
                    <div class="space-y-3">
                        <button class="quick-action-btn bg-blue-50 text-blue-700 hover:bg-blue-100">
                            <i class="fas fa-plus-circle mr-2"></i> Ajouter un nouveau livre
                        </button>
                        <button class="quick-action-btn bg-green-50 text-green-700 hover:bg-green-100">
                            <i class="fas fa-user-plus mr-2"></i> Créer un compte utilisateur
                        </button>
                        <button class="quick-action-btn bg-purple-50 text-purple-700 hover:bg-purple-100">
                            <i class="fas fa-book-reader mr-2"></i> Gérer les emprunts
                        </button>
                        <button class="quick-action-btn bg-orange-50 text-orange-700 hover:bg-orange-100">
                            <i class="fas fa-cog mr-2"></i> Paramètres du système
                        </button>
                    </div>
                    
                    <div class="mt-6">
                        <h4 class="font-medium text-gray-700 mb-2">Statut du système</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>Base de données</span>
                                <span class="text-green-600 font-medium"><i class="fas fa-check-circle mr-1"></i> Connectée</span>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Importation des modules JS -->
    <script type="module" src="/app/js/auth/admin.js"></script>
</body>

</html>