import { apiClient } from "../../util/ocho-api.js";
import { TimeFormatter } from "../../util/formatter.js";
import { showCustomModal, addLoader, removeLoader } from "../../util/utils.js";
import { showConfirmationModal, itemsPerPage } from "./users.js"; // Import showConfirmationModal from users.js

// DOM elements for subscriptions
const subscriptionsTabBtn = document.getElementById('subscriptionsTabBtn');
const subscriptionsContent = document.getElementById('subscriptionsContent');
const subscriptionsTableBody = document.querySelector('#subscriptionsTable tbody');
const subscriptionsPrevPageBtn = document.getElementById('subscriptionsPrevPageBtn');
const subscriptionsNextPageBtn = document.getElementById('subscriptionsNextPageBtn');
const subscriptionsPageInfo = document.getElementById('subscriptionsPageInfo');

// Modal elements for subscriptions
const subscriptionModal = document.getElementById('subscriptionModal');
const subscriptionModalTitle = document.getElementById('subscriptionModalTitle');
const subscriptionForm = document.getElementById('subscriptionForm');
const cancelSubscriptionModalBtn = document.getElementById('cancelSubscriptionModalBtn');
const subscriptionReaderSelect = document.getElementById('subscriptionReader');

// State for subscriptions
let subscriptionsState = {
    data: [],
    currentPage: 1,
    totalPages: 1,
    searchQuery: ''
};
let allReaders = []; // To store readers for the dropdown

// ----------------------------------------------------
// Utility and rendering functions
// ----------------------------------------------------

/**
 * Renders the subscriptions table with data from the current state.
 */
