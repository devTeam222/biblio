<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ma Bibliothèque - Accueil</title>
    <!-- Tailwind CSS CDN -->
    <script src="/app/js/tailwind.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
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

<body class="flex flex-col min-h-screen">
    <!-- En-tête de la page -->
    <header class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white p-4 shadow-lg">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center">
            <h1 class="text-3xl font-bold mb-2 md:mb-0">
                <i class="fas fa-book-reader mr-2"></i>Bibliothèque
            </h1>
            <img loading="lazy" src="https://placehold.co/50x50/FFFFFF/000000?text=LIB" alt="Logo de la bibliothèque" class="rounded-full">
        </div>
    </header>

    <!-- Barre de navigation -->
    <nav class="bg-white shadow-md py-3">
        <div class="container mx-auto flex flex-wrap justify-center md:justify-start gap-4 px-4" id="mainNav">
            <a href="user.html" class="nav-link bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-house-icon lucide-house">
                    <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8" />
                    <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                </svg>
                Accueil
            </a>
            <a href="livres-user.html" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                    <path d="m21 21-4.34-4.34" />
                    <circle cx="11" cy="11" r="8" />
                </svg>
                Rechercher Livres
            </a>
            
        </div>
    </nav>

    <!-- Contenu principal -->
    <main class="flex-grow container mx-auto mt-8 px-4 py-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6" id="userNameDisplay">Bienvenue, Lecteur !</h2>

        <!-- Barre de recherche -->
        <div class="card p-6 mb-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Rechercher des Livres</h3>
            <div class="flex flex-col md:flex-row gap-4">
                <input type="text" id="searchInput" placeholder="Rechercher un livre, un auteur ou une catégorie..." class="flex-grow p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button id="searchButton" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-md transition duration-200 ease-in-out transform hover:scale-105">
                    Rechercher
                </button>
            </div>
        </div>

        <!-- Section Mes Emprunts Actuels -->
        <div class="card p-6 mb-8 hidden" id="current-loans-section">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Mes Emprunts Actuels <span id="loansLoading" class="loader hidden"></span></h3>
            <div id="current-loans-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Les emprunts seront chargés ici par JavaScript -->
            </div>
            <p id="noLoansMessage" class="text-gray-500 hidden">Vous n'avez aucun emprunt en cours.</p>
        </div>

        <!-- Section Ouvrages Tendances -->
        <div class="card p-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Ouvrages Tendances <span id="trendingLoading" class="loader hidden"></span></h3>
            <div id="trending-books-container" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <!-- Les ouvrages tendances seront chargés ici par JavaScript -->
            </div>
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="bg-gray-800 text-white text-center p-4 mt-8 shadow-inner">
        <p>&copy; 2025 Système de Gestion de Bibliothèque. Tous droits réservés. Réalisé par Ochokom.</p>
    </footer>
    <!-- Conteneur pour les modales -->
    <div id="modalContainer"></div>
    <script type="module" src="/app/js/home.js"></script>
</body>

</html>