import { apiClient } from "./util/ocho-api.js";
import { showCustomModal, addLoader, removeLoader, isAuth, updateNavBar } from "./util/utils.js";
import { TimeFormatter } from "./util/formatter.js"; // Importer les fonctions utilitaires nécessaires

const bookDetailsContainer = document.getElementById('bookDetailsContainer');
const bookTitleDisplay = document.getElementById('bookTitleDisplay');
const bookDetailContent = document.getElementById('bookDetailContent');
const userNameDisplay = document.getElementById('userNameDisplay');
const userRoleDisplay = document.getElementById('userRoleDisplay');
const modalContainer = document.getElementById('modalContainer');

// Éléments de gestion des fichiers (pour les admins) - Assurez-vous qu'ils sont définis si cette section est utilisée
const adminFileManagement = document.getElementById('adminFileManagement');
const currentCoverImage = document.getElementById('currentCoverImage');
const coverImageInput = document.getElementById('coverImageInput');
const uploadCoverBtn = document.getElementById('uploadCoverBtn');
const deleteCoverBtn = document.getElementById('deleteCoverBtn');
const currentElectronicFile = document.getElementById('currentElectronicFile');
const electronicFileInput = document.getElementById('electronicFileInput');
const uploadElectronicBtn = document.getElementById('uploadElectronicBtn');
const deleteElectronicBtn = document.getElementById('deleteElectronicBtn');


let currentBookId = null; // Pour stocker l'ID du livre actuellement affiché
let currentUploadModalDismiss = null; // Pour stocker la fonction de fermeture de la modale d'upload

window.modalContainer = modalContainer; // Rendre modalContainer accessible globalement

document.addEventListener('DOMContentLoaded', async () => {
    // Mettre à jour la barre de navigation et le message de bienvenue
    const authResult = await isAuth();
    const userId = authResult?.user?.id; // Correction: userId est sous authResult.user.id
    const userName = authResult?.user?.name;
    const userRole = authResult?.user?.role || 'guest';
    const lecteurId = authResult?.user?.lecteurId || null; // Récupérer lecteurId si disponible
    const possibleRoles = {
        admin: 'Administrateur',
        user: 'Lecteur',
        author: 'Auteur',
        guest: 'Visiteur',
    }
    

    if (authResult && userId) {
        
        updateNavBar(userRole, userRole); // 'book-details' pour indiquer la page active
        userNameDisplay.textContent = `Bienvenue, ${userName || 'Lecteur'}!`;
        userRoleDisplay.textContent = possibleRoles[userRole] || possibleRoles.guest;
        if (userRole === 'admin') {
            if (adminFileManagement) adminFileManagement.classList.remove('hidden'); // Afficher la section de gestion des fichiers pour les admins
        } else {
            if (adminFileManagement) adminFileManagement.classList.add('hidden'); // Cacher la section de gestion des fichiers pour les autres rôles
        }
    } else {
        updateNavBar('guest', 'book-details'); // 'guest' si non connecté
        userNameDisplay.textContent = `Bienvenue, Lecteur !`;
        userRoleDisplay.textContent = '';
    }

    // Récupérer l'ID du livre depuis l'URL
    const urlParams = new URLSearchParams(window.location.search);
    const bookId = urlParams.get('id');

    if (bookId) {
        currentBookId = bookId; // Stocker l'ID du livre
        fetchBookDetails(bookId, userRole, lecteurId); // Passer le rôle et lecteurId
    } else {
        bookDetailContent.innerHTML = '<p class="text-red-500 text-center w-full">ID du livre non trouvé dans l\'URL.</p>';
    }

    // Gérer les événements des inputs de fichiers pour afficher la modale de progression
    if (coverImageInput) {
        coverImageInput.addEventListener('change', async (event) => {
            const file = event.target.files[0];
            if (file) {
                await displayUploadModal('cover', currentBookId, file);
            }
        });
    }

    if (electronicFileInput) {
        electronicFileInput.addEventListener('change', async (event) => {
            const file = event.target.files[0];
            if (file) {
                await displayUploadModal('electronic', currentBookId, file);
            }
        });
    }

    // Les boutons d'upload ne déclenchent plus handleFileUpload directement,
    // mais la fonction d'upload sera appelée via le bouton dans la modale.
    // Ces écouteurs sont maintenus pour la cohérence si vous avez d'autres logiques qui les appellent.
    if (uploadCoverBtn) uploadCoverBtn.addEventListener('click', () => { /* Logic now in modal */ });
    if (deleteCoverBtn) deleteCoverBtn.addEventListener('click', () => handleDeleteFile('cover', currentBookId));
    if (uploadElectronicBtn) uploadElectronicBtn.addEventListener('click', () => { /* Logic now in modal */ });
    if (deleteElectronicBtn) deleteElectronicBtn.addEventListener('click', () => handleDeleteFile('electronic', currentBookId));
});

