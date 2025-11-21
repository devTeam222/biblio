<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, interactive-widget=resizes-content" />
    <title>Créer une Zone d'Étude - GeoLib</title>
    
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
    <link rel="stylesheet" href="/app/css/editor-theme.css">
    
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

<!-- HEADER -->
<header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 shadow-sm z-[1100] shrink-0 relative">
    <!-- Logo & Titre -->
    <div class="flex items-center gap-3">
        <div class="bg-blue-600 text-white p-1.5 rounded-lg shadow-sm">
            <i data-lucide="map" class="w-6 h-6"></i>
        </div>
        <h1 class="text-lg font-bold tracking-tight hidden sm:block">GeoLib <span class="text-slate-400 font-normal text-sm">Éditeur</span></h1>
        <h1 class="text-lg font-bold tracking-tight sm:hidden">GeoLib</h1>
    </div>
    
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
                    <span class="custom-tooltip-static">Apparence</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="/" class="inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-transparent hover:border-slate-200 dark:hover:border-slate-700 text-sm font-semibold text-slate-600 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <i data-lucide="home" class="w-4 h-4"></i>
                    <span class="hidden md:inline">Accueil</span>
                </a>
                <span id="userNameChip" class="text-xs font-semibold text-slate-400 hidden md:inline">Invité</span>
                <button id="userAvatar" class="w-9 h-9 rounded-full border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-200 bg-white dark:bg-slate-800 shadow-sm">
                    <i data-lucide="user-round" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
        
        <div class="hidden md:block h-6 w-px bg-slate-300 dark:bg-slate-700 mx-1"></div>
        
        <!-- Inputs cachés -->
        <input type="file" id="fileInput" accept=".geojson, .zip" class="hidden" />
        <input type="file" id="folderInput" webkitdirectory directory multiple class="hidden" />
        
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
        
        <div class="hidden md:block h-6 w-px bg-slate-300 dark:bg-slate-700 mx-1"></div>
        
        <!-- Bouton Tableau -->
        <div class="relative group">
            <button id="btnOpenTableGlobal" class="btn-icon w-full md:w-auto bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700 font-medium justify-center md:justify-start">
                <i data-lucide="table" class="w-4 h-4"></i>
                <span class="inline">Données</span>
            </button>
            <span class="custom-tooltip-static">Voir le tableau global</span>
        </div>
        
        <div class="md:hidden h-px w-full bg-slate-100 dark:bg-slate-800"></div>
        
        <!-- Groupe Export -->
        <div class="flex flex-col gap-2 md:gap-0">
            <span class="md:hidden text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Exporter</span>
            <div class="flex rounded-md shadow-sm" role="group">
                <div class="relative group flex-1 md:flex-none">
                    <button id="exportGeoJSONButton" class="btn-icon w-full md:w-auto bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 px-3 py-2 rounded-l-md border border-indigo-200 dark:border-indigo-800 font-medium disabled:opacity-50 disabled:cursor-not-allowed justify-center md:justify-start">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        <span class="inline">GeoJSON</span>
                    </button>
                    <span class="custom-tooltip-static">Export GeoJSON</span>
                </div>
                <div class="relative group flex-1 md:flex-none">
                    <button id="exportShapefileButton" class="btn-icon w-full md:w-auto bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 px-3 py-2 rounded-r-md border-t border-b border-r border-indigo-200 dark:border-indigo-800 font-medium disabled:opacity-50 disabled:cursor-not-allowed justify-center md:justify-start">
                        <i data-lucide="package" class="w-4 h-4"></i>
                        <span class="inline">SHP</span>
                    </button>
                    <span class="custom-tooltip-static">Export Shapefile</span>
                </div>
            </div>
        </div>
        
        <!-- Bouton Enregistrer Zone d'Étude -->
        <div class="hidden md:block h-6 w-px bg-slate-300 dark:bg-slate-700 mx-1"></div>
        <div class="relative group">
            <button id="btnOpenSaveStudyArea" class="btn-icon w-full md:w-auto bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-md border border-emerald-700 font-medium justify-center md:justify-start shadow-md">
                <i data-lucide="save" class="w-4 h-4"></i>
                <span class="inline">Enregistrer</span>
            </button>
            <span class="custom-tooltip-static">Enregistrer la zone d'étude</span>
        </div>
    </div>
    
    <!-- Bouton Hamburger (Mobile) -->
    <button id="btnMobileMenu" class="md:hidden p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 focus:outline-none">
        <i id="menuIcon" data-lucide="menu" class="w-6 h-6"></i>
    </button>
</header>

