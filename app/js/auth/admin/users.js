
import { apiClient } from "../../util/ocho-api.js";
import { TimeFormatter } from "../../util/formatter.js";
import { showCustomModal, addLoader, removeLoader, isAuth, updateNavBar } from "../../util/utils.js";

window.modalContainer = document.getElementById('modalContainer');

const userNameDisplay = document.getElementById('userNameDisplay');
const notificationCountEl = document.getElementById('notificationCount');
const adminAvatarEl = document.getElementById('adminAvatar');
const lastUpdateTimeEl = document.getElementById('lastUpdateTime');

const entitySearchInput = document.getElementById('entitySearchInput');
const addEntityBtn = document.getElementById('addEntityBtn');

const usersTabBtn = document.getElementById('usersTabBtn');
const authorsTabBtn = document.getElementById('authorsTabBtn');
const usersContent = document.getElementById('usersContent');
const authorsContent = document.getElementById('authorsContent');

const usersTableBody = document.querySelector('#usersTable tbody');
const authorsTableBody = document.querySelector('#authorsTable tbody');

// Modales et éléments des formulaires
const userModal = document.getElementById('userModal');
const userModalTitle = document.getElementById('userModalTitle');
const userIdInput = document.getElementById('userId');
const userNameInput = document.getElementById('userName');
const userPasswordInput = document.getElementById('userPassword');
const userRoleSelect = document.getElementById('userRole');

const authorModal = document.getElementById('authorModal');
const authorModalTitle = document.getElementById('authorModalTitle');
const authorIdInput = document.getElementById('authorId');
const authorNameInput = document.getElementById('authorName');
const authorBioInput = document.getElementById('authorBio');


let allUsers = [];
let allAuthors = [];
let activeTab = 'users'; // 'users' ou 'authors'

addEventListener('load', async () => {
    
    const authStatus = await isAuth();

    if (!authStatus.success || !authStatus.roles.includes('admin')) {
        await showCustomModal('Accès non autorisé. Vous devez être un administrateur pour accéder à cette page.', { type: 'alert' });
        window.location.href = '/login';
        return;
    }

    userNameDisplay.textContent = authStatus.user.name || 'Admin';
    updateAdminAvatar(authStatus.user.name);
    updateNavBar("admin", 'users'); // Mettre en surbrillance le lien actif
    updateLastModifiedTime();

    // Initial load based on activeTab (default: users)
    await loadUsers();
    // Load authors in background for faster tab switching
    loadAuthors();

    usersTabBtn.addEventListener('click', () => switchTab('users'));
    authorsTabBtn.addEventListener('click', () => switchTab('authors'));
    entitySearchInput.addEventListener('input', handleSearch);
    addEntityBtn.addEventListener('click', handleAddEntityClick);


    setInterval(loadUsers, 300000); // Actualiser les utilisateurs toutes les 5 minutes
    setInterval(loadAuthors, 300000); // Actualiser les auteurs toutes les 5 minutes
});

/**
 * Met à jour l'avatar de l'administrateur avec des initiales dynamiques.
 * @param {string} userName - Le nom de l'utilisateur.
 */
function updateAdminAvatar(userName) {
    if (adminAvatarEl) {
        const initials = userName ? userName.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2) : 'AD';
        adminAvatarEl.src = `https://placehold.co/40x40/4f46e5/ffffff?text=${initials}`;
        adminAvatarEl.alt = `Avatar de ${userName}`;
    }
}

/**
 * Met à jour le temps de la dernière mise à jour affiché sur la page.
 */
function updateLastModifiedTime() {
    const now = new Date();
    const formatter = new TimeFormatter(now.getTime(), { lang: navigator.language, long: true });
    if (lastUpdateTimeEl) {
        lastUpdateTimeEl.textContent = formatter.format();
    }
}

/**
 * Change l'onglet actif (Utilisateurs ou Auteurs).
 * @param {string} tabName - 'users' ou 'authors'.
 */
async function switchTab(tabName) {
    activeTab = tabName;

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
    } else {
        authorsTabBtn.classList.add('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
        authorsContent.classList.remove('hidden');
        await loadAuthors();
        addEntityBtn.textContent = 'Ajouter un auteur';
    }
    handleSearch(); // Appliquer la recherche après le changement d'onglet
}

