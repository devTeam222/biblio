<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <!-- Ajout de interactive-widget=resizes-content pour mieux gérer le clavier virtuel si besoin -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, interactive-widget=resizes-content" />
    <title>Éditeur de Formes Géographiques</title>

    <!-- Tailwind CSS & Config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: { 750: '#2d3748', 850: '#1a202c', 950: '#0f172a' }
                    },
                    zIndex: {
                        'nav': '1050',
                    }
                }
            }
        }
    </script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Google Fonts (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- JSZip -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

    <style>
        body { font-family: "Inter", sans-serif; overflow: hidden; }
        
        /* Layout Principal - CORRECTION MOBILE DVH */
        .main-container { 
            display: flex; 
            /* Utilisation de dvh (dynamic viewport height) pour éviter le resize quand la barre d'adresse disparait */
            height: calc(100dvh - 64px); 
            position: relative; 
            overflow: hidden; 
        }
        
        #map { 
            width: 100%; 
            height: 100%; 
            z-index: 1; 
            /* Empêche le navigateur de gérer le zoom/pan de la page sur la carte */
            touch-action: none; 
        }

        /* Panneau Latéral (Overlay Mode) */
        #attributes-panel {
            position: absolute; top: 0; bottom: 0; right: 0;
            width: 100%; max-width: 400px;
            background-color: white;
            z-index: 1000;
            transform: translateX(0);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: -4px 0 15px rgba(0, 0, 0, 0.1);
            display: flex; flex-direction: column;
        }
        .dark #attributes-panel { background-color: #0f172a; border-left: 1px solid #1e293b; }
        .panel-hidden #attributes-panel { transform: translateX(100%); }

        /* Transitions */
        #attributes-panel, header, .modal-content, body, .btn-icon, #nav-menu {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, opacity 0.2s ease;
        }

        /* Boutons d'action */
        .btn-icon { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.2s; }
        .btn-icon:active { transform: scale(0.98); }

        /* Toggle Flottant Map */
        #mapInspectorToggle {
            position: absolute; top: 1rem; right: 1rem;
            z-index: 400;
            transition: right 0.3s ease, top 0.3s ease;
        }
        @media (max-width: 640px) {
            #mapInspectorToggle { top: 1.25rem; right: 0.75rem; }
        }

        /* Leaflet Overrides */
        .leaflet-container { background-color: #e2e8f0; font-family: "Inter", sans-serif; }
        .dark .leaflet-container { background-color: #1e293b; }
        
        /* Leaflet Controls Styling - Base */
        .leaflet-bar { border: none !important; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important; }
        .leaflet-bar a {
            border-radius: 0.375rem !important; margin-bottom: 4px !important; border-bottom: none !important;
            background-color: white; color: #334155;
            width: 34px !important; height: 34px !important;
            line-height: 34px !important;
            transition: all 0.2s;
        }
        
        /* --- OPTIMISATION MOBILE / TOUCH RENFORCÉE --- */
        @media (max-width: 640px) {
            /* Boutons encore plus visibles et gros */
            .leaflet-bar {
                box-shadow: 0 4px 12px rgba(0,0,0,0.25) !important; /* Ombre plus forte */
                border: 1px solid rgba(0,0,0,0.05) !important;
            }
            .leaflet-bar a {
                width: 48px !important;
                height: 48px !important;
                line-height: 48px !important;
                font-size: 20px;
                background-color: #ffffff !important; /* Force le blanc pour contraste */
                color: #0f172a !important;
            }
            /* Dark mode spécifique mobile pour les contrôles */
            .dark .leaflet-bar a {
                background-color: #1e293b !important;
                color: #f1f5f9 !important;
                border: 1px solid #334155 !important;
            }

            .leaflet-right { margin-right: 0.75rem; }
            .leaflet-left { margin-left: 0.75rem; }
            .leaflet-bottom { margin-bottom: 1rem; }
            
            /* Draw toolbar icons adjustments */
            .leaflet-draw-toolbar a {
                background-size: 24px 24px !important;
            }
        }

        .leaflet-bar a:hover { background-color: #f1f5f9; color: #2563eb; }

        /* --- DARK MODE SPECIFICS FOR DRAWING TOOLS --- */
        /* On inverse les couleurs pour avoir des icônes blanches sur fond sombre, mais on garde le contrôle sur mobile défini plus haut */
        .dark .leaflet-draw-toolbar a {
            filter: invert(1) hue-rotate(180deg);
            background-color: #e2e8f0;
            border-color: #cbd5e1;
        }
        /* Reset filter for mobile dark mode to handle custom colors properly if needed, but invert usually works ok unless explicit override */
        @media (max-width: 640px) {
             /* Sur mobile on préfère gérer les couleurs explicitement sans invert pour plus de netteté */
            .dark .leaflet-draw-toolbar a {
                filter: none;
                /* On utilise les images de base (noires) mais sur fond sombre elles ne se voient pas... 
                   L'inversion reste la méthode la plus simple pour les sprites Leaflet Draw par défaut */
                filter: invert(1) hue-rotate(180deg); 
            }
        }

        .dark .leaflet-control-zoom a { background-color: #1e293b; color: #e2e8f0; border: 1px solid #334155; }
        .dark .leaflet-control-zoom a:hover { background-color: #334155; }

        .selected-feature-highlight {
            filter: drop-shadow(0 0 4px rgba(59, 130, 246, 0.6));
            stroke: #3b82f6; stroke-width: 3px;
        }

        /* Modals */
        .modal-overlay {
            position: fixed; inset: 0;
            background-color: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(2px);
            display: flex; justify-content: center; align-items: center;
            z-index: 1200;
            opacity: 0; visibility: hidden;
            transition: all 0.2s ease-out;
        }
        .modal-overlay.open { opacity: 1; visibility: visible; }
        .modal-content {
            border-radius: 0.75rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            transform: scale(0.95);
            transition: transform 0.2s ease-out;
        }
        .modal-overlay.open .modal-content { transform: scale(1); }

        /* Data Table */
        .table-container { scrollbar-width: thin; }
        .data-table th { position: sticky; top: 0; z-index: 10; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
        
        #messageContainer { z-index: 2000; pointer-events: none; }
        .hidden-force { display: none !important; }
        .ai-pulse { animation: pulse-purple 2s infinite; }
        @keyframes pulse-purple {
            0% { box-shadow: 0 0 0 0 rgba(147, 51, 234, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(147, 51, 234, 0); }
            100% { box-shadow: 0 0 0 0 rgba(147, 51, 234, 0); }
        }

        /* --- TOOLTIP / DESCRIPTION LATERALE --- */
        .custom-tooltip {
            position: absolute; z-index: 5000;
            background-color: #1e293b; color: white;
            padding: 0.35rem 0.75rem; border-radius: 0.375rem;
            font-size: 0.75rem; font-weight: 600;
            white-space: nowrap;
            opacity: 0; visibility: hidden;
            transition: opacity 0.15s, transform 0.15s;
            pointer-events: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex; align-items: center; gap: 6px;
        }
        .custom-tooltip.visible { opacity: 1; visibility: visible; }
        .custom-tooltip::after { content: ''; position: absolute; border-width: 5px; border-style: solid; }
        .custom-tooltip.right-label { transform: translateX(10px); }
        .custom-tooltip.right-label.visible { transform: translateX(0); }
        .custom-tooltip.right-label::after {
            top: 50%; right: 100%; margin-top: -5px;
            border-color: transparent #1e293b transparent transparent;
        }
        .dark .custom-tooltip { background-color: #e2e8f0; color: #0f172a; }
        .dark .custom-tooltip.right-label::after { border-color: transparent #e2e8f0 transparent transparent; }

        /* Header tooltips (statique CSS) */
        .group:hover { z-index: 60; }
        .group:hover .custom-tooltip-static { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); }
        @media (max-width: 768px) {
            .custom-tooltip-static { display: none !important; }
        }
        .custom-tooltip-static {
            position: absolute; 
            top: 115%; left: 50%; 
            transform: translateX(-50%) translateY(5px);
            background-color: #1e293b; color: white;
            padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem;
            white-space: nowrap; opacity: 0; visibility: hidden;
            transition: all 0.2s; pointer-events: none; 
            z-index: 100; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .custom-tooltip-static::after {
            content: ''; position: absolute; bottom: 100%; left: 50%; margin-left: -4px;
            border-width: 4px; border-style: solid; border-color: transparent transparent #1e293b transparent;
        }
        .dark .custom-tooltip-static { background-color: #e2e8f0; color: #0f172a; }
        .dark .custom-tooltip-static::after { border-color: transparent transparent #e2e8f0 transparent; }
    </style>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Leaflet.Draw CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
    <!-- SHP Write -->
    <script src="https://unpkg.com/@mapbox/shp-write@latest/shpwrite.js"></script>
</head>
<body class="flex flex-col h-screen overflow-hidden text-sm bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 transition-colors duration-300">

    <!-- Élément Tooltip Global pour la carte -->
    <div id="global-map-tooltip" class="custom-tooltip"></div>

    <!-- HEADER: Z-Index augmenté à 1100 pour être au-dessus du panneau latéral (1000) -->
    <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 shadow-sm z-[1100] shrink-0 relative">
        
        <!-- Logo & Titre -->
        <div class="flex items-center gap-3">
            <div class="bg-blue-600 text-white p-1.5 rounded-lg shadow-sm">
                <i data-lucide="map" class="w-6 h-6"></i>
            </div>
            <h1 class="text-lg font-bold tracking-tight hidden sm:block">GeoEditor <span class="text-slate-400 font-normal text-sm">Web</span></h1>
            <h1 class="text-lg font-bold tracking-tight sm:hidden">GeoEditor</h1>
        </div>

        <!-- Bouton Hamburger (Mobile) -->
        <button onclick="toggleMobileMenu()" class="md:hidden p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 focus:outline-none">
            <i id="menuIcon" data-lucide="menu" class="w-6 h-6"></i>
        </button>

        <!-- Menu de Navigation -->
        <div id="nav-menu" class="hidden md:flex flex-col md:flex-row absolute md:static top-16 left-0 w-full md:w-auto bg-white dark:bg-slate-900 md:bg-transparent shadow-xl md:shadow-none border-b md:border-0 border-slate-200 dark:border-slate-700 p-4 md:p-0 gap-4 md:gap-2 items-stretch md:items-center z-nav transition-all duration-200 ease-in-out">

            <!-- Dark Mode -->
            <div class="flex items-center justify-between md:justify-start">
                <span class="md:hidden text-sm font-bold text-slate-500 uppercase tracking-wider">Apparence</span>
                <div class="relative group">
                    <button onclick="toggleDarkMode()" class="p-2 mr-1 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 transition-colors">
                        <i id="darkModeIcon" data-lucide="moon" class="w-5 h-5"></i>
                    </button>
                    <span class="custom-tooltip-static">Apparence</span>
                </div>
            </div>

            <!-- Separator (Desktop only) -->
            <div class="hidden md:block h-6 w-px bg-slate-300 dark:bg-slate-700 mx-1"></div>
            <!-- Separator (Mobile only) -->
            <div class="md:hidden h-px w-full bg-slate-100 dark:bg-slate-800"></div>

            <!-- Inputs cachés -->
            <input type="file" id="fileInput" accept=".geojson, .zip" class="hidden" onchange="loadFile()" />
            <input type="file" id="folderInput" webkitdirectory directory multiple class="hidden" onchange="loadFolder()" />

            <!-- Groupe Import -->
            <div class="flex flex-col gap-2 md:gap-0">
                <span class="md:hidden text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Importer</span>
                <div class="flex rounded-md shadow-sm" role="group">
                    <div class="relative group flex-1 md:flex-none">
                        <label for="fileInput" class="btn-icon w-full md:w-auto bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 px-3 py-2 rounded-l-md cursor-pointer border border-slate-200 dark:border-slate-700 font-medium justify-center md:justify-start">
                            <i data-lucide="file" class="w-4 h-4"></i>
                            <span class="inline">Fichier</span>
                        </label>
                        <span class="custom-tooltip-static">Importer un fichier</span>
                    </div>
                    <div class="relative group flex-1 md:flex-none">
                        <label for="folderInput" class="btn-icon w-full md:w-auto bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 px-3 py-2 rounded-r-md cursor-pointer border-t border-b border-r border-slate-200 dark:border-slate-700 font-medium justify-center md:justify-start">
                            <i data-lucide="folder-open" class="w-4 h-4"></i>
                            <span class="inline">Dossier</span>
                        </label>
                        <span class="custom-tooltip-static">Importer un dossier</span>
                    </div>
                </div>
            </div>

            <!-- Separator (Desktop only) -->
            <div class="hidden md:block h-6 w-px bg-slate-300 dark:bg-slate-700 mx-1"></div>

            <!-- Bouton Tableau -->
            <div class="relative group">
                <button onclick="openTableModal()" class="btn-icon w-full md:w-auto bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 font-medium justify-center md:justify-start">
                    <i data-lucide="table" class="w-4 h-4"></i>
                    <span class="inline">Données</span>
                </button>
                <span class="custom-tooltip-static">Voir le tableau global</span>
            </div>

            <!-- Separator (Mobile only) -->
            <div class="md:hidden h-px w-full bg-slate-100 dark:bg-slate-800"></div>

            <!-- Groupe Export -->
            <div class="flex flex-col gap-2 md:gap-0">
                <span class="md:hidden text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Exporter</span>
                <div class="flex rounded-md shadow-sm" role="group">
                    <div class="relative group flex-1 md:flex-none">
                        <button id="exportGeoJSONButton" onclick="openExportModal('geojson')" class="btn-icon w-full md:w-auto bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 px-3 py-2 rounded-l-md border border-indigo-200 dark:border-indigo-800 font-medium disabled:opacity-50 disabled:cursor-not-allowed justify-center md:justify-start">
                            <i data-lucide="download" class="w-4 h-4"></i>
                            <span class="inline">GeoJSON</span>
                        </button>
                        <span class="custom-tooltip-static">Export GeoJSON</span>
                    </div>
                    <div class="relative group flex-1 md:flex-none">
                        <button id="exportShapefileButton" onclick="openExportModal('shp')" class="btn-icon w-full md:w-auto bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 px-3 py-2 rounded-r-md border-t border-b border-r border-indigo-200 dark:border-indigo-800 font-medium disabled:opacity-50 disabled:cursor-not-allowed justify-center md:justify-start">
                            <i data-lucide="package" class="w-4 h-4"></i>
                            <span class="inline">SHP</span>
                        </button>
                        <span class="custom-tooltip-static">Export Shapefile</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- CONTAINER -->
    <div class="main-container">
        <!-- Bouton Toggle: Z-Index 400 (en dessous du header) -->
        <button id="mapInspectorToggle" onclick="toggleAttributePanel()" class="p-2 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-blue-600 border border-slate-200 dark:border-slate-700 shadow-md" title="Basculer l'inspecteur">
            <i id="toggleIcon" data-lucide="panel-right-close" class="w-5 h-5"></i>
        </button>
        
        <div id="map"></div>

        <aside id="attributes-panel">
            <div class="flex border-b border-slate-200 dark:border-slate-800 shrink-0">
                <button onclick="switchSidebarTab('layers')" id="tab-layers" class="flex-1 py-3 text-center font-semibold text-blue-600 border-b-2 border-blue-600 transition-colors">Couches</button>
                <button onclick="switchSidebarTab('attributes')" id="tab-attributes" class="flex-1 py-3 text-center font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">Attributs</button>
                <button onclick="toggleAttributePanel()" class="px-4 text-slate-400 hover:text-slate-600 sm:hidden"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="flex-grow overflow-y-auto p-4 relative">
                
                <!-- VUE COUCHES -->
                <div id="view-layers" class="space-y-4">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Couches actives</h3>
                    <div id="layer-list" class="space-y-2"></div>
                    <div class="text-xs text-slate-400 italic text-center mt-4">Importez des dossiers ou fichiers pour ajouter des couches.</div>
                </div>

                <!-- VUE ATTRIBUTS (SELECTION) -->
                <div id="view-attributes" class="hidden space-y-6">
                    <!-- Placeholder (Visible quand rien n'est sélectionné) -->
                    <div id="attribute-info" class="flex flex-col items-center justify-center h-40 text-center text-slate-400 p-4 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800/50 transition-opacity duration-200">
                        <i data-lucide="mouse-pointer-2" class="w-8 h-8 mb-2 opacity-50"></i>
                        <p class="text-sm font-medium">Sélectionnez une forme sur la carte</p>
                        <p class="text-xs text-slate-500 mt-1">Pour voir et éditer ses attributs</p>
                    </div>

                    <!-- Formulaire d'édition (Visible quand une forme EST sélectionnée) -->
                    <div id="attributes-form" class="hidden space-y-6">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-mono bg-slate-100 dark:bg-slate-800 text-slate-500 px-2 py-1 rounded" id="currentFeatureId">ID</span>
                            <span class="text-xs px-2 py-1 rounded bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-semibold" id="currentFeatureType">Type</span>
                        </div>

                        <!-- Assistant IA -->
                        <div class="bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-slate-800 dark:to-slate-800 rounded-xl border border-indigo-100 dark:border-slate-700 p-4 shadow-sm relative overflow-hidden">
                            <h3 class="font-bold text-indigo-900 dark:text-indigo-300 flex items-center gap-2 mb-3"><i data-lucide="sparkles" class="w-4 h-4 text-purple-600 dark:text-purple-400"></i> Assistant IA</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs font-semibold text-indigo-700 dark:text-indigo-400 mb-1 block">Générer des données :</label>
                                    <select id="aiPromptType" class="w-full text-sm border-indigo-200 dark:border-slate-600 rounded-lg p-2 focus:ring-2 focus:ring-purple-400 focus:outline-none bg-white/80 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                                        <option value="urban">🏙️ Urbanisme & Réel</option>
                                        <option value="environment">🌿 Environnement & Nature</option>
                                        <option value="description">📝 Description Narrative</option>
                                        <option value="rpg">🎲 Données RPG / Fiction</option>
                                    </select>
                                </div>
                                <button id="btnGenerateAI" onclick="generateAIAttributes()" class="w-full btn-icon bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg shadow-md font-medium text-sm transition-all"><i data-lucide="wand-2" class="w-4 h-4"></i> Générer avec Gemini</button>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Mini Éditeur</h3>
                            <div id="inspector-list" class="space-y-0 text-sm border-t border-slate-100 dark:border-slate-800 pt-2"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="footer-attributes" class="hidden p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 shrink-0">
                <button onclick="openTableModal(selectedLayer ? selectedLayer.layerRegistryId : null)" class="w-full btn-icon bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 hover:border-blue-400 hover:text-blue-600 text-slate-700 dark:text-slate-300 py-2 rounded-lg shadow-sm font-medium"><i data-lucide="edit-3" class="w-4 h-4"></i> Éditer dans le tableau</button>
            </div>
        </aside>
    </div>

    <!-- MODAL TABLEAU (Éditeur d'attributs) -->
    <div id="tableModal" class="modal-overlay" onclick="closeTableModal(event)">
        <div class="modal-content w-11/12 max-w-6xl h-[85vh] flex flex-col bg-white dark:bg-slate-900" onclick="event.stopPropagation()">
            
            <div class="flex justify-between items-center px-6 py-4 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h2 id="tableModalTitle" class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <i data-lucide="database" class="w-5 h-5 text-blue-600"></i> Tableau de Données
                    </h2>
                    <p id="tableModalSubtitle" class="text-xs text-slate-400 mt-0.5 font-mono">...</p>
                </div>
                <button onclick="closeTableModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>

            <div class="px-6 py-3 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 flex flex-wrap gap-3 items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <i data-lucide="plus" class="absolute left-2.5 top-2 w-4 h-4 text-slate-400"></i>
                        <input type="text" id="newColumnName" placeholder="Nouvel attribut..." class="pl-9 pr-3 py-1.5 rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm focus:outline-none dark:text-white w-48 shadow-sm">
                    </div>
                    <button onclick="addNewColumn()" class="btn-icon bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-md text-sm font-medium shadow-sm">Ajouter</button>
                </div>
                <div class="text-xs text-slate-400 flex items-center gap-1">
                    <i data-lucide="info" class="w-3 h-3"></i> Modifiez les cellules et cliquez sur Enregistrer.
                </div>
            </div>

            <div class="flex-grow overflow-auto table-container bg-white dark:bg-slate-900">
                <table class="w-full text-left border-collapse data-table" id="attributesTable">
                    <thead></thead>
                    <tbody class="text-slate-600 dark:text-slate-300"></tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 rounded-b-lg flex justify-end gap-3">
                <button onclick="closeTableModal()" class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-800 font-medium">Annuler</button>
                <button onclick="saveTableData()" class="btn-icon bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg shadow-sm font-bold"><i data-lucide="save" class="w-4 h-4"></i> Enregistrer Tout</button>
            </div>
        </div>
    </div>

    <!-- MODAL EXPORT -->
    <div id="exportModal" class="modal-overlay" onclick="closeExportModal(event)">
        <div class="modal-content w-full max-w-lg p-6 bg-white dark:bg-slate-900 flex flex-col max-h-[90vh]" onclick="event.stopPropagation()">
            <div class="flex justify-between items-start mb-6">
                <div class="flex items-center gap-3">
                    <div class="bg-indigo-100 dark:bg-indigo-900/50 p-2 rounded-lg text-indigo-600 dark:text-indigo-400"><i data-lucide="download-cloud" class="w-6 h-6"></i></div>
                    <div><h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Exporter les données</h2><p class="text-sm text-slate-500 dark:text-slate-400" id="exportSubtitle">Configuration de l'export</p></div>
                </div>
                <button onclick="closeExportModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="space-y-4 overflow-y-auto pr-1">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nom du fichier</label>
                    <div class="relative">
                        <i data-lucide="file-text" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                        <input type="text" id="exportFilename" class="w-full pl-9 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none" placeholder="mon_fichier">
                    </div>
                    <p class="text-xs text-slate-400 mt-1 text-right" id="exportExtensionPreview">.geojson</p>
                </div>

                <div class="border-t border-b border-slate-100 dark:border-slate-800 py-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Couches à inclure</label>
                    <div id="exportLayerSelection" class="max-h-[150px] overflow-y-auto border border-slate-200 dark:border-slate-700 rounded-lg p-2 space-y-1 bg-slate-50 dark:bg-slate-800/50"></div>
                </div>

                <div id="shpOptions" class="space-y-4 pt-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Système de Projection (PRJ)</label>
                        <select id="projectionSelect" onchange="handleProjectionChange()" class="w-full p-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none"></select>
                        <textarea id="prjInput" rows="3" class="mt-2 w-full p-2.5 border border-slate-300 dark:border-slate-600 rounded-lg text-xs font-mono bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 focus:outline-none resize-none hidden" placeholder="Collez votre chaîne WKT ici..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Encodage (CPG)</label>
                        <div class="relative">
                            <i data-lucide="type" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                            <input type="text" id="cpgInput" class="w-full pl-9 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none" placeholder="Ex: UTF-8" value="UTF-8">
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button onclick="closeExportModal()" class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg font-medium transition-colors">Annuler</button>
                <button onclick="confirmExport()" class="btn-icon bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg shadow-md font-medium transition-all"><i data-lucide="check" class="w-4 h-4"></i> Exporter</button>
            </div>
        </div>
    </div>

    <!-- MODAL SELECTION IMPORT -->
    <div id="importSelectionModal" class="modal-overlay" onclick="closeImportSelectionModal(event)">
        <div class="modal-content w-full max-w-md p-6 bg-white dark:bg-slate-900" onclick="event.stopPropagation()">
            <div class="flex justify-between items-start mb-4">
                <div class="flex items-center gap-3">
                    <div class="bg-blue-100 dark:bg-blue-900/40 p-2 rounded-lg text-blue-600 dark:text-blue-400"><i data-lucide="folder-search" class="w-6 h-6"></i></div>
                    <div><h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Sélection des couches</h2><p class="text-sm text-slate-500 dark:text-slate-400">Choisissez les fichiers à importer</p></div>
                </div>
                <button onclick="closeImportSelectionModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            
            <div class="flex flex-col gap-2 mb-4">
                <div class="relative"><i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i><input type="text" id="importSearch" onkeyup="filterImportList()" placeholder="Rechercher une couche..." class="w-full pl-9 pr-3 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-white rounded-lg text-sm focus:outline-none focus:border-blue-500 transition-colors"></div>
                <div class="flex items-center gap-2 px-1"><input type="checkbox" id="importSelectAll" onchange="toggleAllImportSelection()" class="w-4 h-4 text-blue-600 rounded border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus:ring-blue-500 cursor-pointer"><label for="importSelectAll" class="text-xs font-medium text-slate-600 dark:text-slate-400 cursor-pointer select-none">Tout sélectionner (visibles)</label></div>
            </div>

            <div class="max-h-[250px] overflow-y-auto border border-slate-100 dark:border-slate-700 rounded-lg p-2 space-y-1" id="importSelectionList"></div>

            <div class="mt-6 flex justify-end gap-3">
                <button onclick="closeImportSelectionModal()" class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg font-medium transition-colors">Annuler</button>
                <button id="btnConfirmImport" onclick="confirmImportSelection()" class="btn-icon bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg shadow-md font-medium transition-all"><i data-lucide="check" class="w-4 h-4"></i> Importer</button>
            </div>
        </div>
    </div>

    <div id="messageContainer" class="fixed top-20 left-1/2 transform -translate-x-1/2 hidden flex items-center gap-3 px-4 py-3 rounded-lg shadow-xl transition-all duration-300 text-white font-medium min-w-[300px] justify-center"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
    <script src="https://unpkg.com/shpjs@latest/dist/shp.js"></script>

    <script type="text/javascript">
        const firebaseAppUrl = "https://www.gstatic.com/firebasejs/11.6.1/firebase-app.js";
        const firebaseAuthUrl = "https://www.gstatic.com/firebasejs/11.6.1/firebase-auth.js";
        const firebaseFirestoreUrl = "https://www.gstatic.com/firebasejs/11.6.1/firebase-firestore.js";

        let map, tileLayer, drawnItems, db, auth;
        let layerRegistry = [], pendingImportCandidates = [];
        let selectedLayer = null, tableState = { columns: [], rows: [] };
        let currentExportFormat = 'geojson', hasZoomedImport = false;
        const apiKey = "";

        // --- TRADUCTION & DESCRIPTION OUTILS ---
        L.drawLocal = {
            draw: {
                toolbar: {
                    actions: { title: 'Annuler le dessin', text: 'Annuler' },
                    finish: { title: 'Terminer le dessin', text: 'Terminer' },
                    undo: { title: 'Supprimer le dernier point', text: 'Effacer' },
                    buttons: { polyline: 'Tracer une Ligne (Route, Chemin)', polygon: 'Tracer un Polygone (Zone, Parcelle)', rectangle: 'Tracer un Rectangle (Zone simple)', circle: 'Tracer un Cercle (Rayon)', marker: 'Placer un Point (Lieu)', circlemarker: 'Placer un Point Cercle' }
                },
                handlers: {
                    circle: { tooltip: { start: 'Cliquez et glissez pour le rayon.' }, radius: 'Rayon' },
                    circlemarker: { tooltip: { start: 'Cliquez pour placer.' } },
                    marker: { tooltip: { start: 'Cliquez pour placer.' } },
                    polygon: { tooltip: { start: 'Cliquez pour commencer.', cont: 'Cliquez pour continuer.', end: 'Cliquez pour fermer.' } },
                    polyline: { error: '<strong>Erreur:</strong> croisement!', tooltip: { start: 'Cliquez pour commencer.', cont: 'Cliquez pour continuer.', end: 'Cliquez sur le dernier point pour finir.' } },
                    rectangle: { tooltip: { start: 'Cliquez et glissez.' } },
                    simpleshape: { tooltip: { end: 'Relâchez pour finir.' } }
                }
            },
            edit: {
                toolbar: {
                    actions: { save: { title: 'Sauvegarder', text: 'Sauvegarder' }, cancel: { title: 'Annuler', text: 'Annuler' }, clearAll: { title: 'Tout effacer', text: 'Vider' } },
                    buttons: { edit: 'Modifier les formes existantes', editDisabled: 'Aucune forme à modifier', remove: 'Supprimer des formes', removeDisabled: 'Aucune forme à supprimer' }
                },
                handlers: {
                    edit: { tooltip: { text: 'Déplacez les points.', subtext: 'Annuler pour revenir.' } },
                    remove: { tooltip: { text: 'Cliquez sur une forme pour la supprimer.' } }
                }
            }
        };

        const PROJECTIONS = {
            "EPSG:4326": { name: "WGS 84 (Standard GPS)", wkt: 'GEOGCS["GCS_WGS_1984",DATUM["D_WGS_1984",SPHEROID["WGS_1984",6378137.0,298.257223563]],PRIMEM["Greenwich",0.0],UNIT["Degree",0.0174532925199433]]' },
            "EPSG:2154": { name: "RGF93 / Lambert-93 (France)", wkt: 'PROJCS["RGF93_Lambert_93",GEOGCS["GCS_RGF93",DATUM["D_RGF_1993",SPHEROID["GRS_1980",6378137.0,298.257222101]],PRIMEM["Greenwich",0.0],UNIT["Degree",0.0174532925199433]],PROJECTION["Lambert_Conformal_Conic"],PARAMETER["False_Easting",700000.0],PARAMETER["False_Northing",6600000.0],PARAMETER["Central_Meridian",3.0],PARAMETER["Standard_Parallel_1",44.0],PARAMETER["Standard_Parallel_2",49.0],PARAMETER["Latitude_Of_Origin",46.5],UNIT["Meter",1.0]]' },
            "EPSG:3857": { name: "Web Mercator (Google/Bing Maps)", wkt: 'PROJCS["WGS_1984_Web_Mercator_Auxiliary_Sphere",GEOGCS["GCS_WGS_1984",DATUM["D_WGS_1984",SPHEROID["WGS_1984",6378137.0,298.257223563]],PRIMEM["Greenwich",0.0],UNIT["Degree",0.0174532925199433]],PROJECTION["Mercator_Auxiliary_Sphere"],PARAMETER["False_Easting",0.0],PARAMETER["False_Northing",0.0],PARAMETER["Central_Meridian",0.0],PARAMETER["Standard_Parallel_1",0.0],PARAMETER["Auxiliary_Sphere_Type",0.0],UNIT["Meter",1.0]]' },
            "custom": { name: "Personnalisé (WKT Brut)", wkt: "" }
        };

        function refreshIcons() { if (typeof lucide !== 'undefined') lucide.createIcons(); }

        function showMessage(text, type = "success") {
            const container = document.getElementById("messageContainer");
            container.innerHTML = "";
            const iconName = type === "error" ? "alert-triangle" : "check-circle-2";
            const bgClass = type === "error" ? "bg-rose-600" : "bg-emerald-600";
            container.innerHTML = `<i data-lucide="${iconName}" class="w-5 h-5"></i> <span>${text}</span>`;
            container.className = `fixed top-20 left-1/2 transform -translate-x-1/2 flex items-center gap-3 px-4 py-3 rounded-lg shadow-xl transition-all duration-300 text-white font-medium min-w-[300px] justify-center z-[2000] ${bgClass}`;
            refreshIcons();
            container.style.opacity = "1";
            container.style.transform = "translate(-50%, 0)";
            setTimeout(() => {
                container.style.opacity = "0";
                container.style.transform = "translate(-50%, -20px)";
                setTimeout(() => container.classList.add("hidden"), 300);
            }, 4000);
        }

        function toggleDarkMode() {
            const html = document.documentElement, isDark = html.classList.toggle('dark'), icon = document.getElementById('darkModeIcon');
            if (isDark) {
                icon.setAttribute('data-lucide', 'sun');
                if(tileLayer) tileLayer.setUrl("https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png");
            } else {
                icon.setAttribute('data-lucide', 'moon');
                if(tileLayer) tileLayer.setUrl("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png");
            }
            refreshIcons();
        }

        // Nouvelle fonction pour le menu mobile
        window.toggleMobileMenu = function() {
            const nav = document.getElementById('nav-menu');
            const icon = document.getElementById('menuIcon');
            
            nav.classList.toggle('hidden');
            
            // Change l'icône menu <-> x
            if (nav.classList.contains('hidden')) {
                icon.setAttribute('data-lucide', 'menu');
            } else {
                icon.setAttribute('data-lucide', 'x');
            }
            refreshIcons();
        };

        window.toggleAttributePanel = function () {
            const container = document.querySelector(".main-container");
            const isHidden = container.classList.toggle("panel-hidden");
            
            // Update Icon
            const toggleIcon = document.getElementById("toggleIcon");
            if (toggleIcon) {
                toggleIcon.setAttribute("data-lucide", isHidden ? "panel-right-open" : "panel-right-close");
                refreshIcons();
            }
            
            // PERSISTENCE: Save state
            localStorage.setItem('geoEditor_panel_hidden', isHidden);

            setTimeout(() => { if (map) map.invalidateSize(); }, 300);
        };

        window.switchSidebarTab = function(tabName) {
            const isLayers = tabName === 'layers';
            document.getElementById('tab-layers').className = isLayers ? 'flex-1 py-3 text-center font-semibold text-blue-600 border-b-2 border-blue-600 transition-colors' : 'flex-1 py-3 text-center font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors';
            document.getElementById('tab-attributes').className = !isLayers ? 'flex-1 py-3 text-center font-semibold text-blue-600 border-b-2 border-blue-600 transition-colors' : 'flex-1 py-3 text-center font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors';
            document.getElementById('view-layers').classList.toggle('hidden', !isLayers);
            document.getElementById('view-attributes').classList.toggle('hidden', isLayers);
            document.getElementById('footer-attributes').classList.toggle('hidden', isLayers);
            if(isLayers) refreshLayerListUI();
        };

        // --- GESTION TOOLTIPS DESSIN AVEC SIDE LABEL ---
        function initMapTooltips() {
            const tooltipEl = document.getElementById('global-map-tooltip');
            const show = (el, text) => {
                tooltipEl.textContent = text;
                tooltipEl.classList.add('visible');
                tooltipEl.classList.add('right-label');
                const rect = el.getBoundingClientRect();
                const top = rect.top + (rect.height / 2) - (tooltipEl.offsetHeight / 2);
                const left = rect.right + 12;
                tooltipEl.style.top = `${top}px`;
                tooltipEl.style.left = `${left}px`;
            };
            const hide = () => {
                tooltipEl.classList.remove('visible');
                tooltipEl.classList.remove('right-label');
            };
            const mapContainer = document.getElementById('map');
            mapContainer.addEventListener('mouseover', (e) => {
                const target = e.target.closest('.leaflet-draw-toolbar a');
                if (target) {
                    const title = target.getAttribute('title');
                    if (title) {
                        target.setAttribute('data-custom-title', title);
                        target.removeAttribute('title');
                    }
                    const text = target.getAttribute('data-custom-title');
                    if (text) show(target, text);
                }
            });
            mapContainer.addEventListener('mouseout', (e) => {
                if (e.target.closest('.leaflet-draw-toolbar a')) hide();
            });
        }

        function registerLayer(name, group, isSystem = false) {
            const id = crypto.randomUUID(), layerData = { id, name, group, visible: true, isSystem };
            layerRegistry.push(layerData);
            if (!map.hasLayer(group) && layerData.visible) map.addLayer(group);
            refreshLayerListUI();
            return layerData;
        }

        function toggleLayerVisibility(id) {
            const layer = layerRegistry.find(l => l.id === id);
            if (!layer) return;
            layer.visible = !layer.visible;
            if (layer.visible) map.addLayer(layer.group); else map.removeLayer(layer.group);
            refreshLayerListUI();
        }

        function deleteLayer(id) {
            const idx = layerRegistry.findIndex(l => l.id === id);
            if (idx === -1) return;
            if (layerRegistry[idx].isSystem) return showMessage("Impossible de supprimer la couche système.", "error");
            if (confirm(`Supprimer la couche "${layerRegistry[idx].name}" ?`)) {
                map.removeLayer(layerRegistry[idx].group);
                layerRegistry.splice(idx, 1);
                refreshLayerListUI();
            }
        }

        function zoomToLayer(id) {
            const layer = layerRegistry.find(l => l.id === id);
            if (layer && layer.group.getLayers().length > 0 && layer.group.getBounds().isValid()) map.fitBounds(layer.group.getBounds());
            else showMessage("Couche vide ou invalide", "error");
        }

        function refreshLayerListUI() {
            const list = document.getElementById('layer-list');
            list.innerHTML = '';
            layerRegistry.forEach(layer => {
                const count = layer.group.getLayers().length, item = document.createElement('div');
                item.className = "flex items-center justify-between p-2 bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-lg shadow-sm hover:shadow-md transition-shadow";
                item.innerHTML = `
                <div class="flex items-center gap-3 overflow-hidden">
                    <button onclick="toggleLayerVisibility('${layer.id}')" class="${layer.visible ? 'text-slate-600 dark:text-slate-300' : 'text-slate-300 dark:text-slate-600'} hover:text-blue-600 transition-colors"><i data-lucide="${layer.visible ? 'eye' : 'eye-off'}" class="w-4 h-4"></i></button>
                    <div class="flex flex-col overflow-hidden"><span class="font-medium text-slate-700 dark:text-slate-200 truncate text-sm" title="${layer.name}">${layer.name}</span><span class="text-[10px] text-slate-400">${count} entités</span></div>
                </div>
                <div class="flex items-center gap-1">
                    <button onclick="openTableModal('${layer.id}')" class="p-1 text-slate-400 hover:text-emerald-600 rounded hover:bg-emerald-50 dark:hover:bg-emerald-900" title="Ouvrir le tableau des attributs"><i data-lucide="table-2" class="w-3 h-3"></i></button>
                    <button onclick="zoomToLayer('${layer.id}')" class="p-1 text-slate-400 hover:text-blue-600 rounded hover:bg-blue-50 dark:hover:bg-blue-900" title="Zoomer"><i data-lucide="maximize-2" class="w-3 h-3"></i></button>
                    ${!layer.isSystem ? `<button onclick="deleteLayer('${layer.id}')" class="p-1 text-slate-400 hover:text-red-600 rounded hover:bg-red-50 dark:hover:bg-red-900" title="Supprimer"><i data-lucide="trash-2" class="w-3 h-3"></i></button>` : ''}
                </div>`;
                list.appendChild(item);
            });
            refreshIcons();
        }

        window.loadFolder = async function() {
            const files = document.getElementById('folderInput').files;
            if (!files || files.length === 0) return;
            pendingImportCandidates = [];
            const groups = {};
            for (let i = 0; i < files.length; i++) {
                const f = files[i], nameParts = f.name.split('.');
                if (nameParts.length < 2 || f.name.startsWith('.')) continue;
                const ext = nameParts.pop().toLowerCase(), base = nameParts.join('.');
                if (['geojson','json'].includes(ext)) pendingImportCandidates.push({ type: 'geojson', name: base, file: f });
                else if (['shp','shx','dbf','prj','cpg'].includes(ext)) {
                    if(!groups[base]) groups[base]=[];
                    groups[base].push(f);
                }
            }
            for (const base in groups) if (groups[base].some(f => f.name.toLowerCase().endsWith('.shp'))) pendingImportCandidates.push({ type: 'shp', name: base, files: groups[base] });
            if (pendingImportCandidates.length === 0) { showMessage("Aucune couche géographique trouvée.", "error"); return; }
            openImportSelectionModal();
            document.getElementById('folderInput').value = "";
        };

        window.openImportSelectionModal = function() {
            document.getElementById('importSearch').value = "";
            document.getElementById('importSelectAll').checked = true;
            const list = document.getElementById('importSelectionList');
            list.innerHTML = '';
            pendingImportCandidates.forEach((c, i) => {
                const div = document.createElement('div');
                div.className = "import-item flex items-center gap-3 p-2 hover:bg-slate-50 dark:hover:bg-slate-800 rounded border border-transparent hover:border-slate-100 dark:hover:border-slate-700 transition-colors";
                div.dataset.name = c.name.toLowerCase();
                const isShp = c.type === 'shp', color = isShp ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30' : 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30';
                div.innerHTML = `<input type="checkbox" id="import_cand_${i}" class="w-4 h-4 text-blue-600 rounded border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus:ring-blue-500 cursor-pointer" checked> <label for="import_cand_${i}" class="flex-grow flex items-center gap-3 cursor-pointer select-none"><div class="p-1.5 rounded-lg ${color}"><i data-lucide="${isShp ? 'package' : 'file-json'}" class="w-5 h-5"></i></div><div class="flex flex-col"><span class="font-medium text-slate-700 dark:text-slate-200 text-sm highlight-text">${c.name}</span><span class="text-[10px] font-bold ${isShp ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-300' : 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-300'} px-1.5 py-0.5 rounded w-fit">${isShp ? 'SHP' : 'JSON'}</span></div></label>`;
                list.appendChild(div);
            });
            refreshIcons();
            document.getElementById('importSelectionModal').classList.add('open');
        };

        window.filterImportList = function() {
            const s = document.getElementById('importSearch').value.toLowerCase();
            document.querySelectorAll('.import-item').forEach(item => item.classList.toggle('hidden', !item.dataset.name.includes(s)));
        };

        window.toggleAllImportSelection = function() {
            const chk = document.getElementById('importSelectAll').checked;
            document.querySelectorAll('.import-item:not(.hidden) input[type="checkbox"]').forEach(cb => cb.checked = chk);
        };

        window.confirmImportSelection = async function() {
            const indices = [];
            document.querySelectorAll('#importSelectionList input[type="checkbox"]').forEach((cb, i) => { if(cb.checked) indices.push(i); });
            if (indices.length === 0) { closeImportSelectionModal(); return; }
            const btn = document.getElementById('btnConfirmImport'), orgTxt = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Importation...`; refreshIcons();
            let count = 0;
            for (const i of indices) {
                try {
                    const c = pendingImportCandidates[i];
                    let geojson;
                    if (c.type === 'shp') {
                        const zip = new JSZip();
                        c.files.forEach(f => zip.file(f.name, f));
                        const buf = await (await zip.generateAsync({type:"blob"})).arrayBuffer();
                        geojson = await shp(buf);
                    } else geojson = JSON.parse(await c.file.text());
                    if (geojson) { addImportedLayer(c.name, geojson); count++; }
                } catch (e) { console.error(e); }
            }
            btn.disabled = false; btn.innerHTML = orgTxt;
            closeImportSelectionModal();
            if (count > 0) { showMessage(`${count} couche(s) importée(s) !`); switchSidebarTab('layers'); } else showMessage("Erreur import.", "error");
        };

        window.closeImportSelectionModal = function(e) {
            if (e && e.target.id !== "importSelectionModal") return;
            document.getElementById('importSelectionModal').classList.remove("open");
        };

        function addImportedLayer(name, geojson) {
            const group = L.geoJSON(geojson, {
                style: f => ({ color: "#" + Math.floor(Math.random()*16777215).toString(16), weight: 2, opacity: 0.8 }),
                pointToLayer: (f, ll) => L.circleMarker(ll, { radius: 6, fillColor: "#ff7800", color: "#000", weight: 1, opacity: 1, fillOpacity: 0.8 }),
                onEachFeature: (f, l) => {
                    l.feature = f;
                    if(!l.feature.id) l.feature.id = crypto.randomUUID(); // FIX: Ensure ID exists on import
                    updateLayerPopup(l);
                    l.on("click", e => { L.DomEvent.stopPropagation(e); selectLayer(e.target); switchSidebarTab('attributes'); });
                }
            });
            registerLayer(name, group);
            if (!hasZoomedImport && group.getBounds().isValid()) { map.fitBounds(group.getBounds()); hasZoomedImport = true; }
        }

        window.loadFile = function () {
            const f = document.getElementById("fileInput").files[0];
            if (!f) return;
            const reader = new FileReader(), base = f.name.split('.')[0], ext = f.name.split('.').pop().toLowerCase();
            reader.onload = async function (e) {
                try {
                    let geojson = ext === "geojson" ? JSON.parse(e.target.result) : await shp(e.target.result);
                    if(geojson) { addImportedLayer(base, geojson); showMessage("Couche ajoutée."); switchSidebarTab('layers'); }
                } catch(err) { showMessage("Erreur: " + err.message, "error"); }
            };
            if(ext==="geojson") reader.readAsText(f); else reader.readAsArrayBuffer(f);
            document.getElementById("fileInput").value = "";
        };

        window.generateAIAttributes = async function() {
            if (!selectedLayer) return showMessage("Sélectionnez une forme", "error");
            const btn = document.getElementById("btnGenerateAI"), orgTxt = btn.innerHTML, type = document.getElementById("aiPromptType").value;
            btn.disabled = true; btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> ...`; refreshIcons(); btn.classList.add("ai-pulse");
            const geom = selectedLayer.feature.geometry?.type || "Unknown", props = JSON.stringify(selectedLayer.feature.properties);
            const query = `Geom: ${geom}. Props: ${props}. Context: ${type}. Generate valid JSON attributes (3-6 keys).`;
            try {
                const res = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=${apiKey}`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ contents: [{ parts: [{ text: query }] }] }) });
                const data = await res.json();
                const newProps = JSON.parse(data.candidates[0].content.parts[0].text.replace(/```json|```/g, '').trim());
                selectedLayer.feature.properties = { ...selectedLayer.feature.properties, ...newProps };
                updateLayerPopup(selectedLayer); selectLayer(selectedLayer); showMessage("Données générées ! ✨");
            } catch (e) { showMessage("Erreur IA", "error"); }
            btn.disabled = false; btn.innerHTML = orgTxt; btn.classList.remove("ai-pulse"); refreshIcons();
        };

        // --- MODIFIED TABLE LOGIC FOR SPECIFIC LAYERS ---
        window.openTableModal = function(targetLayerId = null) {
            let all = [];
            let title = "";
            let subTitle = "";

            // Si un layerId est fourni, on filtre pour cette couche seulement
            if (targetLayerId) {
                const layer = layerRegistry.find(l => l.id === targetLayerId);
                if (!layer) return showMessage("Couche introuvable", "error");
                layer.group.eachLayer(l => { if(l.feature) all.push(l); });
                title = `<span class="truncate">${layer.name}</span>`;
                subTitle = `Édition de la couche (${all.length} entités)`;
            } else {
                // Sinon on prend tout ce qui est visible
                layerRegistry.filter(l => l.visible).forEach(r => r.group.eachLayer(l => {
                    if(l.feature) all.push(l);
                }));
                title = "Global (Toutes les couches)";
                subTitle = `Vue d'ensemble (${all.length} entités)`;
            }

            if (all.length === 0) return showMessage("Aucune donnée à afficher", "error");

            // Ensure standard properties
            all.forEach(l => {
                if(!l.feature) l.feature = { type: "Feature", properties: {} }; // Safety
                if(!l.feature.id) l.feature.id = crypto.randomUUID(); // FIX: Ensure ID exists to prevent substring error
                if(!l.feature.properties) l.feature.properties={};
                if(!l.feature.properties.geom) l.feature.properties.geom = l.feature.geometry?.type || "Unknown";
            });

            // Set modal info
            document.getElementById("tableModalTitle").innerHTML = `<i data-lucide="table-2" class="w-5 h-5 text-blue-600"></i> ${title}`;
            document.getElementById("tableModalSubtitle").textContent = subTitle;

            const keys = new Set();
            all.forEach(l => Object.keys(l.feature.properties).forEach(k => keys.add(k)));
            
            tableState.columns = Array.from(keys).sort();
            tableState.rows = all;
            
            renderTable();
            document.getElementById("tableModal").classList.add("open");
            refreshIcons();
        };

        window.closeTableModal = function(e) {
            if(e && e.target.id !== "tableModal") return;
            document.getElementById("tableModal").classList.remove("open");
        };

        window.renderTable = function() {
            const thead = document.querySelector("#attributesTable thead"), tbody = document.querySelector("#attributesTable tbody");
            thead.innerHTML = ""; tbody.innerHTML = "";

            // HEADERS
            const hRow = document.createElement("tr");
            
            // ID Header
            const thId = document.createElement("th");
            thId.className = "p-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 w-24 text-center text-slate-600 dark:text-slate-400";
            thId.innerHTML = "ID";
            hRow.appendChild(thId);

            // Columns Headers
            tableState.columns.forEach(k => {
                const th = document.createElement("th");
                th.className = "p-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 min-w-[180px] group relative";
                
                const label = document.createElement("span");
                label.className = "font-semibold text-slate-700 dark:text-slate-300";
                label.textContent = k;
                
                th.appendChild(label);

                // Delete button for column (except 'geom')
                if (k !== 'geom') {
                    const delBtn = document.createElement("button");
                    delBtn.className = "absolute right-2 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 p-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-red-100 dark:hover:bg-red-900 hover:text-red-600 transition-all";
                    delBtn.innerHTML = `<i data-lucide="x" class="w-3 h-3"></i>`;
                    delBtn.title = "Supprimer la colonne";
                    delBtn.onclick = () => deleteColumn(k);
                    th.appendChild(delBtn);
                }

                hRow.appendChild(th);
            });
            thead.appendChild(hRow);

            // BODY
            tableState.rows.forEach((l, i) => {
                const tr = document.createElement("tr");
                tr.className = "hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors border-b border-slate-100 dark:border-slate-800/50";
                
                const tdId = document.createElement("td");
                tdId.className = "p-2 text-center border-r border-slate-100 dark:border-slate-800";
                const idStr = l.feature.id ? l.feature.id.toString() : "----";
                tdId.innerHTML = `<span class="font-mono text-xs text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">${idStr.substring(0,4)}</span>`;
                tr.appendChild(tdId);

                tableState.columns.forEach(k => {
                    const td = document.createElement("td");
                    td.className = "p-0 border-r border-slate-100 dark:border-slate-800 relative";
                    const inp = document.createElement("input");
                    inp.type = "text";
                    inp.className = "w-full h-full p-3 bg-transparent border-none focus:ring-0 text-sm text-slate-800 dark:text-slate-200 focus:bg-blue-50 dark:focus:bg-blue-900/20";
                    inp.value = l.feature.properties[k] ?? "";
                    inp.dataset.rowIndex = i;
                    inp.dataset.key = k;
                    
                    if(k==='geom') {
                        inp.readOnly=true;
                        inp.className+=" text-slate-400 dark:text-slate-500 italic bg-slate-50/50 dark:bg-slate-800/30 cursor-not-allowed";
                    }
                    
                    td.appendChild(inp);
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
            refreshIcons();
        };

        window.saveTableData = function() {
            document.querySelectorAll("#attributesTable input").forEach(inp => {
                const r = tableState.rows[parseInt(inp.dataset.rowIndex)];
                if(r && inp.dataset.key !== 'geom') {
                    // Only update if value changed or if property didn't exist
                    const val = inp.value.trim();
                    if (val !== "") {
                        r.feature.properties[inp.dataset.key] = val;
                    } else {
                        // If empty, maybe delete? For now keep as empty string
                        r.feature.properties[inp.dataset.key] = "";
                    }
                }
            });
            
            tableState.rows.forEach(l => updateLayerPopup(l));
            if(selectedLayer) selectLayer(selectedLayer);
            
            showMessage("Modifications enregistrées");
            closeTableModal();
        };

        window.addNewColumn = function() {
            const n = document.getElementById("newColumnName").value.trim();
            if(n && !tableState.columns.includes(n)) {
                tableState.columns.push(n);
                renderTable();
                document.getElementById("newColumnName").value = "";
            } else {
                showMessage("Nom invalide ou existant", "error");
            }
        };

        window.deleteColumn = function(colName) {
            if (!confirm(`Supprimer la colonne "${colName}" ?`)) return;
            
            // Remove from columns list
            tableState.columns = tableState.columns.filter(c => c !== colName);
            
            // Optional: Mark for deletion in actual features immediately? 
            // For now, we just remove it from view, but let's really remove it from features to be a true editor
            tableState.rows.forEach(r => {
                if (r.feature.properties[colName] !== undefined) {
                    delete r.feature.properties[colName];
                }
            });

            renderTable();
        };

        window.handleProjectionChange = function() {
            const s = document.getElementById('projectionSelect'), t = document.getElementById('prjInput');
            if(s.value === 'custom') t.classList.remove('hidden'); else { t.classList.add('hidden'); t.value = PROJECTIONS[s.value].wkt; }
        };

        window.openExportModal = function(format) {
            if (!layerRegistry.some(l => l.visible && l.group.getLayers().length > 0)) return showMessage("Rien à exporter", "error");
            currentExportFormat = format;
            const s = document.getElementById('projectionSelect');
            s.innerHTML = '';
            for (const [k, v] of Object.entries(PROJECTIONS)) {
                const o = document.createElement('option'); o.value = k; o.text = v.name; s.appendChild(o);
            }
            s.value = "EPSG:4326"; handleProjectionChange();
            document.getElementById('shpOptions').style.display = format === 'shp' ? 'block' : 'none';
            document.getElementById('exportFilename').value = `map_data_${new Date().toISOString().slice(0, 10)}`;
            document.getElementById('exportExtensionPreview').textContent = format === 'geojson' ? '.geojson' : '.zip';
            
            const list = document.getElementById('exportLayerSelection'); list.innerHTML = '';
            layerRegistry.forEach((l, i) => {
                if(l.group.getLayers().length === 0) return;
                const div = document.createElement('div');
                div.className = "flex items-center gap-2 p-1 hover:bg-slate-100 dark:hover:bg-slate-700 rounded";
                div.innerHTML = `<input type="checkbox" id="exp_lay_${i}" value="${l.id}" class="w-4 h-4 rounded text-indigo-600 border-gray-300 focus:ring-indigo-500" ${l.visible ? 'checked' : ''}> <label for="exp_lay_${i}" class="text-sm text-slate-700 dark:text-slate-300 select-none flex-grow cursor-pointer truncate">${l.name} <span class="text-xs text-slate-400">(${l.group.getLayers().length})</span></label>`;
                list.appendChild(div);
            });
            document.getElementById("exportModal").classList.add("open");
        };

        window.closeExportModal = function(e) {
            if(e && e.target.id !== "exportModal") return;
            document.getElementById("exportModal").classList.remove("open");
        };

        window.confirmExport = function() {
            const checks = document.querySelectorAll('#exportLayerSelection input:checked');
            if(checks.length === 0) return showMessage("Sélectionnez au moins une couche", "error");
            const ids = Array.from(checks).map(c => c.value);
            exportData(currentExportFormat, { filename: document.getElementById('exportFilename').value.trim() || "export", prj: document.getElementById('prjInput').value, cpg: document.getElementById('cpgInput').value, layerIds: ids });
            closeExportModal();
        };

        window.exportData = async function (format, config = {}) {
            let feats = [];
            const targetLayers = config.layerIds ? layerRegistry.filter(l => config.layerIds.includes(l.id)) : layerRegistry.filter(l => l.visible);
            targetLayers.forEach(l => {
                const gj = l.group.toGeoJSON();
                if (gj.type === 'FeatureCollection') feats = feats.concat(gj.features); else feats.push(gj);
            });
            if (feats.length === 0) return showMessage("Rien à exporter", "error");
            const coll = { type: "FeatureCollection", features: feats };
            if (format === "geojson") {
                downloadBlob(new Blob([JSON.stringify(coll, null, 2)], { type: "application/json" }), `${config.filename}.geojson`);
            } else {
                const v = feats.filter(f => f.geometry && f.geometry.type !== 'GeometryCollection');
                if (v.length === 0) return showMessage("Géométrie invalide pour SHP", "error");
                const san = p => {
                    const r = {}; for(const k in p) r[k.substring(0,10).replace(/[^a-z0-9]/gi,'')] = typeof p[k]==='object'?JSON.stringify(p[k]):String(p[k]??"");
                    return r;
                };
                try {
                    const zip = await shpwrite.zip({ type: "FeatureCollection", features: v.map(f => ({ ...f, properties: san(f.properties) })) }, { folder: config.filename, types: { point: "points", polygon: "polygons", line: "lines" }, prj: config.prj, cpg: config.cpg, outputType: 'blob' });
                    downloadBlob(zip, `${config.filename}.zip`);
                } catch (e) { showMessage("Erreur Export SHP", "error"); }
            }
            showMessage("Export terminé");
        };

        function downloadBlob(b, n) {
            const u = URL.createObjectURL(b), a = document.createElement("a"); a.href = u; a.download = n; document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(u);
        }
        
        function clearSelection() {
            if (selectedLayer && selectedLayer.getElement()) selectedLayer.getElement().classList.remove("selected-feature-highlight");
            selectedLayer = null;
            // Reset UI to placeholder
            document.getElementById("attribute-info").classList.remove("hidden");
            document.getElementById("attributes-form").classList.add("hidden");
        }

        function selectLayer(l) {
            if (selectedLayer && selectedLayer.getElement()) selectedLayer.getElement().classList.remove("selected-feature-highlight");
            selectedLayer = l;
            if(!l.layerRegistryId) {
                const parent = layerRegistry.find(reg => reg.group.hasLayer(l));
                if(parent) l.layerRegistryId = parent.id;
            }
            
            if (l.getElement()) l.getElement().classList.add("selected-feature-highlight");
            if (!l.feature) l.feature = { type: "Feature", properties: {} };
            if (!l.feature.id) l.feature.id = crypto.randomUUID();

            // UPDATE UI VISIBILITY
            document.getElementById("attribute-info").classList.add("hidden");
            document.getElementById("attributes-form").classList.remove("hidden");

            document.getElementById("currentFeatureId").textContent = l.feature.id.substring(0, 8);
            document.getElementById("currentFeatureType").textContent = l.feature.geometry?.type || "Feature";
            
            renderMiniEditor(l);
            refreshIcons();
        }
        
        // Nouvelle fonction pour afficher les champs éditables
        function renderMiniEditor(layer) {
            const lst = document.getElementById("inspector-list");
            lst.innerHTML = "";
            const props = layer.feature.properties;
            const keys = Object.keys(props);

            if (keys.length === 0) {
                lst.innerHTML = `<div class="text-slate-400 italic text-center py-4 flex flex-col items-center gap-2"><i data-lucide="list-x" class="w-5 h-5"></i><span>Aucun attribut</span></div>`;
            } else {
                keys.forEach(k => {
                    const div = document.createElement("div");
                    div.className = "flex flex-col gap-1 pb-3 border-b border-slate-100 dark:border-slate-800/50 last:border-0";
                    
                    const labelRow = document.createElement("div");
                    labelRow.className = "flex justify-between items-center";
                    
                    const label = document.createElement("label");
                    label.className = "text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide";
                    label.textContent = k;
                    
                    // Bouton suppression attribut
                    const delBtn = document.createElement("button");
                    delBtn.className = "text-slate-300 hover:text-red-500 transition-colors";
                    delBtn.innerHTML = `<i data-lucide="x" class="w-3 h-3"></i>`;
                    delBtn.title = "Supprimer cet attribut";
                    delBtn.onclick = () => {
                        delete layer.feature.properties[k];
                        renderMiniEditor(layer);
                        updateLayerPopup(layer);
                    };

                    labelRow.appendChild(label);
                    labelRow.appendChild(delBtn);
                    
                    const input = document.createElement("input");
                    input.type = "text";
                    input.className = "w-full text-sm px-2 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition-all text-slate-700 dark:text-slate-200";
                    input.value = props[k];
                    
                    // Mise à jour live
                    input.oninput = function() {
                         layer.feature.properties[k] = this.value;
                         updateLayerPopup(layer);
                    };

                    div.appendChild(labelRow);
                    div.appendChild(input);
                    lst.appendChild(div);
                });
            }
            
            // Bouton "Ajouter un attribut"
            const addDiv = document.createElement("div");
            addDiv.className = "pt-2 flex gap-2";
            addDiv.innerHTML = `
                <input type="text" id="newAttrKey" placeholder="Nom..." class="flex-1 text-xs px-2 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
                <button id="btnAddAttr" class="bg-slate-100 dark:bg-slate-800 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-slate-600 dark:text-slate-300 hover:text-blue-600 px-2 py-1 rounded border border-slate-200 dark:border-slate-700 transition-colors"><i data-lucide="plus" class="w-3 h-3"></i></button>
            `;
            lst.appendChild(addDiv);
            
            setTimeout(() => {
                const btn = document.getElementById("btnAddAttr");
                const keyInput = document.getElementById("newAttrKey");
                if(btn && keyInput) {
                    btn.onclick = () => {
                        const newKey = keyInput.value.trim();
                        if(newKey && !props.hasOwnProperty(newKey)) {
                            layer.feature.properties[newKey] = ""; // Init empty
                            renderMiniEditor(layer);
                            updateLayerPopup(layer);
                        }
                    };
                }
                refreshIcons();
            }, 0);
        }

        function updateLayerPopup(l) {
            if (!l.feature?.properties) return;
            let h = `<div class="font-sans text-sm min-w-[150px] text-slate-800 dark:text-slate-800">`;
            let c = 0;
            for (const k in l.feature.properties) {
                c++; h += `<div class="flex justify-between border-b border-gray-100 py-1 last:border-0"><span class="font-bold mr-2">${k}:</span><span>${l.feature.properties[k]}</span></div>`;
            }
            if (c === 0) h += `<span class="italic text-gray-400">Sans attributs</span>`;
            h += `</div>`;
            if (l.getPopup()) l.unbindPopup();
            l.bindPopup(h);
        }

        function initMap() {
            map = L.map("map", { zoomControl: false }).setView([48.8566, 2.3522], 10);
            L.control.zoom({ position: 'bottomright' }).addTo(map);
            tileLayer = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { attribution: '&copy; OpenStreetMap' }).addTo(map);
            drawnItems = new L.FeatureGroup();
            registerLayer("Couche Dessin (Défaut)", drawnItems, true);
            
            // Initialiser les contrôles de dessin. On ajuste la position si mobile.
            const isMobile = window.innerWidth <= 640;
            const drawPosition = isMobile ? 'bottomleft' : 'topleft';
            
            const d = new L.Control.Draw({ 
                edit: { featureGroup: drawnItems }, 
                draw: { polygon: { showArea: true }, polyline: true, rectangle: true, circle: true, marker: true, circlemarker: true },
                position: drawPosition 
            });
            map.addControl(d);

            map.on(L.Draw.Event.CREATED, e => {
                const l = e.layer;
                l.feature = l.feature || l.toGeoJSON();
                if(!l.feature.properties) l.feature.properties={nom: "Forme", date: new Date().toISOString().slice(0,10)};
                if(!l.feature.id) l.feature.id=crypto.randomUUID();
                updateLayerPopup(l);
                drawnItems.addLayer(l);
                refreshLayerListUI();
                selectLayer(l);
                switchSidebarTab('attributes');
            });
            
            map.on("draw:edited", () => refreshLayerListUI());
            map.on("draw:deleted", () => { 
                refreshLayerListUI(); 
                if(selectedLayer && !drawnItems.hasLayer(selectedLayer)) {
                    clearSelection();
                }
            });
            
            // Deselect on background click
            map.on('click', (e) => {
                 // Simple check: if the click didn't originate from a layer (which stops prop), it's the map
                 clearSelection();
            });

            drawnItems.on("click", e => { 
                L.DomEvent.stopPropagation(e); // Empêche le map.onclick de se déclencher
                selectLayer(e.layer); 
                switchSidebarTab('attributes'); 
            });
            
            map.on("draw:editstart", () => { if(selectedLayer?.getElement()) selectedLayer.getElement().classList.remove('selected-feature-highlight'); });
            initMapTooltips();
        }
        
        async function setupFirebase() {
            await loadFirebaseScripts();
            if(typeof initializeApp !== 'undefined') {
                try {
                    const app=initializeApp(JSON.parse(__firebase_config||'{}')), auth=getAuth(app);
                    onAuthStateChanged(auth, async u => { if(!u) await signInAnonymously(auth); });
                } catch(e){}
            }
        }

        function loadFirebaseScripts() {
            return Promise.all([import(firebaseAppUrl), import(firebaseAuthUrl), import(firebaseFirestoreUrl)]).then(([a,b,c])=>{
                window.initializeApp=a.initializeApp; window.getAuth=b.getAuth; window.signInAnonymously=b.signInAnonymously; window.onAuthStateChanged=b.onAuthStateChanged;
            });
        }

        window.onload = function () { 
            initMap(); 
            setupFirebase(); 
            refreshIcons();
            
            // RESTORE PANEL STATE
            const savedHidden = localStorage.getItem('geoEditor_panel_hidden');
            if (savedHidden === 'true') {
                document.querySelector(".main-container").classList.add("panel-hidden");
                const toggleIcon = document.getElementById("toggleIcon");
                if (toggleIcon) toggleIcon.setAttribute("data-lucide", "panel-right-open");
                refreshIcons();
                setTimeout(() => { if (map) map.invalidateSize(); }, 300);
            }
        };
    </script>
</body>
</html>