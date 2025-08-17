import { apiClient } from "../../util/ocho-api.js";
import { TimeFormatter } from "../../util/formatter.js";
import { showCustomModal, addLoader, removeLoader, isAuth, updateNavBar } from "../../util/utils.js";

// DOM elements for subscriptions
const subscriptionsTableBody = document.querySelector('#subscriptionsTable tbody');
const subscriptionSearchInput = document.getElementById('subscriptionSearchInput');
const addSubscriptionBtn = document.getElementById('addSubscriptionBtn');
const subscriptionsPrevPageBtn = document.getElementById('subscriptionsPrevPageBtn');
const subscriptionsNextPageBtn = document.getElementById('subscriptionsNextPageBtn');
const subscriptionsPageInfo = document.getElementById('subscriptionsPageInfo');

// Modal elements for subscriptions
const subscriptionModal = document.getElementById('subscriptionModal');
const subscriptionModalTitle = document.getElementById('subscriptionModalTitle');
const subscriptionForm = document.getElementById('subscriptionForm');
const cancelSubscriptionModalBtn = document.getElementById('cancelSubscriptionModalBtn');

// Form fields for subscriptions
const subscriptionIdInput = document.getElementById('subscriptionId');
const subscriptionReaderSelect = document.getElementById('subscriptionReader');
const subscriptionStartDateInput = document.getElementById('subscriptionStartDate');
const subscriptionEndDateInput = document.getElementById('subscriptionEndDate');
const subscriptionStatusSelect = document.getElementById('subscriptionStatus');

// Confirmation modal (assumed to be global or imported from utils)
const confirmationModal = document.getElementById('confirmationModal');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

// State for subscriptions
let subscriptionsState = {
    data: [],
    currentPage: 1,
    totalPages: 1,
    searchQuery: ''
};
const itemsPerPage = 5;

let allReaders = []; // To store readers for the dropdown

let currentItemToDelete = null; // For tracking item to delete with confirmation modal
let currentDeleteType = null; // For tracking type of item to delete ('subscription')

document.addEventListener('DOMContentLoaded', async () => {
    const authStatus = await isAuth();

    if (!authStatus.success || !authStatus.user.role || authStatus.user.role !== 'admin') {
        await showCustomModal('Accès non autorisé. Vous devez être un administrateur pour accéder à cette page.', { type: 'alert' });
        window.location.href = '/login';
        return;
    }
    document.getElementById('userNameDisplay').textContent = authStatus.user.name || 'Admin';
    updateNavBar('admin', window.location.pathname); // Highlight the active link (subscriptions)
    updateLastModifiedTime();

    await loadSubscriptions();
    await loadReadersForDropdown(); // Load readers when page loads

    subscriptionSearchInput.addEventListener('input', debounce((e) => {
        loadSubscriptions(e.target.value);
    }, 500));

    addSubscriptionBtn.addEventListener('click', () => openSubscriptionModal());

    subscriptionsTableBody.addEventListener('click', async (event) => {
        const editBtn = event.target.closest('.edit-subscription-btn');
        const deleteBtn = event.target.closest('.delete-subscription-btn');

        if (editBtn) {
            const subscriptionId = editBtn.dataset.subscriptionId;
            await openSubscriptionModal(subscriptionId);
        } else if (deleteBtn) {
            const subscriptionId = deleteBtn.dataset.subscriptionId;
            const readerName = deleteBtn.dataset.readerName;
            showConfirmationModal('subscription', subscriptionId, `l'abonnement de ${readerName}`);
        }
    });

    // Pagination for Subscriptions
    subscriptionsPrevPageBtn.addEventListener('click', () => {
        if (subscriptionsState.currentPage > 1) {
            loadSubscriptions(subscriptionsState.searchQuery, subscriptionsState.currentPage - 1);
        }
    });
    subscriptionsNextPageBtn.addEventListener('click', () => {
        if (subscriptionsState.currentPage < subscriptionsState.totalPages) {
            loadSubscriptions(subscriptionsState.searchQuery, subscriptionsState.currentPage + 1);
        }
    });

    // Confirmation modal logic
    confirmDeleteBtn.addEventListener('click', async () => {
        if (currentDeleteType === 'subscription' && currentItemToDelete) {
            await deleteSubscription(currentItemToDelete);
        }
        closeModal(confirmationModal);
        currentItemToDelete = null;
        currentDeleteType = null;
    });
    cancelDeleteBtn.addEventListener('click', () => {
        closeModal(confirmationModal);
        currentItemToDelete = null;
        currentDeleteType = null;
    });

    subscriptionForm.addEventListener('submit', handleSubscriptionFormSubmit);
    cancelSubscriptionModalBtn.addEventListener('click', closeSubscriptionModal);

    setInterval(loadSubscriptions, 300000); // Refresh every 5 minutes
});

/**
 * Utility function to debounce a function call.
 * @param {Function} func - The function to debounce.
 * @param {number} delay - The delay in milliseconds.
 * @returns {Function} - The debounced function.
 */
function debounce(func, delay) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
}

/**
 * Updates the last modified time display.
 */
function updateLastModifiedTime() {
    const now = new Date();
    const formatter = new TimeFormatter(now.getTime(), { lang: navigator.language, long: true });
    document.getElementById('lastUpdateTime').textContent = formatter.format();
}

/**
 * Displays a custom confirmation modal.
 * @param {string} type - 'subscription'.
 * @param {string} id - The ID of the item to delete.
 * @param {string} [name='cet élément'] - The name of the item to delete (for user-friendly message).
 */