/**
 * Gère le clic sur le bouton "Ajouter" en fonction de l'onglet actif.
 */
function handleAddEntityClick() {
    if (activeTab === 'users') {
        openUserModal();
    } else {
        openAuthorModal();
    }
}

/**
 * Charge la liste des utilisateurs depuis l'API.
 */
async function loadUsers() {
    addLoader(usersTableBody);
    try {
        const response = await apiClient.get('/api/admin/users.php?action=list');
        if (response.data.success) {
            allUsers = response.data.data;
            renderUsers(allUsers);
        } else {
            showCustomModal(`Erreur chargement utilisateurs: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
            usersTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-red-500">Erreur: ${response.data.message || 'Impossible de charger les utilisateurs.'}</td></tr>`;
        }
    } catch (error) {
        console.error("Erreur lors du chargement des utilisateurs:", error);
        showCustomModal("Une erreur est survenue lors du chargement des utilisateurs.", { type: 'alert' });
        usersTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-red-500">Erreur de connexion API.</td></tr>`;
    } finally {
        removeLoader(usersTableBody);
    }
}

/**
 * Affiche les utilisateurs dans le tableau.
 * @param {Array<Object>} usersToDisplay - Les utilisateurs à afficher.
 */
function renderUsers(usersToDisplay) {
    usersTableBody.innerHTML = '';
    if (usersToDisplay.length === 0) {
        usersTableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-gray-500">Aucun utilisateur trouvé.</td></tr>`;
        return;
    }

    usersToDisplay.forEach(user => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="py-3 px-2">${user.id}</td>
            <td class="py-3 px-2">${user.nom}</td>
            <td class="py-3 px-2">${user.email}</td>
            <td class="py-3 px-2">${user.role}</td>
            <td class="py-3 px-2 flex space-x-2">
                <button class="action-button bg-blue-100 text-blue-700 hover:bg-blue-200 text-sm edit-user-btn" data-user-id="${user.id}">Éditer</button>
                <button class="action-button bg-red-100 text-red-700 hover:bg-red-200 text-sm delete-user-btn" data-user-id="${user.id}" data-user-name="${user.nom}">Supprimer</button>
            </td>
        `;
        const editBtn = row.querySelector('.edit-user-btn');
        const deleteBtn = row.querySelector('.delete-user-btn');
        editBtn.addEventListener('click', () => openUserModal(user.id));
        deleteBtn.addEventListener('click', async () => {
            const confirm = await showCustomModal(`Confirmer la suppression de l'utilisateur "${user.nom}" ?`, { type: 'confirm' });
            if (confirm) {
                await deleteUser(user.id);
            }
        });
        usersTableBody.appendChild(row);
    });
}

/**
 * Charge la liste des auteurs depuis l'API.
 */
async function loadAuthors() {
    addLoader(authorsTableBody);
    try {
        const response = await apiClient.get('/api/admin/authors.php?action=list');
        if (response.data.success) {
            allAuthors = response.data.data;
            renderAuthors(allAuthors);
        } else {
            showCustomModal(`Erreur chargement auteurs: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
            authorsTableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-red-500">Erreur: ${response.data.message || 'Impossible de charger les auteurs.'}</td></tr>`;
        }
    } catch (error) {
        console.error("Erreur lors du chargement des auteurs:", error);
        showCustomModal("Une erreur est survenue lors du chargement des auteurs.", { type: 'alert' });
        authorsTableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-red-500">Erreur de connexion API.</td></tr>`;
    } finally {
        removeLoader(authorsTableBody);
    }
}

/**
 * Affiche les auteurs dans le tableau.
 * @param {Array<Object>} authorsToDisplay - Les auteurs à afficher.
 */
function renderAuthors(authorsToDisplay) {
    authorsTableBody.innerHTML = '';
    if (authorsToDisplay.length === 0) {
        authorsTableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-gray-500">Aucun auteur trouvé.</td></tr>`;
        return;
    }

    authorsToDisplay.forEach(author => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="py-3 px-2">${author.id}</td>
            <td class="py-3 px-2">${author.nom}</td>
            <td class="py-3 px-2">${author.biographie || 'N/A'}</td>
            <td class="py-3 px-2 flex space-x-2">
                <button class="action-button bg-blue-100 text-blue-700 hover:bg-blue-200 text-sm edit-author-btn" data-author-id="${author.id}">Éditer</button>
                <button class="action-button bg-red-100 text-red-700 hover:bg-red-200 text-sm delete-author-btn" data-author-id="${author.id}" data-author-name="${author.nom}">Supprimer</button>
            </td>
        `;
        const editBtn = row.querySelector('.edit-author-btn');
        const deleteBtn = row.querySelector('.delete-author-btn');
        editBtn.addEventListener('click', () => openAuthorModal(author.id));
        deleteBtn.addEventListener('click', async () => {
            const confirm = await showCustomModal(`Confirmer la suppression de l'auteur "${author.nom}" ?`, { type: 'confirm' });
            if (confirm) {
                await deleteAuthor(author.id);
            }
        });
        authorsTableBody.appendChild(row);
    });
}

