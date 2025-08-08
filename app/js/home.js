import { apiClient } from "./util/ocho-api.js";
import { showLoading, hideLoading, isAuth, updateNavBar } from "./util/utils.js";
import { TimeFormatter } from "./util/formatter.js"; // Importer les fonctions utilitaires nécessaires

const trendingBooksContainer = document.getElementById('trending-books-container');
const trendingLoadingSpinner = document.getElementById('trendingLoading');
const currentLoansContainer = document.getElementById('current-loans-container');
const loansLoadingSpinner = document.getElementById('loansLoading');
const noLoansMessage = document.getElementById('noLoansMessage');
const currentLoansSection = document.getElementById('current-loans-section');
const userNameDisplay = document.getElementById('userNameDisplay');
const userRoleDisplay = document.getElementById('userRoleDisplay');
const modalContainer = document.getElementById('modalContainer'); // Assurez-vous que ce conteneur est présent dans index.php

// Éléments de recherche
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const trendingBooksTitle = document.getElementById('trendingTitle'); // Pour changer le titre "Ouvrages Tendances"

let searchTimeout = null; // Variable pour stocker le timeout du debounce

window.modalContainer = modalContainer; // Rendre modalContainer accessible globalement pour showCustomModal

document.addEventListener('DOMContentLoaded', async () => {
    // Mettre à jour la barre de navigation et le message de bienvenue
    const authResult = await isAuth();
    const userId = authResult?.user?.id; // Accès aux propriétés via authResult.user
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
        // Indiquer 'home' comme page active pour la navigation
        updateNavBar(userRole, 'home');
        userNameDisplay.textContent = `Bienvenue, ${userName || 'Lecteur'}!`;
        userRoleDisplay.textContent = possibleRoles[userRole] || possibleRoles.guest;

        // Afficher la section des emprunts actuels pour les utilisateurs connectés
        if (userRole !== 'guest' && lecteurId) {
            currentLoansSection.classList.remove('hidden');
            fetchCurrentLoans();
        } else {
            currentLoansSection.classList.add('hidden');
        }
    } else {
        // Pour les invités, la page d'accueil est toujours la page active
        updateNavBar('guest', 'home');
        userNameDisplay.textContent = `Bienvenue, Lecteur !`;
        userRoleDisplay.textContent = '';
        currentLoansSection.classList.add('hidden'); // Cacher la section des emprunts pour les invités
    }

    // Charger les ouvrages tendances pour tous les utilisateurs au chargement initial
    fetchTrendingBooks();

    // Gérer la recherche
    if (searchButton) {
        searchButton.addEventListener('click', () => {
            clearTimeout(searchTimeout); // Annuler tout debounce en cours si le bouton est cliqué
            performSearch();
        });
    }

    if (searchInput) {
        // Écouteur pour le debounce sur chaque saisie
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout); // Annuler le timeout précédent
            searchTimeout = setTimeout(() => {
                performSearch();
            }, 500); // Délai de 500ms après la dernière frappe
        });

        // Écouteur pour la touche Entrée (sans debounce, pour une recherche immédiate si l'utilisateur appuie sur Entrée)
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                clearTimeout(searchTimeout); // Annuler le debounce pour une recherche immédiate
                performSearch();
            }
        });
    }
});

/**
 * Exécute une recherche basée sur l'entrée de l'utilisateur.
 */
async function performSearch() {
    const query = searchInput.value.trim();

    // Cacher la section des emprunts actuels si une recherche est lancée
    // et qu'il y a un contenu non vide dans le champ de recherche.
    if (currentLoansSection) {
        if (query !== '') {
            currentLoansSection.classList.add('hidden');
        } else {
            // Si la recherche est vide, réafficher les emprunts si l'utilisateur est un lecteur
            const authResult = await isAuth();
            if (authResult?.user?.role === 'user' && authResult?.user?.lecteurId) {
                currentLoansSection.classList.remove('hidden');
            }
        }
    }

    if (query) {
        trendingBooksTitle.innerHTML = `Résultats de recherche pour "${query}"`;
        await fetchBooksBySearch(query);
    } else {
        trendingBooksTitle.innerHTML = `Ouvrages Tendances `;
        await fetchTrendingBooks(); // Revenir aux ouvrages tendances si la recherche est vide
    }
}


/**
 * Récupère et affiche les ouvrages tendances.
 */
