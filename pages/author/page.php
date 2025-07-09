<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace Auteur - Accueil</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
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
            height: 200px; /* Fixed height for covers */
            object-fit: cover;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <!-- En-tête de la page -->
    <header class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white p-4 shadow-lg">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center">
            <h1 class="text-3xl font-bold mb-2 md:mb-0">
                <i class="fas fa-pen-nib mr-2"></i> Mon Espace Auteur <span class="text-xl opacity-80">(Auteur)</span>
            </h1>
            <img loading="lazy" src="https://placehold.co/50x50/FFFFFF/000000?text=AUT" alt="Logo de l'auteur" class="rounded-full">
        </div>
    </header>

    <!-- Barre de navigation -->
    <nav class="bg-white shadow-md py-3">
        <div class="container mx-auto flex flex-wrap justify-center md:justify-start gap-4 px-4">
            <a href="#" class="nav-link bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard-icon lucide-layout-dashboard">
                    <rect width="7" height="9" x="3" y="3" rx="1" />
                    <rect width="7" height="5" x="14" y="3" rx="1" />
                    <rect width="7" height="9" x="14" y="12" rx="1" />
                    <rect width="7" height="5" x="3" y="16" rx="1" />
                </svg>
                Tableau de bord
            </a>
            <a href="livres-author.html" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                </svg>
                Mes Livres
            </a>
            <a href="#" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Statistiques
            </a>
            <a href="#" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.515-1.378 2.05-1.378 2.565 0L15 6.435l2.42 2.378a2 2 0 001.442-.693l2.378-2.42c1.378-.515 1.378-2.05 0-2.565L17.565 4a2 2 0 00-2.658 0L12 6.435l-2.42-2.378a2 2 0 00-1.442.693l-2.378 2.42c-1.378.515-1.378 2.05 0 2.565L6.435 12l-2.378 2.42a2 2 0 00.693 1.442l2.42 2.378c.515 1.378 2.05 1.378 2.565 0L12 17.565l2.42 2.378a2 2 0 001.442-.693l2.378-2.42c1.378-.515 1.378-2.05 0-2.565L17.565 12l2.378-2.42a2 2 0 00-.693-1.442l-2.42-2.378z" />
                </svg>
                Mon Profil
            </a>
        </div>
    </nav>

    <!-- Contenu principal -->
    <main class="flex-grow container mx-auto mt-8 px-4 py-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">Bienvenue, Auteur !</h2>

        <!-- Section Statistiques de mes Livres -->
        <div class="card p-6 mb-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Statistiques de mes Livres</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-blue-100 p-4 rounded-lg shadow-sm text-center">
                    <h4 class="text-2xl font-bold text-blue-800">5</h4>
                    <p class="text-sm text-blue-600">Total Livres Publiés</p>
                </div>
                <div class="bg-green-100 p-4 rounded-lg shadow-sm text-center">
                    <h4 class="text-2xl font-bold text-green-800">120</h4>
                    <p class="text-sm text-green-600">Total Emprunts</p>
                </div>
                <div class="bg-purple-100 p-4 rounded-lg shadow-sm text-center">
                    <h4 class="text-2xl font-bold text-purple-800">4.5 <span class="text-lg">/ 5</span></h4>
                    <p class="text-sm text-purple-600">Note Moyenne</p>
                </div>
            </div>
        </div>

        <!-- Section Mes Publications -->
        <div class="card p-6 mb-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Mes Publications</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                <!-- Livre de l'auteur 1 -->
                <div class="bg-gray-100 p-4 rounded-lg shadow-sm text-center">
                    <img loading="lazy" src="https://placehold.co/150x200/F0F8FF/000000?text=Mon+Livre+1" alt="Couverture Mon Livre 1" class="book-cover mx-auto mb-3">
                    <h4 class="font-bold text-gray-900 text-lg">Mon Premier Roman</h4>
                    <p class="text-sm text-gray-600">Catégorie : Fiction</p>
                    <span class="inline-block bg-orange-200 text-orange-800 text-xs font-semibold px-2.5 py-0.5 rounded-full mt-2">En cours de révision</span>
                    <button class="mt-3 bg-indigo-500 hover:bg-indigo-600 text-white py-1.5 px-4 rounded-md text-sm transition duration-200 ease-in-out">Modifier</button>
                </div>
                <!-- Livre de l'auteur 2 -->
                <div class="bg-gray-100 p-4 rounded-lg shadow-sm text-center">
                    <img loading="lazy" src="https://placehold.co/150x200/FFF0F5/000000?text=Mon+Livre+2" alt="Couverture Mon Livre 2" class="book-cover mx-auto mb-3">
                    <h4 class="font-bold text-gray-900 text-lg">Le Guide du Développement Web</h4>
                    <p class="text-sm text-gray-600">Catégorie : Informatique</p>
                    <span class="inline-block bg-green-200 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded-full mt-2">Publié</span>
                    <button class="mt-3 bg-blue-500 hover:bg-blue-600 text-white py-1.5 px-4 rounded-md text-sm transition duration-200 ease-in-out">Voir les détails</button>
                </div>
            </div>
            <div class="flex justify-center mt-6">
                <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-md flex items-center transition duration-200 ease-in-out transform hover:scale-105">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Soumettre un Nouveau Manuscrit
                </button>
            </div>
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="bg-gray-800 text-white text-center p-4 mt-8 shadow-inner">
        <p>&copy; 2025 Système de Gestion de Bibliothèque. Tous droits réservés. Réalisé par Ochokom.</p>
    </footer>
</body>
</html>
