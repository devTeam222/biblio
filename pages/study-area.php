<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualisation des Zones d'Étude (Shapefiles)</title>
    <!-- Chargement de Tailwind CSS -->
    <script src="/app/js/tailwind"></script>
    <!-- Chargement de Leaflet CSS -->
    <style>
        /* Style obligatoire pour que la carte Leaflet ait une taille */
        #map {
            height: 80vh; 
            width: 100%;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f9;
        }
        /* Style des popups Leaflet */
        .leaflet-popup-content-wrapper, .leaflet-control-layers-expanded {
            border-radius: 0.5rem;
        }
        .leaflet-container a {
            color: #10B981; /* Couleur d'un lien Tailwind vert 500 */
        }
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            border-radius: 0.5rem;
            transition: opacity 0.3s;
        }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #10B981;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <!-- Chargement de la librairie shpjs (shapefile-js) -->
    <script src="https://unpkg.com/shpjs@latest/dist/shp.js"></script>
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
    <div class="max-w-7xl mx-auto">
        <div id="map-container" class="relative">
            <div id="map"></div>
            <div id="loading-overlay" class="loading-overlay hidden">
                <div class="loader"></div>
                <p class="ml-4 text-gray-600 font-semibold">Chargement des shapefiles...</p>
            </div>
        </div>
        
        <p id="status-message" class="mt-4 text-gray-600"></p>
    </div>

    <!-- Chargement des librairies JS -->
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20n6a9KxI6X8w2N0q5f3z4Gmsxah8y29g5wz9X2mO7g="
        crossorigin=""></script>
    <!-- JSZip (pour dézipper le shapefile) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <!-- shp.js (pour parser le shapefile en GeoJSON) -->
    <script src="https://cdn.jsdelivr.net/npm/shpjs@3.6.3/dist/shp.js"></script>

    <script type="module" src="/app/js/study-area.js"></script>
</body>
</html>