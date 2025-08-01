import { apiClient } from "../../util/ocho-api.js";
import { TimeFormatter } from "../../util/formatter.js";
import { showCustomModal, addLoader, removeLoader, isAuth, updateNavBar } from "../../util/utils.js";

// DOM elements from page.php
const usersTabBtn = document.getElementById('usersTabBtn');
const authorsTabBtn = document.getElementById('authorsTabBtn');
const usersContent = document.getElementById('usersContent');
const authorsContent = document.getElementById('authorsContent');
const usersTableBody = document.querySelector('#usersTable tbody');
const authorsTableBody = document.querySelector('#authorsTable tbody');
const entitySearchInput = document.getElementById('entitySearchInput');
const addEntityBtn = document.getElementById('addEntityBtn');
const lastUpdateTimeEl = document.getElementById('lastUpdateTime');

// Modal elements
const userModal = document.getElementById('userModal');
const authorModal = document.getElementById('authorModal');
const userForm = document.getElementById('userForm');
const authorForm = document.getElementById('authorForm');
const cancelUserModalBtn = document.getElementById('cancelUserModalBtn');
const cancelAuthorModalBtn = document.getElementById('cancelAuthorModalBtn');
const confirmationModal = document.getElementById('confirmationModal');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

// Pagination elements
const usersPrevPageBtn = document.getElementById('usersPrevPageBtn');
const usersNextPageBtn = document.getElementById('usersNextPageBtn');
const usersPageInfo = document.getElementById('usersPageInfo');
const authorsPrevPageBtn = document.getElementById('authorsPrevPageBtn');
const authorsNextPageBtn = document.getElementById('authorsNextPageBtn');
const authorsPageInfo = document.getElementById('authorsPageInfo');

// Global state variables
let currentActiveTab = sessionStorage.getItem('admin-tab') ?? 'users';
const itemsPerPage = 5;
let currentItemToDelete = null;
let currentDeleteType = null;
let usersState = {
    data: [],
    currentPage: 1,
    totalPages: 1,
    searchQuery: ''
};
let authorsState = {
    data: [],
    currentPage: 1,
    totalPages: 1,
    searchQuery: ''
};

// ----------------------------------------------------
// Utility and rendering functions
// ----------------------------------------------------

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
 * Updates the UI based on the active tab.
 * @param {string} tabName - 'users' or 'authors'.
 */
