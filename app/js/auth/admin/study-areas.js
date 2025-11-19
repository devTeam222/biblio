import { apiClient } from "../../util/ocho-api";
import { updateNavBar, isAuth } from "../../util/utils";

// --- Configuration ---
const API_BASE_URL = '/api/admin'; // Base pour les scripts PHP d'administration
const UPLOAD_URL = '/api/files/upload_shapefile'; // URL spécifique pour l'upload

// --- Références DOM ---
const feedbackMessage = document.getElementById('feedbackMessage');
const createForm = document.getElementById('createStudyAreaForm');
const studyAreasList = document.getElementById('studyAreasList');
const shapefileInput = document.getElementById('shapefile');
const shapefileIdInput = document.getElementById('shapefile_id');
const fileStatus = document.getElementById('fileStatus');
const submitButton = document.getElementById('submitButton');
const previewMapContainer = document.getElementById('previewMap'); 
const previewStatus = document.getElementById('previewStatus'); 

// Références Modal de Suppression
const deleteModal = document.getElementById('deleteConfirmationModal');
const modalAreaName = document.getElementById('modalAreaName');
const confirmDeleteButton = document.getElementById('confirmDeleteButton');
const cancelDeleteButton = document.getElementById('cancelDeleteButton');

// Références du Bouton de Soumission
const buttonTextSpan = document.getElementById('buttonText');
const loadingSpinner = document.getElementById('loadingSpinner');
const defaultIcon = document.getElementById('defaultIcon');

// Variables Leaflet pour la prévisualisation
let previewMap;
let previewShapefileLayer;
let currentAreaIdToDelete = null; // Variable pour stocker l'ID de l'élément à supprimer

// --- Fonctions Utilitaires ---

/**
 * Affiche un message de feedback à l'utilisateur, stylisé avec ombre.
 * @param {string} message - Le message à afficher.
 * @param {string} type - 'success', 'error', ou 'info'.
 */
function showFeedback(message, type = 'info') {
    feedbackMessage.textContent = message;
    // Ajout de shadow-md pour un meilleur style
    feedbackMessage.className = `p-4 mb-4 text-sm rounded-lg shadow-md transition duration-300 ${type === 'success' ? 'bg-green-100 text-green-700' : type === 'error' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'}`;
    feedbackMessage.classList.remove('hidden');

    // Masquer après 5 secondes
    setTimeout(() => {
        feedbackMessage.classList.add('hidden');
    }, 5000);
}

/**
 * Initialise la carte Leaflet de prévisualisation.
 */
function initPreviewMap() {
    if (!previewMap) {
        // Création de la carte centrée sur le monde par défaut
        previewMap = L.map('previewMap').setView([0, 0], 2);

        // Ajout d'une couche de tuiles OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(previewMap);
    }
}

/**
 * Nettoie la couche de prévisualisation et masque la carte.
 */
function clearPreviewMap() {
    if (previewShapefileLayer) {
        previewMap.removeLayer(previewShapefileLayer);
    }
    previewMapContainer.classList.add('hidden');
    previewStatus.classList.add('hidden');
    shapefileIdInput.value = '';
    fileStatus.textContent = '';
}

/**
 * Prévisualise le shapefile ZIP sélectionné sur la carte locale.
 * @param {File} file - Le fichier .zip à prévisualiser.
 */
