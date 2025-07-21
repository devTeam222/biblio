import { apiClient } from "./util/ocho-api.js";
import { TimeFormatter, NumberFormatter } from "./util/formatter.js";
import { showCustomModal, showLoading, hideLoading, isAuth } from "./util/utils.js";
// Sélection des éléments DOM
const trendingBooksContainer = document.getElementById('trending-books-container');
const currentLoansSection = document.getElementById('current-loans-section');
const currentLoansContainer = document.getElementById('current-loans-container');
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const noLoansMessage = document.getElementById('noLoansMessage');
const loansLoadingSpinner = document.getElementById('loansLoading');
const trendingLoadingSpinner = document.getElementById('trendingLoading');
const modalContainer = document.getElementById('modalContainer');

window.modalContainer = modalContainer; // Assurez-vous que modalContainer est accessible globalement


function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

function getGuestId() {
    let guestId = document.cookie.split('; ').find(row => row.startsWith(GUEST_ID_COOKIE_NAME + '='));
    if (guestId) {
        return guestId.split('=')[1];
    } else {
        guestId = generateUUID();
        // Définir le cookie pour 30 jours
        document.cookie = `${GUEST_ID_COOKIE_NAME}=${guestId}; expires=${new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toUTCString()}; path=/`;
        return guestId;
    }
}


// --- Fonctions de chargement des données (utilisant apiClient) ---

/**
 * Vérifie l'état d'authentification de l'utilisateur.
 * Met à jour CURRENT_USER_ID, CURRENT_LECTEUR_ID et CURRENT_USER_NAME si authentifié.
 * @returns {Promise<boolean>} True si authentifié, False sinon.
 */
async function session() {
    try {
        const response = await apiClient.get('/api/auth/check', { throwHttpErrors: false });
        return {
            success: response.data.success,
            user_id: response.data.user_id || null,
            lecteur_id: response.data.lecteur_id || null,
            user_name: response.data.user_name || null
        }
    } catch (error) {
        console.error("Erreur lors de la vérification de l'authentification :", error);
        return {
            success: false,
            user_id: null,
            lecteur_id: null,
            user_name: null
        };
    }
}

/**
 * Gère la déconnexion de l'utilisateur.
 */
async function handleLogout() {
    const confirmed = await showCustomModal('Voulez-vous vraiment vous déconnecter ?', 'confirm');
    if (!confirmed) {
        return;
    }

    try {
        const response = await apiClient.post('/api/auth/logout', {}, { throwHttpErrors: true });
        if (response.data.success) {
            await showCustomModal('Déconnexion réussie !');
            // Recharger la page ou mettre à jour l'UI
            await initUserSession(); // Réinitialise la session et met à jour l'UI
        } else {
            await showCustomModal(`Erreur de déconnexion: ${response.data.message}`);
        }
    } catch (error) {
        console.error("Erreur lors de la déconnexion :", error);
        await showCustomModal("Une erreur est survenue lors de la déconnexion.");
    }
}

/**
 * Charge et affiche les emprunts actuels de l'utilisateur.
 */
