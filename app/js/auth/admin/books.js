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
// Sélecteurs pour les éléments de la modale et du formulaire
const bookYearInput = document.getElementById('bookYear'); // Champ pour l'année académique

bookYearInput.maxLength = 9; // Limite de caractères pour l'année académique
bookYearInput.placeholder = 'Ex: 2023-2024'; // Placeholder pour l'année académique
bookYearInput.title = 'Entrez l\'année académique au format YYYY-YYYY'; // Info-bulle pour l'année académique
bookYearInput.pattern = '^(\\d{4}-\\d{4})$'; // Regex pour valider le format de l'année académique
bookYearInput.addEventListener('input', ()=> {
    // Ajouter un tiret au 4ème caractère si c'est un nombre
    if (bookYearInput.value.length === 4 && !bookYearInput.value.includes('-')) {
        bookYearInput.value += '-';
        // Ajouter l'année suivante si l'année de début est valide
        const startYear = parseInt(bookYearInput.value, 10);
        if (!isNaN(startYear)) {
            const nextYear = startYear + 1;
            bookYearInput.value += nextYear.toString();
        }
    }
    // Si la longueur est 9 vérifier si l'année de fin est de 1 supérieur à l'année de début
    if (bookYearInput.value.length === 9) {
        const years = bookYearInput.value.split('-');
        if (years.length === 2 && years[0].length === 4 && years[1].length === 4) {
            const startYear = parseInt(years[0], 10);
            const endYear = parseInt(years[1], 10);
            if (endYear !== startYear + 1) {
                showCustomModal('L\'année de fin doit être l\'année de début + 1.', { type: 'alert' });
            }
        }
    }
});
// Modale et éléments du formulaire
const bookModal = document.getElementById('bookModal');
const bookModalTitle = document.getElementById('bookModalTitle');
const bookAuthorSelect = document.getElementById('bookAuthor');
const bookAvailableCheckbox = document.getElementById('bookAvailable');

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
        const response = await apiClient.get('/api/admin/books?action=list');

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
        const response = await apiClient.get('/api/admin/authors?action=list'); // Utilisez l'API des auteurs

        if (response.data.success) {
            allAuthors = response.data.data;
            
            allAuthors.forEach(author => {
                const option = document.createElement('option');
                option.value = author.authorid;
                option.textContent = author.pseudo || author.nom_complet || 'Anonyme';
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
            <td class="py-3 px-2">${book.emplacement || 'N/A'}</td>
            <td class="py-3 px-2">${book.annee_academique || 'N/A'}</td> <!-- Changed from book.year -->
            <td class="py-3 px-2">
                <span class="${book.disponible ? 'text-green-600' : 'text-red-600'} font-medium">
                    ${book.disponible ? 'Oui' : 'Non'}
                </span>
            </td>
            <td class="py-3 px-2 flex space-x-2">
                <a href="/books?id=${book.id}" class="action-button bg-blue-100 text-blue-700 hover:bg-blue-200 text-sm info-book-btn" data-book-id="${book.id}">Info</a>
                <button class="action-button bg-yellow-100 text-yellow-700 hover:bg-yellow-200 text-sm edit-book-btn" data-book-id="${book.id}">Éditer</button>
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
        (book.isbn && book.isbn.toLowerCase().includes(searchTerm)) ||
        (book.annee_academique && book.annee_academique.toLowerCase().includes(searchTerm)) // Added academic year to search
    );
    renderBooks(filteredBooks);
}

/**
 * Ouvre la modale pour ajouter ou modifier un livre.
 * @param {string} [bookId=null] - L'ID du livre à modifier, ou null pour un nouveau livre.
 */
async function openBookModal(bookId = null) {
    bookModal.classList.remove('hidden');
    const form = document.getElementById('bookForm');
    const bookIdInput = form.querySelector('#bookId');


    // bookForm.reset(); // Réinitialiser le formulaire
    bookIdInput.value = bookId ?? ''; // Vider l'ID caché

    if (bookId) {
        bookModalTitle.textContent = 'Modifier le livre';
        addLoader(bookModal, "absolute top-[50%] left-[50%] translate-x-[-50%] translate-y-[-50%]");
        bookModal.classList.add('opacity-[0.75]'); // Ajouter une classe pour indiquer le chargement
        bookModal.classList.add('pointer-events-none'); // Désactiver les interactions pendant le chargement
        try {
            const response = await apiClient.get(`/api/admin/books?action=details&id=${bookId}`);


            if (response.data.success) {
                const data = response.data.data;
                const book = {
                    id: data.id,
                    titre: data.titre,
                    auteur_id: data.auteur_id,
                    emplacement: data.emplacement || '',
                    isbn: data.isbn || '',
                    description: data.description || '',
                    disponible: data.disponible || false,
                    annee_academique: data.annee_academique || '' // Ensure this field is populated
                }
                updateBookForm(book);



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
            bookModal.classList.remove('opacity-[0.75]'); // Retirer la classe de chargement
            bookModal.classList.remove('pointer-events-none'); // Réactiver les interactions
        }
    } else {
        bookModalTitle.textContent = 'Ajouter un nouveau livre';
        const emptyBook = {
            id: '',
            titre: '',
            auteur_id: '',
            emplacement: '',
            isbn: '',
            description: '',
            disponible: true, // Par défaut, un nouveau livre est disponible
            annee_academique: '' // Initialize academic year for new books
        };
        updateBookForm(emptyBook);
        bookAvailableCheckbox.disabled = false; // Assurez-vous que la case à cocher est
    }
    const bookFormEl = document.getElementById('bookForm');
    bookFormEl.addEventListener('submit', handleBookFormSubmit);
    const cancelBookModalBtn = bookModal.querySelector('#cancelBookModalBtn');
    cancelBookModalBtn.addEventListener('click', closeBookModal);
}

/**
 * Ferme la modale du livre.
 */
function closeBookModal() {
    bookModal.classList.add('hidden');
}

function updateBookForm(book) {
    const form = document.getElementById('bookForm');
    const bookIdInput = form.querySelector('#bookId');
    const bookTitleInput = form.querySelector('#bookTitle');
    const bookAuthorSelect = form.querySelector('#bookAuthor');
    const bookPositionInput = form.querySelector('#bookPosition');
    const bookYearInput = form.querySelector('#bookYear'); // Academic year input
    const bookDescriptionInput = form.querySelector('#bookDescription');
    const bookAvailableCheckbox = form.querySelector('#bookAvailable');

    bookIdInput.value = book.id;
    bookTitleInput.value = book.titre;
    bookAuthorSelect.value = book.auteur_id;
    bookPositionInput.value = book.emplacement || '';
    bookYearInput.value = book.annee_academique || ''; // Use annee_academique here
    bookDescriptionInput.value = book.description || '';
    bookAvailableCheckbox.checked = book.disponible || false;
    bookAvailableCheckbox.disabled = false; // Assurez-vous que la case à cocher est activée
}

/**
 * Gère la soumission du formulaire d'ajout/modification de livre.
 * @param {Event} event - L'événement de soumission.
 */
async function handleBookFormSubmit(event) {
    event.preventDefault();
    addLoader(bookModal, "absolute top-[50%] left-[50%] translate-x-[-50%] translate-y-[-50%]");
    bookModal.classList.add('opacity-[0.75]'); // Ajouter une classe pour indiquer le chargement
    bookModal.classList.add('pointer-events-none'); // Désactiver les interactions pendant le chargement

    const form = event.target;
    const bookIdInput = form.querySelector('#bookId');
    const bookTitleInput = form.querySelector('#bookTitle');
    const bookAuthorSelect = form.querySelector('#bookAuthor');
    const bookPositionInput = form.querySelector('#bookPosition');
    const bookYearInput = form.querySelector('#bookYear'); // Academic year input
    const bookDescriptionInput = form.querySelector('#bookDescription');
    const bookAvailableCheckbox = form.querySelector('#bookAvailable');

    // Frontend validation for academic year format and difference
    const academicYear = bookYearInput.value;
    const yearPattern = /^(\d{4})-(\d{4})$/;
    const match = academicYear.match(yearPattern);

    if (match) {
        const startYear = parseInt(match[1], 10);
        const endYear = parseInt(match[2], 10);
        if (endYear !== startYear + 1) {
            showCustomModal('L\'année de fin doit être l\'année de début + 1 (ex: 2023-2024).', { type: 'alert' });
            removeLoader(bookModal);
            bookModal.classList.remove('opacity-[0.75]');
            bookModal.classList.remove('pointer-events-none');
            return; // Stop form submission
        }
    } else if (academicYear !== '') { // Allow empty if not required, otherwise add an else for required
        showCustomModal('Le format de l\'année académique doit être YYYY-YYYY (ex: 2023-2024).', { type: 'alert' });
        removeLoader(bookModal);
        bookModal.classList.remove('opacity-[0.75]');
        bookModal.classList.remove('pointer-events-none');
        return; // Stop form submission
    }


    const bookData = {
        titre: bookTitleInput.value,
        auteur_id: bookAuthorSelect.value,
        annee_academique: bookYearInput.value, // Send academic year
        emplacement: bookPositionInput.value,
        description: bookDescriptionInput.value,
    };
    const initialBookData = {
        id: bookIdInput.value,
        titre: bookTitleInput.value,
        auteur_id: bookAuthorSelect.value,
        annee_academique: bookYearInput.value, // Initial academic year
        emplacement: bookPositionInput.value,
        description: bookDescriptionInput.value,
        disponible: bookAvailableCheckbox.checked
    }

    try {
        let response;
        if (bookIdInput.value) {
            // Modification
            bookData.id = bookIdInput.value;
            response = await apiClient.post(`/api/admin/books?action=update`, { body: bookData });

        } else {
            // Ajout
            response = await apiClient.post('/api/admin/books?action=add', { body: bookData });
        }

        if (response.data.success) {
            showCustomModal(`Livre ${bookIdInput.value ? 'modifié' : 'ajouté'} avec succès !`);

            closeBookModal();
            await loadBooks(); // Recharger la liste
        } else {
            showCustomModal(`Erreur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
            console.error("Erreur lors de l'enregistrement du livre:", response.data);
            // Réinitialiser le formulaire aux valeurs initiales
            updateBookForm(initialBookData);
            if (response.data.error && response.data.input) {
                console.error("Détails de l'erreur:", response.data.error, response.data.input);
                console.log(bookData);

            }
        }
    } catch (error) {
        console.error("Erreur lors de l'enregistrement du livre:", error);
        showCustomModal("Une erreur est survenue lors de l'enregistrement du livre.", { type: 'alert' });
        // Réinitialiser le formulaire aux valeurs initiales
        updateBookForm(initialBookData);
    } finally {
        removeLoader(bookModal);
        bookModal.classList.remove('opacity-[0.75]'); // Ajouter une classe pour indiquer le chargement
        bookModal.classList.remove('pointer-events-none'); // Désactiver les interactions pendant le chargement
    }
}

/**
 * Supprime un livre.
 * @param {string} bookId - L'ID du livre à supprimer.
 */
async function deleteBook(bookId) {
    addLoader(booksTableBody, "mx-auto");
    try {
        const response = await apiClient.delete(`/api/admin/books?action=delete&id=${bookId}`);
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