export function showConfirmationModal(type, id, name = 'cet élément') {
    currentItemToDelete = id;
    currentDeleteType = type;
    const messageElement = confirmationModal.querySelector('p.mb-4');
    if (messageElement) {
        messageElement.textContent = `Voulez-vous vraiment supprimer ${name} ?`;
    }
    openModal(confirmationModal);
}

/**
 * Handles the logic for showing/hiding modals.
 * @param {HTMLElement} modal - The modal element.
 */
function openModal(modal) {
    modal.classList.remove('hidden');
}

/**
 * Closes a modal.
 * @param {HTMLElement} modal - The modal element to close.
 */
function closeModal(modal) {
    modal.classList.add('hidden');
}

/**
 * Fetches and loads subscriptions from the API with search and pagination.
 * @param {string} searchQuery - The search term.
 * @param {number} page - The page number to load.
 */
export async function loadSubscriptions(searchQuery = '', page = 1) {
    addLoader(subscriptionsTableBody);
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
 * Renders the subscriptions table with data from the current state.
 */
function renderPaginatedSubscriptions() {
    const { data, currentPage, totalPages } = subscriptionsState;
    subscriptionsTableBody.innerHTML = '';

    if (data.length === 0) {
        subscriptionsTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-gray-500">Aucun abonnement trouvé.</td></tr>`;
    } else {
        data.forEach(sub => {
            const startDate = new TimeFormatter(sub.date_debut * 1000).format();
            const endDate = new TimeFormatter(sub.date_fin * 1000).format();
            const statusClass = sub.statut === 'actif' ? 'bg-green-100 text-green-800' : (sub.statut === 'expire' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800');

            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="text-sm font-medium text-gray-900">${sub.id}</td>
                <td class="text-sm text-gray-600">${sub.lecteur_nom}</td>
                <td class="text-sm text-gray-600">${startDate}</td>
                <td class="text-sm text-gray-600">${endDate}</td>
                <td class="text-sm text-gray-600 capitalize"><span class="px-2 py-1 rounded-full text-xs font-semibold ${statusClass}">${sub.statut}</span></td>
                <td class="flex space-x-2">
                    <button class="rounded-md edit-subscription-btn text-sm bg-indigo-100 text-indigo-700 hover:bg-indigo-200 action-button" data-subscription-id="${sub.id}">Modifier</button>
                    <button class="rounded-md delete-subscription-btn text-sm bg-red-100 text-red-700 hover:bg-red-200 action-button" data-subscription-id="${sub.id}" data-reader-name="${sub.lecteur_nom}">Supprimer</button>
                </td>
            `;
            subscriptionsTableBody.appendChild(row);
        });
    }
    updatePaginationControls(subscriptionsState.currentPage, subscriptionsState.totalPages, subscriptionsPageInfo, subscriptionsPrevPageBtn, subscriptionsNextPageBtn);
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
 * Loads readers for the subscription form dropdown.
 */
async function loadReadersForDropdown() {
    try {
        const response = await apiClient.get('/api/admin/readers?action=list'); // Assuming an API endpoint for readers
        if (response.data.success) {
            allReaders = response.data.data;
            subscriptionReaderSelect.innerHTML = '<option value="">-- Sélectionner un lecteur --</option>';
            allReaders.forEach(reader => {
                const option = document.createElement('option');
                option.value = reader.id;
                option.textContent = reader.nom; // Assuming reader object has a 'name' property
                subscriptionReaderSelect.appendChild(option);
            });
        } else {
            console.error("Erreur chargement lecteurs pour le formulaire:", response.data.message);
        }
    } catch (error) {
        console.error("Erreur lors du chargement des lecteurs pour le formulaire:", error);
    }
}

/**
 * Opens the subscription modal for adding or editing.
 * @param {string} [subscriptionId=null] - The ID of the subscription to edit, or null for a new subscription.
 */
export async function openSubscriptionModal(subscriptionId = null) {
    subscriptionModal.classList.remove('hidden');
    subscriptionForm.reset(); // Reset form fields
    subscriptionIdInput.value = subscriptionId ?? '';

    await loadReadersForDropdown(); // Ensure readers are loaded

    if (subscriptionId) {
        subscriptionModalTitle.textContent = 'Modifier l\'abonnement';
        addLoader(subscriptionModal);
        subscriptionModal.classList.add('opacity-[0.75]', 'pointer-events-none');
        try {
            const response = await apiClient.get(`/api/admin/subscriptions?action=details&id=${subscriptionId}`);
            if (response.data.success) {
                const sub = response.data.data;
                subscriptionReaderSelect.value = sub.lecteur_id || '';
                subscriptionStartDateInput.value = new Date(sub.date_debut * 1000).toISOString().split('T')[0];
                subscriptionEndDateInput.value = new Date(sub.date_fin * 1000).toISOString().split('T')[0];
                subscriptionStatusSelect.value = sub.statut || '';
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
    addLoader(subscriptionModal);
    subscriptionModal.classList.add('opacity-[0.75]', 'pointer-events-none');

    const subscriptionId = subscriptionIdInput.value;
    const subscriptionData = {
        lecteur_id: subscriptionReaderSelect.value,
        date_debut: Math.floor(new Date(subscriptionStartDateInput.value).getTime() / 1000), // Convert to Unix timestamp
        date_fin: Math.floor(new Date(subscriptionEndDateInput.value).getTime() / 1000),     // Convert to Unix timestamp
        statut: subscriptionStatusSelect.value
    };

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
    addLoader(subscriptionsTableBody);
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
