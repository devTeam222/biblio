import { apiClient } from "../../util/ocho-api.js";
import { updateNavBar, isAuth } from "../../util/utils.js";

const THEME_STORAGE_KEY = "geolib-theme";
const LIGHT_TILE_URL = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
const DARK_TILE_URL = "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png";

applyStoredTheme();

// Variables globales de l'éditeur
let map, tileLayer, drawnItems, db, auth;
let layerRegistry = [], pendingImportCandidates = [];
let selectedLayer = null, tableState = { columns: [], rows: [] };
let currentExportFormat = 'geojson', hasZoomedImport = false;

// --- TRADUCTION & DESCRIPTION OUTILS ---
L.drawLocal = {
    draw: {
        toolbar: {
            actions: {
                title: 'Annuler le dessin',
                text: 'Annuler'
            },
            finish: {
                title: 'Terminer le dessin',
                text: 'Terminer'
            },
            undo: {
                title: 'Supprimer le dernier point',
                text: 'Effacer'
            },
            buttons: {
                polyline: 'Tracer une Ligne (Route, Chemin)',
                polygon: 'Tracer un Polygone (Zone, Parcelle)',
                rectangle: 'Tracer un Rectangle (Zone simple)',
                circle: 'Tracer un Cercle (Rayon)',
                marker: 'Placer un Point (Lieu)',
                circlemarker: 'Placer un Point Cercle'
            }
        },
        handlers: {
            circle: {
                tooltip: {
                    start: 'Cliquez et glissez pour le rayon.'
                },
                radius: 'Rayon'
            },
            circlemarker: {
                tooltip: {
                    start: 'Cliquez pour placer.'
                }
            },
            marker: {
                tooltip: {
                    start: 'Cliquez pour placer.'
                }
            },
            polygon: {
                tooltip: {
                    start: 'Cliquez pour commencer.',
                    cont: 'Cliquez pour continuer.',
                    end: 'Cliquez pour fermer.'
                }
            },
            polyline: {
                error: '<strong>Erreur:</strong> croisement!',
                tooltip: {
                    start: 'Cliquez pour commencer.',
                    cont: 'Cliquez pour continuer.',
                    end: 'Cliquez sur le dernier point pour finir.'
                }
            },
            rectangle: {
                tooltip: {
                    start: 'Cliquez et glissez.'
                }
            },
            simpleshape: {
                tooltip: {
                    end: 'Relâchez pour finir.'
                }
            }
        }
    },
    edit: {
        toolbar: {
            actions: {
                save: {
                    title: 'Sauvegarder',
                    text: 'Sauvegarder'
                },
                cancel: {
                    title: 'Annuler',
                    text: 'Annuler'
                },
                clearAll: {
                    title: 'Tout effacer',
                    text: 'Vider'
                }
            },
            buttons: {
                edit: 'Modifier les formes existantes',
                editDisabled: 'Aucune forme à modifier',
                remove: 'Supprimer des formes',
                removeDisabled: 'Aucune forme à supprimer'
            }
        },
        handlers: {
            edit: {
                tooltip: {
                    text: 'Déplacez les points.',
                    subtext: 'Annuler pour revenir.'
                }
            },
            remove: {
                tooltip: {
                    text: 'Cliquez sur une forme pour la supprimer.'
                }
            }
        }
    }
};

const PROJECTIONS = {
    "EPSG:4326": {
        name: "WGS 84 (Standard GPS)",
        wkt: 'GEOGCS["GCS_WGS_1984",DATUM["D_WGS_1984",SPHEROID["WGS_1984",6378137.0,298.257223563]],PRIMEM["Greenwich",0.0],UNIT["Degree",0.0174532925199433]]'
    },
    "EPSG:2154": {
        name: "RGF93 / Lambert-93 (France)",
        wkt: 'PROJCS["RGF93_Lambert_93",GEOGCS["GCS_RGF93",DATUM["D_RGF_1993",SPHEROID["GRS_1980",6378137.0,298.257222101]],PRIMEM["Greenwich",0.0],UNIT["Degree",0.0174532925199433]],PROJECTION["Lambert_Conformal_Conic"],PARAMETER["False_Easting",700000.0],PARAMETER["False_Northing",6600000.0],PARAMETER["Central_Meridian",3.0],PARAMETER["Standard_Parallel_1",44.0],PARAMETER["Standard_Parallel_2",49.0],PARAMETER["Latitude_Of_Origin",46.5],UNIT["Meter",1.0]]'
    },
    "EPSG:3857": {
        name: "Web Mercator (Google/Bing Maps)",
        wkt: 'PROJCS["WGS_1984_Web_Mercator_Auxiliary_Sphere",GEOGCS["GCS_WGS_1984",DATUM["D_WGS_1984",SPHEROID["WGS_1984",6378137.0,298.257223563]],PRIMEM["Greenwich",0.0],UNIT["Degree",0.0174532925199433]],PROJECTION["Mercator_Auxiliary_Sphere"],PARAMETER["False_Easting",0.0],PARAMETER["False_Northing",0.0],PARAMETER["Central_Meridian",0.0],PARAMETER["Standard_Parallel_1",0.0],PARAMETER["Auxiliary_Sphere_Type",0.0],UNIT["Meter",1.0]]'
    },
    "custom": {
        name: "Personnalisé (WKT Brut)",
        wkt: ""
    }
};

