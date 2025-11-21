<?php
session_start();
// Rediriger si non connecté
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}
// Inclure la connexion à la base de données si nécessaire pour des informations statiques
// require_once __DIR__ . '/../../app/db.php'; 
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - GéoLib</title>
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

        .tab-button {
            padding: 10px 20px;
            border-radius: 0.5rem 0.5rem 0 0;
            font-weight: 500;
            transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }

        .tab-button.active {
            background-color: #ffffff;
            color: #4f46e5;
            border-color: #4f46e5;
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
    </style>
</head>
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
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Mon Profil</h2>

            <!-- Onglets de navigation -->
            <div class="flex border-b border-gray-200 mb-4">
                <button id="profileDetailsTabBtn" class="tab-button active bg-white text-green-700 border-green-500">Détails du Profil</button>
                <button id="loanHistoryTabBtn" class="tab-button bg-gray-100 text-gray-700">Historique des Emprunts</button>
                <button id="subscriptionHistoryTabBtn" class="tab-button bg-gray-100 text-gray-700">Mes Abonnements</button>
            </div>

            <!-- Contenu des onglets -->
            <div id="profileDetailsContent" class="tab-content relative">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Informations du profil -->
                    <div class="card p-4">
                        <h3 class="font-semibold text-gray-700 mb-3">Mes Informations</h3>
                        <p class="text-gray-700 mb-2"><strong>Nom:</strong> <span id="profileName"></span></p>
                        <p class="text-gray-700 mb-2"><strong>Email:</strong> <span id="profileEmail"></span></p>
                        <p class="text-gray-700 mb-2"><strong>Rôle:</strong> <span id="profileRole"></span></p>
                        <p class="text-gray-700 mb-2"><strong>Date de naissance:</strong> <span id="profileBirthdate"></span></p>
                        <p class="text-gray-700 mb-2"><strong>Bio:</strong> <span id="profileBio"></span></p>
                        <button id="editProfileBtn" class="mt-4 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            <span class="loader hidden"></span> Modifier le profil
                        </button>
                    </div>

                    <!-- Formulaire de mise à jour du profil -->
                    <div class="card p-4 hidden" id="profileUpdateFormContainer">
                        <h3 class="font-semibold text-gray-700 mb-3">Mettre à jour mon profil</h3>
                        <form id="profileUpdateForm" class="space-y-4">
                            <div>
                                <label for="updateName" class="block text-sm font-medium text-gray-700">Nom</label>
                                <input type="text" id="updateName" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500" required>
                            </div>
                            <div>
                                <label for="updateBio" class="block text-sm font-medium text-gray-700">Bio</label>
                                <textarea id="updateBio" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"></textarea>
                            </div>
                            <div>
                                <label for="updateBirthdate" class="block text-sm font-medium text-gray-700">Date de naissance</label>
                                <input type="date" id="updateBirthdate" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <label for="updatePassword" class="block text-sm font-medium text-gray-700">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                                <input type="password" id="updatePassword" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" id="cancelUpdateProfileBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Annuler</button>
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                    <span class="loader hidden"></span> Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Section Contacter l'administrateur -->
                <div class="card p-4 mt-6">
                    <h3 class="font-semibold text-gray-700 mb-3">Contacter l'administrateur</h3>
                    <p class="text-gray-600 mb-4">Si vous avez des questions concernant votre abonnement, un problème avec un livre, ou toute autre demande, utilisez ce formulaire pour envoyer un message à l'administrateur.</p>
                    <form id="contactAdminForm" class="space-y-4">
                        <div>
                            <label for="adminMessage" class="block text-sm font-medium text-gray-700">Votre message</label>
                            <textarea id="adminMessage" rows="5" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500" placeholder="Décrivez votre demande ici..." required></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                <span class="loader hidden"></span> Envoyer le message
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="loanHistoryContent" class="tab-content hidden">
                <h3 class="font-semibold text-gray-700 mb-3">Historique de mes emprunts</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white rounded-md overflow-hidden">
                        <thead>
                            <tr>
                                <th>Livre</th>
                                <th>Date d'emprunt</th>
                                <th>Date de retour prévue</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody id="loanHistoryTableBody">
                            <!-- Les emprunts seront chargés ici par JavaScript -->
                            <tr><td colspan="4" class="text-center py-4 text-gray-500">Chargement de l'historique des emprunts...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="subscriptionHistoryContent" class="tab-content hidden">
                <h3 class="font-semibold text-gray-700 mb-3">Historique de mes abonnements</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white rounded-md overflow-hidden">
                        <thead>
                            <tr>
                                <th>ID Abonnement</th>
                                <th>Date de début</th>
                                <th>Date de fin</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody id="subscriptionHistoryTableBody">
                            <!-- Les abonnements seront chargés ici par JavaScript -->
                            <tr><td colspan="4" class="text-center py-4 text-gray-500">Chargement de l'historique des abonnements...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="bg-gray-800 text-white py-4 shadow-inner">
        <div class="container mx-auto px-4 text-center text-sm">
            <p>&copy; 2025 Base des données Dimart Géosciences. Tous droits réservés.</p>
        </div>
    </footer>
    <!-- Conteneur pour les modales -->
    <div id="modalContainer"></div>
    <script type="module" src="/app/js/profile.js"></script>
</body>

</html>