async function fetchTrendingBooks() {
    showLoading(trendingLoadingSpinner);
    try {
        const response = await apiClient.get('/api/books/trending', { throwHttpErrors: false });


        if (response.data.success && response.data.data.length > 0) {
            trendingBooksContainer.innerHTML = ''; // Vider le conteneur avant d'ajouter de nouveaux livres
            response.data.data.forEach(book => {
                const bookCard = createBookCard(book);
                trendingBooksContainer.appendChild(bookCard);
            });
        } else {
            trendingBooksContainer.innerHTML = '<p class="text-gray-500 text-center w-full">Aucun ouvrage tendance disponible pour le moment.</p>';
        }
    } catch (error) {
        console.error('Erreur lors de la récupération des ouvrages tendances:', error);
        trendingBooksContainer.innerHTML = '<p class="text-red-500 text-center w-full">Une erreur est survenue lors du chargement des ouvrages tendances.</p>';
    } finally {
        hideLoading(trendingLoadingSpinner);
    }
}

/**
 * Récupère et affiche les livres basés sur une requête de recherche.
 * @param {string} query - La requête de recherche.
 */
async function fetchBooksBySearch(query) {
    showLoading(trendingLoadingSpinner);
    try {
        const response = await apiClient.get(`/api/books/search?query=${encodeURIComponent(query)}`, { throwHttpErrors: false });
        if (response.data.success && response.data.data.length > 0) {
            trendingBooksContainer.innerHTML = ''; // Vider le conteneur avant d'ajouter de nouveaux livres
            response.data.data.forEach(book => {
                const bookCard = createBookCard(book);
                trendingBooksContainer.appendChild(bookCard);
            });
        } else {
            trendingBooksContainer.innerHTML = '<p class="text-gray-500 text-center w-full">Aucun résultat trouvé pour votre recherche.</p>';
        }
    } catch (error) {
        console.error('Erreur lors de la recherche des ouvrages:', error);
        trendingBooksContainer.innerHTML = '<p class="text-red-500 text-center w-full">Une erreur est survenue lors de la recherche des ouvrages.</p>';
    } finally {
        hideLoading(trendingLoadingSpinner);
    }
}


/**
 * Crée une carte de livre HTML.
 * @param {object} book - L'objet livre.
 * @returns {HTMLElement} L'élément HTML de la carte de livre.
 */
function createBookCard(book) {
    const card = document.createElement('div');
    card.className = 'bg-white rounded-lg shadow-md overflow-hidden transform transition duration-300 hover:scale-105 hover:shadow-lg';

    // Construire l'URL vers la page de détails du livre
    const detailPageUrl = `/books?id=${book.id}`;
    const cover = book.cover_url ? `<img src="${book.cover_url}" alt="Couverture de ${book.titre}" class="w-full h-48 object-cover">` :     `<svg xmlns="http://www.w3.org/2000/svg" width="800px" height="800px" viewBox="0 0 120 120" fill="none" class="w-full h-48 book-cover-detail mx-auto md:mx-0 mb-6 md:mb-0" title="Pas de couverture disponible">
            <rect width="120" height="120" fill="#aeb0b1"></rect>
            <path fill-rule="evenodd" clip-rule="evenodd" d="M33.2503 38.4816C33.2603 37.0472 34.4199 35.8864 35.8543 35.875H83.1463C84.5848 35.875 85.7503 37.0431 85.7503 38.4816V80.5184C85.7403 81.9528 84.5807 83.1136 83.1463 83.125H35.8543C34.4158 83.1236 33.2503 81.957 33.2503 80.5184V38.4816ZM80.5006 41.1251H38.5006V77.8751L62.8921 53.4783C63.9172 52.4536 65.5788 52.4536 66.6039 53.4783L80.5006 67.4013V41.1251ZM43.75 51.6249C43.75 54.5244 46.1005 56.8749 49 56.8749C51.8995 56.8749 54.25 54.5244 54.25 51.6249C54.25 48.7254 51.8995 46.3749 43.75 46.3749C46.1005 46.3749 43.75 48.7254 43.75 51.6249Z" fill="#687787"></path>
        </svg>`;

    const availabilityClass = book.disponible ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    const availabilityText = book.disponible ? 'Disponible' : 'Non disponible';

    card.innerHTML = `
        <a href="${detailPageUrl}" class="block">
            ${cover}
            <div class="p-4">
                <h4 class="text-lg font-semibold text-gray-800 truncate">${book.titre}</h4>
                <p class="text-sm text-gray-600">${book.auteur || 'Auteur inconnu'}</p>
                <p class="text-sm text-gray-500 mt-2">Catégorie: ${book.categorie || 'Non spécifiée'}</p>
                <span class="inline-block px-2 py-1 mt-3 rounded-full text-xs font-semibold ${availabilityClass}">
                    ${availabilityText}
                </span>
            </div>
        </a>
    `;
    return card;
}

