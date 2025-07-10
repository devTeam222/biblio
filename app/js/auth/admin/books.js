
import { apiClient } from "../../util/ocho-api.js";
import { TimeFormatter } from "../../util/formatter.js";
import { showCustomModal, addLoader, removeLoader, isAuth, updateNavBar } from "../../util/utils.js";

window.modalContainer = document.getElementById('modalContainer');

const userNameDisplay = document.getElementById('userNameDisplay');
const notificationCountEl = document.getElementById('notificationCount');
const adminAvatarEl = document.getElementById('adminAvatar');
const lastUpdateTimeEl = document.getElementById('lastUpdateTime');
const booksTableBody = document.querySelector('#booksTable tbody');
const bookSearchInput = document.getElementById('bookSearchInput');
const addBookBtn = document.getElementById('addBookBtn');

// Modale et éléments du formulaire
const bookModal = document.getElementById('bookModal');
const bookModalTitle = document.getElementById('bookModalTitle');
const bookForm = document.getElementById('bookForm');
const bookIdInput = document.getElementById('bookId');
const bookTitleInput = document.getElementById('bookTitle');
const bookAuthorSelect = document.getElementById('bookAuthor');
const bookIsbnInput = document.getElementById('bookIsbn');
const bookDescriptionInput = document.getElementById('bookDescription');
const bookAvailableCheckbox = document.getElementById('bookAvailable');
const cancelBookModalBtn = document.getElementById('cancelBookModalBtn');

let allBooks = []; // Pour stocker tous les livres et permettre la recherche côté client
let allAuthors = []; // Pour stocker les auteurs pour le dropdown