<!-- CONTAINER -->
<div class="main-container">
    <!-- Bouton Toggle -->
    <button id="mapInspectorToggle" class="p-2 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-blue-600 border border-slate-200 dark:border-slate-700 shadow-md" title="Basculer l'inspecteur">
        <i id="toggleIcon" data-lucide="panel-right-close" class="w-5 h-5"></i>
    </button>
    
    <div id="map"></div>
    
    <aside id="attributes-panel">
        <div class="flex border-b border-slate-200 dark:border-slate-800 shrink-0">
            <button id="btnTabLayers" class="flex-1 py-3 text-center font-semibold text-blue-600 border-b-2 border-blue-600 transition-colors">Couches</button>
            <button id="btnTabAttributes" class="flex-1 py-3 text-center font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">Attributs</button>
            <button id="btnCloseAttributesPanelMobile" class="px-4 text-slate-400 hover:text-slate-600 sm:hidden"><i data-lucide="x" class="w-5 h-5"></i></button>
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
                <!-- Placeholder -->
                <div id="attribute-info" class="flex flex-col items-center justify-center h-40 text-center text-slate-400 p-4 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800/50 transition-opacity duration-200">
                    <i data-lucide="mouse-pointer-2" class="w-8 h-8 mb-2 opacity-50"></i>
                    <p class="text-sm font-medium">Sélectionnez une forme sur la carte</p>
                    <p class="text-xs text-slate-500 mt-1">Pour voir et éditer ses attributs</p>
                </div>
                
                <!-- Formulaire d'édition -->
                <div id="attributes-form" class="hidden space-y-6">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-mono bg-slate-100 dark:bg-slate-800 text-slate-500 px-2 py-1 rounded" id="currentFeatureId">ID</span>
                        <span class="text-xs px-2 py-1 rounded bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-semibold" id="currentFeatureType">Type</span>
                    </div>
                
                <div>
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Mini Éditeur</h3>
                        <div id="inspector-list" class="space-y-0 text-sm border-t border-slate-100 dark:border-slate-800 pt-2"></div>
                    </div>
                </div>
            </div>
                </div>

        <div id="footer-attributes" class="hidden p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 shrink-0">
            <button id="btnOpenTableSelected" class="w-full btn-icon bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 hover:border-blue-400 hover:text-blue-600 text-slate-700 dark:text-slate-300 py-2 rounded-lg shadow-sm font-medium">
                <i data-lucide="edit-3" class="w-4 h-4"></i> Éditer dans le tableau
            </button>
        </div>
    </aside>
</div>

<!-- MODAL ENREGISTRER ZONE D'ÉTUDE -->
<div id="saveStudyAreaModal" class="modal-overlay">
    <div id="saveStudyAreaModalContent" class="modal-content w-full max-w-lg p-6 bg-white dark:bg-slate-900 flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-start mb-6">
            <div class="flex items-center gap-3">
                <div class="bg-emerald-100 dark:bg-emerald-900/50 p-2 rounded-lg text-emerald-600 dark:text-emerald-400">
                    <i data-lucide="save" class="w-6 h-6"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Enregistrer la Zone d'Étude</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Sauvegarder votre travail</p>
                </div>
            </div>
            <button id="btnCloseSaveStudyAreaModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
                </div>
                
        <form id="saveStudyAreaForm" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nom de la Zone d'Étude *</label>
                <input type="text" id="studyAreaName" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Ex: Bassin du Fleuve Congo">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
                <textarea id="studyAreaDescription" rows="3" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Description détaillée de la zone..."></textarea>
            </div>
            
            <div class="text-xs text-slate-500 dark:text-slate-400">
                <i data-lucide="info" class="w-3 h-3 inline"></i> Les données géographiques seront sauvegardées en format GeoJSON.
            </div>
        </form>
        
        <div class="mt-6 flex justify-end gap-3">
            <button id="btnCancelSaveStudyArea" class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg font-medium transition-colors">Annuler</button>
            <button id="btnConfirmSaveStudyArea" class="btn-icon bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-lg shadow-md font-medium transition-all">
                <i data-lucide="check" class="w-4 h-4"></i> Enregistrer
            </button>
        </div>
    </div>
                </div>