async function switchTab(tabName) {
    currentActiveTab = tabName;

    usersTabBtn.classList.remove('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
    usersTabBtn.classList.add('bg-gray-100', 'text-gray-700');
    authorsTabBtn.classList.remove('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
    authorsTabBtn.classList.add('bg-gray-100', 'text-gray-700');

    usersContent.classList.add('hidden');
    authorsContent.classList.add('hidden');

    if (tabName === 'users') {
        usersTabBtn.classList.add('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
        usersContent.classList.remove('hidden');
        await loadUsers();
        addEntityBtn.textContent = 'Ajouter un utilisateur';
        addEntityBtn.onclick = () => openModal(userModal, true);
        sessionStorage.setItem('admin-tab', 'users');
    } else {
        authorsTabBtn.classList.add('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
        authorsContent.classList.remove('hidden');
        await loadAuthors();
        addEntityBtn.textContent = 'Ajouter un auteur';
        addEntityBtn.onclick = () => openModal(authorModal, true);
        sessionStorage.setItem('admin-tab', 'authors');
    }
}

/**
 * Renders the users table with data from the current state.
 */
function renderPaginatedUsers() {
    const { data, currentPage, totalPages } = usersState;
    usersTableBody.innerHTML = '';

    if (data.length === 0) {
        usersTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-gray-500">Aucun utilisateur trouvé.</td></tr>`;
    } else {
        data.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="text-sm font-medium text-gray-900">${user.id}</td>
                <td class="text-sm text-gray-600">${user.nom}</td>
                <td class="text-sm text-gray-600">${user.email}</td>
                <td class="text-sm text-gray-600 capitalize">${user.role}</td>
                <td class="flex space-x-2">
                    <button class="edit-user-btn text-sm bg-indigo-100 text-indigo-700 hover:bg-indigo-200 action-button">Modifier</button>
                    <button class="delete-user-btn text-sm bg-red-100 text-red-700 hover:bg-red-200 action-button">Supprimer</button>
                </td>
            `;
            // Add event listeners directly to the buttons after they are created
            const editButton = row.querySelector('.edit-user-btn');
            const deleteButton = row.querySelector('.delete-user-btn');

            editButton.addEventListener('click', () => editUser(user.id));
            deleteButton.addEventListener('click', () => showConfirmationModal('user', user.id));

            usersTableBody.appendChild(row);
        });
    }
    updatePaginationControls(currentPage, totalPages, usersPageInfo, usersPrevPageBtn, usersNextPageBtn);
}

/**
 * Renders the authors table with data from the current state.
 */
function renderPaginatedAuthors() {
    const { data, currentPage, totalPages } = authorsState;
    authorsTableBody.innerHTML = '';

    if (data.length === 0) {
        authorsTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-gray-500">Aucun auteur trouvé.</td></tr>`;
    } else {
        data.forEach(author => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="text-sm font-medium text-gray-900">${author.authorid}</td>
                <td class="text-sm text-gray-600">${author.pseudo}</td>
                <td class="text-sm text-gray-600">${author.nom_complet || 'N/A'}</td>
                <td class="text-sm text-gray-600">${author.biographie ? author.biographie.substring(0, 50) + '...' : 'N/A'}</td>
                <td class="flex space-x-2">
                    <button class="edit-author-btn text-sm bg-indigo-100 text-indigo-700 hover:bg-indigo-200 action-button">Modifier</button>
                    <button class="delete-author-btn text-sm bg-red-100 text-red-700 hover:bg-red-200 action-button">Supprimer</button>
                </td>
            `;
            // Add event listeners directly to the buttons after they are created
            const editButton = row.querySelector('.edit-author-btn');
            const deleteButton = row.querySelector('.delete-author-btn');

            editButton.addEventListener('click', () => editAuthor(author.authorid));
            deleteButton.addEventListener('click', () => showConfirmationModal('author', author.authorid));

            authorsTableBody.appendChild(row);
        });
    }
    updatePaginationControls(currentPage, totalPages, authorsPageInfo, authorsPrevPageBtn, authorsNextPageBtn);
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
    nextBtn.disabled = currentPage === totalPages || totalPages === 0;
}

/**
 * Fetches and loads users from the API with search and pagination.
 * @param {string} searchQuery - The search term.
 * @param {number} page - The page number to load.
 */
async function loadUsers(searchQuery = '', page = 1) {
    addLoader(usersTableBody);
    usersState.searchQuery = searchQuery;
    usersState.currentPage = page;
    try {
        const response = await apiClient.get(`/api/admin/users?action=list&search=${encodeURIComponent(searchQuery)}&page=${page}&limit=${itemsPerPage}`);
        if (response.data.success) {
            usersState.data = response.data.data;
            usersState.totalPages = Math.ceil(response.data.total / itemsPerPage);
            renderPaginatedUsers();
        } else {
            showCustomModal(`Erreur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors du chargement des utilisateurs:", error);
        showCustomModal("Une erreur est survenue lors du chargement des utilisateurs.", { type: 'alert' });
    } finally {
        removeLoader(usersTableBody);
    }
}

/**
 * Fetches and loads authors from the API with search and pagination.
 * @param {string} searchQuery - The search term.
 * @param {number} page - The page number to load.
 */
async function loadAuthors(searchQuery = '', page = 1) {
    addLoader(authorsTableBody);
    authorsState.searchQuery = searchQuery;
    authorsState.currentPage = page;
    try {
        const response = await apiClient.get(`/api/admin/authors?action=list&search=${encodeURIComponent(searchQuery)}&page=${page}&limit=${itemsPerPage}`);
        if (response.data.success) {
            authorsState.data = response.data.data;
            authorsState.totalPages = Math.ceil(response.data.total / itemsPerPage);
            renderPaginatedAuthors();
        } else {
            showCustomModal(`Erreur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors du chargement des auteurs:", error);
        showCustomModal("Une erreur est survenue lors du chargement des auteurs.", { type: 'alert' });
    } finally {
        removeLoader(authorsTableBody);
    }
}

// ----------------------------------------------------
// Event listeners
// ----------------------------------------------------

/**
 * Updates the last modified time display.
 */
function updateLastModifiedTime() {
    const now = new Date();
    const formatter = new TimeFormatter(now.getTime(), { lang: navigator.language, long: true });
    if (lastUpdateTimeEl) {
        lastUpdateTimeEl.textContent = formatter.format();
    }
}
document.addEventListener('DOMContentLoaded', () => {
    isAuth().then(isAuthenticated => {
        if (isAuthenticated) {
            updateNavBar("admin", 'users'); // Mettre en surbrillance le lien actif
            loadUsers();
            loadAuthors();
            switchTab(currentActiveTab);
            updateLastModifiedTime();
        } else {
            window.location.href = '/login';
        }
    });

    // Tab switching
    usersTabBtn.addEventListener('click', () => switchTab('users'));
    authorsTabBtn.addEventListener('click', () => switchTab('authors'));

    // Debounced search functionality
    const debouncedSearch = debounce((e) => {
        const searchQuery = e.target.value;
        if (currentActiveTab === 'users') {
            loadUsers(searchQuery);
        } else {
            loadAuthors(searchQuery);
        }
    }, 500); // 500ms delay
    entitySearchInput.addEventListener('input', debouncedSearch);

    // Pagination for Users
    usersPrevPageBtn.addEventListener('click', () => {
        if (usersState.currentPage > 1) {
            loadUsers(usersState.searchQuery, usersState.currentPage - 1);
        }
    });
    usersNextPageBtn.addEventListener('click', () => {
        if (usersState.currentPage < usersState.totalPages) {
            loadUsers(usersState.searchQuery, usersState.currentPage + 1);
        }
    });

    // Pagination for Authors
    authorsPrevPageBtn.addEventListener('click', () => {
        if (authorsState.currentPage > 1) {
            loadAuthors(authorsState.searchQuery, authorsState.currentPage - 1);
        }
    });
    authorsNextPageBtn.addEventListener('click', () => {
        if (authorsState.currentPage < authorsState.totalPages) {
            loadAuthors(authorsState.searchQuery, authorsState.currentPage + 1);
        }
    });

    // Confirmation modal logic
    confirmDeleteBtn.addEventListener('click', async () => {
        if (currentDeleteType === 'user' && currentItemToDelete) {
            await deleteUser(currentItemToDelete);
        } else if (currentDeleteType === 'author' && currentItemToDelete) {
            await deleteAuthor(currentItemToDelete);
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

    // Modal forms submission
    userForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        await saveUser();
    });

    authorForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        await saveAuthor();
    });

    // Modal close buttons
    cancelUserModalBtn.addEventListener('click', () => closeModal(userModal));
    cancelAuthorModalBtn.addEventListener('click', () => closeModal(authorModal));
});

/**
 * Displays a custom confirmation modal.
 * @param {string} type - 'user' or 'author'.
 * @param {string} id - The ID of the item to delete.
 */
function showConfirmationModal(type, id) {
    currentItemToDelete = id;
    currentDeleteType = type;
    openModal(confirmationModal);
}

/**
 * Handles the logic for showing/hiding modals.
 * @param {HTMLElement} modal - The modal element.
 * @param {boolean} isNew - True if it's a new item, false otherwise.
 */
function openModal(modal, isNew = false) {
    if (modal === userModal) {
        document.getElementById('userModalTitle').textContent = isNew ? 'Ajouter un nouvel utilisateur' : 'Modifier un utilisateur';
    } else if (modal === authorModal) {
        document.getElementById('authorModalTitle').textContent = isNew ? 'Ajouter un nouvel auteur' : 'Modifier un auteur';
    }
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
 * Populates the user form for editing.
 * @param {string} userId - The ID of the user to edit.
 */
async function editUser(userId) {
    const user = usersState.data.find(u => u.id == userId);
    if (user) {
        document.getElementById('userModalTitle').textContent = `Modifier l'utilisateur: ${user.nom}`;
        document.getElementById('userId').value = user.id;
        document.getElementById('userName').value = user.nom;
        document.getElementById('userEmail').value = user.email;
        document.getElementById('userEmail').setAttribute('readonly', 'true');
        document.getElementById('userRole').value = user.role;
        openModal(userModal);
    }
}

/**
 * Saves a user (add or update).
 */
async function saveUser() {
    const userId = document.getElementById('userId').value;
    const userData = {
        id: userId,
        nom: document.getElementById('userName').value,
        email: document.getElementById('userEmail').value,
        role: document.getElementById('userRole').value,
    };
    addLoader(userModal);
    try {
        const url = `/api/admin/users?action=${userId ? 'update' : 'add'}`;
        const response = await apiClient.post(url, { body: userData });
        if (response.data.success) {
            showCustomModal(`Utilisateur ${userId ? 'modifié' : 'ajouté'} avec succès !`, { type: 'success' });
            closeModal(userModal);
            await loadUsers(usersState.searchQuery, usersState.currentPage); // Reload data to reflect changes
        } else {
            showCustomModal(`Erreur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors de l'enregistrement de l'utilisateur:", error);
        showCustomModal("Une erreur est survenue lors de l'enregistrement de l'utilisateur.", { type: 'alert' });
    } finally {
        removeLoader(userModal);
    }
}

/**
 * Deletes a user.
 * @param {string} userId - The ID of the user to delete.
 */
async function deleteUser(userId) {
    addLoader(usersTableBody);
    try {
        const response = await apiClient.delete(`/api/admin/users?action=delete&id=${userId}`);
        if (response.data.success) {
            showCustomModal('Utilisateur supprimé avec succès !', { type: 'success' });
            await loadUsers(usersState.searchQuery, usersState.currentPage); // Reload data
        } else {
            showCustomModal(`Erreur lors de la suppression: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors de la suppression de l'utilisateur:", error);
        showCustomModal("Une erreur est survenue lors de la suppression de l'utilisateur.", { type: 'alert' });
    } finally {
        removeLoader(usersTableBody);
    }
}

/**
 * Populates the author form for editing.
 * @param {string} authorId - The ID of the author to edit.
 */
async function editAuthor(authorId) {
    const author = authorsState.data.find(a => a.authorId == authorId);
    if (author) {
        document.getElementById('authorModalTitle').textContent = `Modifier l'auteur: ${author.pseudo}`;
        document.getElementById('authorId').value = author.authorId;
        document.getElementById('authorName').value = author.pseudo;
        document.getElementById('authorFullname').value = author.nom_complet || '';
        document.getElementById('authorBio').value = author.biographie;
        openModal(authorModal);
    }
}

/**
 * Saves an author (add or update).
 */
async function saveAuthor() {
    const authorId = document.getElementById('authorId').value;
    const authorData = {
        id: authorId,
        nom: document.getElementById('authorName').value,
        biographie: document.getElementById('authorBio').value,
    };
    addLoader(authorModal);
    try {
        const url = `/api/admin/authors?action=${authorId ? 'update' : 'add'}`;
        const response = await apiClient.post(url, {body: authorData});
        if (response.data.success) {
            showCustomModal(`Auteur ${authorId ? 'modifié' : 'ajouté'} avec succès !`, { type: 'success' });
            closeModal(authorModal);
            await loadAuthors(authorsState.searchQuery, authorsState.currentPage); // Reload data
        } else {
            showCustomModal(`Erreur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors de l'enregistrement de l'auteur:", error);
        showCustomModal("Une erreur est survenue lors de l'enregistrement de l'auteur.", { type: 'alert' });
    } finally {
        removeLoader(authorModal);
    }
}

/**
 * Deletes an author.
 * @param {string} authorId - The ID of the author to delete.
 */
async function deleteAuthor(authorId) {
    addLoader(authorsTableBody);
    try {
        const response = await apiClient.delete(`/api/admin/authors?action=delete&id=${authorId}`);
        if (response.data.success) {
            showCustomModal('Auteur supprimé avec succès !', { type: 'success' });
            await loadAuthors(authorsState.searchQuery, authorsState.currentPage); // Reload data
        } else {
            showCustomModal(`Erreur lors de la suppression: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors de la suppression de l'auteur:", error);
        showCustomModal("Une erreur est survenue lors de la suppression de l'auteur.", { type: 'alert' });
    } finally {
        removeLoader(authorsTableBody);
    }
}