async function loadCurrentLoans() {
    const isAuthenticated = await isAuth();
    if (!isAuthenticated) {
        currentLoansSection.classList.add('hidden'); // Cache la section si non authentifié
        return;
    }
    console.log();


    currentLoansSection.classList.remove('hidden'); // Affiche la section si authentifié
    showLoading(loansLoadingSpinner);
    currentLoansContainer.innerHTML = ''; // Vide le conteneur avant de charger
    noLoansMessage.classList.add('hidden'); // Cache le message "pas d'emprunts"

    try {
        const response = await apiClient.get('/api/loans/current', { throwHttpErrors: true });
        hideLoading(loansLoadingSpinner);

        if (!response?.data?.success) {
            currentLoansContainer.innerHTML = `<p class="text-red-500 col-span-full">${response?.data?.message || "Erreur inconnue lors du chargement des emprunts."}</p>`;
            return;
        }

        const loans = response.data.data.map(loan => {

            // Assurez-vous que l'objet emprunt a les propriétés nécessaires
            if (!loan.date_emprunt || !loan.date_retour) {
                console.warn("Emprunt sans date d'emprunt ou de retour :", loan);
                showCustomModal("Un de vos emprunts ne contient pas de date valide. Veuillez contacter l'administrateur.");
                return null; // Ignore cet emprunt
            }
            const lang = navigator.language || 'fr-FR'; // Utilise la langue du navigateur ou 'fr-FR' par défaut
            // Formate les dates pour l'affichage
            const date_emprunt = `${(new TimeFormatter(loan.date_emprunt * 1000, { lang, long: true, full: true }).format())} (${(new TimeFormatter(loan.date_emprunt * 1000, { lang, long: true }).formatRelativeTime())})`;
            const date_retour = new TimeFormatter(loan.date_retour * 1000, { lang, long: true, full: false }).format();
            return {
                id: loan.id,
                livre_id: loan.livre_id,
                titre: loan.titre,
                auteur: loan.auteur,
                rendu: loan.rendu,
                lecteur_id: loan.lecteur_id,
                date_emprunt,
                date_retour
            };
        });
        // Prioriser les emprunts non rendus
        loans.sort((a, b) => {
            return a.rendu === b.rendu ? 0 : (a.rendu ? 1 : -1); // Si les deux ont le même état de rendu, on les considère égaux
        });

        if (!loans || loans.length === 0) {
            noLoansMessage.classList.remove('hidden');
            return;
        } else {
            noLoansMessage.classList.add('hidden');
        }

        loans.forEach((loan) => {
            const returnDate = new Date(loan.date_retour);
            const today = new Date();
            const isReturned = loan.rendu;
            const isOverdue = !isReturned && returnDate < today; // Vérifie si l'emprunt est en retard
            console.log(loan);


            const loanCard = document.createElement('div');
            loanCard.className = 'bg-gray-100 p-4 rounded-lg shadow-sm';
            loanCard.innerHTML = `
                        <h4 class="font-bold text-gray-900 text-lg mb-1">${loan.titre}</h4>
                        <p class="text-sm text-gray-600">Auteur : ${loan.auteur}</p>
                        <p class="text-sm text-gray-600">Date d'emprunt : ${loan.date_emprunt}</p>
                        ${!isReturned ? `<p class="text-sm ${isOverdue ? 'text-red-600' : 'text-green-600'} font-semibold">Date de retour : ${loan.date_retour} ${isOverdue ? '(En retard)' : ''}</p>` : ""}
                        <button class="mt-3 ${!isReturned ? "bg-red-500 hover:bg-red-600 text-white" : "bg-green-400 hover:bg-green-500 text-green-950"} py-1.5 px-4 rounded-md text-sm transition duration-200 ease-in-out remind">${isReturned ? "Rendu" : "Rappeler le retour"}</button>
                    `;
            const remindBtn = loanCard.querySelector('.remind');
            !isReturned && remindBtn.addEventListener('click', () => handleRemindReturn(loan.id))
            currentLoansContainer.appendChild(loanCard);
        });
    } catch (error) {
        console.error("Erreur lors du chargement des emprunts :", error);
        hideLoading(loansLoadingSpinner);
        currentLoansContainer.innerHTML = '<p class="text-red-500 col-span-full">Une erreur est survenue lors du chargement des emprunts.</p>';
    }
}