/**
 * Récupère les détails d'un livre spécifique depuis l'API.
 * @param {string} bookId - L'ID du livre à récupérer.
 * @param {string} userRole - Le rôle de l'utilisateur connecté.
 * @param {number|null} lecteurId - L'ID du lecteur si l'utilisateur est un lecteur.
 */
async function fetchBookDetails(bookId, userRole, lecteurId) {
    bookDetailContent.innerHTML = '<p class="text-center w-full text-gray-500"><span class="loader"></span> Chargement des détails...</p>';

    try {
        // L'API est maintenant '/api/books/details' sans .php, comme dans votre modification
        const response = await apiClient.get(`/api/books/details?id=${bookId}`, { throwHttpErrors: false });

        if (response.data.success) {
            const book = response.data.data;
            renderBookDetails(book, userRole, lecteurId); // Passer le rôle et lecteurId
            if (userRole === 'admin') {
                if (adminFileManagement) adminFileManagement.classList.remove('hidden');
                updateFileManagementUI(book);
            } else {
                if (adminFileManagement) adminFileManagement.classList.add('hidden');
            }
        } else {
            bookDetailContent.innerHTML = `<p class="text-red-500 text-center w-full">Erreur lors du chargement des détails du livre : ${response.data.message || 'Inconnu'}.</p>`;
        }
    } catch (error) {
        console.error('Erreur lors de la récupération des détails du livre:', error);
        bookDetailContent.innerHTML = '<p class="text-red-500 text-center w-full">Une erreur est survenue lors du chargement des détails du livre. Veuillez réessayer.</p>';
    }
}

/**
 * Rend les détails du livre dans le conteneur HTML.
 * @param {object} book - L'objet livre contenant les détails.
 * @param {string} userRole - Le rôle de l'utilisateur connecté.
 * @param {number|null} lecteurId - L'ID du lecteur si l'utilisateur est un lecteur.
 */
