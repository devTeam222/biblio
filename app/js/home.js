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
        console.log(response.data);
        
        
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

    const coverImageUrl = book.cover_url || 'https://placehold.co/150x200/cccccc/333333?text=Pas+de+couverture';
    const availabilityClass = book.disponible ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    const availabilityText = book.disponible ? 'Disponible' : 'Non disponible';

    card.innerHTML = `
        <a href="${detailPageUrl}" class="block">
            <img src="${coverImageUrl}" alt="Couverture de ${book.titre}" class="w-full h-48 object-cover">
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

    const coverImageUrl = loan.cover_url || 'https://placehold.co/100x150/cccccc/333333?text=Pas+de+couverture';

    card.innerHTML = `
        <a href="${detailPageUrl}" class="flex items-center gap-4">
            <img src="${coverImageUrl}" alt="Couverture de ${loan.titre}" class="w-20 h-24 object-cover rounded-md">
            <div>
                <h4 class="text-lg font-semibold text-gray-800">${loan.titre}</h4>
                <p class="text-sm text-gray-600">Auteur: ${loan.auteur || 'Inconnu'}</p>
                <p class="text-sm text-gray-500">Emprunté le: ${dateEmprunt}</p>
                <p class="text-sm text-gray-500">Retour prévu le: ${dateRetour}</p>
            </div>
        </a>
    `;
    return card;
}