/**
 * Récupère et affiche les emprunts actuels de l'utilisateur.
 * @param {number} lecteurId - L'ID du lecteur.
 */
async function fetchCurrentLoans() {
    showLoading(loansLoadingSpinner);
    try {
        const response = await apiClient.get(`/api/loans/current`, { throwHttpErrors: false });

        if (response.data.success && response.data.data.length > 0) {
            currentLoansContainer.innerHTML = '';
            noLoansMessage.classList.add('hidden');
            response.data.data.forEach(loan => {
                const loanCard = createLoanCard(loan);
                currentLoansContainer.appendChild(loanCard);
            });
        } else {
            currentLoansContainer.innerHTML = '';
            noLoansMessage.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Erreur lors de la récupération des emprunts actuels:', error);
        currentLoansContainer.innerHTML = '<p class="text-red-500 text-center w-full">Une erreur est survenue lors du chargement de vos emprunts.</p>';
        noLoansMessage.classList.add('hidden'); // Cacher le message "aucun emprunt" en cas d'erreur
    } finally {
        hideLoading(loansLoadingSpinner);
    }
}

/**
 * Crée une carte d'emprunt HTML.
 * @param {object} loan - L'objet emprunt.
 * @returns {HTMLElement} L'élément HTML de la carte d'emprunt.
 */
function createLoanCard(loan) {
    const card = document.createElement('div');
    card.className = 'bg-white rounded-lg shadow-md overflow-hidden p-4';

    const dateEmprunt = loan.date_emprunt ? new TimeFormatter(loan.date_emprunt * 1000).formatFullTime() : 'N/A';
    const dateRetour = loan.date_retour ? new TimeFormatter(loan.date_retour * 1000).formatFullTime() : 'N/A';

    // Construire l'URL vers la page de détails du livre emprunté
    const detailPageUrl = `/books?id=${loan.livre_id}`;

    const cover = loan.cover_url ? `<img src="${loan.cover_url}" alt="Couverture de ${loan.titre}" class="w-20 h-24 object-cover rounded-md">` :     `
        <div class="w-24 overflow-hidden h-24 min-w-24  flex items-center justify-center bg-gray-200 rounded-md">
            <svg xmlns="http://www.w3.org/2000/svg" width="800px" height="800px" viewBox="0 0 120 120" fill="none" class="w-full h-full object-cover" title="Pas de couverture disponible">
                <rect width="120" height="120" fill="#aeb0b1"></rect>
                <path fill-rule="evenodd" clip-rule="evenodd" d="M33.2503 38.4816C33.2603 37.0472 34.4199 35.8864 35.8543 35.875H83.1463C84.5848 35.875 85.7503 37.0431 85.7503 38.4816V80.5184C85.7403 81.9528 84.5807 83.1136 83.1463 83.125H35.8543C34.4158 83.1236 33.2503 81.957 33.2503 80.5184V38.4816ZM80.5006 41.1251H38.5006V77.8751L62.8921 53.4783C63.9172 52.4536 65.5788 52.4536 66.6039 53.4783L80.5006 67.4013V41.1251ZM43.75 51.6249C43.75 54.5244 46.1005 56.8749 49 56.8749C51.8995 56.8749 54.25 54.5244 54.25 51.6249C54.25 48.7254 51.8995 46.3749 43.75 46.3749C46.1005 46.3749 43.75 48.7254 43.75 51.6249Z" fill="#687787"></path>
            </svg>
        </div>`;

    card.innerHTML = `
        <a href="${detailPageUrl}" class="flex items-center gap-4">
            ${cover}
            <div>
                <h4 class="text-lg font-semibold text-gray-800" title="${loan.titre}">${loan.titre}</h4>
                <p class="text-sm text-gray-600">Auteur: ${loan.auteur || 'Inconnu'}</p>
                <p class="text-sm text-gray-500">Emprunté le: ${dateEmprunt}</p>
                <p class="text-sm text-gray-500">Retour prévu le: ${dateRetour}</p>
            </div>
        </a>
    `;
    return card;
}