function refreshIcons() {
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function applyStoredTheme() {
    const stored = localStorage.getItem(THEME_STORAGE_KEY);
    const prefersDark =
        typeof window !== "undefined" &&
        window.matchMedia &&
        window.matchMedia("(prefers-color-scheme: dark)").matches;
    const shouldUseDark = stored ? stored === "dark" : prefersDark;
    document.documentElement.classList.toggle("dark", shouldUseDark);
}

function syncThemeArtifacts() {
    const isDark = document.documentElement.classList.contains("dark");
    const icon = document.getElementById("darkModeIcon");
    if (icon) {
        icon.setAttribute("data-lucide", isDark ? "sun" : "moon");
    }
    if (tileLayer) {
        tileLayer.setUrl(isDark ? DARK_TILE_URL : LIGHT_TILE_URL);
    }
    refreshIcons();
}

function setUserBadge(user) {
    const avatar = document.getElementById("userAvatar");
    const chip = document.getElementById("userNameChip");
    if (!avatar) return;

    if (user?.name) {
        const initials = user.name
            .split(" ")
            .filter(Boolean)
            .slice(0, 2)
            .map((chunk) => chunk[0])
            .join("")
            .toUpperCase();
        avatar.textContent = initials || "✓";
        avatar.classList.add("bg-blue-600", "text-white");
        avatar.title = user.name;
        if (chip) chip.textContent = user.name;
    } else {
        avatar.innerHTML = `<i data-lucide="user-round" class="w-4 h-4"></i>`;
        avatar.classList.remove("bg-blue-600", "text-white");
        avatar.title = "Utilisateur invité";
        if (chip) chip.textContent = "Invité";
        refreshIcons();
    }
}

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
    container.classList.remove("hidden");
    setTimeout(() => {
        container.style.opacity = "0";
        container.style.transform = "translate(-50%, -20px)";
        setTimeout(() => container.classList.add("hidden"), 300);
    }, 4000);
}

function toggleDarkMode() {
    const html = document.documentElement;
    const shouldBeDark = !html.classList.contains('dark');
    html.classList.toggle('dark', shouldBeDark);
    localStorage.setItem(THEME_STORAGE_KEY, shouldBeDark ? 'dark' : 'light');
    syncThemeArtifacts();
}

window.toggleMobileMenu = function() {
    const nav = document.getElementById('nav-menu');
    const icon = document.getElementById('menuIcon');
    nav.classList.toggle('hidden');
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
    const toggleIcon = document.getElementById("toggleIcon");
    if (toggleIcon) {
        toggleIcon.setAttribute("data-lucide", isHidden ? "panel-right-open" : "panel-right-close");
        refreshIcons();
    }
    localStorage.setItem('geoEditor_panel_hidden', isHidden);
    setTimeout(() => {
        if (map) map.invalidateSize();
    }, 300);
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
    const id = crypto.randomUUID();
    const layerData = { id, name, group, visible: true, isSystem };
    layerRegistry.push(layerData);
    if (!map.hasLayer(group) && layerData.visible) map.addLayer(group);
    refreshLayerListUI();
    return layerData;
}

function toggleLayerVisibility(id) {
    const layer = layerRegistry.find(l => l.id === id);
    if (!layer) return;
    layer.visible = !layer.visible;
    if (layer.visible) map.addLayer(layer.group);
    else map.removeLayer(layer.group);
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
    if (layer && layer.group.getLayers().length > 0 && layer.group.getBounds().isValid()) {
        map.fitBounds(layer.group.getBounds());
    } else {
        showMessage("Couche vide ou invalide", "error");
    }
}