async function loadTrendingBooks(searchTerm = '') {
    showLoading(trendingLoadingSpinner);
    trendingBooksContainer.innerHTML = ''; // Vide le conteneur avant de charger

    const endpoint = searchTerm ? `/api/books/search?query=${encodeURIComponent(searchTerm)}` : '/api/books/trending';

    try {
        const response = await apiClient.get(endpoint, { throwHttpErrors: true });
        hideLoading(trendingLoadingSpinner);

        if (!response?.data?.success) {
            trendingBooksContainer.innerHTML = `<p class="text-red-500 col-span-full">${response?.data?.message || "Erreur inconnue lors du chargement des ouvrages."}</p>`;
            return;
        }
        const result = response.data.data;

        const booksToDisplay = searchTerm ? result : result.slice(0, 4); // Si recherche, affiche tous les résultats, sinon 4 tendances
        if (!booksToDisplay || booksToDisplay.length === 0) {
            trendingBooksContainer.innerHTML = '<p class="text-gray-500 col-span-full">Aucun ouvrage trouvé pour votre recherche.</p>';
            return;
        }

        booksToDisplay.forEach((book) => {
            const bookCard = renderBookCard(book, true);
            trendingBooksContainer.appendChild(bookCard);
        });
    } catch (error) {
        console.error("Erreur lors du chargement des ouvrages tendances :", error);
        hideLoading(trendingLoadingSpinner);
        trendingBooksContainer.innerHTML = '<p class="text-red-500 col-span-full">Une erreur est survenue lors du chargement des ouvrages.</p>';
    }
}

function renderBookCard(book, showBorrowButton = true) {
    const availabilityClass = book.disponible ? 'bg-green-200 text-green-800' : 'bg-red-200 text-red-800';
    const availabilityText = book.disponible ? 'Disponible' : 'Non disponible';

    // Générer une couleur de catégorie aléatoire pour la démo
    const categoryColors = [
        'bg-red-200 text-red-800', 'bg-purple-200 text-purple-800', 'bg-indigo-200 text-indigo-800', 'bg-teal-200 text-teal-800', 'bg-blue-200 text-blue-800', 'bg-yellow-200 text-yellow-800',
    ];

    // Utiliser une couleur  pour la catégorie avec son id
    // Si la catégorie est définie, on utilise une couleur spécifique, sinon on en génère une aléatoire 
    const categorieId = book.categorie_id - 1;
    const colorIndex = categorieId % categoryColors.length; // Assure que l'index est dans les limites du tableau
    // Utiliser la couleur de catégorie correspondante
    const categoryColor = categoryColors[colorIndex] || 'bg-gray-200 text-gray-800'; // Valeur par défaut si l'index est hors limites
    const categoryClass = `inline-block ${categoryColor} text-xs font-semibold px-2.5 py-0.5 rounded-full mt-2 mr-2`;
    const categoryText = book.categorie || 'Non catégorisé';

    const buttonHtml = showBorrowButton
        ? `<button class="borrow-btn mt-3 ${book.disponible ? 'bg-green-500 hover:bg-green-600' : 'bg-gray-400 cursor-not-allowed'} text-white py-1.5 px-4 rounded-md text-sm transition duration-200 ease-in-out" ${book.disponible ? `` : 'disabled'}>
                    ${book.disponible ? 'Emprunter' : 'Non disponible'}
                   </button>`
        : '';
    const coverEl = book.cover ? `<img src="${book.cover}" alt="Couverture de ${book.titre}" class="book-cover mx-auto mb-3">` : `<svg xmlns="http://www.w3.org/2000/svg" width="800px" height="800px" viewBox="0 0 120 120" fill="none" class="book-cover mx-auto mb-3" title="Pas de couverture disponible">
<rect width="120" height="120" fill="#aeb0b1"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M33.2503 38.4816C33.2603 37.0472 34.4199 35.8864 35.8543 35.875H83.1463C84.5848 35.875 85.7503 37.0431 85.7503 38.4816V80.5184C85.7403 81.9528 84.5807 83.1136 83.1463 83.125H35.8543C34.4158 83.1236 33.2503 81.957 33.2503 80.5184V38.4816ZM80.5006 41.1251H38.5006V77.8751L62.8921 53.4783C63.9172 52.4536 65.5788 52.4536 66.6039 53.4783L80.5006 67.4013V41.1251ZM43.75 51.6249C43.75 54.5244 46.1005 56.8749 49 56.8749C51.8995 56.8749 54.25 54.5244 54.25 51.6249C54.25 48.7254 51.8995 46.3749 49 46.3749C46.1005 46.3749 43.75 48.7254 43.75 51.6249Z" fill="#687787"/>
</svg>`;

    const bookCard = document.createElement('div');
    bookCard.className = 'bg-gray-100 p-4 rounded-lg shadow-sm text-center transform hover:scale-105 transition duration-200 ease-in-out';
    bookCard.innerHTML = `
                ${coverEl}
                <h4 class="font-bold text-gray-900 text-lg">${book.titre}</h4>
                <p class="text-sm text-gray-600">${book.auteur}</p>
                <span class="${categoryClass}">${categoryText}</span>
                <span class="inline-block ${availabilityClass} text-xs font-semibold px-2.5 py-0.5 rounded-full mt-2">${availabilityText}</span>
                ${buttonHtml}
            `;
    // Ajout de l'écouteur d'événement pour le bouton d'emprunt
    if (showBorrowButton && book.disponible) {
        const borrowButton = bookCard.querySelector('.borrow-btn');
        borrowButton.addEventListener('click', () => handleBorrowBook(book.id));
    }
    return bookCard;
}