<!-- MODAL TABLEAU (Éditeur d'attributs) -->
<div id="tableModal" class="modal-overlay">
    <div id="tableModalContent" class="modal-content w-11/12 max-w-6xl h-[85vh] flex flex-col bg-white dark:bg-slate-900">
        <div class="flex justify-between items-center px-6 py-4 border-b border-slate-100 dark:border-slate-800">
            <div>
                <h2 id="tableModalTitle" class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <i data-lucide="database" class="w-5 h-5 text-blue-600"></i> Tableau de Données
                </h2>
                <p id="tableModalSubtitle" class="text-xs text-slate-400 mt-0.5 font-mono">...</p>
                        </div>
            <button id="btnCloseTableModalTop" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
        </div>
        
        <div class="px-6 py-3 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 flex flex-wrap gap-3 items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="relative">
                    <i data-lucide="plus" class="absolute left-2.5 top-2 w-4 h-4 text-slate-400"></i>
                    <input type="text" id="newColumnName" placeholder="Nouvel attribut..." class="pl-9 pr-3 py-1.5 rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm focus:outline-none dark:text-white w-48 shadow-sm">
                </div>
                <button id="btnAddNewColumn" class="btn-icon bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-md text-sm font-medium shadow-sm">Ajouter</button>
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
            <button id="btnCloseTableModalBottom" class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-800 font-medium">Annuler</button>
            <button id="btnSaveTableData" class="btn-icon bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg shadow-sm font-bold">
                <i data-lucide="save" class="w-4 h-4"></i> Enregistrer Tout
            </button>
        </div>
    </div>
</div>

<!-- MODAL EXPORT -->
<div id="exportModal" class="modal-overlay">
    <div id="exportModalContent" class="modal-content w-full max-w-lg p-6 bg-white dark:bg-slate-900 flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-start mb-6">
            <div class="flex items-center gap-3">
                <div class="bg-indigo-100 dark:bg-indigo-900/50 p-2 rounded-lg text-indigo-600 dark:text-indigo-400">
                    <i data-lucide="download-cloud" class="w-6 h-6"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Exporter les données</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400" id="exportSubtitle">Configuration de l'export</p>
                </div>
            </div>
            <button id="btnCloseExportModalTop" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
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
            <button id="btnCloseExportModalBottom" class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg font-medium transition-colors">Annuler</button>
            <button id="btnConfirmExport" class="btn-icon bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg shadow-md font-medium transition-all">
                <i data-lucide="check" class="w-4 h-4"></i> Exporter
                </button>
        </div>
    </div>
</div>

<!-- MODAL SELECTION IMPORT -->
<div id="importSelectionModal" class="modal-overlay">
    <div id="importSelectionModalContent" class="modal-content w-full max-w-md p-6 bg-white dark:bg-slate-900">
        <div class="flex justify-between items-start mb-4">
            <div class="flex items-center gap-3">
                <div class="bg-blue-100 dark:bg-blue-900/40 p-2 rounded-lg text-blue-600 dark:text-blue-400">
                    <i data-lucide="folder-search" class="w-6 h-6"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Sélection des couches</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Choisissez les fichiers à importer</p>
                </div>
            </div>
            <button id="btnCloseImportModalTop" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <i data-lucide="x" class="w-5 h-5"></i>
                </button>
        </div>
        
        <div class="flex flex-col gap-2 mb-4">
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                <input type="text" id="importSearch" placeholder="Rechercher une couche..." class="w-full pl-9 pr-3 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-white rounded-lg text-sm focus:outline-none focus:border-blue-500 transition-colors">
            </div>
            <div class="flex items-center gap-2 px-1">
                <input type="checkbox" id="importSelectAll" class="w-4 h-4 text-blue-600 rounded border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus:ring-blue-500 cursor-pointer">
                <label for="importSelectAll" class="text-xs font-medium text-slate-600 dark:text-slate-400 cursor-pointer select-none">Tout sélectionner (visibles)</label>
            </div>
        </div>
        
        <div class="max-h-[250px] overflow-y-auto border border-slate-100 dark:border-slate-700 rounded-lg p-2 space-y-1" id="importSelectionList"></div>
        
        <div class="mt-6 flex justify-end gap-3">
            <button id="btnCloseImportModalBottom" class="px-4 py-2 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg font-medium transition-colors">Annuler</button>
            <button id="btnConfirmImport" class="btn-icon bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg shadow-md font-medium transition-all">
                <i data-lucide="check" class="w-4 h-4"></i> Importer
            </button>
        </div>
    </div>
</div>

<div id="messageContainer" class="fixed top-20 left-1/2 transform -translate-x-1/2 hidden items-center gap-3 px-4 py-3 rounded-lg shadow-xl transition-all duration-300 text-white font-medium min-w-[300px] justify-center"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script src="https://unpkg.com/shpjs@latest/dist/shp.js"></script>
<script type="module" src="/app/js/auth/admin/study-area-editor.js"></script>
</body>
</html>
