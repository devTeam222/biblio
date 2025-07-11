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
    <title>Gérer les Livres - Ma Bibliothèque</title>
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
</head>

<body class="min-h-screen flex flex-col">
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
                    <h1 class="text-2xl md:text-3xl font-bold">Gérer les Livres</h1>
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
                        <span id="notificationCount"
                            class="bg-red-500 text-xs rounded-full h-5 w-5 flex items-center justify-center">0</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="text-right hidden md:block">
                            <p id="userNameDisplay" class="font-medium"></p>
                            <p class="text-xs text-indigo-200">Administrateur</p>
                        </div>
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
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-center mb-4">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4 md:mb-0">Liste des Livres</h2>
                <div class="flex flex-col md:flex-row gap-4 w-full md:w-2/3 lg:w-1/2">
                    <input type="text" id="bookSearchInput" placeholder="Rechercher par titre, auteur, ISBN..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <button id="addBookBtn"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 flex items-center justify-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-plus-icon lucide-plus">
                            <path d="M5 12h14" />
                            <path d="M12 5v14" />
                        </svg>
                        Ajouter un livre
                    </button>
                </div>
            </div>

            <div class="table-container">
                <table id="booksTable" class="min-w-full bg-white rounded-md overflow-hidden">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Titre</th>
                            <th>Auteur</th>
                            <th>ISBN</th>
                            <th>Disponible</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Les livres seront chargés ici par JavaScript -->
                        <tr>
                            <td colspan="6" class="text-center py-4 text-gray-500">Chargement des livres...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Pied de page (Copie du tableau de bord admin) -->
    <footer class="bg-gray-800 text-white py-4 shadow-inner">
        <div class="container mx-auto px-4 text-center text-sm">
            <p>&copy; 2025 Système de Gestion de Bibliothèque. Tous droits réservés. Réalisé par Ochokom.</p>
            <p class="mt-1 text-gray-400">Dernière mise à jour: <span id="lastUpdateTime">maintenant</span></p>
        </div>
    </footer>


    <!-- Modale Ajouter/Modifier Livre -->
    <div id="bookModal" class="modal-overlay hidden">
        <div class="modal-content">
            <h3 id="bookModalTitle" class="text-xl font-semibold text-gray-800 mb-4">Ajouter un nouveau livre</h3>
            <form id="bookForm" class="space-y-4">
                <input type="hidden" id="bookId">
                <div>
                    <label for="bookTitle" class="block text-sm font-medium text-gray-700 text-left">Titre</label>
                    <input type="text" id="bookTitle"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                        required>
                </div>
                <div>
                    <label for="bookAuthor" class="block text-sm font-medium text-gray-700 text-left">Auteur</label>
                    <select id="bookAuthor"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                        required>
                        <!-- Options chargées par JS -->
                    </select>
                </div>
                <div>
                    <label for="bookIsbn" class="block text-sm font-medium text-gray-700 text-left">ISBN</label>
                    <input type="text" id="bookIsbn"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label for="bookDescription"
                        class="block text-sm font-medium text-gray-700 text-left">Description</label>
                    <textarea id="bookDescription" rows="3"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" id="bookAvailable"
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="bookAvailable" class="ml-2 block text-sm text-gray-900">Disponible</label>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelBookModalBtn"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Annuler</button>
                    <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Conteneur pour les modales -->
    <div id="modalContainer"></div>
    <script type="module" src="/app/js/auth/admin/books.js"></script>
</body>

</html>
