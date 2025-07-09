<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de Bibliothèque - Livres</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* Light gray background */
        }
        .card {
            background-color: #ffffff;
            border-radius: 0.75rem; /* rounded-xl */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); /* shadow-md */
            padding: 1.5rem; /* p-6 */
        }
        .nav-link {
            transition: all 0.2s ease-in-out;
        }
        .nav-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        /* Style pour les tables */
        th {
            padding: 0.75rem; /* p-3 */
            text-align: left;
            font-weight: 600; /* font-semibold */
            font-size: 0.875rem; /* text-sm */
            color: #4b5563; /* text-gray-700 */
            text-transform: uppercase;
            letter-spacing: 0.05em; /* tracking-wider */
            border-bottom: 2px solid #e5e7eb; /* border-b-2 border-gray-200 */
        }
        td {
            padding: 0.75rem; /* p-3 */
            font-size: 0.875rem; /* text-sm */
            color: #374151; /* text-gray-800 */
            border-bottom: 1px solid #e5e7eb; /* border-b border-gray-200 */
        }
        tr:nth-child(even) {
            background-color: #f9fafb; /* bg-gray-50 */
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <!-- En-tête de la page -->
    <header class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white p-4 shadow-lg">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center">
            <h1 class="text-3xl font-bold mb-2 md:mb-0">
                <i class="fas fa-book-reader mr-2"></i> Système de Gestion de Bibliothèque
            </h1>
            <img src="https://placehold.co/50x50/FFFFFF/000000?text=LIB" alt="Logo de la bibliothèque" class="rounded-full">
        </div>
    </header>

    <!-- Barre de navigation -->
    <nav class="bg-white shadow-md py-3">
        <div class="container mx-auto flex flex-wrap justify-center md:justify-start gap-4 px-4">
            <a href="../page.php" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 9.414V17a1 1 0 01-1.447.894l-3-1A1 1 0 017 15V9.414L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                </svg>
                Tableau de Bord
            </a>
            <a href="livres.html" class="nav-link bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                </svg>
                Livres
            </a>
            <a href="#" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                </svg>
                Lecteurs
            </a>
            <a href="#" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V4a2 2 0 00-2-2H6zm-3 8v3a1 1 0 001 1h2V9.667L4.333 8.333 3 10zm7 0H7v5h3v-5zm4 0h-3v5h3a1 1 0 001-1v-3.333L15.667 8.333 17 10zm-1-7v5h-4V3h4z" clip-rule="evenodd" />
                </svg>
                Emprunts
            </a>
            <a href="#" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l3 3a1 1 0 001.414-1.414L11 9.586V6z" clip-rule="evenodd" />
                </svg>
                Abonnements
            </a>
            <a href="#" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                </svg>
                Paiements
            </a>
        </div>
    </nav>

    <!-- Contenu principal de la page Livres -->
    <main class="flex-grow container mx-auto mt-8 px-4 py-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">Gestion des Livres</h2>

        <div class="card p-6 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Liste des Livres</h3>
                <button class="mt-4 md:mt-0 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md flex items-center transition duration-200 ease-in-out transform hover:scale-105">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Ajouter un Livre
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border-collapse rounded-lg overflow-hidden">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Auteur</th>
                            <th>Catégorie</th>
                            <th>Disponible</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Exemple de données (à remplacer par des données dynamiques) -->
                        <tr>
                            <td>Le Seigneur des Anneaux</td>
                            <td>J.R.R. Tolkien</td>
                            <td>Fantasy</td>
                            <td class="text-green-600 font-semibold">Oui</td>
                            <td class="flex gap-2">
                                <button class="bg-yellow-500 hover:bg-yellow-600 text-white py-1 px-3 rounded-md text-sm">Modifier</button>
                                <button class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md text-sm">Supprimer</button>
                            </td>
                        </tr>
                        <tr>
                            <td>1984</td>
                            <td>George Orwell</td>
                            <td>Dystopie</td>
                            <td class="text-red-600 font-semibold">Non</td>
                            <td class="flex gap-2">
                                <button class="bg-yellow-500 hover:bg-yellow-600 text-white py-1 px-3 rounded-md text-sm">Modifier</button>
                                <button class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md text-sm">Supprimer</button>
                            </td>
                        </tr>
                        <tr>
                            <td>Fondation</td>
                            <td>Isaac Asimov</td>
                            <td>Science-Fiction</td>
                            <td class="text-green-600 font-semibold">Oui</td>
                            <td class="flex gap-2">
                                <button class="bg-yellow-500 hover:bg-yellow-600 text-white py-1 px-3 rounded-md text-sm">Modifier</button>
                                <button class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md text-sm">Supprimer</button>
                            </td>
                        </tr>
                        <!-- Plus de lignes peuvent être ajoutées ici -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="bg-gray-800 text-white text-center p-4 mt-8 shadow-inner">
        <p>&copy; 2025 Système de Gestion de Bibliothèque. Tous droits réservés. Réalisé par Ochokom.</p>
    </footer>
</body>
</html>