function renderBookDetails(book, userRole, lecteurId) {
    bookTitleDisplay.textContent = book.titre; // Mettre à jour le titre dans le H2

    const availabilityClass = book.disponible ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    const availabilityText = book.disponible ? 'Disponible' : 'Non disponible';

    const buttonHtml = `<button id="borrowButton" class="mt-6 px-6 py-3 rounded-lg shadow-md transition duration-200 ease-in-out transform hover:scale-105
                        ${book.disponible ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-gray-400 text-gray-800 cursor-not-allowed'}"
                        ${!book.disponible ? 'disabled' : ''}>
                        ${book.disponible ? 'Emprunter cet ouvrage' : 'Non disponible à l\'emprunt'}
                       </button>`;

    const coverEl = book.cover_image_url ? `<img src="${book.cover_image_url}" alt="Couverture de ${book.titre}" class="book-cover-detail mx-auto md:mx-0 mb-6 md:mb-0">` : `<svg xmlns="http://www.w3.org/2000/svg" width="800px" height="800px" viewBox="0 0 120 120" fill="none" class="book-cover-detail mx-auto md:mx-0 mb-6 md:mb-0" title="Pas de couverture disponible">
<rect width="120" height="120" fill="#aeb0b1"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M33.2503 38.4816C33.2603 37.0472 34.4199 35.8864 35.8543 35.875H83.1463C84.5848 35.875 85.7503 37.0431 85.7503 38.4816V80.5184C85.7403 81.9528 84.5807 83.1136 83.1463 83.125H35.8543C34.4158 83.1236 33.2503 81.957 33.2503 80.5184V38.4816ZM80.5006 41.1251H38.5006V77.8751L62.8921 53.4783C63.9172 52.4536 65.5788 52.4536 66.6039 53.4783L80.5006 67.4013V41.1251ZM43.75 51.6249C43.75 54.5244 46.1005 56.8749 49 56.8749C51.8995 56.8749 54.25 54.5244 54.25 51.6249C54.25 48.7254 51.8995 46.3749 49 46.3749C46.1005 46.3749 43.75 48.7254 43.75 51.6249Z" fill="#687787"/>
</svg>`;

    const date_publication = book.date_publication ? new TimeFormatter(book.date_publication * 1000).formatFullTime() : 'N/A';

    // Vérification de l'accès au fichier électronique
    let electronicFileHtml = '';
    if (book.electronic_file_url && book.file_id) {
        // L'URL côté client n'aura pas l'extension .php
        const downloadUrl = `/api/files/download?file_id=${book.file_id}&book_id=${book.id}`;
        
        // Afficher le bouton si l'utilisateur est admin ou un lecteur abonné
        if (userRole === 'admin' || (userRole === 'user' && lecteurId)) {
            electronicFileHtml = `
                <p class="text-lg text-gray-700 mt-4">
                    <strong>Fichier électronique:</strong> 
                    <button id="downloadElectronicFileBtn-${book.id}" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                        Télécharger le fichier
                    </button>
                </p>
                <p class="text-sm text-gray-500">Accès réservé aux administrateurs et abonnés.</p>
            `;
        } else {
            electronicFileHtml = `
                <p class="text-lg text-gray-700 mt-4">
                    <strong>Fichier électronique:</strong> 
                    <span class="text-gray-500">Accès restreint.</span>
                </p>
                <p class="text-sm text-gray-500">Connectez-vous ou abonnez-vous pour accéder à ce contenu.</p>
            `;
        }
    } else {
        electronicFileHtml = `<p class="text-lg text-gray-700 mt-4"><strong>Fichier électronique:</strong> Non disponible.</p>`;
    }

    bookDetailContent.innerHTML = `
        <div class="md:w-1/3 flex justify-center">
            ${coverEl}
        </div>
        <div class="md:w-2/3">
            <h3 class="text-3xl font-bold text-gray-900 mb-2">${book.titre}</h3>
            <p class="text-xl text-gray-700 mb-2"><strong>Auteur:</strong> ${book.auteur_nom || 'N/A'}</p>
            <p class="text-lg text-gray-600 mb-2"><strong>Catégorie:</strong> ${book.categorie_nom || 'N/A'}</p>
            <p class="text-lg text-gray-600 mb-2"><strong>ISBN:</strong> ${book.isbn || 'N/A'}</p>
            <p class="text-lg text-gray-600 mb-2"><strong>Date de publication:</strong> ${date_publication}</p>
            <p class="text-lg text-gray-600 mb-4"><strong>Statut:</strong>
                <span class="px-3 py-1 rounded-full text-sm font-semibold ${availabilityClass}">
                    ${availabilityText}
                </span>
            </p>
            <p class="text-gray-700 mb-4">${book.descr || 'Aucune description disponible.'}</p>
            <p class="text-gray-700 mb-4"><strong>Emplacement:</strong> ${book.emplacement || 'N/A'}</p>
            ${electronicFileHtml}
            ${buttonHtml}
        </div>
    `;

    // Attacher l'écouteur d'événement au bouton d'emprunt
    const borrowButton = document.getElementById('borrowButton');
    if (borrowButton && book.disponible) {
        borrowButton.addEventListener('click', () => handleBorrowBook(book.id));
    }

    // Attacher l'écouteur d'événement au nouveau bouton de téléchargement du fichier électronique
    const downloadElectronicFileButton = document.getElementById(`downloadElectronicFileBtn-${book.id}`);
    if (downloadElectronicFileButton) {
        downloadElectronicFileButton.addEventListener('click', () => handleDownloadElectronicFile(book.id, book.file_id));
    }
}

/**
 * Met à jour l'interface de gestion des fichiers pour les admins.
 * @param {object} book - L'objet livre.
 */
function updateFileManagementUI(book) {
    // Afficher l'image de couverture actuelle
    if (book.cover_image_url) {
        if (currentCoverImage) currentCoverImage.innerHTML = `<img src="${book.cover_image_url}" alt="Couverture actuelle" class="w-24 h-auto rounded-md mb-2"/>`;
        if (deleteCoverBtn) deleteCoverBtn.classList.remove('hidden');
    } else {
        if (currentCoverImage) currentCoverImage.innerHTML = `<p class="text-gray-500">Aucune couverture actuelle.</p>`;
        if (deleteCoverBtn) deleteCoverBtn.classList.add('hidden');
    }

    // Afficher le fichier électronique actuel
    if (book.electronic_file_url) {
        if (currentElectronicFile) {
            currentElectronicFile.innerHTML = `
                <div class="text-gray-700 flex items-center gap-1 flex-col w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                    <p class="text-ellipsis w-full overflow-hidden whitespace-nowrap ">${book.electronic_file_url.split('/').pop()}</p>
                    
                </div>`;
        }
        if (deleteElectronicBtn) deleteElectronicBtn.classList.remove('hidden');
    } else {
        if (currentElectronicFile) currentElectronicFile.innerHTML = `<p class="text-gray-500">Aucun fichier électronique actuel.</p>`;
        if (deleteElectronicBtn) deleteElectronicBtn.classList.add('hidden');
    }
}