/**
 * Gère la recherche d'entités (utilisateurs ou auteurs) en fonction de l'onglet actif.
 */
function handleSearch() {
    const searchTerm = entitySearchInput.value.toLowerCase();
    if (activeTab === 'users') {
        const filteredUsers = allUsers.filter(user =>
            user.nom.toLowerCase().includes(searchTerm) ||
            user.email.toLowerCase().includes(searchTerm) ||
            user.role.toLowerCase().includes(searchTerm)
        );
        renderUsers(filteredUsers);
    } else {
        const filteredAuthors = allAuthors.filter(author =>
            author.nom.toLowerCase().includes(searchTerm) ||
            (author.biographie && author.biographie.toLowerCase().includes(searchTerm))
        );
        renderAuthors(filteredAuthors);
    }
}

// --- Fonctions de gestion des Utilisateurs ---

/**
 * Ouvre la modale pour ajouter ou modifier un utilisateur.
 * @param {string} [userId=null] - L'ID de l'utilisateur à modifier, ou null pour un nouvel utilisateur.
 */
async function openUserModal(userId = null) {
    const userForm = userModal.querySelector('#userForm');
    userForm.reset();
    const userIdInput = userForm.querySelector('#userId');
    const userNameInput = userForm.querySelector('#userName');
    const userEmailInput = userForm.querySelector('#userEmail');
    const userRoleSelect = userForm.querySelector('#userRole');
    userIdInput.value = '';

    if (userId) {
        userModalTitle.textContent = 'Modifier l\'utilisateur';
        addLoader(userModal);
        try {
            const response = await apiClient.get(`/api/admin/users.php?action=details&id=${userId}`);
            if (response.data.success) {
                const user = response.data.data;
                userIdInput.value = user.id;
                userNameInput.value = user.nom;
                userEmailInput.value = user.email;
                userRoleSelect.value = user.role;
            } else {
                showCustomModal(`Erreur chargement détails utilisateur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
                closeUserModal();
                return;
            }
        } catch (error) {
            console.error("Erreur lors du chargement des détails de l'utilisateur:", error);
            showCustomModal("Une erreur est survenue lors du chargement des détails de l'utilisateur.", { type: 'alert' });
            closeUserModal();
            return;
        } finally {
            removeLoader(userModal);
        }
    } else {
        userModalTitle.textContent = 'Ajouter un nouvel utilisateur';
    }
    userModal.classList.remove('hidden');
    const cancelUserModalBtn = userModal.querySelector('#cancelUserModalBtn');
    cancelUserModalBtn.addEventListener('click', closeUserModal);
    const userFormElement = userModal.querySelector('#userForm');
    userFormElement.addEventListener('submit', handleUserFormSubmit);
}

/**
 * Ferme la modale utilisateur.
 */
function closeUserModal() {
    console.log("Fermeture de la modale utilisateur");
    
    userModal.classList.add('hidden');
}

/**
 * Gère la soumission du formulaire d'ajout/modification d'utilisateur.
 * @param {Event} event - L'événement de soumission.
 */
async function handleUserFormSubmit(event) {
    event.preventDefault();
    addLoader(userModal);
    const userNameInput = userModal.querySelector('#userName');
    const userRoleSelect = userModal.querySelector('#userRole');

    const userData = {
        nom: userNameInput.value,
        role: userRoleSelect.value,
    };

    try {
        let response;
        if (userIdInput.value) {
            // Modification
            userData.id = userIdInput.value;
            response = await apiClient.post(`/api/admin/users.php?action=update`, {body: userData});
        } else {
            // Ajout
            response = await apiClient.post('/api/admin/users.php?action=add', {body: userData});
        }

        if (response.data.success) {
            showCustomModal(`Utilisateur ${userIdInput.value ? 'modifié' : 'ajouté'} avec succès !`, { type: 'success' });
            closeUserModal();
            await loadUsers();
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
 * Supprime un utilisateur.
 * @param {string} userId - L'ID de l'utilisateur à supprimer.
 */
async function deleteUser(userId) {
    addLoader(usersTableBody);
    try {
        const response = await apiClient.delete(`/api/admin/users.php?action=delete&id=${userId}`);
        if (response.data.success) {
            showCustomModal('Utilisateur supprimé avec succès !', { type: 'success' });
            await loadUsers();
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

// --- Fonctions de gestion des Auteurs ---

/**
 * Ouvre la modale pour ajouter ou modifier un auteur.
 * @param {string} [authorId=null] - L'ID de l'auteur à modifier, ou null pour un nouvel auteur.
 */
async function openAuthorModal(authorId = null) {
    const authorForm = authorModal.querySelector('#authorForm');
    authorForm.reset();
    authorIdInput.value = '';

    if (authorId) {
        authorModalTitle.textContent = 'Modifier l\'auteur';
        addLoader(authorModal);
        try {
            const response = await apiClient.get(`/api/admin/authors.php?action=details&id=${authorId}`);
            if (response.data.success) {
                const author = response.data.data;
                authorIdInput.value = author.id;
                authorNameInput.value = author.nom;
                authorBioInput.value = author.biographie || '';
            } else {
                showCustomModal(`Erreur chargement détails auteur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
                closeAuthorModal();
                return;
            }
        } catch (error) {
            console.error("Erreur lors du chargement des détails de l'auteur:", error);
            showCustomModal("Une erreur est survenue lors du chargement des détails de l'auteur.", { type: 'alert' });
            closeAuthorModal();
            return;
        } finally {
            removeLoader(authorModal);
        }
    } else {
        authorModalTitle.textContent = 'Ajouter un nouvel auteur';
    }
    authorModal.classList.remove('hidden');
    // Author Modal Event Listeners
    const cancelAuthorModalBtn = authorModal.querySelector('#cancelAuthorModalBtn');
    cancelAuthorModalBtn.addEventListener('click', closeAuthorModal);
    const eventForm = authorModal.querySelector('#authorForm');
    eventForm.addEventListener('submit', handleAuthorFormSubmit);
}

/**
 * Ferme la modale auteur.
 */
function closeAuthorModal() {
    authorModal.classList.add('hidden');
}

/**
 * Gère la soumission du formulaire d'ajout/modification d'auteur.
 * @param {Event} event - L'événement de soumission.
 */
async function handleAuthorFormSubmit(event) {
    event.preventDefault();
    addLoader(authorModal);

    const authorData = {
        nom: authorNameInput.value,
        biographie: authorBioInput.value,
    };

    try {
        let response;
        if (authorIdInput.value) {
            console.log("Modification de l'auteur avec ID:", authorIdInput.value);
            
            // Modification
            authorData.id = authorIdInput.value;
            response = await apiClient.post(`/api/admin/authors.php?action=update`, {body: authorData});
        } else {
            // Ajout
            response = await apiClient.post('/api/admin/authors.php?action=add', {body: authorData});
        }

        if (response.data.success) {
            showCustomModal(`Auteur ${authorIdInput.value ? 'modifié' : 'ajouté'} avec succès !`);
            closeAuthorModal();
            await loadAuthors();
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
 * Supprime un auteur.
 * @param {string} authorId - L'ID de l'auteur à supprimer.
 */
async function deleteAuthor(authorId) {
    addLoader(authorsTableBody);
    try {
        const response = await apiClient.delete(`/api/admin/authors.php?action=delete&id=${authorId}`);
        if (response.data.success) {
            showCustomModal('Auteur supprimé avec succès !', { type: 'success' });
            await loadAuthors();
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