function refreshLayerListUI() {
    const list = document.getElementById('layer-list');
    list.innerHTML = '';
    layerRegistry.forEach(layer => {
        const count = layer.group.getLayers().length;
        const item = document.createElement('div');
        item.className = "flex items-center justify-between p-2 bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-lg shadow-sm hover:shadow-md transition-shadow";

        const left = document.createElement('div');
        left.className = "flex items-center gap-3 overflow-hidden";

        const toggleBtn = document.createElement('button');
        toggleBtn.className = `${layer.visible ? 'text-slate-600 dark:text-slate-300' : 'text-slate-300 dark:text-slate-600'} hover:text-blue-600 transition-colors`;
        toggleBtn.innerHTML = `<i data-lucide="${layer.visible ? 'eye' : 'eye-off'}" class="w-4 h-4"></i>`;
        toggleBtn.addEventListener('click', () => toggleLayerVisibility(layer.id));

        const info = document.createElement('div');
        info.className = "flex flex-col overflow-hidden";
        info.innerHTML = `
            <span class="font-medium text-slate-700 dark:text-slate-200 truncate text-sm" title="${layer.name}">${layer.name}</span>
            <span class="text-[10px] text-slate-400">${count} entités</span>
        `;

        left.appendChild(toggleBtn);
        left.appendChild(info);

        const right = document.createElement('div');
        right.className = "flex items-center gap-1";

        const tableBtn = document.createElement('button');
        tableBtn.className = "p-1 text-slate-400 hover:text-emerald-600 rounded hover:bg-emerald-50 dark:hover:bg-emerald-900";
        tableBtn.title = "Ouvrir le tableau des attributs";
        tableBtn.innerHTML = `<i data-lucide="table-2" class="w-3 h-3"></i>`;
        tableBtn.addEventListener('click', () => openTableModal(layer.id));

        const zoomBtn = document.createElement('button');
        zoomBtn.className = "p-1 text-slate-400 hover:text-blue-600 rounded hover:bg-blue-50 dark:hover:bg-blue-900";
        zoomBtn.title = "Zoomer";
        zoomBtn.innerHTML = `<i data-lucide="maximize-2" class="w-3 h-3"></i>`;
        zoomBtn.addEventListener('click', () => zoomToLayer(layer.id));

        right.appendChild(tableBtn);
        right.appendChild(zoomBtn);

        if (!layer.isSystem) {
            const deleteBtn = document.createElement('button');
            deleteBtn.className = "p-1 text-slate-400 hover:text-red-600 rounded hover:bg-red-50 dark:hover:bg-red-900";
            deleteBtn.title = "Supprimer";
            deleteBtn.innerHTML = `<i data-lucide="trash-2" class="w-3 h-3"></i>`;
            deleteBtn.addEventListener('click', () => deleteLayer(layer.id));
            right.appendChild(deleteBtn);
        }

        item.appendChild(left);
        item.appendChild(right);
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
        const f = files[i];
        const nameParts = f.name.split('.');
        if (nameParts.length < 2 || f.name.startsWith('.')) continue;
        const ext = nameParts.pop().toLowerCase();
        const base = nameParts.join('.');
        if (['geojson','json'].includes(ext)) {
            pendingImportCandidates.push({ type: 'geojson', name: base, file: f });
        } else if (['shp','shx','dbf','prj','cpg'].includes(ext)) {
            if(!groups[base]) groups[base]=[];
            groups[base].push(f);
        }
    }
    for (const base in groups) {
        if (groups[base].some(f => f.name.toLowerCase().endsWith('.shp'))) {
            pendingImportCandidates.push({ type: 'shp', name: base, files: groups[base] });
        }
    }
    if (pendingImportCandidates.length === 0) {
        showMessage("Aucune couche géographique trouvée.", "error");
        return;
    }
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
        const isShp = c.type === 'shp';
        const color = isShp ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30' : 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30';
        div.innerHTML = `
            <input type="checkbox" id="import_cand_${i}" class="w-4 h-4 text-blue-600 rounded border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus:ring-blue-500 cursor-pointer" checked>
            <label for="import_cand_${i}" class="flex-grow flex items-center gap-3 cursor-pointer select-none">
                <div class="p-1.5 rounded-lg ${color}">
                    <i data-lucide="${isShp ? 'package' : 'file-json'}" class="w-5 h-5"></i>
                </div>
                <div class="flex flex-col">
                    <span class="font-medium text-slate-700 dark:text-slate-200 text-sm highlight-text">${c.name}</span>
                    <span class="text-[10px] font-bold ${isShp ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-300' : 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-300'} px-1.5 py-0.5 rounded w-fit">${isShp ? 'SHP' : 'JSON'}</span>
                </div>
            </label>
        `;
        list.appendChild(div);
    });
    refreshIcons();
    document.getElementById('importSelectionModal').classList.add('open');
};

window.filterImportList = function() {
    const s = document.getElementById('importSearch').value.toLowerCase();
    document.querySelectorAll('.import-item').forEach(item => {
        item.classList.toggle('hidden', !item.dataset.name.includes(s));
    });
};

window.toggleAllImportSelection = function() {
    const chk = document.getElementById('importSelectAll').checked;
    document.querySelectorAll('.import-item:not(.hidden) input[type="checkbox"]').forEach(cb => {
        cb.checked = chk;
    });
};

