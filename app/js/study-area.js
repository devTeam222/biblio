import { updateNavBar, isAuth } from "./util/utils";

const THEME_STORAGE_KEY = "geolib-theme";
const LIGHT_TILE_URL = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
const DARK_TILE_URL = "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png";
const viewerParams = new URLSearchParams(window.location.search);
const requestedAreaIds = viewerParams.get("area_ids");

applyStoredTheme();

function refreshIcons() {
    if (typeof lucide !== "undefined") lucide.createIcons();
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

function toggleDarkMode() {
    const html = document.documentElement;
    const shouldBeDark = !html.classList.contains("dark");
    html.classList.toggle("dark", shouldBeDark);
    localStorage.setItem(THEME_STORAGE_KEY, shouldBeDark ? "dark" : "light");
    syncThemeArtifacts();
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

function configureEditToolbar(canEdit, isAuthenticated) {
    const toolbar = document.getElementById("editToolbar");
    const notice = document.getElementById("editNotice");
    const editorBtn = document.getElementById("openEditorBtn");

    if (!toolbar || !editorBtn) return;

    if (canEdit) {
        toolbar.classList.remove("hidden");
        const editorUrl = requestedAreaIds
            ? `/admin/study-areas/edit/${encodeURIComponent(requestedAreaIds)}`
            : "/admin/study-areas/new";
        editorBtn.onclick = () => {
            window.location.href = editorUrl;
        };
        if (notice) notice.classList.add("hidden");
    } else {
        toolbar.classList.add("hidden");
        if (notice) {
            notice.textContent = isAuthenticated
                ? "Seuls les administrateurs ou auteurs peuvent modifier cette visualisation."
                : "Connectez-vous pour demander des droits d'édition.";
            notice.classList.remove("hidden");
        }
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

let tileLayer;

document.addEventListener('DOMContentLoaded', async () => {
    // Mettre à jour la barre de navigation
    const authResult = await isAuth();
    const userRole = authResult?.user?.role || 'guest';
    updateNavBar(userRole, window.location.pathname);
    setUserBadge(authResult?.user || null);
    const canEdit = ["admin", "author"].includes(userRole);
    configureEditToolbar(canEdit, Boolean(authResult?.user));
    syncThemeArtifacts();

    refreshIcons();

    // Dark mode button
    const darkBtn = document.getElementById('btnDarkModeToggle');
    if (darkBtn) {
        darkBtn.addEventListener('click', toggleDarkMode);
    }

    const MAP_API_URL = '/api/map/get_shapefiles'; // Votre nouveau endpoint
    const mapDiv = document.getElementById('map');
    const loadingOverlay = document.getElementById('loading-overlay');
    const statusMessage = document.getElementById('status-message');

    // 1. Initialisation de la carte
    const map = L.map('map', {
        center: [20, 0], // Centre initial (global)
        zoom: 2,         // Zoom initial
        zoomControl: true
    });

    tileLayer = L.tileLayer(LIGHT_TILE_URL, {
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);
    syncThemeArtifacts();

    L.control.zoom({ position: 'topright' }).addTo(map);

    const overlayLayers = {};
    let layerControl = L.control.layers({}, overlayLayers, { collapsed: false, position: 'topleft' }).addTo(map);
    const loadedShapefiles = []; // Pour stocker les couches GeoJSON

    /**
     * Affiche l'état de chargement
     * @param {boolean} show 
     * @param {string} message 
     */
    const toggleLoading = (show, message = 'Chargement des shapefiles...') => {
        loadingOverlay.classList.toggle('hidden', !show);
        statusMessage.textContent = show ? message : '';
    };

    /**
     * Style par défaut pour les couches GeoJSON
     */
    const getDefaultStyle = (feature) => {
        // Style dynamique basé sur l'ID de la zone pour une couleur distincte
        const id = feature.properties.id || 0;
        const colors = ['#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6', '#1abc9c', '#d35400'];
        const color = colors[id % colors.length];

        return {
            fillColor: color,
            weight: 2,
            opacity: 1,
            color: color,
            fillOpacity: 0.5
        };
    };

    /**
     * Ajoute une couche GeoJSON à la carte et au LayerControl
     * @param {object} geoJson 
     * @param {string} name 
     * @param {number} studyAreaId 
     */
    const addGeoJsonToMap = (geoJson, name, studyAreaId) => {
        const geoJsonLayer = L.geoJSON(geoJson, {
            style: (feature) => getDefaultStyle({ properties: { id: studyAreaId } }),
            onEachFeature: (feature, layer) => {
                // Créer un popup avec les propriétés (simplifié)
                let popupContent = `<h4 class="font-bold">${name}</h4>`;
                popupContent += `<p class="text-sm">ID Zone d'étude: ${studyAreaId}</p>`;

                // Ajouter les propriétés du shapefile au popup
                if (feature.properties) {
                    popupContent += '<div class="mt-2 text-xs">';
                    Object.entries(feature.properties).forEach(([key, value]) => {
                        // Exclure les propriétés vides ou trop longues
                        if (value && String(value).length < 50) {
                            popupContent += `<strong>${key}:</strong> ${value}<br>`;
                        }
                    });
                    popupContent += '</div>';
                }

                layer.bindPopup(popupContent);
            }
        });

        // Ajouter la couche au groupe et au LayerControl
        geoJsonLayer.addTo(map);
        layerControl.addOverlay(geoJsonLayer, name);
        loadedShapefiles.push(geoJsonLayer);

        // Zoom sur l'étendue de la dernière couche ajoutée
        if (geoJsonLayer.getBounds().isValid()) {
            map.fitBounds(geoJsonLayer.getBounds());
        } else {
            console.warn(`Bounds invalides pour la zone ${name}`);
        }
    };

    /**
     * Récupère et parse un fichier distant (Shapefile ZIP ou GeoJSON)
     * @param {string} url 
     * @param {string} name 
     * @param {number} studyAreaId
     * @param {string} fileType - Type de fichier ('application/zip', 'application/geo+json', etc.)
     */
    const loadRemoteFile = async (url, name, studyAreaId, fileType) => {
        try {
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`Erreur HTTP ${response.status} lors du chargement de ${url}`);
            }
            
            let geoJson;
            
            // Si c'est un GeoJSON, le parser directement
            if (fileType && (fileType.includes('geo+json') || url.toLowerCase().endsWith('.geojson'))) {
                const text = await response.text();
                geoJson = JSON.parse(text);
            } else {
                // Sinon, c'est un Shapefile ZIP
                const arrayBuffer = await response.arrayBuffer();
                geoJson = await shp(arrayBuffer);
            }

            // shp() peut retourner un seul GeoJSON ou un tableau de GeoJSON
            if (Array.isArray(geoJson)) {
                geoJson.forEach((gj, index) => {
                    addGeoJsonToMap(gj, `${name} - Partie ${index + 1}`, studyAreaId);
                });
            } else if (geoJson.features && geoJson.features.length > 0) {
                addGeoJsonToMap(geoJson, name, studyAreaId);
            } else {
                console.warn(`Fichier ${name} n'a pas pu être converti en GeoJSON valide.`);
            }

        } catch (error) {
            console.error(`Erreur lors du traitement du fichier ${name} (${url}):`, error);
            statusMessage.textContent = `Erreur lors du chargement de "${name}": ${error.message}`;
            statusMessage.className = "absolute bottom-4 left-4 right-4 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 px-4 py-2 rounded-lg shadow-md z-[500]";
        }
    };

    // 2. Fonction principale pour charger tous les fichiers
    const loadAllShapefiles = async () => {
        toggleLoading(true, 'Récupération de la liste des shapefiles...');

        let api_url = MAP_API_URL;
        if (requestedAreaIds) {
            // Ajouter le paramètre de filtrage à l'URL de l'API
            api_url = `${MAP_API_URL}?area_ids=${requestedAreaIds}`;
            statusMessage.textContent = `Filtrage appliqué: uniquement les zones avec ID(s) ${requestedAreaIds} seront chargées.`;
        }

        try {
            // Utiliser l'URL de l'API potentiellement modifiée
            const response = await fetch(api_url);
            const result = await response.json();

            if (!result.success) {
                statusMessage.textContent = result.message || "Erreur lors de la récupération de la liste des fichiers.";
                statusMessage.className = "absolute bottom-4 left-4 right-4 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 px-4 py-2 rounded-lg shadow-md z-[500]";
                toggleLoading(false);
                return;
            }

            const shapefiles = result.data;
            if (shapefiles.length === 0) {
                statusMessage.textContent = "Aucun fichier trouvé pour cette sélection.";
                statusMessage.className = "absolute bottom-4 left-4 right-4 text-sm text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 px-4 py-2 rounded-lg shadow-md z-[500]";
                toggleLoading(false);
                return;
            }

            statusMessage.textContent = `Début du chargement de ${shapefiles.length} zone(s) d'étude...`;
            statusMessage.className = "absolute bottom-4 left-4 right-4 text-sm text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 px-4 py-2 rounded-lg shadow-md z-[500]";
            toggleLoading(true, `Chargement de ${shapefiles.length} fichier(s)...`);

            // Charger chaque fichier séquentiellement
            for (const file of shapefiles) {
                await loadRemoteFile(file.shapefile_url, file.study_area_name, file.study_area_id, file.file_type);
            }

            if (loadedShapefiles.length > 0) {
                // Zoomer sur l'étendue globale de toutes les couches chargées
                const bounds = new L.FeatureGroup(loadedShapefiles).getBounds();
                if (bounds.isValid()) {
                    map.fitBounds(bounds, { padding: [50, 50] });
                }
                statusMessage.textContent = `${loadedShapefiles.length} zone(s) d'étude chargée(s) avec succès.`;
                statusMessage.className = "absolute bottom-4 left-4 right-4 text-sm text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-2 rounded-lg shadow-md z-[500]";
                showMessage(`${loadedShapefiles.length} zone(s) d'étude chargée(s) avec succès.`);
            } else {
                statusMessage.textContent = "Aucun fichier n'a pu être chargé correctement.";
                statusMessage.className = "absolute bottom-4 left-4 right-4 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 px-4 py-2 rounded-lg shadow-md z-[500]";
            }



        } catch (error) {
            console.error("Erreur générale lors du processus de chargement:", error);
            statusMessage.textContent = `Erreur irrécupérable: ${error.message}. Vérifiez la console et l'URL de l'API.`;
            statusMessage.className = "absolute bottom-4 left-4 right-4 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 px-4 py-2 rounded-lg shadow-md z-[500]";
            showMessage(`Erreur: ${error.message}`, "error");
        } finally {
            toggleLoading(false);
        }
    };

    loadAllShapefiles();
});