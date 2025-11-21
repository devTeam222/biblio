import { apiClient } from "../../util/ocho-api.js";
import { TimeFormatter } from "../../util/formatter.js";
import { showCustomModal, addLoader, removeLoader } from "../../util/utils.js";
import { showConfirmationModal, itemsPerPage } from "./users.js"; // Import showConfirmationModal from users.js

// DOM elements for study areas
const studyAreasTabBtn = document.getElementById('studyAreasTabBtn');
const studyAreasContent = document.getElementById('studyAreasContent');
const studyAreasTableBody = document.querySelector('#studyAreasTable tbody');
const studyAreasPrevPageBtn = document.getElementById('studyAreasPrevPageBtn');
const studyAreasNextPageBtn = document.getElementById('studyAreasNextPageBtn');
const studyAreasPageInfo = document.getElementById('studyAreasPageInfo');

// Modal elements for study areas
const studyAreaModal = document.getElementById('studyAreaModal');
const studyAreaModalTitle = document.getElementById('studyAreaModalTitle');
const studyAreaForm = document.getElementById('studyAreaForm');
const cancelStudyAreaModalBtn = document.getElementById('cancelStudyAreaModalBtn');

// State for study areas
let studyAreasState = {
    data: [],
    currentPage: 1,
    totalPages: 1,
    searchQuery: ''
};

// ----------------------------------------------------
// Utility and rendering functions
// ----------------------------------------------------

/**
 * Renders the study areas table with data from the current state.
 */
function renderPaginatedStudyAreas() {
    const { data, currentPage, totalPages } = studyAreasState;
    studyAreasTableBody.innerHTML = '';

    if (data.length === 0) {
        studyAreasTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-gray-500">Aucune zone d'étude trouvée.</td></tr>`;
    } else {
        data.forEach(area => {
            const row = document.createElement('tr');
            const createdAt = new TimeFormatter(area.created_at * 1000).format();
            
            let mapLinkHtml = 'N/A';
            if (area.latitude && area.longitude) {
                const mapUrl = `https://www.google.com/maps/@${area.latitude},${area.longitude},5000m/`;
                // Enhanced styling for the map button
                mapLinkHtml = `<a href="${mapUrl}" target="_blank" 
                    class="inline-flex items-center gap-1 px-3 py-1 bg-blue-600 text-white rounded-md shadow hover:bg-blue-700 transition duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin-icon lucide-map-pin"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                    Voir carte
                </a>`;
            }

            row.innerHTML = `
                <td class="text-sm font-medium text-gray-900">${area.id}</td>
                <td class="text-sm text-gray-600">${area.name}</td>
                <td class="text-sm text-gray-600">${area.description ? area.description.substring(0, 70) + '...' : 'N/A'}</td>
                <td class="text-sm text-gray-600">${mapLinkHtml}</td>
                <td class="text-sm text-gray-600">${createdAt}</td>
                <td class="flex space-x-2">
                    <button class="rounded-md edit-study-area-btn text-sm bg-green-100 text-green-700 hover:bg-green-200 action-button" data-study-area-id="${area.id}">Modifier</button>
                    <button class="rounded-md delete-study-area-btn text-sm bg-red-100 text-red-700 hover:bg-red-200 action-button" data-study-area-id="${area.id}" data-study-area-name="${area.name}">Supprimer</button>
                </td>
            `;
            studyAreasTableBody.appendChild(row);
        });

        // Attach event listeners after rendering
        studyAreasTableBody.querySelectorAll('.edit-study-area-btn').forEach(button => {
            button.addEventListener('click', (e) => editStudyArea(e.target.dataset.studyAreaId));
        });
        studyAreasTableBody.querySelectorAll('.delete-study-area-btn').forEach(button => {
            button.addEventListener('click', (e) => showConfirmationModal('study_area', e.target.dataset.studyAreaId, `la zone d'étude "${e.target.dataset.studyAreaName}"`));
        });
    }
    updatePaginationControls(currentPage, totalPages, studyAreasPageInfo, studyAreasPrevPageBtn, studyAreasNextPageBtn);
}

/**
 * Updates the pagination buttons and info display.
 * @param {number} currentPage - The current page number.
 * @param {number} totalPages - The total number of pages.
 * @param {HTMLElement} pageInfoEl - The element displaying page information.
 * @param {HTMLElement} prevBtn - The "previous page" button.
 * @param {HTMLElement} nextBtn - The "next page" button.
 */
function updatePaginationControls(currentPage, totalPages, pageInfoEl, prevBtn, nextBtn) {
    pageInfoEl.textContent = `Page ${currentPage} sur ${totalPages || 1}`;
    prevBtn.disabled = currentPage === 1;
    nextBtn.disabled = currentPage === totalPages || !totalPages;
}

/**
 * Fetches and loads study areas from the API with search and pagination.
 * @param {string} searchQuery - The search term.
 * @param {number} page - The page number to load.
 */
