<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration des Zones d'Étude (Study Areas)</title>
    <!-- Chargement de Tailwind CSS -->
    <script src="/app/js/tailwind"></script>
    
    <!-- Dépendances Leaflet pour la prévisualisation (AJOUTÉES) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <!-- Chargement de la librairie shpjs (shapefile-js) (AJOUTÉ) -->
    <script src="https://unpkg.com/shpjs@latest/dist/shp.js"></script>

    <style>
        /* Configuration de la police Inter */
        body {
            font-family: 'Inter', sans-serif;
            /* Nuance de fond subtilement plus chaude */
            background-color: #f8fafc; 
        }
        
        /* MISE À JOUR: Bouton principal avec dégradé et ombre plus intense */
        .btn-primary {
            @apply px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-bold text-lg rounded-xl shadow-xl hover:shadow-2xl hover:from-blue-700 hover:to-blue-800 transition duration-300 ease-in-out transform hover:scale-[1.01] flex items-center justify-center space-x-3;
        }
        .btn-danger {
            /* Bouton d'action secondaire (suppression) */
            @apply px-4 py-2 bg-red-600 text-white font-semibold rounded-lg shadow-md hover:bg-red-700 transition duration-150 ease-in-out text-sm;
        }
        /* Style obligatoire pour que la carte de prévisualisation ait une taille */
        #previewMap {
            height: 200px; /* Hauteur fixe pour la prévisualisation */
            width: 100%;
            border-radius: 0.5rem;
            margin-top: 1rem;
            border: 1px solid #ccc;
        }
    </style>
</head>
<body>

<!-- En-tête de la page (Style Admin) -->
    <header class="bg-gradient-to-r from-green-600 to-blue-700 text-white shadow-lg">
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
                        <p id="userNameDisplay" class="font-medium">Bienvenue, Lecteur !</p>
                        <p class="text-xs text-green-200" id="userRoleDisplay"></p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Barre de navigation (Style Admin) -->
    <nav class="bg-white shadow-sm">
        <div class="container mx-auto p-4">
            <div class="container mx-auto flex flex-wrap justify-center md:justify-start gap-4 px-4" id="mainNav">
                <!-- Les liens seront injectés ici par JavaScript -->
            </div>
        </div>
    </nav>


        

        <!-- Section de Création (Ajouter une Study Area) -->
        <div class="my-10 p-8 border-2 border-blue-300 rounded-2xl bg-blue-50/70 shadow-lg max-w-4xl mx-auto">
            <!-- Section Message/Feedback. Le JS ajoute 'shadow-md' pour le style. -->
            <div id="feedbackMessage" class="hidden p-4 mb-4 text-sm rounded-lg" role="alert"></div>
            <h2 class="text-2xl font-semibold text-blue-800 mb-6 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Créer une Nouvelle Zone d'Étude
            </h2>
            <form id="createStudyAreaForm" class="space-y-6">
                
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nom de la Zone d'Étude</label>
                    <!-- Classes Tailwind appliquées directement -->
                    <input type="text" id="name" name="name" required 
                           class="w-full p-3 border border-gray-300 rounded-lg bg-gray-50 shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out" 
                           placeholder="Ex: Bassin du Fleuve Congo">
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <!-- Classes Tailwind appliquées directement -->
                    <textarea id="description" name="description" rows="3" 
                              class="w-full p-3 border border-gray-300 rounded-lg bg-gray-50 shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out" 
                              placeholder="Description détaillée de la zone..."></textarea>
                </div>
                
                <hr class="border-blue-200">

                <!-- Section Upload Shapefile -->
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-inner">
                    <h3 class="text-lg font-medium text-gray-800 mb-2 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L6.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                        Fichier Shapefile (Optionnel)
                    </h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Veuillez uploader un fichier **.zip** contenant le shapefile (.shp, .dbf, .shx, etc.).
                    </p>
                    
                    <label for="shapefile" class="block text-sm font-medium text-gray-700 mb-2">Sélectionner un fichier ZIP</label>
                    <input type="file" id="shapefile" name="shapefile" accept=".zip" class="w-full text-sm text-gray-600
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-full file:border-0
                        file:text-sm file:font-semibold
                        file:bg-blue-100 file:text-blue-700
                        hover:file:bg-blue-200 transition duration-150 ease-in-out
                    ">
                    <input type="hidden" id="shapefile_id" name="shapefile_id">
                    <div id="fileStatus" class="mt-2 text-sm text-gray-600 italic"></div>
                    
                    <!-- Conteneur de la carte de prévisualisation -->
                    <div id="previewMap" class="hidden"></div>
                    <p id="previewStatus" class="mt-2 text-sm text-gray-500 hidden font-medium">Prévisualisation du fichier ZIP...</p>
                </div>

                <div class="pt-6">
                    <!-- Mise à jour de la structure du bouton pour inclure l'icône/spinner -->
                    <button type="submit" class="btn-primary w-full" id="submitButton">
                        <div id="buttonContent" class="flex items-center space-x-3">
                            <!-- Icone par défaut -->
                            <svg id="defaultIcon" class="h-6 w-6 transition-opacity duration-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <!-- Spinner (caché par défaut) -->
                            <svg id="loadingSpinner" class="animate-spin h-6 w-6 text-white hidden" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span id="buttonText">Créer la Zone d'Étude</span>
                        </div>
                    </button>
                </div>
            </form>
        </div>

        <!-- Section Liste des Zones d'Étude -->
        <div class="my-10  max-w-4xl mx-auto">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6 flex items-center border-b pb-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
                Liste des Zones d'Étude Existantes
            </h2>
            <div id="studyAreasList" class="space-y-4">
                <!-- Les cartes de zones d'étude seront insérées ici par le JS -->
                
                <p class="text-gray-500 text-center py-4 italic">Chargement des données...</p>
            </div>
        </div>

    <!-- Confirmation Modal (hidden by default) -->
    <div id="deleteConfirmationModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center hidden transition-opacity duration-300">
        <div class="bg-white rounded-xl p-8 max-w-lg w-full shadow-2xl transform scale-100 transition-transform duration-300">
            <div class="flex items-center space-x-4 mb-4">
                <div class="flex-shrink-0 p-3 rounded-full bg-red-100 text-red-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.332 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900">Confirmer la Suppression</h3>
            </div>
            
            <p class="text-gray-700 mb-6">
                Êtes-vous sûr de vouloir supprimer la zone d'étude : 
                <span id="modalAreaName" class="font-extrabold text-red-600 italic break-words"></span> ? 
                Cette action est irréversible.
            </p>
            
            <div class="flex justify-end space-x-4">
                <button id="cancelDeleteButton" type="button" class="px-6 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 font-medium transition duration-150">
                    Annuler
                </button>
                <button id="confirmDeleteButton" type="button" class="btn-danger px-6 py-3 hover:scale-100">
                    Oui, Supprimer Définitivement
                </button>
            </div>
        </div>
    </div>

    <!-- Script JavaScript pour la logique du Frontend (le chemin est conservé) -->
    <script type="module" src="/app/js/auth/admin/study-areas.js"></script>
</body>
</html>