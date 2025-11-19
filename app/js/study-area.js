import { updateNavBar, isAuth } from "./util/utils";

const userNameDisplay = document.getElementById('userNameDisplay');
const userRoleDisplay = document.getElementById('userRoleDisplay');

document.addEventListener('DOMContentLoaded', async () => {
    // Mettre à jour la barre de navigation et le message de bienvenue
    const authResult = await isAuth();
    const userId = authResult?.user?.id; // Correction: userId est sous authResult.user.id
    const userName = authResult?.user?.name;
    const userRole = authResult?.user?.role || 'guest';
    const possibleRoles = {
        admin: 'Administrateur',
        user: 'Lecteur',
        author: 'Auteur',
        guest: 'Visiteur',
    }


    if (authResult && userId) {

        updateNavBar(userRole, window.location.pathname); // Utilisez window.location.pathname pour la page active
        userNameDisplay.textContent = `Bienvenue, ${userName || 'Lecteur'}!`;
        userRoleDisplay.textContent = possibleRoles[userRole] || possibleRoles.guest;
        
    } else {
        updateNavBar('guest', window.location.pathname); // 'guest' si non connecté
        userNameDisplay.textContent = `Bienvenue, Lecteur !`;
        userRoleDisplay.textContent = '';
    }
    const MAP_API_URL = '/api/map/get_shapefiles'; // Votre nouveau endpoint
    const mapDiv = document.getElementById('map');
    const loadingOverlay = document.getElementById('loading-overlay');
    const statusMessage = document.getElementById('status-message');

    // 1. Initialisation de la carte
    const map = L.map('map', {
        center: [20, 0], // Centre initial (global)
        zoom: 2,         // Zoom initial
        zoomControl: false // Nous ajouterons un contrôle personnalisé
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

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
     * Récupère et parse un fichier ZIP Shapefile distant
     * @param {string} url 
     * @param {string} name 
     * @param {number} studyAreaId
     */
    const loadRemoteShapefile = async (url, name, studyAreaId) => {
        try {
            // Charger le fichier ZIP brut
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`Erreur HTTP ${response.status} lors du chargement de ${url}`);
            }
            const arrayBuffer = await response.arrayBuffer();

            // shp.js peut accepter directement l'ArrayBuffer d'un fichier ZIP Shapefile
            const geoJson = await shp(arrayBuffer);

            // shp() peut retourner un seul GeoJSON ou un tableau de GeoJSON
            if (Array.isArray(geoJson)) {
                geoJson.forEach((gj, index) => {
                    addGeoJsonToMap(gj, `${name} - Partie ${index + 1}`, studyAreaId);
                });
            } else if (geoJson.features && geoJson.features.length > 0) {
                addGeoJsonToMap(geoJson, name, studyAreaId);
            } else {
                console.warn(`Shapefile ${name} n'a pas pu être converti en GeoJSON valide.`);
            }

        } catch (error) {
            console.error(`Erreur lors du traitement du shapefile ${name} (${url}):`, error);
            statusMessage.innerHTML += `<p class="text-red-500">Erreur lors du chargement de "${name}": ${error.message}</p>`;
        }
    };

    // 2. Fonction principale pour charger tous les fichiers
    const loadAllShapefiles = async () => {
        toggleLoading(true, 'Récupération de la liste des shapefiles...');

        // NOUVEAU: Récupérer le paramètre area_ids de l'URL de la page
        const urlParams = new URLSearchParams(window.location.search);
        const areaIds = urlParams.get('area_ids'); // ex: "1,5,12"

        let api_url = MAP_API_URL;
        if (areaIds) {
            // Ajouter le paramètre de filtrage à l'URL de l'API
            api_url = `${MAP_API_URL}?area_ids=${areaIds}`;
            statusMessage.textContent = `Filtrage appliqué: uniquement les zones avec ID(s) ${areaIds} seront chargées.`;
        }

        try {
            // Utiliser l'URL de l'API potentiellement modifiée
            const response = await fetch(api_url);
            const result = await response.json();

            if (!result.success) {
                statusMessage.textContent = result.message || "Erreur lors de la récupération de la liste des fichiers.";
                toggleLoading(false);
                return;
            }

            const shapefiles = result.data;
            if (shapefiles.length === 0) {
                statusMessage.textContent = "Aucun shapefile trouvé pour cette sélection.";
                toggleLoading(false);
                return;
            }

            statusMessage.textContent = `Début du chargement de ${shapefiles.length} zone(s) d'étude...`;
            toggleLoading(true, `Chargement de ${shapefiles.length} shapefile(s)...`);

            // Charger chaque shapefile séquentiellement (ou en parallèle, mais séquentiel est plus simple à gérer)
            for (const file of shapefiles) {
                await loadRemoteShapefile(file.shapefile_url, file.study_area_name, file.study_area_id);
            }

            if (loadedShapefiles.length > 0) {
                // Zoomer sur l'étendue globale de toutes les couches chargées
                const bounds = new L.FeatureGroup(loadedShapefiles).getBounds();
                if (bounds.isValid()) {
                    map.fitBounds(bounds, { padding: [50, 50] });
                }
                statusMessage.textContent = `${loadedShapefiles.length} zone(s) d'étude chargées avec succès.`;
            } else {
                statusMessage.textContent = "Aucun shapefile n'a pu être chargé correctement.";
            }



        } catch (error) {
            console.error("Erreur générale lors du processus de chargement:", error);
            statusMessage.textContent = `Erreur irrécupérable: ${error.message}. Vérifiez la console et l'URL de l'API.`;
        } finally {
            toggleLoading(false);
        }
    };

    loadAllShapefiles();
});