export async function loadStudyAreas(searchQuery = '', page = 1) {
    addLoader(studyAreasTableBody, "flex m-auto");
    studyAreasState.searchQuery = searchQuery;
    studyAreasState.currentPage = page;
    try {
        const response = await apiClient.get(`/api/admin/study_areas?action=list&search=${encodeURIComponent(searchQuery)}&page=${page}&limit=${itemsPerPage}`);
        if (response.data.success) {
            studyAreasState.data = response.data.data;
            studyAreasState.totalPages = Math.ceil(response.data.total / itemsPerPage);
            renderPaginatedStudyAreas();
        } else {
            showCustomModal(`Erreur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors du chargement des zones d'étude:", error);
        showCustomModal("Une erreur est survenue lors du chargement des zones d'étude.", { type: 'alert' });
    } finally {
        removeLoader(studyAreasTableBody);
    }
}

// ----------------------------------------------------
// Modal and Form functions
// ----------------------------------------------------

/**
 * Opens the study area modal for adding or editing.
 * @param {string} [studyAreaId=null] - The ID of the study area to edit, or null for a new study area.
 */
export async function openStudyAreaModal(studyAreaId = null) {
    studyAreaModal.classList.remove('hidden');
    const form = document.getElementById('studyAreaForm');
    form.reset(); // Reset form fields
    document.getElementById('studyAreaId').value = studyAreaId ?? '';

    if (studyAreaId) {
        studyAreaModalTitle.textContent = 'Modifier la zone d\'étude';
        addLoader(studyAreaModal, "absolute top-[50%] left-[50%] translate-x-[-50%] translate-y-[-50%]");
        studyAreaModal.classList.add('opacity-[0.75]', 'pointer-events-none');
        try {
            const response = await apiClient.get(`/api/admin/study_areas?action=details&id=${studyAreaId}`);
            if (response.data.success) {
                const area = response.data.data;
                document.getElementById('studyAreaName').value = area.name || '';
                document.getElementById('studyAreaDescription').value = area.description || '';
                document.getElementById('studyAreaLatitude').value = area.latitude || '';
                document.getElementById('studyAreaLongitude').value = area.longitude || '';
            } else {
                showCustomModal(`Erreur chargement détails zone d'étude: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
                closeStudyAreaModal();
            }
        } catch (error) {
            console.error("Erreur lors du chargement des détails de la zone d'étude:", error);
            showCustomModal("Une erreur est survenue lors du chargement des détails de la zone d'étude.", { type: 'alert' });
            closeStudyAreaModal();
        } finally {
            removeLoader(studyAreaModal);
            studyAreaModal.classList.remove('opacity-[0.75]', 'pointer-events-none');
        }
    } else {
        studyAreaModalTitle.textContent = 'Ajouter une nouvelle zone d\'étude';
    }
}

/**
 * Calls openStudyAreaModal to edit an existing study area.
 * @param {string} studyAreaId - The ID of the study area to edit.
 */
function editStudyArea(studyAreaId) {
    openStudyAreaModal(studyAreaId);
}

/**
 * Closes the study area modal.
 */
export function closeStudyAreaModal() {
    studyAreaModal.classList.add('hidden');
}

/**
 * Handles the submission of the study area form.
 * @param {Event} event - The form submission event.
 */
export async function handleStudyAreaFormSubmit(event) {
    event.preventDefault();
    addLoader(studyAreaModal, "absolute top-[50%] left-[50%] translate-x-[-50%] translate-y-[-50%]");
    studyAreaModal.classList.add('opacity-[0.75]', 'pointer-events-none');

    const studyAreaId = document.getElementById('studyAreaId').value;
    const name = document.getElementById('studyAreaName').value;
    const description = document.getElementById('studyAreaDescription').value;
    const latitude = document.getElementById('studyAreaLatitude').value;
    const longitude = document.getElementById('studyAreaLongitude').value;

    const studyAreaData = {
        name,
        description,
        latitude: latitude === '' ? null : parseFloat(latitude), // Convert to float or null
        longitude: longitude === '' ? null : parseFloat(longitude) // Convert to float or null
    };

    try {
        let response;
        if (studyAreaId) {
            studyAreaData.id = studyAreaId;
            response = await apiClient.post(`/api/admin/study_areas?action=update`, { body: studyAreaData });
        } else {
            response = await apiClient.post('/api/admin/study_areas?action=add', { body: studyAreaData });
        }

        if (response.data.success) {
            const createdId = response.data.id;
            const isCreation = !studyAreaId && Boolean(createdId);
            showCustomModal(`Zone d'étude ${studyAreaId ? 'modifiée' : 'ajoutée'} avec succès !`, { type: 'success' });
            closeStudyAreaModal();
            await loadStudyAreas(studyAreasState.searchQuery, studyAreasState.currentPage);
            if (isCreation) {
                setTimeout(() => {
                    window.location.href = `/study-area?area_ids=${createdId}`;
                }, 1200);
            }
        } else {
            showCustomModal(`Erreur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
            console.error("Erreur lors de l'enregistrement de la zone d'étude:", response.data);
        }
    } catch (error) {
        console.error("Erreur lors de l'enregistrement de la zone d'étude:", error);
        showCustomModal("Une erreur est survenue lors de l'enregistrement de la zone d'étude.", { type: 'alert' });
    } finally {
        removeLoader(studyAreaModal);
        studyAreaModal.classList.remove('opacity-[0.75]', 'pointer-events-none');
    }
}

/**
 * Deletes a study area.
 * @param {string} studyAreaId - The ID of the study area to delete.
 */
export async function deleteStudyArea(studyAreaId) {
    addLoader(studyAreasTableBody, "mx-auto");
    try {
        const response = await apiClient.delete(`/api/admin/study_areas?action=delete&id=${studyAreaId}`);
        if (response.data.success) {
            showCustomModal('Zone d\'étude supprimée avec succès !', { type: 'success' });
            await loadStudyAreas(studyAreasState.searchQuery, studyAreasState.currentPage);
        } else {
            showCustomModal(`Erreur lors de la suppression: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors de la suppression de la zone d'étude:", error);
        showCustomModal("Une erreur est survenue lors de la suppression de la zone d'étude.", { type: 'alert' });
    } finally {
        removeLoader(studyAreasTableBody);
    }
}

// Export state and functions for use in main admin.js if needed
export { studyAreasState, renderPaginatedStudyAreas };