/**
 * Crée et retourne l'interface utilisateur pour une barre de progression.
 * @param {string} [imageUrl=null] - URL de l'image à prévisualiser (optionnel).
 * @param {string} [messageText=''] - Texte du message à afficher dans la modale.
 * @param {Function} [uploadStartCallback=null] - Callback à appeler quand le bouton d'upload est cliqué.
 * @returns {{element: HTMLElement, update: Function}} Un objet contenant l'élément HTML et une fonction pour mettre à jour la progression.
 */
function createProgressBarUI(imageUrl = null, messageText = '', uploadStartCallback = null) {
    const progressDiv = document.createElement('div');
    progressDiv.className = 'flex flex-col items-center p-4 w-full'; // Ajout de w-full pour la largeur

    let imageOrSvgHtml;
    if (imageUrl) {
        imageOrSvgHtml = `<img src="${imageUrl}" alt="Aperçu" class="w-32 h-32 object-cover rounded-md mb-4 shadow-md">`;
    } else {
        // SVG d'un document générique si pas d'image de couverture
        imageOrSvgHtml = `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text text-gray-400 mb-4">
            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
            <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
            <path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
        </svg>`;
    }

    progressDiv.innerHTML = `
        ${messageText ? `<p class="text-lg mb-4 text-gray-800 font-semibold">${messageText}</p>` : ''}
        ${imageOrSvgHtml}
        <p id="uploadProgressText" class="text-lg mb-2 text-gray-700">0%</p>
        <progress id="uploadProgressBar" value="0" max="100" class="w-full h-4 rounded-full appearance-none bg-gray-200 [&::-webkit-progress-bar]:rounded-full [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-indigo-600 [&::-moz-progress-bar]:rounded-full [&::-moz-progress-bar]:bg-indigo-600"></progress>
        ${uploadStartCallback ? `<button id="startUploadBtn" class="mt-4 px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Démarrer le téléversement</button>` : ''}
    `;
    const progressBar = progressDiv.querySelector('#uploadProgressBar');
    const progressText = progressDiv.querySelector('#uploadProgressText');
    const startUploadBtn = progressDiv.querySelector('#startUploadBtn');

    if (startUploadBtn && uploadStartCallback) {
        startUploadBtn.addEventListener('click', uploadStartCallback);
    }

    return {
        element: progressDiv,
        update: (percentage) => {
            if (progressBar && progressText) {
                progressBar.value = percentage;
                progressText.textContent = `${percentage.toFixed(0)}%`;
            }
        },
        startUploadBtn: startUploadBtn // Retourne le bouton pour pouvoir le désactiver/activer
    };
}

/**
 * Affiche la modale de prévisualisation et de progression avant le téléversement.
 * @param {string} type - 'cover' ou 'electronic'.
 * @param {number} bookId - ID du livre.
 * @param {File} file - Le fichier à uploader.
 */
async function displayUploadModal(type, bookId, file) {
    let imageUrl = null;
    if (type === 'cover') {
        imageUrl = await new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.readAsDataURL(file);
        });
    }

    // Callback qui sera appelée par le bouton "Démarrer le téléversement"
    const uploadCallback = async () => {
        // Désactiver le bouton de démarrage de l'upload et afficher le loader
        const modalContentDiv = document.querySelector('.modal-content div');
        const startUploadBtn = modalContentDiv ? modalContentDiv.startUploadBtn : null;
        const updateProgressBar = modalContentDiv ? modalContentDiv.updateProgressBar : null;

        if (startUploadBtn) {
            addLoader(startUploadBtn);
            startUploadBtn.disabled = true;
        }

        // Démarrer le téléversement réel
        await handleFileUpload(type, bookId, file, updateProgressBar);

        // Le loader et le bouton seront gérés dans le finally de handleFileUpload
        // car la modale sera fermée après l'upload.
    };

    // Créer l'interface de la barre de progression (avec aperçu si c'est une couverture)
    const { element: progressBarElement, update: updateProgressBar, startUploadBtn: modalStartUploadBtn } = createProgressBarUI(imageUrl, `Prêt à téléverser le fichier ${type === 'cover' ? 'de couverture' : 'électronique'}`, uploadCallback);

    // Attacher la fonction de mise à jour et le bouton à l'élément pour qu'ils soient accessibles par uploadCallback
    progressBarElement.updateProgressBar = updateProgressBar;
    progressBarElement.startUploadBtn = modalStartUploadBtn; 

    // Afficher la modale de progression
    const modalResult = await showCustomModal(null, { // message est null car il est dans le body
        body: progressBarElement, // Passer le custom HTML element
        type: 'alert', // Utiliser le type 'alert' pour n'avoir aucun bouton par défaut
        actions: [] // Aucune action, la modale sera fermée programmatiquement
    });
    currentUploadModalDismiss = modalResult.dismiss; // Stocker la fonction de fermeture
}

/**
 * Gère l'upload d'un fichier (couverture ou électronique).
 * Cette fonction est maintenant appelée par le bouton "Démarrer le téléversement" dans la modale.
 * @param {string} type - 'cover' ou 'electronic'.
 * @param {number} bookId - ID du livre.
 * @param {File} file - Le fichier à uploader.
 * @param {Function} updateProgressBar - La fonction pour mettre à jour la barre de progression dans la modale.
 */
async function handleFileUpload(type, bookId, file, updateProgressBar) {
    const formData = new FormData();
    formData.append('book_id', bookId);
    formData.append('file', file);

    // Suppression de l'extension .php des endpoints
    const endpoint = type === 'cover' ? '/api/files/upload_cover' : '/api/files/upload_electronic';

    try {
        const response = await apiClient.post(
            endpoint,
            {
                body: formData,
                throwHttpErrors: true
            },
            (progress, event) => { // onProgress callback
                if (progress !== null && updateProgressBar) {
                    updateProgressBar(progress); // Mettre à jour la barre de progression dans la modale
                }
            }
        );

        if (response.data.success) {
            showCustomModal(`Fichier ${type === 'cover' ? 'image de couverture' : 'électronique'} mis à jour avec succès !`, { type: 'success' });
            // Recharger les détails avec le rôle et lecteurId actuels
            const authResult = await isAuth();
            const userRole = authResult?.user?.role || 'guest'; // Accès corrigé
            const lecteurId = authResult?.user?.lecteurId || null; // Accès corrigé
            fetchBookDetails(bookId, userRole, lecteurId); 
        } else {
            showCustomModal(`Erreur lors de l'upload: ${response.data.message || 'Inconnu'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error(`Erreur lors de l'upload du fichier ${type}:`, error);
        showCustomModal(`Une erreur est survenue lors de l'upload du fichier ${type}.`, { type: 'alert' });
    } finally {
        // Réinitialiser l'input file pour permettre de re-sélectionner le même fichier
        if (type === 'cover' && coverImageInput) coverImageInput.value = '';
        else if (electronicFileInput) electronicFileInput.value = '';

        // Fermer la modale de progression si elle est ouverte
        if (currentUploadModalDismiss) {
            currentUploadModalDismiss();
            currentUploadModalDismiss = null; // Réinitialiser la variable
        }
    }
}