function previewShapefile(file) {
    // 1. Nettoyage et Affichage
    if (previewShapefileLayer) {
        previewMap.removeLayer(previewShapefileLayer);
    }
    previewMapContainer.classList.remove('hidden');
    previewStatus.classList.remove('hidden');
    previewStatus.textContent = "Prévisualisation: Chargement...";

    // IMPORTANT : Invalider la taille pour forcer Leaflet à se redessiner correctement
    if (previewMap) {
        previewMap.invalidateSize();
    }
    

    const reader = new FileReader();

    reader.onload = function(e) {
        const arrayBuffer = e.target.result;
        
        // Utilisation de shpjs pour parser l'ArrayBuffer en GeoJSON
        shp(arrayBuffer).then(function(geojson) {
            
            // Vérification de fichier GeoJSON valide
            if (!geojson || (geojson.features && geojson.features.length === 0)) {
                previewStatus.textContent = `Erreur: Fichier ${file.name} vide ou invalide. Le Shapefile ZIP doit contenir au moins les fichiers .shp, .dbf et .shx.`;
                clearPreviewMap(); // Efface la carte en cas d'erreur de contenu
                return;
            }

            // Création de la couche GeoJSON avec un style simple
            previewShapefileLayer = L.geoJson(geojson, {
                style: function() {
                    return {
                        color: '#ff7800',
                        weight: 3,
                        opacity: 0.8,
                        fillColor: '#ff7800',
                        fillOpacity: 0.3
                    };
                },
                onEachFeature: function(feature, layer) {
                    if (feature.properties) {
                        // Ajout d'un popup avec les attributs de base
                        let popupContent = `<h4 class="font-semibold text-gray-800">${file.name} - Attributs</h4>`;
                        const keys = Object.keys(feature.properties);
                        // Afficher max 3 attributs pour la prévisualisation
                        for (let i = 0; i < Math.min(keys.length, 3); i++) {
                            const key = keys[i];
                            popupContent += `<p class="text-sm"><b>${key}:</b> ${feature.properties[key]}</p>`;
                        }
                        layer.bindPopup(popupContent, { maxHeight: 150 });
                    }
                }
            }).addTo(previewMap);
            
            // Ajuster le zoom de la carte à l'étendue de la nouvelle couche
            try {
                previewMap.fitBounds(previewShapefileLayer.getBounds(), { padding: [10, 10] });
                previewStatus.textContent = `Prévisualisation: Fichier ${file.name} chargé.`;
            } catch (e) {
                // Si getBounds échoue (ex: un seul point ou zone invalide), revenir au zoom par défaut
                previewMap.setView([0, 0], 2);
                previewStatus.textContent = `Prévisualisation: Fichier ${file.name} chargé (zoom par défaut).`;
            }

        }).catch(error => {
            previewStatus.textContent = `Erreur de conversion GeoJSON pour ${file.name}. Vérifiez le format du fichier ZIP.`;
            console.error("Erreur shpjs:", error);
            clearPreviewMap(); // Efface la carte en cas d'erreur
        });

    };

    reader.onerror = function() {
        previewStatus.textContent = `Erreur de lecture du fichier par le navigateur.`;
        console.error("Erreur de FileReader.");
        clearPreviewMap(); // Efface la carte en cas d'erreur
    };

    reader.readAsArrayBuffer(file);
}

/**
 * 1. Gère l'upload du Shapefile avant la création de la Study Area.
 * @param {File} file - Le fichier .zip à uploader.
 * @returns {Promise<number|null>} L'ID du fichier ou null en cas d'échec.
 */
async function uploadShapefile(file) {
    fileStatus.textContent = "Téléchargement en cours...";
    submitButton.disabled = true;

    const formData = new FormData();
    formData.append('shapefile', file);

    try {

        const response = await apiClient.post(UPLOAD_URL, { // Utilisation de la constante UPLOAD_URL
            body: formData
        });

        const data = response.data;

        if (data.success) {
            fileStatus.innerHTML = `✅ Fichier: <a href="${data.file_url}" target="_blank" class="text-blue-600 hover:underline font-medium">${file.name}</a> uploadé (ID: ${data.file_id})`;
            showFeedback(`Shapefile uploadé avec succès. ID: ${data.file_id}`, 'success');
            return data.file_id;
        } else {
            fileStatus.textContent = `❌ Erreur d'upload: ${data.message}`;
            showFeedback(`Erreur d'upload du Shapefile: ${data.message}`, 'error');
            return null;
        }

    } catch (error) {
        fileStatus.textContent = `❌ Erreur réseau lors de l'upload.`;
        showFeedback(`Erreur réseau: ${error.message}`, 'error');
        return null;
    } finally {
        // Laisser le bouton désactivé jusqu'à la fin de createStudyArea pour éviter un double clic.
    }
}


/**
 * 2. Crée une nouvelle Study Area après l'upload optionnel du Shapefile.
 * @param {object} areaData - Données de la zone d'étude.
 */