function renderPaginatedSubscriptions() {
    const { data, currentPage, totalPages } = subscriptionsState;
    subscriptionsTableBody.innerHTML = '';

    if (data.length === 0) {
        subscriptionsTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-gray-500">Aucun abonnement trouvé.</td></tr>`;
    } else {
        data.forEach(sub => {
            const row = document.createElement('tr');
            const startDate = new TimeFormatter(sub.date_debut * 1000).format();
            const endDate = new TimeFormatter(sub.date_fin * 1000).format();
            const statusClass = sub.statut === 'actif' ? 'text-green-600' : 'text-red-600';

            row.innerHTML = `
                <td class="text-sm font-medium text-gray-900">${sub.subscription_id}</td>
                <td class="text-sm text-gray-600">${sub.user_name} (${sub.user_email})</td>
                <td class="text-sm text-gray-600">${startDate}</td>
                <td class="text-sm text-gray-600">${endDate}</td>
                <td class="text-sm ${statusClass} capitalize">${sub.statut}</td>
                <td class="flex space-x-2">
                    <button class="rounded-md edit-subscription-btn text-sm bg-indigo-100 text-indigo-700 hover:bg-indigo-200 action-button" data-subscription-id="${sub.subscription_id}">Modifier</button>
                    <button class="rounded-md delete-subscription-btn text-sm bg-red-100 text-red-700 hover:bg-red-200 action-button" data-subscription-id="${sub.subscription_id}" data-subscription-user="${sub.user_name}">Supprimer</button>
                </td>
            `;
            subscriptionsTableBody.appendChild(row);
        });

        // Attach event listeners after rendering
        subscriptionsTableBody.querySelectorAll('.edit-subscription-btn').forEach(button => {
            button.addEventListener('click', (e) => editSubscription(e.target.dataset.subscriptionId));
        });
        subscriptionsTableBody.querySelectorAll('.delete-subscription-btn').forEach(button => {
            button.addEventListener('click', (e) => showConfirmationModal('subscription', e.target.dataset.subscriptionId, `l'abonnement de ${e.target.dataset.subscriptionUser}`));
        });
    }
    updatePaginationControls(currentPage, totalPages, subscriptionsPageInfo, subscriptionsPrevPageBtn, subscriptionsNextPageBtn);
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
 * Fetches and loads subscriptions from the API with search and pagination.
 * @param {string} searchQuery - The search term.
 * @param {number} page - The page number to load.
 */
export async function loadSubscriptions(searchQuery = '', page = 1) {
    addLoader(subscriptionsTableBody, "flex m-auto");
    subscriptionsState.searchQuery = searchQuery;
    subscriptionsState.currentPage = page;
    try {
        const response = await apiClient.get(`/api/admin/subscriptions?action=list&search=${encodeURIComponent(searchQuery)}&page=${page}&limit=${itemsPerPage}`);
        if (response.data.success) {
            subscriptionsState.data = response.data.data;
            subscriptionsState.totalPages = Math.ceil(response.data.total / itemsPerPage);
            renderPaginatedSubscriptions();
        } else {
            showCustomModal(`Erreur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors du chargement des abonnements:", error);
        showCustomModal("Une erreur est survenue lors du chargement des abonnements.", { type: 'alert' });
    } finally {
        removeLoader(subscriptionsTableBody);
    }
}

/**
 * Loads readers for the subscription form dropdown.
 */
async function loadReadersForDropdown() {
    try {
        const response = await apiClient.get('/api/admin/subscriptions?action=list_readers');
        if (response.data.success) {
            allReaders = response.data.data;
            subscriptionReaderSelect.innerHTML = '<option value="">Sélectionner un lecteur</option>';
            allReaders.forEach(reader => {
                const option = document.createElement('option');
                option.value = reader.lecteur_id;
                option.textContent = `${reader.user_name} (${reader.user_email})`;
                subscriptionReaderSelect.appendChild(option);
            });
        } else {
            console.error("Erreur chargement lecteurs:", response.data.message);
            showCustomModal(`Erreur chargement lecteurs pour le formulaire: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors du chargement des lecteurs pour le formulaire:", error);
        showCustomModal("Une erreur est survenue lors du chargement des lecteurs pour le formulaire.", { type: 'alert' });
    }
}

// ----------------------------------------------------
// Modal and Form functions
// ----------------------------------------------------

/**
 * Opens the subscription modal for adding or editing.
 * @param {string} [subscriptionId=null] - The ID of the subscription to edit, or null for a new subscription.
 */
export async function openSubscriptionModal(subscriptionId = null) {
    subscriptionModal.classList.remove('hidden');
    const form = document.getElementById('subscriptionForm');
    form.reset(); // Reset form fields
    document.getElementById('subscriptionId').value = subscriptionId ?? '';

    await loadReadersForDropdown(); // Load readers every time the modal opens

    if (subscriptionId) {
        subscriptionModalTitle.textContent = 'Modifier l\'abonnement';
        addLoader(subscriptionModal, "absolute top-[50%] left-[50%] translate-x-[-50%] translate-y-[-50%]");
        subscriptionModal.classList.add('opacity-[0.75]', 'pointer-events-none');
        try {
            const response = await apiClient.get(`/api/admin/subscriptions?action=details&id=${subscriptionId}`);
            if (response.data.success) {
                const sub = response.data.data;
                document.getElementById('subscriptionReader').value = sub.lecteur_id;
                document.getElementById('subscriptionStartDate').value = new Date(sub.date_debut * 1000).toISOString().split('T')[0];
                document.getElementById('subscriptionEndDate').value = new Date(sub.date_fin * 1000).toISOString().split('T')[0];
                document.getElementById('subscriptionStatus').value = sub.statut;
            } else {
                showCustomModal(`Erreur chargement détails abonnement: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
                closeSubscriptionModal();
            }
        } catch (error) {
            console.error("Erreur lors du chargement des détails de l'abonnement:", error);
            showCustomModal("Une erreur est survenue lors du chargement des détails de l'abonnement.", { type: 'alert' });
            closeSubscriptionModal();
        } finally {
            removeLoader(subscriptionModal);
            subscriptionModal.classList.remove('opacity-[0.75]', 'pointer-events-none');
        }
    } else {
        subscriptionModalTitle.textContent = 'Ajouter un nouvel abonnement';
    }
}

/**
 * Calls openSubscriptionModal to edit an existing subscription.
 * @param {string} subscriptionId - The ID of the subscription to edit.
 */
function editSubscription(subscriptionId) {
    openSubscriptionModal(subscriptionId);
}

/**
 * Closes the subscription modal.
 */
export function closeSubscriptionModal() {
    subscriptionModal.classList.add('hidden');
}

/**
 * Handles the submission of the subscription form.
 * @param {Event} event - The form submission event.
 */
export async function handleSubscriptionFormSubmit(event) {
    event.preventDefault();
    addLoader(subscriptionModal, "absolute top-[50%] left-[50%] translate-x-[-50%] translate-y-[-50%]");
    subscriptionModal.classList.add('opacity-[0.75]', 'pointer-events-none');

    const subscriptionId = document.getElementById('subscriptionId').value;
    const lecteur_id = document.getElementById('subscriptionReader').value;
    const date_debut = Math.floor(new Date(document.getElementById('subscriptionStartDate').value).getTime() / 1000);
    const date_fin = Math.floor(new Date(document.getElementById('subscriptionEndDate').value).getTime() / 1000);
    const statut = document.getElementById('subscriptionStatus').value;

    const subscriptionData = {
        lecteur_id,
        date_debut,
        date_fin,
        statut
    };

    // Basic date validation
    if (date_fin <= date_debut) {
        showCustomModal('La date de fin doit être postérieure à la date de début.', { type: 'alert' });
        removeLoader(subscriptionModal);
        subscriptionModal.classList.remove('opacity-[0.75]', 'pointer-events-none');
        return;
    }

    try {
        let response;
        if (subscriptionId) {
            subscriptionData.id = subscriptionId;
            response = await apiClient.post(`/api/admin/subscriptions?action=update`, { body: subscriptionData });
        } else {
            response = await apiClient.post('/api/admin/subscriptions?action=add', { body: subscriptionData });
        }

        if (response.data.success) {
            showCustomModal(`Abonnement ${subscriptionId ? 'modifié' : 'ajouté'} avec succès !`, { type: 'success' });
            closeSubscriptionModal();
            await loadSubscriptions(subscriptionsState.searchQuery, subscriptionsState.currentPage);
        } else {
            showCustomModal(`Erreur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
            console.error("Erreur lors de l'enregistrement de l'abonnement:", response.data);
        }
    } catch (error) {
        console.error("Erreur lors de l'enregistrement de l'abonnement:", error);
        showCustomModal("Une erreur est survenue lors de l'enregistrement de l'abonnement.", { type: 'alert' });
    } finally {
        removeLoader(subscriptionModal);
        subscriptionModal.classList.remove('opacity-[0.75]', 'pointer-events-none');
    }
}

/**
 * Deletes a subscription.
 * @param {string} subscriptionId - The ID of the subscription to delete.
 */
export async function deleteSubscription(subscriptionId) {
    addLoader(subscriptionsTableBody, "mx-auto");
    try {
        const response = await apiClient.delete(`/api/admin/subscriptions?action=delete&id=${subscriptionId}`);
        if (response.data.success) {
            showCustomModal('Abonnement supprimé avec succès !', { type: 'success' });
            await loadSubscriptions(subscriptionsState.searchQuery, subscriptionsState.currentPage);
        } else {
            showCustomModal(`Erreur lors de la suppression: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors de la suppression de l'abonnement:", error);
        showCustomModal("Une erreur est survenue lors de la suppression de l'abonnement.", { type: 'alert' });
    } finally {
        removeLoader(subscriptionsTableBody);
    }
}

// Export state and functions for use in main admin.js if needed
export { subscriptionsState, renderPaginatedSubscriptions };