/**
 * Gère la suppression d'un fichier (couverture ou électronique).
 * @param {string} type - 'cover' ou 'electronic'.
 * @param {number} bookId - ID du livre.
 */
async function handleDeleteFile(type, bookId) {
    const confirmed = await showCustomModal(`Voulez-vous vraiment supprimer la ${type === 'cover' ? 'couverture' : 'fichier électronique'} ?`, { type: 'confirm' });
    if (!confirmed) return;

    const button = type === 'cover' ? deleteCoverBtn : deleteElectronicBtn;
    if (button) {
        addLoader(button);
        button.disabled = true;
    }

    try {
        // Suppression de l'extension .php de l'endpoint
        const response = await apiClient.post('/api/files/delete_file', { body: { book_id: bookId, file_type: type }, throwHttpErrors: true }); 

        if (response.data.success) {
            showCustomModal(`Fichier ${type === 'cover' ? 'couverture' : 'électronique'} supprimé avec succès !`, { type: 'success' });
            // Recharger les détails avec le rôle et lecteurId actuels
            const authResult = await isAuth();
            const userRole = authResult?.user?.role || 'guest'; // Accès corrigé
            const lecteurId = authResult?.user?.lecteurId || null; // Accès corrigé
            fetchBookDetails(bookId, userRole, lecteurId); 
        } else {
            showCustomModal(`Erreur lors de la suppression: ${response.data.message || 'Inconnu'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error(`Erreur lors de la suppression du fichier ${type}:`, error);
        showCustomModal(`Une erreur est survenue lors de la suppression du fichier ${type}.`, { type: 'alert' });
    } finally {
        if (button) {
            removeLoader(button);
            button.disabled = false;
        }
    }
}

/**
 * Gère l'action d'emprunt d'un livre depuis la page de détails.
 * @param {number} bookId - L'ID du livre à emprunter.
 */
async function handleBorrowBook(bookId) {
    const authResult = await isAuth();
    const userId = authResult?.user?.id; // Accès corrigé

    if (!authResult || !userId) {
        showCustomModal('Vous devez être connecté pour emprunter un livre.', { type: 'alert' });
        return;
    }

    showCustomModal('Voulez-vous vraiment emprunter ce livre ?', {
        type: 'confirm',
        actions: [
            {
                label: 'Annuler',
                callback: () => { },
                className: 'bg-gray-400 hover:bg-gray-500 text-white',
                value: false
            },
            {
                label: 'Oui, emprunter',
                className: 'bg-indigo-600 hover:bg-indigo-700 text-white',
                callback: async () => {
                    const button = document.getElementById('borrowButton');
                    if (button) {
                        addLoader(button); // Utiliser showLoading pour le bouton
                        button.disabled = true;
                    }

                    try {
                        const response = await apiClient.post('/api/books/borrow', { body: { bookId }, throwHttpErrors: true });
                        if (response.data.success) {
                            showCustomModal('Livre emprunté avec succès !', { type: 'success' });
                            // Recharger les détails pour mettre à jour le statut avec le rôle et lecteurId actuels
                            const authResult = await isAuth();
                            const userRole = authResult?.user?.role || 'guest'; // Accès corrigé
                            const lecteurId = authResult?.user?.lecteurId || null; // Accès corrigé
                            fetchBookDetails(bookId, userRole, lecteurId); 
                        } else {
                            showCustomModal(`${response?.data?.message || "Erreur inconnue lors de l'emprunt."}`, { type: 'alert' });
                        }
                    } catch (error) {
                        console.error("Erreur lors de l'emprunt du livre :", error);
                        showCustomModal("Une erreur est survenue lors de l'emprunt. Veuillez réessayer.", { type: 'alert' });
                    } finally {
                        if (button) {
                            removeLoader(button); // Utiliser hideLoading pour le bouton
                            button.disabled = false; // Réactiver le bouton (sera désactivé si non disponible après rechargement)
                        }
                    }
                },
                value: true,
            },
        ]
    });
}

/**
 * Gère le téléchargement d'un fichier électronique.
 * @param {number} bookId - L'ID du livre.
 * @param {number} fileId - L'ID du fichier électronique.
 */
async function handleDownloadElectronicFile(bookId, fileId) {
    const button = document.getElementById(`downloadElectronicFileBtn-${bookId}`);
    if (button) {
        addLoader(button);
        button.disabled = true;
    }

    try {
        // L'URL côté client n'aura pas l'extension .php
        const downloadUrl = `/api/files/download?file_id=${fileId}&book_id=${bookId}`;
        
        // Rediriger le navigateur pour déclencher le téléchargement.
        // Aucune réponse JSON n'est attendue ici, le navigateur gère le téléchargement du fichier.
        window.location.href = downloadUrl;

        // Optionnel: Afficher un message de succès après un court délai,
        // car le téléchargement peut ne pas être instantané.
        // showCustomModal('Le téléchargement du fichier a démarré.', { type: 'success' });

    } catch (error) {
        console.error('Erreur lors du téléchargement du fichier électronique :', error);
        showCustomModal('Une erreur est survenue lors du téléchargement du fichier. Veuillez réessayer.', { type: 'alert' });
    } finally {
        if (button) {
            // Retirer le loader et réactiver le bouton après un court délai
            // pour permettre au téléchargement de s'initier.
            setTimeout(() => {
                removeLoader(button);
                button.disabled = false;
            }, 1500); // Délai de 1.5 seconde
        }
    }
}