async function createStudyArea(areaData) {
    submitButton.disabled = true;
    
    // Afficher l'état de chargement
    buttonTextSpan.textContent = "Création en cours...";
    if (loadingSpinner) loadingSpinner.classList.remove('hidden');
    if (defaultIcon) defaultIcon.classList.add('hidden');

    try {
        const response = await apiClient.post(`api/admin/study_areas?action=create`, {
            body: areaData
        });

        const data = response.data;
        

        if (data.success) {
            showFeedback(`Zone d'étude '${areaData.name}' créée avec succès!`, 'success');
            createForm.reset();
            clearPreviewMap(); // Utilisation de la fonction de nettoyage
            loadStudyAreas(); // Recharger la liste
        } else {
            showFeedback(`Erreur lors de la création de la zone d'étude: ${data.message}`, 'error');
        }

    } catch (error) {
        showFeedback(`Erreur réseau lors de la création: ${error.message}`, 'error');
    } finally {
        submitButton.disabled = false;
        // Restaurer l'état normal
        buttonTextSpan.textContent = "Créer la Zone d'Étude";
        if (loadingSpinner) loadingSpinner.classList.add('hidden');
        if (defaultIcon) defaultIcon.classList.remove('hidden');
    }
}


/**
 * Affiche un aperçu de la Study Area dans la liste avec un style de carte amélioré.
 * @param {object} area - Objet Zone d'Étude.
 * @returns {string} Le HTML de la carte.
 */
function renderStudyAreaCard(area) {
    const date = new Date(area.created_at * 1000).toLocaleDateString("fr-FR");
    const fileLink = area.shapefile_url
        ? `<a href="${area.shapefile_url}" target="_blank" class="text-green-600 hover:underline font-medium">Fichier Shapefile (ID: ${area.shapefile_id})</a>`
        : `<span class="text-yellow-700">Aucun Shapefile attaché</span>`;

    // NOUVEAU: Création du lien de visualisation
    const visualizationLink = `/study-area?area_ids=${area.id}`;

    // Utilisation d'un style de carte plus élevé avec une meilleure typographie et gestion de l'événement de suppression.
    return `
        <div id="area-${area.id}" class="bg-white p-6 rounded-xl border border-gray-200 shadow-lg hover:shadow-xl transition duration-300 ease-in-out">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
                <div class="mb-4 sm:mb-0 w-full sm:w-3/4">
                    <h3 class="text-xl font-extrabold text-blue-800">${area.name}</h3>
                    <p class="text-sm text-gray-500 mt-1">ID: <span class="font-mono text-xs text-gray-400">${area.id}</span></p>
                </div>
                
                <div class="flex-shrink-0 flex items-center space-x-3">
                    <!-- NOUVEAU: Bouton pour voir sur la carte -->
                    <a href="${visualizationLink}" target="_blank" class="px-4 py-2 bg-green-500 text-white font-semibold rounded-lg shadow-md hover:bg-green-600 transition duration-150 ease-in-out text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.828 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Voir sur la Carte
                    </a>
                    
                    <!-- Bouton de suppression existant -->
                    <button onclick="showDeleteConfirmation(${area.id}, \`${area.name}\`)" class="btn-danger hover:scale-100 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Supprimer
                    </button>
                </div>
            </div>
            <p class="text-gray-700 mt-4 border-t border-blue-100 pt-4 text-sm leading-relaxed">
                ${area.description || '<span class="italic text-gray-400">Pas de description fournie.</span>'}
            </p>
            <div class="mt-3 text-xs font-semibold flex items-center space-x-4">
                <span class="text-gray-500">Créée le: ${date}</span>
                <span class="text-sm">${fileLink}</span>
            </div>
        </div>
    `;
}

/**
 * 3. Charge la liste des Study Areas, avec de meilleurs états visuels.
 */
async function loadStudyAreas() {
    // État de chargement stylisé
    studyAreasList.innerHTML = `
        <p class="text-center text-gray-500 py-6 bg-gray-100 rounded-xl border border-gray-300">
            <svg class="animate-spin h-5 w-5 mr-3 inline text-blue-500" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> 
            Chargement des zones d'étude...
        </p>
    `;

    try {
        const response = await apiClient.get(`/api/admin/study_areas?action=list&limit=100`);
        const data = response.data.data;
        

        if (response.data.success) {
            studyAreasList.innerHTML = '';
            if (data.length > 0) {
                data.forEach(area => {
                    studyAreasList.innerHTML += renderStudyAreaCard(area);
                });
            } else {
                // État vide stylisé
                studyAreasList.innerHTML = `
                    <div class="text-center p-6 border-2 border-dashed border-yellow-300 rounded-xl bg-yellow-50">
                        <p class="font-semibold text-yellow-800">Aucune zone d'étude trouvée.</p>
                        <p class="text-sm text-yellow-700 mt-1">Commencez par en créer une en utilisant le formulaire ci-dessus !</p>
                    </div>
                `;
            }
        } else {
            studyAreasList.innerHTML = `<p class="text-center text-red-500 py-4 p-6 bg-red-50 rounded-lg">Erreur lors du chargement: ${data.message}</p>`;
        }
    } catch (error) {
        studyAreasList.innerHTML = `<p class="text-center text-red-500 py-4 p-6 bg-red-50 rounded-lg">Erreur réseau: ${error.message}</p>`;
    }
}

