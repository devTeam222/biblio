<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, interactive-widget=resizes-content" />
    <title>Visualisation des Zones d'Étude - GeoLib</title>
    
    <!-- Tailwind CSS & Config -->
    <script src="https://cdn.tailwindcss.com"></script>
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
                    }
                }
            }
        }
    </script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Google Fonts (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/app/css/editor-theme.css">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- JSZip -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <!-- shp.js -->
    <script src="https://unpkg.com/shpjs@latest/dist/shp.js"></script>
</head>
<body class="flex flex-col h-screen overflow-hidden text-sm bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 transition-colors duration-300">

<!-- HEADER -->
<header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 shadow-sm z-[1100] shrink-0 relative">
    <div class="flex items-center gap-3">
        <div class="bg-blue-600 text-white p-1.5 rounded-lg shadow-sm">
            <i data-lucide="map" class="w-6 h-6"></i>
        </div>
        <div class="flex flex-col leading-tight">
            <h1 class="text-lg font-bold tracking-tight hidden sm:block">GeoLib <span class="text-slate-400 font-normal text-sm">Visualisation</span></h1>
            <h1 class="text-lg font-bold tracking-tight sm:hidden">GeoLib</h1>
            <p class="text-xs text-slate-400 -mt-1 hidden sm:block" id="userNameChip">Invité</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <a href="/" id="homeLink" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold text-slate-600 dark:text-slate-200 rounded-lg border border-transparent hover:border-slate-200 dark:hover:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
            <i data-lucide="home" class="w-4 h-4"></i>
            <span class="hidden sm:inline">Accueil</span>
        </a>
        <div id="editToolbar" class="hidden sm:flex items-center">
            <button id="openEditorBtn" type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors shadow">
                <i data-lucide="wand" class="w-4 h-4"></i>
                Modifier
            </button>
        </div>
        <div class="flex items-center gap-3">
            <button id="btnDarkModeToggle" class="p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 transition-colors">
                <i id="darkModeIcon" data-lucide="moon" class="w-5 h-5"></i>
            </button>
            <button id="userAvatar" class="w-9 h-9 rounded-full border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-200 bg-white dark:bg-slate-800 shadow-sm">
                <i data-lucide="user-round" class="w-4 h-4"></i>
            </button>
        </div>
    </div>
</header>
<p id="editNotice" class="hidden text-xs sm:text-sm text-amber-600 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 px-4 py-2 border-b border-amber-100 dark:border-amber-800"></p>

<!-- CONTAINER -->
<div class="flex-1 relative overflow-hidden">
    <div id="map-container" class="relative h-full">
        <div id="map"></div>
        <div id="loading-overlay" class="loading-overlay hidden">
            <div class="flex flex-col items-center gap-4">
                <div class="loader"></div>
                <p class="text-slate-600 dark:text-slate-300 font-semibold">Chargement des zones d'étude...</p>
            </div>
        </div>
    </div>
    <p id="status-message" class="absolute bottom-4 left-4 right-4 text-sm text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 px-4 py-2 rounded-lg shadow-md z-[500]"></p>
</div>

<div id="messageContainer" class="fixed top-20 left-1/2 transform -translate-x-1/2 hidden flex items-center gap-3 px-4 py-3 rounded-lg shadow-xl transition-all duration-300 text-white font-medium min-w-[300px] justify-center"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script type="module" src="/app/js/study-area.js"></script>
</body>
</html>