window.confirmImportSelection = async function() {
    const indices = [];
    document.querySelectorAll('#importSelectionList input[type="checkbox"]').forEach((cb, i) => {
        if(cb.checked) indices.push(i);
    });
    if (indices.length === 0) {
        closeImportSelectionModal();
        return;
    }
    const btn = document.getElementById('btnConfirmImport');
    const orgTxt = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Importation...`;
    refreshIcons();
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
            } else {
                geojson = JSON.parse(await c.file.text());
            }
            if (geojson) {
                addImportedLayer(c.name, geojson);
                count++;
            }
        } catch (e) {
            console.error(e);
        }
    }
    btn.disabled = false;
    btn.innerHTML = orgTxt;
    closeImportSelectionModal();
    if (count > 0) {
        showMessage(`${count} couche(s) importée(s) !`);
        switchSidebarTab('layers');
    } else {
        showMessage("Erreur import.", "error");
    }
};

window.closeImportSelectionModal = function(e) {
    if (e && e.target.id !== "importSelectionModal") return;
    document.getElementById('importSelectionModal').classList.remove("open");
};

function addImportedLayer(name, geojson) {
    const group = L.geoJSON(geojson, {
        style: f => ({
            color: "#" + Math.floor(Math.random()*16777215).toString(16),
            weight: 2,
            opacity: 0.8
        }),
        pointToLayer: (f, ll) => L.circleMarker(ll, {
            radius: 6,
            fillColor: "#ff7800",
            color: "#000",
            weight: 1,
            opacity: 1,
            fillOpacity: 0.8
        }),
        onEachFeature: (f, l) => {
            l.feature = f;
            if(!l.feature.id) l.feature.id = crypto.randomUUID();
            updateLayerPopup(l);
            l.on("click", e => {
                L.DomEvent.stopPropagation(e);
                selectLayer(e.target);
                switchSidebarTab('attributes');
            });
        }
    });
    registerLayer(name, group);
    if (!hasZoomedImport && group.getBounds().isValid()) {
        map.fitBounds(group.getBounds());
        hasZoomedImport = true;
    }
}

window.loadFile = function () {
    const f = document.getElementById("fileInput").files[0];
    if (!f) return;
    const reader = new FileReader();
    const base = f.name.split('.')[0];
    const ext = f.name.split('.').pop().toLowerCase();
    reader.onload = async function (e) {
        try {
            let geojson = ext === "geojson" ? JSON.parse(e.target.result) : await shp(e.target.result);
            if(geojson) {
                addImportedLayer(base, geojson);
                showMessage("Couche ajoutée.");
                switchSidebarTab('layers');
            }
        } catch(err) {
            showMessage("Erreur: " + err.message, "error");
        }
    };
    if(ext==="geojson") reader.readAsText(f);
    else reader.readAsArrayBuffer(f);
    document.getElementById("fileInput").value = "";
};

window.openTableModal = function(targetLayerId = null) {
    let all = [];
    let title = "";
    let subTitle = "";
    
    if (targetLayerId) {
        const layer = layerRegistry.find(l => l.id === targetLayerId);
        if (!layer) return showMessage("Couche introuvable", "error");
        layer.group.eachLayer(l => {
            if(l.feature) all.push(l);
        });
        title = `<span class="truncate">${layer.name}</span>`;
        subTitle = `Édition de la couche (${all.length} entités)`;
    } else {
        layerRegistry.filter(l => l.visible).forEach(r => {
            r.group.eachLayer(l => {
                if(l.feature) all.push(l);
            });
        });
        title = "Global (Toutes les couches)";
        subTitle = `Vue d'ensemble (${all.length} entités)`;
    }
    
    if (all.length === 0) return showMessage("Aucune donnée à afficher", "error");
    
    all.forEach(l => {
        if(!l.feature) l.feature = { type: "Feature", properties: {} };
        if(!l.feature.id) l.feature.id = crypto.randomUUID();
        if(!l.feature.properties) l.feature.properties={};
        if(!l.feature.properties.geom) l.feature.properties.geom = l.feature.geometry?.type || "Unknown";
    });
    
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
    const thead = document.querySelector("#attributesTable thead");
    const tbody = document.querySelector("#attributesTable tbody");
    thead.innerHTML = "";
    tbody.innerHTML = "";
    
    const hRow = document.createElement("tr");
    const thId = document.createElement("th");
    thId.className = "p-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 w-24 text-center text-slate-600 dark:text-slate-400";
    thId.innerHTML = "ID";
    hRow.appendChild(thId);
    
    tableState.columns.forEach(k => {
        const th = document.createElement("th");
        th.className = "p-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 min-w-[180px] group relative";
        const label = document.createElement("span");
        label.className = "font-semibold text-slate-700 dark:text-slate-300";
        label.textContent = k;
        th.appendChild(label);
        
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
            const val = inp.value.trim();
            if (val !== "") {
                r.feature.properties[inp.dataset.key] = val;
            } else {
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
    tableState.columns = tableState.columns.filter(c => c !== colName);
    tableState.rows.forEach(r => {
        if (r.feature.properties[colName] !== undefined) {
            delete r.feature.properties[colName];
        }
    });
    renderTable();
};

window.handleProjectionChange = function() {
    const s = document.getElementById('projectionSelect');
    const t = document.getElementById('prjInput');
    if(s.value === 'custom') {
        t.classList.remove('hidden');
    } else {
        t.classList.add('hidden');
        t.value = PROJECTIONS[s.value].wkt;
    }
};

window.openExportModal = function(format) {
    if (!layerRegistry.some(l => l.visible && l.group.getLayers().length > 0)) {
        return showMessage("Rien à exporter", "error");
    }
    currentExportFormat = format;
    const s = document.getElementById('projectionSelect');
    s.innerHTML = '';
    for (const [k, v] of Object.entries(PROJECTIONS)) {
        const o = document.createElement('option');
        o.value = k;
        o.text = v.name;
        s.appendChild(o);
    }
    s.value = "EPSG:4326";
    handleProjectionChange();
    document.getElementById('shpOptions').style.display = format === 'shp' ? 'block' : 'none';
    document.getElementById('exportFilename').value = `map_data_${new Date().toISOString().slice(0, 10)}`;
    document.getElementById('exportExtensionPreview').textContent = format === 'geojson' ? '.geojson' : '.zip';
    
    const list = document.getElementById('exportLayerSelection');
    list.innerHTML = '';
    layerRegistry.forEach((l, i) => {
        if(l.group.getLayers().length === 0) return;
        const div = document.createElement('div');
        div.className = "flex items-center gap-2 p-1 hover:bg-slate-100 dark:hover:bg-slate-700 rounded";
        div.innerHTML = `
            <input type="checkbox" id="exp_lay_${i}" value="${l.id}" class="w-4 h-4 rounded text-indigo-600 border-gray-300 focus:ring-indigo-500" ${l.visible ? 'checked' : ''}>
            <label for="exp_lay_${i}" class="text-sm text-slate-700 dark:text-slate-300 select-none flex-grow cursor-pointer truncate">${l.name} <span class="text-xs text-slate-400">(${l.group.getLayers().length})</span></label>
        `;
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
    exportData(currentExportFormat, {
        filename: document.getElementById('exportFilename').value.trim() || "export",
        prj: document.getElementById('prjInput').value,
        cpg: document.getElementById('cpgInput').value,
        layerIds: ids
    });
    closeExportModal();
};

window.exportData = async function (format, config = {}) {
    let feats = [];
    const targetLayers = config.layerIds ? layerRegistry.filter(l => config.layerIds.includes(l.id)) : layerRegistry.filter(l => l.visible);
    targetLayers.forEach(l => {
        const gj = l.group.toGeoJSON();
        if (gj.type === 'FeatureCollection') {
            feats = feats.concat(gj.features);
        } else {
            feats.push(gj);
        }
    });
    
    if (feats.length === 0) return showMessage("Rien à exporter", "error");
    
    const coll = { type: "FeatureCollection", features: feats };
    
    if (format === "geojson") {
        downloadBlob(new Blob([JSON.stringify(coll, null, 2)], { type: "application/json" }), `${config.filename}.geojson`);
    } else {
        const v = feats.filter(f => f.geometry && f.geometry.type !== 'GeometryCollection');
        if (v.length === 0) return showMessage("Géométrie invalide pour SHP", "error");
        const san = p => {
            const r = {};
            for(const k in p) {
                r[k.substring(0,10).replace(/[^a-z0-9]/gi,'')] = typeof p[k]==='object'?JSON.stringify(p[k]):String(p[k]??"");
            }
            return r;
        };
        try {
            const zip = await shpwrite.zip({
                type: "FeatureCollection",
                features: v.map(f => ({ ...f, properties: san(f.properties) }))
            }, {
                folder: config.filename,
                types: { point: "points", polygon: "polygons", line: "lines" },
                prj: config.prj,
                cpg: config.cpg,
                outputType: 'blob'
            });
            downloadBlob(zip, `${config.filename}.zip`);
        } catch (e) {
            showMessage("Erreur Export SHP", "error");
        }
    }
    showMessage("Export terminé");
};

function downloadBlob(b, n) {
    const u = URL.createObjectURL(b);
    const a = document.createElement("a");
    a.href = u;
    a.download = n;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(u);
}

function clearSelection() {
    if (selectedLayer && selectedLayer.getElement()) {
        selectedLayer.getElement().classList.remove("selected-feature-highlight");
    }
    selectedLayer = null;
    document.getElementById("attribute-info").classList.remove("hidden");
    document.getElementById("attributes-form").classList.add("hidden");
}

function selectLayer(l) {
    if (selectedLayer && selectedLayer.getElement()) {
        selectedLayer.getElement().classList.remove("selected-feature-highlight");
    }
    selectedLayer = l;
    if(!l.layerRegistryId) {
        const parent = layerRegistry.find(reg => reg.group.hasLayer(l));
        if(parent) l.layerRegistryId = parent.id;
    }
    if (l.getElement()) l.getElement().classList.add("selected-feature-highlight");
    if (!l.feature) l.feature = { type: "Feature", properties: {} };
    if (!l.feature.id) l.feature.id = crypto.randomUUID();
    
    document.getElementById("attribute-info").classList.add("hidden");
    document.getElementById("attributes-form").classList.remove("hidden");
    document.getElementById("currentFeatureId").textContent = l.feature.id.substring(0, 8);
    document.getElementById("currentFeatureType").textContent = l.feature.geometry?.type || "Feature";
    renderMiniEditor(l);
    refreshIcons();
}

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
            input.oninput = function() {
                layer.feature.properties[k] = this.value;
                updateLayerPopup(layer);
            };
            div.appendChild(labelRow);
            div.appendChild(input);
            lst.appendChild(div);
        });
    }
    
    const addDiv = document.createElement("div");
    addDiv.className = "pt-2 flex gap-2";
    addDiv.innerHTML = `
        <input type="text" id="newAttrKey" placeholder="Nom..." class="flex-1 text-xs px-2 py-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
        <button id="btnAddAttr" class="bg-slate-100 dark:bg-slate-800 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-slate-600 dark:text-slate-300 hover:text-blue-600 px-2 py-1 rounded border border-slate-200 dark:border-slate-700 transition-colors">
            <i data-lucide="plus" class="w-3 h-3"></i>
        </button>
    `;
    lst.appendChild(addDiv);
    setTimeout(() => {
        const btn = document.getElementById("btnAddAttr");
        const keyInput = document.getElementById("newAttrKey");
        if(btn && keyInput) {
            btn.onclick = () => {
                const newKey = keyInput.value.trim();
                if(newKey && !props.hasOwnProperty(newKey)) {
                    layer.feature.properties[newKey] = "";
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
        c++;
        h += `<div class="flex justify-between border-b border-gray-100 py-1 last:border-0"><span class="font-bold mr-2">${k}:</span><span>${l.feature.properties[k]}</span></div>`;
    }
    if (c === 0) h += `<span class="italic text-gray-400">Sans attributs</span>`;
    h += `</div>`;
    if (l.getPopup()) l.unbindPopup();
    l.bindPopup(h);
}

function initMap() {
    map = L.map("map", { zoomControl: false }).setView([48.8566, 2.3522], 10);
    L.control.zoom({ position: 'bottomright' }).addTo(map);
    tileLayer = L.tileLayer(LIGHT_TILE_URL, {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    syncThemeArtifacts();
    
    drawnItems = new L.FeatureGroup();
    registerLayer("Couche Dessin (Défaut)", drawnItems, true);
    
    const isMobile = window.innerWidth <= 640;
    const drawPosition = isMobile ? 'bottomleft' : 'topleft';
    const d = new L.Control.Draw({
        edit: {
            featureGroup: drawnItems
        },
        draw: {
            polygon: { showArea: true },
            polyline: true,
            rectangle: true,
            circle: true,
            marker: true,
            circlemarker: true
        },
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
    
    map.on('click', (e) => {
        clearSelection();
    });
    
    drawnItems.on("click", e => {
        L.DomEvent.stopPropagation(e);
        selectLayer(e.layer);
        switchSidebarTab('attributes');
    });
    
    map.on("draw:editstart", () => {
        if(selectedLayer?.getElement()) {
            selectedLayer.getElement().classList.remove('selected-feature-highlight');
        }
    });
    
    initMapTooltips();
}

// Fonctions pour sauvegarder la zone d'étude
window.openSaveStudyAreaModal = function() {
    const allFeatures = [];
    layerRegistry.forEach(l => {
        l.group.eachLayer(layer => {
            if(layer.feature) allFeatures.push(layer.feature);
        });
    });
    
    if (allFeatures.length === 0) {
        showMessage("Aucune donnée géographique à sauvegarder. Dessinez ou importez des formes d'abord.", "error");
        return;
    }
    
    document.getElementById('saveStudyAreaModal').classList.add('open');
    document.getElementById('studyAreaName').value = '';
    document.getElementById('studyAreaDescription').value = '';
};

window.closeSaveStudyAreaModal = function(e) {
    if(e && e.target.id !== "saveStudyAreaModal") return;
    document.getElementById('saveStudyAreaModal').classList.remove('open');
};

window.saveStudyArea = async function() {
    const name = document.getElementById('studyAreaName').value.trim();
    const description = document.getElementById('studyAreaDescription').value.trim();
    
    if (name.length < 3) {
        showMessage("Le nom de la zone d'étude est requis (minimum 3 caractères).", "error");
        return;
    }
    
    // Collecter toutes les features
    const allFeatures = [];
    layerRegistry.forEach(l => {
        l.group.eachLayer(layer => {
            if(layer.feature) allFeatures.push(layer.feature);
        });
    });
    
    if (allFeatures.length === 0) {
        showMessage("Aucune donnée géographique à sauvegarder.", "error");
        return;
    }
    
    const geojson = {
        type: "FeatureCollection",
        features: allFeatures
    };
    
    // Créer un FormData pour envoyer le GeoJSON
    const formData = new FormData();
    formData.append('geojson', JSON.stringify(geojson));
    formData.append('name', name);
    formData.append('description', description);
    
    try {
        const response = await apiClient.post('/api/admin/study_areas?action=create', {
            body: formData
        });
        
        if (response.data.success) {
            showMessage(`Zone d'étude "${name}" créée avec succès !`, "success");
            closeSaveStudyAreaModal();
            const newId = response.data.id;
            setTimeout(() => {
                if (newId) {
                    window.location.href = `/study-area?area_ids=${newId}`;
                } else {
                    window.location.href = '/study-area';
                }
            }, 1200);
        } else {
            showMessage(`Erreur: ${response.data.message || 'Erreur inconnue'}`, "error");
        }
    } catch (error) {
        console.error("Erreur lors de la sauvegarde:", error);
        showMessage("Erreur réseau lors de la sauvegarde.", "error");
    }
};

// Initialisation
document.addEventListener('DOMContentLoaded', async () => {
    // Mettre à jour la barre de navigation
    const authResult = await isAuth();
    const userRole = authResult?.user?.role || 'guest';
    updateNavBar(userRole, window.location.pathname);
    setUserBadge(authResult?.user || null);
    syncThemeArtifacts();

    // Initialiser la carte et les icônes
    initMap();
    refreshIcons();

    // Restaurer l'état du panneau
    const savedHidden = localStorage.getItem('geoEditor_panel_hidden');
    if (savedHidden === 'true') {
        document.querySelector(".main-container").classList.add("panel-hidden");
        const toggleIcon = document.getElementById("toggleIcon");
        if (toggleIcon) toggleIcon.setAttribute("data-lucide", "panel-right-open");
        refreshIcons();
        setTimeout(() => {
            if (map) map.invalidateSize();
        }, 300);
    }

    // ---- Écouteurs d'évènements au lieu des attributs onclick ----

    // Dark mode
    const darkBtn = document.getElementById('btnDarkModeToggle');
    if (darkBtn) {
        darkBtn.addEventListener('click', toggleDarkMode);
    }

    // Menu mobile
    const mobileMenuBtn = document.getElementById('btnMobileMenu');
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', () => {
            const nav = document.getElementById('nav-menu');
            const icon = document.getElementById('menuIcon');
            nav.classList.toggle('hidden');
            if (nav.classList.contains('hidden')) {
                icon.setAttribute('data-lucide', 'menu');
            } else {
                icon.setAttribute('data-lucide', 'x');
            }
            refreshIcons();
        });
    }

    // Toggle panneau d'attributs
    const inspectorToggleBtn = document.getElementById('mapInspectorToggle');
    if (inspectorToggleBtn) {
        inspectorToggleBtn.addEventListener('click', () => window.toggleAttributePanel());
    }

    const closeAttrMobileBtn = document.getElementById('btnCloseAttributesPanelMobile');
    if (closeAttrMobileBtn) {
        closeAttrMobileBtn.addEventListener('click', () => window.toggleAttributePanel());
    }

    // Tabs latéraux
    const tabLayersBtn = document.getElementById('btnTabLayers');
    if (tabLayersBtn) {
        tabLayersBtn.addEventListener('click', () => window.switchSidebarTab('layers'));
    }

    const tabAttributesBtn = document.getElementById('btnTabAttributes');
    if (tabAttributesBtn) {
        tabAttributesBtn.addEventListener('click', () => window.switchSidebarTab('attributes'));
    }

    // Boutons de tableau
    const openTableGlobalBtn = document.getElementById('btnOpenTableGlobal');
    if (openTableGlobalBtn) {
        openTableGlobalBtn.addEventListener('click', () => window.openTableModal());
    }

    const openTableSelectedBtn = document.getElementById('btnOpenTableSelected');
    if (openTableSelectedBtn) {
        openTableSelectedBtn.addEventListener('click', () => {
            window.openTableModal(selectedLayer ? selectedLayer.layerRegistryId : null);
        });
    }

    // Export
    const exportGeoJSONBtn = document.getElementById('exportGeoJSONButton');
    if (exportGeoJSONBtn) {
        exportGeoJSONBtn.addEventListener('click', () => window.openExportModal('geojson'));
    }

    const exportShpBtn = document.getElementById('exportShapefileButton');
    if (exportShpBtn) {
        exportShpBtn.addEventListener('click', () => window.openExportModal('shp'));
    }

    // Inputs fichier / dossier
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.addEventListener('change', () => window.loadFile());
    }

    const folderInput = document.getElementById('folderInput');
    if (folderInput) {
        folderInput.addEventListener('change', () => window.loadFolder());
    }

    // Modal Enregistrer zone d'étude
    const saveStudyAreaModal = document.getElementById('saveStudyAreaModal');
    const saveStudyAreaContent = document.getElementById('saveStudyAreaModalContent');
    if (saveStudyAreaModal && saveStudyAreaContent) {
        saveStudyAreaModal.addEventListener('click', (e) => window.closeSaveStudyAreaModal(e));
        saveStudyAreaContent.addEventListener('click', (e) => e.stopPropagation());
    }

    const openSaveBtn = document.getElementById('btnOpenSaveStudyArea');
    if (openSaveBtn) {
        openSaveBtn.addEventListener('click', () => window.openSaveStudyAreaModal());
    }

    const closeSaveTopBtn = document.getElementById('btnCloseSaveStudyAreaModal');
    if (closeSaveTopBtn) {
        closeSaveTopBtn.addEventListener('click', () => window.closeSaveStudyAreaModal());
    }

    const cancelSaveBtn = document.getElementById('btnCancelSaveStudyArea');
    if (cancelSaveBtn) {
        cancelSaveBtn.addEventListener('click', () => window.closeSaveStudyAreaModal());
    }

    const confirmSaveBtn = document.getElementById('btnConfirmSaveStudyArea');
    if (confirmSaveBtn) {
        confirmSaveBtn.addEventListener('click', () => window.saveStudyArea());
    }

    // Modal Tableau
    const tableModal = document.getElementById('tableModal');
    const tableModalContent = document.getElementById('tableModalContent');
    if (tableModal && tableModalContent) {
        tableModal.addEventListener('click', (e) => window.closeTableModal(e));
        tableModalContent.addEventListener('click', (e) => e.stopPropagation());
    }

    const closeTableTopBtn = document.getElementById('btnCloseTableModalTop');
    if (closeTableTopBtn) {
        closeTableTopBtn.addEventListener('click', () => window.closeTableModal());
    }

    const closeTableBottomBtn = document.getElementById('btnCloseTableModalBottom');
    if (closeTableBottomBtn) {
        closeTableBottomBtn.addEventListener('click', () => window.closeTableModal());
    }

    const addColumnBtn = document.getElementById('btnAddNewColumn');
    if (addColumnBtn) {
        addColumnBtn.addEventListener('click', () => window.addNewColumn());
    }

    const saveTableBtn = document.getElementById('btnSaveTableData');
    if (saveTableBtn) {
        saveTableBtn.addEventListener('click', () => window.saveTableData());
    }

    // Modal Export
    const exportModal = document.getElementById('exportModal');
    const exportModalContent = document.getElementById('exportModalContent');
    if (exportModal && exportModalContent) {
        exportModal.addEventListener('click', (e) => window.closeExportModal(e));
        exportModalContent.addEventListener('click', (e) => e.stopPropagation());
    }

    const closeExportTopBtn = document.getElementById('btnCloseExportModalTop');
    if (closeExportTopBtn) {
        closeExportTopBtn.addEventListener('click', () => window.closeExportModal());
    }

    const closeExportBottomBtn = document.getElementById('btnCloseExportModalBottom');
    if (closeExportBottomBtn) {
        closeExportBottomBtn.addEventListener('click', () => window.closeExportModal());
    }

    const confirmExportBtn = document.getElementById('btnConfirmExport');
    if (confirmExportBtn) {
        confirmExportBtn.addEventListener('click', () => window.confirmExport());
    }

    // Modal Import
    const importModal = document.getElementById('importSelectionModal');
    const importModalContent = document.getElementById('importSelectionModalContent');
    if (importModal && importModalContent) {
        importModal.addEventListener('click', (e) => window.closeImportSelectionModal(e));
        importModalContent.addEventListener('click', (e) => e.stopPropagation());
    }

    const closeImportTopBtn = document.getElementById('btnCloseImportModalTop');
    if (closeImportTopBtn) {
        closeImportTopBtn.addEventListener('click', () => window.closeImportSelectionModal());
    }

    const closeImportBottomBtn = document.getElementById('btnCloseImportModalBottom');
    if (closeImportBottomBtn) {
        closeImportBottomBtn.addEventListener('click', () => window.closeImportSelectionModal());
    }

    const confirmImportBtn = document.getElementById('btnConfirmImport');
    if (confirmImportBtn) {
        confirmImportBtn.addEventListener('click', () => window.confirmImportSelection());
    }

    const importSearchInput = document.getElementById('importSearch');
    if (importSearchInput) {
        importSearchInput.addEventListener('keyup', () => window.filterImportList());
    }

    const importSelectAllCheckbox = document.getElementById('importSelectAll');
    if (importSelectAllCheckbox) {
        importSelectAllCheckbox.addEventListener('change', () => window.toggleAllImportSelection());
    }
});