// --- Logique du Modal de Suppression ---

/**
 * Affiche le modal de confirmation de suppression.
 * @param {number} id - ID de la zone d'étude à supprimer.
 * @param {string} name - Nom de la zone d'étude.
 */
function showDeleteConfirmation(id, name) {
    currentAreaIdToDelete = id;
    modalAreaName.textContent = name;
    deleteModal.classList.remove('hidden');
}

/**
 * Cache le modal de confirmation de suppression.
 */
function hideDeleteConfirmation() {
    deleteModal.classList.add('hidden');
    currentAreaIdToDelete = null;
}

/**
 * 4. Supprime une Study Area par ID.
 * @param {number} id - ID de la zone d'étude à supprimer.
 */
async function deleteStudyArea(id) {
    // Cette fonction est maintenant appelée uniquement après confirmation via le modal.
    try {
        // Utilisation de la méthode DELETE pour la suppression
        const response = await apiClient.delete(`${API_BASE_URL}/study_areas?action=delete&id=${id}`);

        const data = response.data;

        if (data.success) {
            showFeedback(`Zone d'étude ID: ${id} supprimée.`, 'success');
            loadStudyAreas(); // Recharger la liste
        } else {
            showFeedback(`Erreur lors de la suppression de la zone d'étude: ${data.message}`, 'error');
        }

    } catch (error) {
        showFeedback(`Erreur réseau lors de la suppression: ${error.message}`, 'error');
    }
}

// --- Événements ---

// Événement de soumission du formulaire principal
createForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const name = document.getElementById('name').value.trim();
    const description = document.getElementById('description').value.trim();
    const file = shapefileInput.files[0];
    let shapefileId = null;

    if (name.length < 3) {
        showFeedback("Le nom de la zone d'étude est requis (minimum 3 caractères).", 'error');
        return;
    }

    // Étape 1: Upload du fichier si un fichier est sélectionné
    if (file) {
        // Vérification du type de fichier
        if (!file.name.toLowerCase().endsWith('.zip')) {
             showFeedback("Le fichier sélectionné doit être un fichier ZIP.", 'error');
             return;
        }

        shapefileId = await uploadShapefile(file);

        if (shapefileId === null) {
            // L'upload a échoué, arrêter la création de la Study Area
            return;
        }
    }

    // Étape 2: Création de la Study Area
    const areaData = {
        name: name,
        description: description,
        shapefile_id: shapefileId // Peut être null
    };

    await createStudyArea(areaData);
});
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
    loadStudyAreas();
    initPreviewMap(); // Initialise la carte au chargement
});

// Gestionnaire de changement de fichier pour la prévisualisation (MODIFIÉ pour utiliser clearPreviewMap)
shapefileInput.addEventListener('change', () => {
    const file = shapefileInput.files[0];

    if (!file) {
        clearPreviewMap();
    } else {
        // Vérification de base du type
        if (file.name.toLowerCase().endsWith('.zip')) {
            fileStatus.textContent = `Fichier sélectionné: ${file.name}. Cliquez sur 'Créer' pour uploader et créer la zone.`;
            // Lancer la prévisualisation
            previewShapefile(file);
        } else {
             fileStatus.textContent = `Erreur: Veuillez sélectionner un fichier .zip.`;
             shapefileInput.value = ''; // Réinitialiser l'input
             showFeedback("Le fichier sélectionné doit être un fichier ZIP.", 'error');
             clearPreviewMap(); // Nettoyer la carte si le fichier est mauvais
        }
    }
});

// --- Événements du Modal de Suppression ---
cancelDeleteButton.addEventListener('click', hideDeleteConfirmation);

confirmDeleteButton.addEventListener('click', async () => {
    if (currentAreaIdToDelete !== null) {
        // Exécuter la suppression
        await deleteStudyArea(currentAreaIdToDelete);
        hideDeleteConfirmation(); // Cacher le modal après l'opération (succès ou échec)
    }
});

// Rend la fonction showDeleteConfirmation disponible globalement (pour le onclick dans renderStudyAreaCard)
window.showDeleteConfirmation = showDeleteConfirmation;