document.addEventListener('DOMContentLoaded', async () => {
    const authStatus = await isAuth();

    if (!authStatus.success || !authStatus.roles.includes('admin')) {
        await showCustomModal('Accès non autorisé. Vous devez être un administrateur pour accéder à cette page.', { type: 'alert' });
        window.location.href = '/login';
        return;
    }
    userNameDisplay.textContent = authStatus.user.name || 'Admin';
    updateAdminAvatar(authStatus.user.name);
    updateNavBar('admin', 'books'); // Mettre en surbrillance le lien actif
    updateLastModifiedTime();

    await loadBooks();
    await loadAuthorsForDropdown();

    bookSearchInput.addEventListener('input', handleSearch);
    addBookBtn.addEventListener('click', () => openBookModal());
    cancelBookModalBtn.addEventListener('click', closeBookModal);
    bookForm.addEventListener('submit', handleBookFormSubmit);

    // Délégation d'événements pour les boutons d'édition et de suppression
    booksTableBody.addEventListener('click', async (event) => {
        const editBtn = event.target.closest('.edit-book-btn');
        const deleteBtn = event.target.closest('.delete-book-btn');

        if (editBtn) {
            const bookId = editBtn.dataset.bookId;
            await openBookModal(bookId);
        } else if (deleteBtn) {
            const bookId = deleteBtn.dataset.bookId;
            const bookTitle = deleteBtn.dataset.bookTitle;
            const confirm = await showCustomModal(`Confirmer la suppression du livre "${bookTitle}" ?`, { type: 'confirm' });
            if (confirm) {
                await deleteBook(bookId);
            }
        }
    });

    setInterval(loadBooks, 300000); // Actualiser toutes les 5 minutes
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
 * Charge la liste des livres depuis l'API.
 */
async function loadBooks() {
    addLoader(booksTableBody);
    try {
        const response = await apiClient.get('/api/admin/books.php?action=list');
        
        if (response.data.success) {
            allBooks = response.data.data;
            renderBooks(allBooks);
        } else {
            showCustomModal(`Erreur chargement livres: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
            booksTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-red-500">Erreur: ${response.data.message || 'Impossible de charger les livres.'}</td></tr>`;
        }
    } catch (error) {
        console.error("Erreur lors du chargement des livres:", error);
        showCustomModal("Une erreur est survenue lors du chargement des livres.", { type: 'alert' });
        booksTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-red-500">Erreur de connexion API.</td></tr>`;
    } finally {
        removeLoader(booksTableBody);
    }
}

/**
 * Charge la liste des auteurs pour le sélecteur d'auteur dans le formulaire.
 */
async function loadAuthorsForDropdown() {
    try {
        const response = await apiClient.get('/api/admin/authors.php?action=list'); // Utilisez l'API des auteurs

        if (response.data.success) {
            allAuthors = response.data.data;
            bookAuthorSelect.innerHTML = '<option value="">Sélectionner un auteur</option>';
            allAuthors.forEach(author => {
                const option = document.createElement('option');
                option.value = author.id;
                option.textContent = author.nom;
                bookAuthorSelect.appendChild(option);
            });
        } else {
            console.error("Erreur chargement auteurs:", response.data.message);
            showCustomModal(`Erreur chargement auteurs pour le formulaire: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors du chargement des auteurs pour le formulaire:", error);
        showCustomModal("Une erreur est survenue lors du chargement des auteurs pour le formulaire.", { type: 'alert' });
    }
}

/**
 * Affiche les livres dans le tableau.
 * @param {Array<Object>} booksToDisplay - Les livres à afficher.
 */
function renderBooks(booksToDisplay) {
    booksTableBody.innerHTML = '';
    if (booksToDisplay.length === 0) {
        booksTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-gray-500">Aucun livre trouvé.</td></tr>`;
        return;
    }

    booksToDisplay.forEach(book => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="py-3 px-2">${book.id}</td>
            <td class="py-3 px-2">${book.titre}</td>
            <td class="py-3 px-2">${book.auteur_nom}</td>
            <td class="py-3 px-2">${book.isbn || 'N/A'}</td>
            <td class="py-3 px-2">
                <span class="${book.disponible ? 'text-green-600' : 'text-red-600'} font-medium">
                    ${book.disponible ? 'Oui' : 'Non'}
                </span>
            </td>
            <td class="py-3 px-2 flex space-x-2">
                <button class="action-button bg-blue-100 text-blue-700 hover:bg-blue-200 text-sm edit-book-btn" data-book-id="${book.id}">Éditer</button>
                <button class="action-button bg-red-100 text-red-700 hover:bg-red-200 text-sm delete-book-btn" data-book-id="${book.id}" data-book-title="${book.titre}">Supprimer</button>
            </td>
        `;
        booksTableBody.appendChild(row);
    });
}

/**
 * Gère la recherche de livres.
 */
function handleSearch() {
    const searchTerm = bookSearchInput.value.toLowerCase();
    const filteredBooks = allBooks.filter(book =>
        book.titre.toLowerCase().includes(searchTerm) ||
        book.auteur_nom.toLowerCase().includes(searchTerm) ||
        (book.isbn && book.isbn.toLowerCase().includes(searchTerm))
    );
    renderBooks(filteredBooks);
}

/**
 * Ouvre la modale pour ajouter ou modifier un livre.
 * @param {string} [bookId=null] - L'ID du livre à modifier, ou null pour un nouveau livre.
 */
async function openBookModal(bookId = null) {
    bookForm.reset(); // Réinitialiser le formulaire
    bookIdInput.value = ''; // Vider l'ID caché

    if (bookId) {
        bookModalTitle.textContent = 'Modifier le livre';
        addLoader(bookModal);
        try {
            const response = await apiClient.get(`/api/admin/books.php?action=details&id=${bookId}`);
            if (response.data.success) {
                const book = response.data.data;
                bookIdInput.value = book.id;
                bookTitleInput.value = book.titre;
                bookAuthorSelect.value = book.auteur_id; // Assurez-vous que l'ID de l'auteur correspond
                bookIsbnInput.value = book.isbn || '';
                bookDescriptionInput.value = book.description || '';
                bookAvailableCheckbox.checked = book.disponible;
            } else {
                showCustomModal(`Erreur chargement détails livre: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
                closeBookModal();
                return;
            }
        } catch (error) {
            console.error("Erreur lors du chargement des détails du livre:", error);
            showCustomModal("Une erreur est survenue lors du chargement des détails du livre.", { type: 'alert' });
            closeBookModal();
            return;
        } finally {
            removeLoader(bookModal);
        }
    } else {
        bookModalTitle.textContent = 'Ajouter un nouveau livre';
    }
    bookModal.classList.remove('hidden');
}

/**
 * Ferme la modale du livre.
 */
function closeBookModal() {
    bookModal.classList.add('hidden');
}

/**
 * Gère la soumission du formulaire d'ajout/modification de livre.
 * @param {Event} event - L'événement de soumission.
 */
async function handleBookFormSubmit(event) {
    event.preventDefault();
    addLoader(bookModal);

    const bookData = {
        titre: bookTitleInput.value,
        auteur_id: bookAuthorSelect.value,
        isbn: bookIsbnInput.value,
        description: bookDescriptionInput.value,
        disponible: bookAvailableCheckbox.checked ? 1 : 0 // Convertir en 0 ou 1 pour la BDD
    };

    try {
        let response;
        if (bookIdInput.value) {
            // Modification
            bookData.id = bookIdInput.value;
            response = await apiClient.put(`/api/admin/books.php?action=update`, {body: bookData});
        } else {
            // Ajout
            response = await apiClient.post('/api/admin/books.php?action=add', {body: bookData});
        }

        if (response.data.success) {
            showCustomModal(`Livre ${bookIdInput.value ? 'modifié' : 'ajouté'} avec succès !`, { type: 'success' });
            closeBookModal();
            await loadBooks(); // Recharger la liste
        } else {
            showCustomModal(`Erreur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors de l'enregistrement du livre:", error);
        showCustomModal("Une erreur est survenue lors de l'enregistrement du livre.", { type: 'alert' });
    } finally {
        removeLoader(bookModal);
    }
}

/**
 * Supprime un livre.
 * @param {string} bookId - L'ID du livre à supprimer.
 */
async function deleteBook(bookId) {
    addLoader(booksTableBody);
    try {
        const response = await apiClient.delete(`/api/admin/books.php?action=delete&id=${bookId}`);
        if (response.data.success) {
            showCustomModal('Livre supprimé avec succès !', { type: 'success' });
            await loadBooks(); // Recharger la liste
        } else {
            showCustomModal(`Erreur lors de la suppression: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors de la suppression du livre:", error);
        showCustomModal("Une erreur est survenue lors de la suppression du livre.", { type: 'alert' });
    } finally {
        removeLoader(booksTableBody);
    }
}
