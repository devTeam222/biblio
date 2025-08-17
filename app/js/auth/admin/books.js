import { apiClient } from "../../util/ocho-api.js";
import { TimeFormatter } from "../../util/formatter.js";
import { showCustomModal, addLoader, removeLoader, isAuth, updateNavBar, debounce } from "../../util/utils.js";
import { createDropdownSearch } from "../../components/DropdownSearch.js"; // Importez le nouveau composant

window.modalContainer = document.getElementById('modalContainer');

const userNameDisplay = document.getElementById('userNameDisplay');
const notificationCountEl = document.getElementById('notificationCount');
const adminAvatarEl = document.getElementById('adminAvatar');
const lastUpdateTimeEl = document.getElementById('lastUpdateTime');
const booksTableBody = document.querySelector('#booksTable tbody');
const bookSearchInput = document.getElementById('bookSearchInput');
const addBookBtn = document.getElementById('addBookBtn');

// Éléments spécifiques à la modale du livre et au formulaire multi-étapes
const bookIdInput = document.getElementById('bookId');
const bookTitleInput = document.getElementById('bookTitle');
// Les éléments de sélection seront des conteneurs DIV pour DropdownSearch
const bookAuthorSelectContainer = document.getElementById('bookAuthorSelectContainer');
const bookCategorySelectContainer = document.getElementById('bookCategorySelectContainer');
const bookAcademicYearSelectContainer = document.getElementById('bookAcademicYearSelectContainer');
const bookStudyAreaSelectContainer = document.getElementById('bookStudyAreaSelectContainer');
const bookISBNInput = document.getElementById('bookISBN');
const bookDescriptionTextarea = document.getElementById('bookDescription');
const bookLocationInput = document.getElementById('bookLocation');
const bookAvailableCheckbox = document.getElementById('bookAvailable');

// Modale et éléments du formulaire des livres
const bookModal = document.getElementById('bookModal');
const bookModalTitle = document.getElementById('bookModalTitle');
const bookForm = document.getElementById('bookForm');
const cancelBookModalBtn = document.getElementById('cancelBookModalBtn');

// Éléments spécifiques au formulaire multi-étapes
const bookFormStep1 = document.getElementById('bookFormStep1');
const bookFormStep2 = document.getElementById('bookFormStep2');
const bookFormStep3 = document.getElementById('bookFormStep3');
const nextStepBtn = document.getElementById('nextStepBtn');
const prevStepBtn = document.getElementById('prevStepBtn');
const submitBookBtn = document.getElementById('submitBookBtn');
const stepIndicatorsContainer = document.getElementById('stepIndicatorsContainer');


// Nouveaux éléments pour la gestion des zones d'étude sur la page des livres
const booksTabBtn = document.getElementById('booksTabBtn');
const studyAreasTabBtn = document.getElementById('studyAreasTabBtn');
const booksContent = document.getElementById('booksContent');
const studyAreasContent = document.getElementById('studyAreasContent');
const studyAreasTableBody = document.querySelector('#studyAreasTable tbody');

// Modale et formulaire des zones d'étude
const studyAreaModal = document.getElementById('studyAreaModal');
const studyAreaModalTitle = document.getElementById('studyAreaModalTitle');
const studyAreaForm = document.getElementById('studyAreaForm');
const cancelStudyAreaModalBtn = document.getElementById('cancelStudyAreaModalBtn');
const studyAreaIdInput = document.getElementById('studyAreaId');
const studyAreaNameInput = document.getElementById('studyAreaName');
const studyAreaDescriptionTextarea = document.getElementById('studyAreaDescription');
const studyAreaLatitudeInput = document.getElementById('studyAreaLatitude');
const studyAreaLongitudeInput = document.getElementById('studyAreaLongitude');

const studyAreasPrevPageBtn = document.getElementById('studyAreasPrevPageBtn');
const studyAreasNextPageBtn = document.getElementById('studyAreasNextPageBtn');
const studyAreasPageInfo = document.getElementById('studyAreasPageInfo');

const confirmationModal = document.getElementById('confirmationModal');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

let currentItemToDelete = null; // Pour le suivi de l'élément à supprimer
let currentDeleteType = null; // Pour le suivi du type d'élément à supprimer ('book' ou 'study_area')

let allBooks = []; // Pour stocker tous les livres et permettre la recherche côté client
let studyAreasState = { // État pour les zones d'étude
    data: [],
    currentPage: 1,
    totalPages: 1,
    searchQuery: ''
};

