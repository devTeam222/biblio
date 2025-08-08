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

<body class="flex flex-col min-h-screen">
    <!-- En-tête de la page -->
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
                        <p id="userNameDisplay" class="font-medium">Bienvenue !</p>
                        <p class="text-xs text-indigo-200" id="userRoleDisplay"></p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Barre de navigation -->
    <nav class="bg-white shadow-sm">
        <div class="container mx-auto p-4">
            <div class="container mx-auto flex flex-wrap justify-center md:justify-start gap-4 px-4" id="mainNav">
                <!-- Les liens seront injectés ici par JavaScript -->
            </div>
        </div>
    </nav>

    <!-- Contenu principal -->
    <main class="flex-grow container mx-auto px-4 py-6">
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Mon Profil</h2>

            <!-- Onglets de navigation -->
            <div class="flex border-b border-gray-200 mb-4">
                <button id="profileDetailsTabBtn" class="tab-button active bg-white text-indigo-700 border-indigo-500">Détails du Profil</button>
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
                        <button id="editProfileBtn" class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            <span class="loader hidden"></span> Modifier le profil
                        </button>
                    </div>

                    <!-- Formulaire de mise à jour du profil -->
                    <div class="card p-4 hidden" id="profileUpdateFormContainer">
                        <h3 class="font-semibold text-gray-700 mb-3">Mettre à jour mon profil</h3>
                        <form id="profileUpdateForm" class="space-y-4">
                            <div>
                                <label for="updateName" class="block text-sm font-medium text-gray-700">Nom</label>
                                <input type="text" id="updateName" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                            </div>
                            <div>
                                <label for="updateBio" class="block text-sm font-medium text-gray-700">Bio</label>
                                <textarea id="updateBio" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                            <div>
                                <label for="updateBirthdate" class="block text-sm font-medium text-gray-700">Date de naissance</label>
                                <input type="date" id="updateBirthdate" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="updatePassword" class="block text-sm font-medium text-gray-700">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                                <input type="password" id="updatePassword" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" id="cancelUpdateProfileBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Annuler</button>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
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
                            <textarea id="adminMessage" rows="5" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="Décrivez votre demande ici..." required></textarea>
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