async function handleBorrowBook(bookId) {
    await showCustomModal('Voulez-vous vraiment emprunter ce livre ?', {
        type: 'confirm',
        actions: [
            {
                label: 'Annuler',
                callback: () => { },
                className: 'bg-gray-400 hover:bg-gray-500 text-white',
                value: false // valeur de retour explicite
            },
            {
                label: 'Oui, emprunter',
                class: 'bg-green-500 hover:bg-green-600 text-white',
                callback: async () => {
                    // Désactiver temporairement le bouton
                    const button = event.target; // Récupère le bouton qui a été cliqué
                    button.disabled = true;
                    button.textContent = 'Emprunt en cours...';


                    try {
                        console.log(`Emprunt du livre avec ID: ${bookId}`);
                        const response = await apiClient.post('/api/books/borrow', { body: { bookId: bookId } }, { throwHttpErrors: true });
                        if (response.data.success) {
                            await showCustomModal(response.data.message);
                            // Recharger les sections pour mettre à jour l'état
                            await loadTrendingBooks();
                            await loadCurrentLoans();
                        } else {
                            console.log(response.data);

                            await showCustomModal(`${response?.data?.message || "Erreur inconnue lors de l'emprunt."}`);
                        }
                    } catch (error) {
                        console.error("Erreur lors de l'emprunt du livre :", error);
                        await showCustomModal("Une erreur est survenue lors de l'emprunt. Veuillez réessayer.");
                    } finally {
                        // Le rechargement des données va réinitialiser les boutons, donc pas besoin de réactiver spécifiquement
                        // Si le rechargement échoue, on peut réactiver manuellement ici
                        if (button) {
                            button.disabled = false;
                            button.textContent = 'Emprunter';
                        }
                    }
                }, // Retourne true pour confirmer l'emprunt
                value: true,
            },
        ]
    });


}
async function handleRemindReturn(loanId) {
    const confirmed = await showCustomModal('Voulez-vous envoyer un rappel pour le retour de ce livre ?', { type: 'confirm' });
    if (!confirmed) {
        return;
    }

    // Désactiver temporairement le bouton
    const button = event.target;
    button.disabled = true;
    button.textContent = 'Envoi...';

    try {
        const response = await apiClient.post('/api/loans/remind', { loanId: loanId }, { throwHttpErrors: true });
        if (response.data.success) {
            await showCustomModal(response.data.message);
        } else {
            await showCustomModal(`Erreur: ${response.data.message}`);
        }
    } catch (error) {
        console.error("Erreur lors de l'envoi du rappel :", error);
        await showCustomModal("Une erreur est survenue lors de l'envoi du rappel.");
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = 'Rappeler le retour';
        }
    }
}

function handleSearch() {
    const query = searchInput.value.trim();
    loadTrendingBooks(query); // Utilise la fonction de chargement des tendances pour la recherche
}

// --- Initialisation de la page ---
document.addEventListener('DOMContentLoaded', () => {
    loadCurrentLoans();
    loadTrendingBooks();

    // Ajout des écouteurs d'événements
    searchButton.addEventListener('click', handleSearch);
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            handleSearch();
        }
    });
});