// Instances de DropdownSearch
let authorDropdown;
let categoryDropdown;
let academicYearDropdown;
let studyAreaDropdown;

let currentStep = 1;
const totalSteps = 3;
export const itemsPerPage = 10; // Définir pour la pagination des tables

let currentActiveTab = sessionStorage.getItem('books-admin-tab') ?? 'books';


document.addEventListener('DOMContentLoaded', async () => {
    const authStatus = await isAuth();

    if (!authStatus.success || !authStatus.user.role || authStatus.user.role !== 'admin') {
        await showCustomModal('Accès non autorisé. Vous devez être un administrateur pour accéder à cette page.', { type: 'alert' });
        window.location.href = '/login';
        return;
    }
    userNameDisplay.textContent = authStatus.user.name || 'Admin';
    updateAdminAvatar(authStatus.user.name);
    updateNavBar('admin'); // Mettre en surbrillance le lien actif
    updateLastModifiedTime();

    // Initialiser les dropdowns de recherche avec listType et apiBaseUrl
    const listApiBaseUrl = '/api/admin/lists';

    authorDropdown = createDropdownSearch({
        targetElementId: 'bookAuthorSelectContainer',
        listType: 'authors',
        apiBaseUrl: listApiBaseUrl,
        idField: 'authorid',
        textField: 'nom',
        placeholder: 'Sélectionner un auteur',
        searchParam: 'search'
    });

    categoryDropdown = createDropdownSearch({
        targetElementId: 'bookCategorySelectContainer',
        listType: 'departement',
        apiBaseUrl: listApiBaseUrl,
        idField: 'id',
        textField: 'name',
        placeholder: 'Sélectionner un département',
        searchParam: 'search'
    });

    academicYearDropdown = createDropdownSearch({
        targetElementId: 'bookAcademicYearSelectContainer',
        listType: 'academic_years',
        apiBaseUrl: listApiBaseUrl,
        idField: 'id',
        textField: 'annee_academique',
        placeholder: 'Sélectionner une année académique',
        searchParam: 'search'
    });

    studyAreaDropdown = createDropdownSearch({
        targetElementId: 'bookStudyAreaSelectContainer',
        listType: 'study_areas',
        apiBaseUrl: listApiBaseUrl,
        idField: 'id',
        textField: 'name',
        placeholder: 'Sélectionner une zone d\'étude',
        searchParam: 'search'
    });

    // Écouteur pour la logique de formatage de l'année académique
    setTimeout(() => {
        const academicYearSearchInput = document.getElementById('bookAcademicYearSelectContainer')?.querySelector('input[type="text"]');
        if (academicYearSearchInput) {
            academicYearSearchInput.maxLength = 9;
            academicYearSearchInput.placeholder = 'Ex: 2023-2024';
            academicYearSearchInput.title = 'Entrez l\'année académique au format YYYY-YYYY';

            academicYearSearchInput.addEventListener('input', () => {
                let value = academicYearSearchInput?.value;
                value = value.replace(/[^0-9-]/g, '');

                if (value.length === 4 && !value.includes('-')) {
                    value += '-';
                    academicYearSearchInput.value = value;
                    const startYear = parseInt(value, 10);
                    if (!isNaN(startYear)) {
                        const nextYear = startYear + 1;
                        if (academicYearSearchInput?.value.length === 5) {
                            academicYearSearchInput.value += nextYear.toString();
                        }
                    }
                }
                if (value.length === 9) {
                    const years = value.split('-');
                    if (years.length === 2 && years[0].length === 4 && years[1].length === 4) {
                        const startYear = parseInt(years[0], 10);
                        const endYear = parseInt(years[1], 10);
                        if (endYear !== startYear + 1) {
                            showCustomModal('L\'année de fin doit être l\'année de début + 1.', { type: 'alert' });
                        }
                    }
                }
            });
        }
    }, 100);

    // Gestion des onglets
    booksTabBtn.addEventListener('click', () => switchTab('books'));
    studyAreasTabBtn.addEventListener('click', () => switchTab('study_areas'));

    // Charger le bon onglet au démarrage
    await switchTab(currentActiveTab);

    // Événements pour la recherche et l'ajout (bouton "Ajouter" commun)
    const debouncedSearch = debounce((e) => {
        const searchQuery = e.target.value;
        if (currentActiveTab === 'books') {
            handleSearch(searchQuery); // La recherche des livres utilise la logique locale
        } else if (currentActiveTab === 'study_areas') {
            loadStudyAreas(searchQuery); // La recherche des zones d'étude utilise l'API
        }
    }, 500);
    document.getElementById('entitySearchInput').addEventListener('input', debouncedSearch);

    document.getElementById('addEntityBtn').addEventListener('click', () => {
        if (currentActiveTab === 'books') {
            openBookModal();
        } else if (currentActiveTab === 'study_areas') {
            openStudyAreaModal();
        }
    });


    // Délégation d'événements pour les boutons d'édition et de suppression des LIVRES
    booksTableBody.addEventListener('click', async (event) => {
        const editBtn = event.target.closest('.edit-book-btn');
        const deleteBtn = event.target.closest('.delete-book-btn');

        if (editBtn) {
            const bookId = editBtn.dataset.bookId;
            await openBookModal(bookId);
        } else if (deleteBtn) {
            const bookId = deleteBtn.dataset.bookId;
            const bookTitle = deleteBtn.dataset.bookTitle;
            showConfirmationModal('book', bookId, `le livre "${bookTitle}"`);
        }
    });

    // Délégation d'événements pour les boutons d'édition et de suppression des ZONES D'ÉTUDE
    studyAreasTableBody.addEventListener('click', async (event) => {
        const editBtn = event.target.closest('.edit-study-area-btn');
        const deleteBtn = event.target.closest('.delete-study-area-btn');

        if (editBtn) {
            const studyAreaId = editBtn.dataset.studyAreaId;
            await openStudyAreaModal(studyAreaId);
        } else if (deleteBtn) {
            const studyAreaId = deleteBtn.dataset.studyAreaId;
            const studyAreaName = deleteBtn.dataset.studyAreaName;
            showConfirmationModal('study_area', studyAreaId, `la zone d'étude "${studyAreaName}"`);
        }
    });

    // Écouteurs pour la modale de confirmation
    confirmDeleteBtn.addEventListener('click', async () => {
        if (currentDeleteType === 'book' && currentItemToDelete) {
            await deleteBook(currentItemToDelete);
        } else if (currentDeleteType === 'study_area' && currentItemToDelete) {
            await deleteStudyArea(currentItemToDelete);
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

    // Gestion des événements pour le formulaire multi-étapes des livres
    nextStepBtn.addEventListener('click', () => {
        if (validateCurrentStep()) {
            currentStep++;
            showStep(currentStep);
        }
    });
    prevStepBtn.addEventListener('click', () => {
        currentStep--;
        showStep(currentStep);
    });

    bookForm.addEventListener('submit', handleBookFormSubmit);
    cancelBookModalBtn.addEventListener('click', closeBookModal);

    // Gestion des événements pour le formulaire des zones d'étude
    studyAreaForm.addEventListener('submit', handleStudyAreaFormSubmit);
    cancelStudyAreaModalBtn.addEventListener('click', closeStudyAreaModal);

    // Pagination pour les zones d'étude
    studyAreasPrevPageBtn.addEventListener('click', () => {
        if (studyAreasState.currentPage > 1) {
            loadStudyAreas(studyAreasState.searchQuery, studyAreasState.currentPage - 1);
        }
    });
    studyAreasNextPageBtn.addEventListener('click', () => {
        if (studyAreasState.currentPage < studyAreasState.totalPages) {
            loadStudyAreas(studyAreasState.searchQuery, studyAreasState.currentPage + 1);
        }
    });

    setInterval(updateLastModifiedTime, 60000); // Actualiser le temps toutes les minutes
    setInterval(() => {
        if (currentActiveTab === 'books') {
            loadBooks(); // Actualiser les livres toutes les 5 minutes
        } else if (currentActiveTab === 'study_areas') {
            loadStudyAreas(); // Actualiser les zones d'étude toutes les 5 minutes
        }
    }, 300000);
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
 * Gère le changement d'onglet.
 * @param {string} tabName - 'books' ou 'study_areas'.
 */
async function switchTab(tabName) {
    currentActiveTab = tabName;
    sessionStorage.setItem('books-admin-tab', tabName);

    // Mettre à jour les classes actives des boutons d'onglet
    booksTabBtn.classList.remove('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
    booksTabBtn.classList.add('bg-gray-100', 'text-gray-700');
    studyAreasTabBtn.classList.remove('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
    studyAreasTabBtn.classList.add('bg-gray-100', 'text-gray-700');

    // Cacher tous les contenus d'onglet
    booksContent.classList.add('hidden');
    studyAreasContent.classList.add('hidden');

    // Afficher le contenu de l'onglet actif et mettre à jour le bouton "Ajouter"
    if (tabName === 'books') {
        booksTabBtn.classList.add('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
        booksContent.classList.remove('hidden');
        document.getElementById('entitySearchInput').placeholder = 'Rechercher par titre, auteur, ISBN...';
        document.getElementById('addEntityBtn').textContent = 'Ajouter un livre';
        // document.getElementById('addEntityBtn').querySelector('svg').outerHTML = `
        //     <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
        //         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        //         class="lucide lucide-plus-icon lucide-plus">
        //         <path d="M5 12h14" />
        //         <path d="M12 5v14" />
        //     </svg> Ajouter un livre
        // `;
        await loadBooks();
    } else if (tabName === 'study_areas') {
        studyAreasTabBtn.classList.add('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
        studyAreasContent.classList.remove('hidden');
        document.getElementById('entitySearchInput').placeholder = 'Rechercher par nom, description...';
        document.getElementById('addEntityBtn').textContent = 'Ajouter une zone d\'étude';
        // document.getElementById('addEntityBtn').querySelector('svg').outerHTML = `
        //     <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
        //         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        //         class="lucide lucide-plus-icon lucide-plus">
        //         <path d="M5 12h14" />
        //         <path d="M12 5v14" />
        //     </svg> Ajouter une zone d'étude
        // `;
        await loadStudyAreas();
    }
    // Réinitialiser le champ de recherche quand on change d'onglet
    document.getElementById('entitySearchInput').value = '';
}


// ----------------------------------------------------
// Fonctions de gestion des LIVRES
// ----------------------------------------------------

/**
 * Charge la liste des livres depuis l'API.
 */
async function loadBooks() {
    addLoader(booksTableBody);
    try {
        const response = await apiClient.get(`/api/admin/books?action=list&search=${encodeURIComponent(bookSearchInput?.value || '')}`);

        if (response.data.success) {
            allBooks = response.data.data;
            renderBooks(allBooks);
        } else {
            showCustomModal(`Erreur chargement livres: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
            booksTableBody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-red-500">Erreur: ${response.data.message || 'Impossible de charger les livres.'}</td></tr>`;
        }
    } catch (error) {
        console.error("Erreur lors du chargement des livres:", error);
        showCustomModal("Une erreur est survenue lors du chargement des livres.", { type: 'alert' });
        booksTableBody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-red-500">Erreur de connexion API.</td></tr>`;
    } finally {
        removeLoader(booksTableBody);
    }
}

/**
 * Affiche les livres dans le tableau.
 * @param {Array<Object>} booksToDisplay - Les livres à afficher.
 */
function renderBooks(booksToDisplay) {
    booksTableBody.innerHTML = '';
    if (booksToDisplay.length === 0) {
        booksTableBody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-gray-500">Aucun livre trouvé.</td></tr>`;
        return;
    }

    booksToDisplay.forEach(book => {
        const row = document.createElement('tr');
        const availabilityClass = book.disponible ? 'text-green-600' : 'text-red-600';
        const availabilityText = book.disponible ? 'Oui' : 'Non';

        row.innerHTML = `
            <td class="py-3 px-2">${book.id}</td>
            <td class="py-3 px-2 line-clamp-3 text-ellipsis overflow-hidden">${book.titre}</td>
            <td class="py-3 px-2">${book.auteur_nom || 'N/A'}</td>
            <td class="py-3 px-2">${book.emplacement || 'N/A'}</td>
            <td class="py-3 px-2">${book.annee_academique || 'N/A'}</td>
            <td class="py-3 px-2">${book.study_area_name || 'N/A'}</td> 
            <td class="py-3 px-2">
                <span class="${availabilityClass} font-medium">
                    ${availabilityText}
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
 * Gère la recherche de livres (filtrage côté client).
 */
function handleSearch(searchTerm) {
    const lowerCaseSearchTerm = searchTerm.toLowerCase();
    const filteredBooks = allBooks.filter(book =>
        book.titre.toLowerCase().includes(lowerCaseSearchTerm) ||
        (book.auteur_nom && book.auteur_nom.toLowerCase().includes(lowerCaseSearchTerm)) ||
        (book.isbn && book.isbn.toLowerCase().includes(lowerCaseSearchTerm)) ||
        (book.annee_academique && book.annee_academique.toLowerCase().includes(lowerCaseSearchTerm)) ||
        (book.study_area_name && book.study_area_name.toLowerCase().includes(lowerCaseSearchTerm))
    );
    renderBooks(filteredBooks);
}

/**
 * Gère l'affichage des différentes étapes du formulaire et la visibilité des boutons de navigation.
 * @param {number} stepNum - Le numéro de l'étape à afficher.
 */
function showStep(stepNum) {
    // Cacher toutes les étapes
    bookFormStep1.classList.add('hidden');
    bookFormStep2.classList.add('hidden');
    bookFormStep3.classList.add('hidden');

    // Afficher l'étape actuelle
    switch (stepNum) {
        case 1:
            bookFormStep1.classList.remove('hidden');
            break;
        case 2:
            bookFormStep2.classList.remove('hidden');
            break;
        case 3:
            bookFormStep3.classList.remove('hidden');
            break;
    }

    // Gérer la visibilité des boutons de navigation
    prevStepBtn.classList.toggle('hidden', stepNum === 1);
    nextStepBtn.classList.toggle('hidden', stepNum === totalSteps);
    submitBookBtn.classList.toggle('hidden', stepNum !== totalSteps);

    // Mettre à jour les indicateurs de progression
    updateStepIndicators(stepNum);
}

/**
 * Met à jour les indicateurs visuels de l'étape actuelle.
 * @param {number} currentStep - Le numéro de l'étape actuelle.
 */
function updateStepIndicators(currentStep) {
    stepIndicatorsContainer.innerHTML = '';
    for (let i = 1; i <= totalSteps; i++) {
        const indicator = document.createElement('span');
        indicator.className = `h-3 w-3 rounded-full mx-1 ${i === currentStep ? 'bg-indigo-600' : 'bg-gray-300'}`;
        stepIndicatorsContainer.appendChild(indicator);
    }
}

/**
 * Valide les champs de l'étape actuelle avant de passer à la suivante.
 * @returns {boolean} - True si l'étape est valide, false sinon.
 */
function validateCurrentStep() {
    let isValid = true;
    let errorMessage = '';

    switch (currentStep) {
        case 1:
            if (!bookTitleInput?.value.trim()) {
                errorMessage = 'Le titre du livre est requis.';
                isValid = false;
            } else if (!authorDropdown.getValue()) {
                errorMessage = 'L\'auteur est requis.';
                isValid = false;
            }
            break;
        case 2:
            const academicYearTypedValue = academicYearDropdown.getTypedValue().trim();
            const academicYearSelectedId = academicYearDropdown.getValue();
            if (!academicYearSelectedId && academicYearTypedValue) {
                const yearPattern = /^(\d{4})-(\d{4})$/;
                if (!yearPattern.test(academicYearTypedValue)) {
                    errorMessage = 'Le format de l\'année académique est invalide. Utilisez YYYY-YYYY.';
                    isValid = false;
                } else {
                    const years = academicYearTypedValue.split('-');
                    const startYear = parseInt(years[0], 10);
                    const endYear = parseInt(years[1], 10);
                    if (endYear !== startYear + 1) {
                        errorMessage = 'L\'année de fin doit être l\'année de début + 1.';
                        isValid = false;
                    }
                }
            }
            break;
        case 3:
            break;
    }

    if (!isValid) {
        showCustomModal(errorMessage, { type: 'alert' });
    }
    return isValid;
}

/**
 * Ouvre la modale pour ajouter ou modifier un livre.
 * @param {string} [bookId=null] - L'ID du livre à modifier, ou null pour un nouveau livre.
 */
async function openBookModal(bookId = null) {
    bookModal.classList.remove('hidden');
    bookForm.reset();
    bookIdInput.value = bookId ?? '';

    currentStep = 1;
    showStep(currentStep);

    authorDropdown.clear();
    categoryDropdown.clear();
    academicYearDropdown.clear();
    studyAreaDropdown.clear();

    const academicYearSearchInput = document.getElementById('bookAcademicYearSelectContainer').querySelector('input[type="text"]');
    if (academicYearSearchInput) {
        academicYearSearchInput.value = '';
    }

    if (bookId) {
        bookModalTitle.textContent = 'Modifier le livre';
        addLoader(bookModal, "absolute top-[50%] left-[50%] translate-x-[-50%] translate-y-[-50%]");
        bookModal.classList.add('opacity-[0.75]', 'pointer-events-none');
        try {
            const response = await apiClient.get(`/api/admin/books?action=details&id=${bookId}`);

            if (response.data.success) {
                const book = response.data.data;
                bookTitleInput.value = book.titre || '';
                authorDropdown.setValue(book.auteur_id);
                categoryDropdown.setValue(book.departement_id);

                if (book.annee_id) {
                    academicYearDropdown.setValue(book.annee_id);
                } else if (book.annee_academique && academicYearSearchInput) {
                    academicYearDropdown.clear();
                    academicYearSearchInput.value = book.annee_academique;
                } else {
                    academicYearDropdown.clear();
                }

                studyAreaDropdown.setValue(book.study_area_id);
                bookISBNInput.value = book.isbn || '';
                bookDescriptionTextarea.value = book.descr || '';
                bookLocationInput.value = book.emplacement || '';
                bookAvailableCheckbox.checked = book.disponible;
            } else {
                showCustomModal(`Erreur chargement détails livre: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
                closeBookModal(); // Utilisez closeBookModal
            }
        } catch (error) {
            console.error("Erreur lors du chargement des détails du livre:", error);
            showCustomModal("Une erreur est survenue lors du chargement des détails du livre.", { type: 'alert' });
            closeBookModal(); // Utilisez closeBookModal
        } finally {
            removeLoader(bookModal);
            bookModal.classList.remove('opacity-[0.75]', 'pointer-events-none');
        }
    } else {
        bookModalTitle.textContent = 'Ajouter un nouveau livre';
        bookTitleInput.value = '';
        bookISBNInput.value = '';
        bookDescriptionTextarea.value = '';
        bookLocationInput.value = '';
        bookAvailableCheckbox.checked = true;
    }
}

/**
 * Ferme la modale du livre.
 */
function closeBookModal() {
    bookModal.classList.add('hidden');
    bookForm.reset();
    bookIdInput.value = '';
    authorDropdown.clear();
    categoryDropdown.clear();
    academicYearDropdown.clear();
    studyAreaDropdown.clear();
    bookAvailableCheckbox.checked = true;
    bookModalTitle.textContent = 'Ajouter un nouveau livre';
    currentStep = 1;
    showStep(currentStep);
    bookModal.classList.remove('opacity-[0.75]', 'pointer-events-none');
    removeLoader(bookModal);
}

/**
 * Gère la soumission du formulaire d'ajout/modification de livre.
 * @param {Event} event - L'événement de soumission.
 */
async function handleBookFormSubmit(event) {
    event.preventDefault();

    if (!validateCurrentStep()) {
        return;
    }

    addLoader(bookModal, "absolute top-[50%] left-[50%] translate-x-[-50%] translate-y-[-50%]");
    bookModal.classList.add('opacity-[0.75]', 'pointer-events-none');

    let academicYearToSend = null;
    const academicYearSelectedId = academicYearDropdown.getValue();
    const academicYearTypedValue = academicYearDropdown.getTypedValue().trim();

    if (academicYearSelectedId) {
        const allData = academicYearDropdown.getAllData();
        const selectedYear = allData.find(y => String(y.id) === String(academicYearSelectedId));
        academicYearToSend = selectedYear?.annee_academique || null;

    } else if (academicYearTypedValue) {
        academicYearToSend = academicYearTypedValue;
    }

    const bookData = {
        id: bookIdInput?.value || null,
        titre: bookTitleInput?.value,
        auteur_id: authorDropdown.getValue(),
        departement_id: categoryDropdown.getValue(),
        annee_academique: academicYearToSend,
        study_area_id: studyAreaDropdown.getValue(),
        isbn: bookISBNInput?.value,
        descr: bookDescriptionTextarea.value,
        emplacement: bookLocationInput?.value,
        disponible: bookAvailableCheckbox.checked
    };

    try {
        let response;
        if (bookData.id) {
            response = await apiClient.post(`/api/admin/books?action=update`, { body: bookData });
        } else {
            response = await apiClient.post('/api/admin/books?action=add', { body: bookData });
        }

        if (response.data.success) {
            showCustomModal(`Livre ${bookData.id ? 'modifié' : 'ajouté'} avec succès !`);
            closeBookModal();
            await loadBooks();
        } else {
            showCustomModal(`Erreur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
            console.error("Erreur lors de l'enregistrement du livre:", response.data);
        }
    } catch (error) {
        console.error("Erreur lors de l'enregistrement du livre:", error);
        showCustomModal("Une erreur est survenue lors de l'enregistrement du livre.", { type: 'alert' });
    } finally {
        removeLoader(bookModal);
        bookModal.classList.remove('opacity-[0.75]', 'pointer-events-none');
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
            await loadBooks();
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

// ----------------------------------------------------
// Fonctions de gestion des ZONES D'ÉTUDE
// ----------------------------------------------------

/**
 * Fetches and loads study areas from the API with search and pagination.
 * @param {string} searchQuery - The search term.
 * @param {number} page - The page number to load.
 */
async function loadStudyAreas(searchQuery = '', page = 1) {
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

/**
 * Renders the study areas table with data from the current state.
 */
function renderPaginatedStudyAreas() {
    const { data, currentPage, totalPages } = studyAreasState;
    studyAreasTableBody.innerHTML = '';

    if (data.length === 0) {
        studyAreasTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-gray-500">Aucune zone d'étude trouvée.</td></tr>`;
    } else {
        data.forEach(studyArea => {
            const row = document.createElement('tr');
            
            const creationDate = studyArea.created_at ? new TimeFormatter(studyArea.created_at * 1000).format() : 'N/A';
            // Générer le HTML pour la zone d'étude et le bouton de carte
            let studyAreaHtml = '';
            if ((studyArea.latitude && studyArea.longitude)) {
                    const mapUrl = `https://www.google.com/maps/search/?api=1&query=${studyArea.latitude},${studyArea.longitude}`;
                studyAreaHtml = `
                    <p class="text-gray-600 mb-2">
                        <a href="${mapUrl}" target="_blank" class="inline-flex items-center gap-1 px-3 py-1 bg-blue-600 text-white rounded-md shadow hover:bg-blue-700 transition duration-200 ml-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin-icon lucide-map-pin"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            Voir carte
                        </a>
                    </p>`;
            } else {
                studyAreaHtml = `<p class="text-lg text-gray-600 mb-2"><strong>Zone d'étude:</strong> Non spécifiée</p>`;
            }
            row.innerHTML = `
                <td class="text-sm font-medium text-gray-900">${studyArea.id}</td>
                <td class="text-sm text-gray-600">${studyArea.name}</td>
                <td class="text-sm text-gray-600">${studyArea.description ? studyArea.description.substring(0, 50) + '...' : 'N/A'}</td>
                <td class="text-sm text-gray-600">${studyAreaHtml}</td>
                <td class="text-sm text-gray-600">${creationDate}</td>
                <td class="flex space-x-2">
                    <button class="rounded-md edit-study-area-btn text-sm bg-indigo-100 text-indigo-700 hover:bg-indigo-200 action-button" data-study-area-id="${studyArea.id}">Modifier</button>
                    <button class="rounded-md delete-study-area-btn text-sm bg-red-100 text-red-700 hover:bg-red-200 action-button" data-study-area-id="${studyArea.id}" data-study-area-name="${studyArea.name}">Supprimer</button>
                </td>
            `;
            studyAreasTableBody.appendChild(row);
        });
    }
    updatePaginationControls(currentPage, totalPages, studyAreasPageInfo, studyAreasPrevPageBtn, studyAreasNextPageBtn);
}

/**
 * Updates the pagination buttons and info display for Study Areas.
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
 * Ouvre la modale pour ajouter ou modifier une zone d'étude.
 * @param {string} [studyAreaId=null] - L'ID de la zone d'étude à modifier, ou null pour une nouvelle zone.
 */
async function openStudyAreaModal(studyAreaId = null) {
    studyAreaModal.classList.remove('hidden');
    studyAreaForm.reset();
    studyAreaIdInput.value = studyAreaId ?? '';

    if (studyAreaId) {
        studyAreaModalTitle.textContent = 'Modifier la zone d\'étude';
        addLoader(studyAreaModal, "absolute top-[50%] left-[50%] translate-x-[-50%] translate-y-[-50%]");
        studyAreaModal.classList.add('opacity-[0.75]', 'pointer-events-none');
        try {
            const response = await apiClient.get(`/api/admin/study_areas?action=details&id=${studyAreaId}`);
            if (response.data.success) {
                const studyArea = response.data.data;
                studyAreaNameInput.value = studyArea.name || '';
                studyAreaDescriptionTextarea.value = studyArea.description || '';
                studyAreaLatitudeInput.value = studyArea.latitude !== null ? studyArea.latitude : '';
                studyAreaLongitudeInput.value = studyArea.longitude !== null ? studyArea.longitude : '';
            } else {
                showCustomModal(`Erreur chargement détails zone d'étude: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
                closeModal(studyAreaModal);
            }
        } catch (error) {
            console.error("Erreur lors du chargement des détails de la zone d'étude:", error);
            showCustomModal("Une erreur est survenue lors du chargement des détails de la zone d'étude.", { type: 'alert' });
            closeModal(studyAreaModal);
        } finally {
            removeLoader(studyAreaModal);
            studyAreaModal.classList.remove('opacity-[0.75]', 'pointer-events-none');
        }
    } else {
        studyAreaModalTitle.textContent = 'Ajouter une nouvelle zone d\'étude';
    }
}

/**
 * Ferme la modale de la zone d'étude.
 */
function closeStudyAreaModal() {
    studyAreaModal.classList.add('hidden');
    studyAreaForm.reset();
    studyAreaIdInput.value = '';
    studyAreaModalTitle.textContent = 'Ajouter une nouvelle zone d\'étude';
    studyAreaModal.classList.remove('opacity-[0.75]', 'pointer-events-none');
    removeLoader(studyAreaModal);
}

/**
 * Gère la soumission du formulaire d'ajout/modification de zone d'étude.
 * @param {Event} event - L'événement de soumission.
 */
async function handleStudyAreaFormSubmit(event) {
    event.preventDefault();

    addLoader(studyAreaModal, "absolute top-[50%] left-[50%] translate-x-[-50%] translate-y-[-50%]");
    studyAreaModal.classList.add('opacity-[0.75]', 'pointer-events-none');

    const studyAreaData = {
        id: studyAreaIdInput?.value || null,
        name: studyAreaNameInput?.value.trim(),
        description: studyAreaDescriptionTextarea.value.trim(),
        latitude: studyAreaLatitudeInput?.value !== '' ? parseFloat(studyAreaLatitudeInput?.value) : null,
        longitude: studyAreaLongitudeInput?.value !== '' ? parseFloat(studyAreaLongitudeInput?.value) : null,
    };

    if (!studyAreaData.name) {
        showCustomModal('Le nom de la zone d\'étude est requis.', { type: 'alert' });
        removeLoader(studyAreaModal);
        studyAreaModal.classList.remove('opacity-[0.75]', 'pointer-events-none');
        return;
    }

    try {
        let response;
        if (studyAreaData.id) {
            response = await apiClient.post(`/api/admin/study_areas?action=update`, { body: studyAreaData });
        } else {
            response = await apiClient.post('/api/admin/study_areas?action=add', { body: studyAreaData });
        }

        if (response.data.success) {
            showCustomModal(`Zone d'étude ${studyAreaData.id ? 'modifiée' : 'ajoutée'} avec succès !`);
            closeStudyAreaModal();
            await loadStudyAreas(studyAreasState.searchQuery, studyAreasState.currentPage);
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
async function deleteStudyArea(studyAreaId) {
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

/**
 * Displays a custom confirmation modal.
 * @param {string} type - 'book' or 'study_area'.
 * @param {string} id - The ID of the item to delete.
 * @param {string} [name='cet élément'] - The name of the item to delete (for user-friendly message).
 */
function showConfirmationModal(type, id, name = 'cet élément') {
    currentItemToDelete = id;
    currentDeleteType = type;
    const messageElement = confirmationModal.querySelector('p.mb-4');
    if (messageElement) {
        messageElement.textContent = `Voulez-vous vraiment supprimer ${name} ?`;
    }
    closeModal(confirmationModal); // Close any existing modal
    confirmationModal.classList.remove('hidden'); // Show the confirmation modal
}

/**
 * Closes a general modal.
 * @param {HTMLElement} modal - The modal element to close.
 */
function closeModal(modal) {
    modal.classList.add('hidden');
}

// Export for potential use in other modules if needed
export { loadBooks, openBookModal, closeBookModal, handleBookFormSubmit, deleteBook, loadStudyAreas, openStudyAreaModal, closeStudyAreaModal, handleStudyAreaFormSubmit, deleteStudyArea, showConfirmationModal };
