// --- Simulation du Backend PHP (à remplacer par de vrais appels API) ---
// Cette section simule les données et les réponses de votre backend PHP.
// Dans une application réelle, vous feriez des requêtes `fetch` à des fichiers PHP
// qui interrogent votre base de données et retournent des JSON.

const MOCK_USER_ID = 1; // ID de l'utilisateur fictif pour cette démo

const mockBooksData = [
    { id: 1, titre: "Le Cycle de Dune", auteur: "Frank Herbert", categorie: "Science-Fiction", disponible: true, cover: "https://placehold.co/150x200/F0F8FF/000000?text=Dune" },
    { id: 2, titre: "L'Alchimiste", auteur: "Paulo Coelho", categorie: "Philosophie", disponible: false, cover: "https://placehold.co/150x200/FFF0F5/000000?text=Alchimiste" },
    { id: 3, titre: "Sapiens", auteur: "Yuval Noah Harari", categorie: "Histoire", disponible: true, cover: "https://placehold.co/150x200/E6E6FA/000000?text=Sapiens" },
    { id: 4, titre: "Harry Potter", auteur: "J.K. Rowling", categorie: "Fantasy", disponible: true, cover: "https://placehold.co/150x200/F5FFFA/000000?text=HP" },
    { id: 5, titre: "1984", auteur: "George Orwell", categorie: "Dystopie", disponible: false, cover: "https://placehold.co/150x200/C0C0C0/000000?text=1984" },
    { id: 6, titre: "Orgueil et Préjugés", auteur: "Jane Austen", categorie: "Roman", disponible: true, cover: "https://placehold.co/150x200/ADD8E6/000000?text=Orgueil" },
    { id: 7, titre: "Le Petit Prince", auteur: "Antoine de Saint-Exupéry", categorie: "Philosophie", disponible: true, cover: "https://placehold.co/150x200/FFDAB9/000000?text=Petit+Prince" },
    { id: 8, titre: "Les Misérables", auteur: "Victor Hugo", categorie: "Classique", disponible: true, cover: "https://placehold.co/150x200/E0FFFF/000000?text=Miserables" },
    { id: 9, titre: "Crime et Châtiment", auteur: "Fiodor Dostoïevski", categorie: "Classique", disponible: false, cover: "https://placehold.co/150x200/F8F8FF/000000?text=Crime" },
];

const mockLoansData = [
    { id: 101, lecteur_id: MOCK_USER_ID, livre_id: 1, date_emprunt: "2025-06-01", date_retour: "2025-06-15", rendu: false },
    { id: 102, lecteur_id: MOCK_USER_ID, livre_id: 5, date_emprunt: "2025-05-20", date_retour: "2025-06-20", rendu: true }, // Supposons celui-ci rendu
    { id: 103, lecteur_id: 2, livre_id: 3, date_emprunt: "2025-06-10", date_retour: "2025-06-25", rendu: false }, // Autre utilisateur
];

/**
 * Simule un appel API vers le backend PHP.
 * @param {string} endpoint - L'endpoint de l'API (ex: '/api/books/trending').
 * @param {Object} [data=null] - Les données à envoyer avec la requête (pour POST/PUT).
 * @param {string} [method='GET'] - La méthode HTTP.
 * @returns {Promise<Object>} Une promesse qui résout avec les données simulées.
 */
function mockApiCall(endpoint, data = null, method = 'GET') {
    return new Promise(resolve => {
        setTimeout(() => {
            let responseData = {};
            let success = true;

            if (endpoint === '/api/books/trending') {
                // Retourne quelques livres qui ne sont pas empruntés par l'utilisateur actuel et sont disponibles
                const trendingBooks = mockBooksData.filter(book => book.disponible);
                responseData = { success: true, data: trendingBooks.slice(0, 4) }; // Limite à 4 pour les tendances
            } else if (endpoint === `/api/loans/user/${MOCK_USER_ID}`) {
                const userLoans = mockLoansData.filter(loan => loan.lecteur_id === MOCK_USER_ID)
                    .map(loan => {
                        const book = mockBooksData.find(b => b.id === loan.livre_id);
                        return { ...loan, titre: book ? book.titre : 'Titre inconnu', auteur: book ? book.auteur : 'Auteur inconnu' };
                    });
                responseData = { success: true, data: userLoans };
            } else if (endpoint === '/api/books/borrow' && method === 'POST') {
                const { bookId, userId } = data;
                const bookToBorrow = mockBooksData.find(book => book.id === bookId);
                if (bookToBorrow && bookToBorrow.disponible && userId === MOCK_USER_ID) {
                    bookToBorrow.disponible = false; // Marque le livre comme non disponible
                    const newLoan = {
                        id: Math.floor(Math.random() * 1000) + 200, // ID d'emprunt aléatoire
                        lecteur_id: userId,
                        livre_id: bookId,
                        date_emprunt: new Date().toISOString().slice(0, 10),
                        date_retour: new Date(Date.now() + 15 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10), // +15 jours
                        rendu: false
                    };
                    mockLoansData.push(newLoan); // Ajoute le nouvel emprunt aux données mockées
                    responseData = { success: true, message: "Livre emprunté avec succès!", loan: newLoan };
                } else {
                    success = false;
                    responseData = { success: false, message: "Livre non disponible ou erreur d'emprunt." };
                }
            } else if (endpoint === '/api/loans/remind' && method === 'POST') {
                const { loanId } = data;
                const loan = mockLoansData.find(l => l.id === loanId);
                if (loan && !loan.rendu) {
                    responseData = { success: true, message: `Rappel envoyé pour l'emprunt ${loanId}.` };
                } else {
                    success = false;
                    responseData = { success: false, message: "Emprunt non trouvé ou déjà rendu." };
                }
            } else if (endpoint === '/api/search') {
                const { query } = data;
                if (!query) {
                    responseData = { success: true, data: mockBooksData.filter(book => book.disponible) }; // Si vide, retourne tous les dispo
                } else {
                    const filteredBooks = mockBooksData.filter(book =>
                        book.titre.toLowerCase().includes(query.toLowerCase()) ||
                        book.auteur.toLowerCase().includes(query.toLowerCase()) ||
                        book.categorie.toLowerCase().includes(query.toLowerCase())
                    );
                    responseData = { success: true, data: filteredBooks };
                }
            } else {
                success = false;
                responseData = { success: false, message: "Endpoint non trouvé." };
            }

            resolve(responseData);
        }, 700); // Simule une latence réseau de 700ms
    });
}
// --- Fin de la simulation du Backend PHP ---


// --- Fonctions pour la manipulation du DOM et les interactions ---

const trendingBooksContainer = document.getElementById('trending-books-container');
const currentLoansContainer = document.getElementById('current-loans-container');
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const noLoansMessage = document.getElementById('noLoansMessage');
const loansLoadingSpinner = document.getElementById('loansLoading');
const trendingLoadingSpinner = document.getElementById('trendingLoading');

/**
 * Affiche un spinner de chargement.
 * @param {HTMLElement} spinnerElement - L'élément du spinner.
 */
function showLoading(spinnerElement) {
    spinnerElement.classList.remove('hidden');
}

/**
 * Cache un spinner de chargement.
 * @param {HTMLElement} spinnerElement - L'élément du spinner.
 */
function hideLoading(spinnerElement) {
    spinnerElement.classList.add('hidden');
}

/**
 * Rend une carte de livre dans le DOM.
 * @param {Object} book - L'objet livre.
 * @param {boolean} showBorrowButton - Indique si le bouton "Emprunter" doit être affiché.
 * @returns {string} Le HTML de la carte du livre.
 */
function renderBookCard(book, showBorrowButton = true) {
    const availabilityClass = book.disponible ? 'bg-green-200 text-green-800' : 'bg-red-200 text-red-800';
    const availabilityText = book.disponible ? 'Disponible' : 'Non disponible';
    const buttonHtml = showBorrowButton
        ? `<button class="mt-3 ${book.disponible ? 'bg-green-500 hover:bg-green-600' : 'bg-gray-400 cursor-not-allowed'} text-white py-1.5 px-4 rounded-md text-sm transition duration-200 ease-in-out" ${book.disponible ? `onclick="handleBorrowBook(${book.id})"` : 'disabled'}>
                    ${book.disponible ? 'Emprunter' : 'Non disponible'}
                   </button>`
        : '';

    const coverEl = book.cover ? `<img src="${book.cover}" alt="Couverture de ${book.titre}" class="book-cover mx-auto mb-3">` : `<svg xmlns="http://www.w3.org/2000/svg" width="800px" height="800px" viewBox="0 0 120 120" fill="none" class="book-cover mx-auto mb-3" title="Pas de couverture disponible">
<rect width="120" height="120" fill="#EFF1F3"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M33.2503 38.4816C33.2603 37.0472 34.4199 35.8864 35.8543 35.875H83.1463C84.5848 35.875 85.7503 37.0431 85.7503 38.4816V80.5184C85.7403 81.9528 84.5807 83.1136 83.1463 83.125H35.8543C34.4158 83.1236 33.2503 81.957 33.2503 80.5184V38.4816ZM80.5006 41.1251H38.5006V77.8751L62.8921 53.4783C63.9172 52.4536 65.5788 52.4536 66.6039 53.4783L80.5006 67.4013V41.1251ZM43.75 51.6249C43.75 54.5244 46.1005 56.8749 49 56.8749C51.8995 56.8749 54.25 54.5244 54.25 51.6249C54.25 48.7254 51.8995 46.3749 49 46.3749C46.1005 46.3749 43.75 48.7254 43.75 51.6249Z" fill="#687787"/>
</svg>`;

    return `
                <div class="bg-gray-100 p-4 rounded-lg shadow-sm text-center transform hover:scale-105 transition duration-200 ease-in-out">
                    ${coverEl}
                    <h4 class="font-bold text-gray-900 text-lg">${book.titre}</h4>
                    <p class="text-sm text-gray-600">${book.auteur}</p>
                    <span class="inline-block ${availabilityClass} text-xs font-semibold px-2.5 py-0.5 rounded-full mt-2">${availabilityText}</span>
                    ${buttonHtml}
                </div>
            `;
}

/**
 * Charge et affiche les ouvrages tendances.
 * @param {string} [searchTerm=''] - Terme de recherche optionnel pour filtrer les livres.
 */
async function loadTrendingBooks(searchTerm = '') {
    showLoading(trendingLoadingSpinner);
    trendingBooksContainer.innerHTML = ''; // Vide le conteneur avant de charger

    try {
        const response = await mockApiCall('/api/search', { query: searchTerm }, 'GET');
        hideLoading(trendingLoadingSpinner);

        if (response.success) {
            const booksToDisplay = searchTerm ? response.data : response.data.slice(0, 4); // Si recherche, affiche tous les résultats, sinon 4 tendances
            if (booksToDisplay.length > 0) {
                booksToDisplay.forEach(book => {
                    trendingBooksContainer.innerHTML += renderBookCard(book, true);
                });
            } else {
                trendingBooksContainer.innerHTML = '<p class="text-gray-500 col-span-full">Aucun ouvrage trouvé pour votre recherche.</p>';
            }
        } else {
            trendingBooksContainer.innerHTML = `<p class="text-red-500 col-span-full">Erreur: ${response.message}</p>`;
        }
    } catch (error) {
        console.error("Erreur lors du chargement des ouvrages tendances :", error);
        hideLoading(trendingLoadingSpinner);
        trendingBooksContainer.innerHTML = '<p class="text-red-500 col-span-full">Une erreur est survenue lors du chargement des ouvrages.</p>';
    }
}

/**
 * Charge et affiche les emprunts actuels de l'utilisateur.
 */
async function loadCurrentLoans() {
    showLoading(loansLoadingSpinner);
    currentLoansContainer.innerHTML = ''; // Vide le conteneur
    noLoansMessage.classList.add('hidden'); // Cache le message "pas d'emprunts"

    try {
        const response = await mockApiCall(`/api/loans/user/${MOCK_USER_ID}`, null, 'GET');
        hideLoading(loansLoadingSpinner);

        if (response.success) {
            const activeLoans = response.data.filter(loan => !loan.rendu); // Filtrer les emprunts non rendus
            if (activeLoans.length > 0) {
                activeLoans.forEach(loan => {
                    const returnDate = new Date(loan.date_retour);
                    const today = new Date();
                    // Vérifie si la date de retour est passée
                    const isOverdue = returnDate < today;

                    currentLoansContainer.innerHTML += `
                                <div class="bg-gray-100 p-4 rounded-lg shadow-sm">
                                    <h4 class="font-bold text-gray-900 text-lg mb-1">${loan.titre}</h4>
                                    <p class="text-sm text-gray-600">Auteur : ${loan.auteur}</p>
                                    <p class="text-sm text-gray-600">Date d'emprunt : ${loan.date_emprunt}</p>
                                    <p class="text-sm ${isOverdue ? 'text-red-600' : 'text-green-600'} font-semibold">Date de retour : ${loan.date_retour} ${isOverdue ? '(En retard)' : ''}</p>
                                    <button class="mt-3 bg-red-500 hover:bg-red-600 text-white py-1.5 px-4 rounded-md text-sm transition duration-200 ease-in-out" onclick="handleRemindReturn(${loan.id})">Rappeler le retour</button>
                                </div>
                            `;
                });
            } else {
                noLoansMessage.classList.remove('hidden'); // Affiche le message
            }
        } else {
            currentLoansContainer.innerHTML = `<p class="text-red-500 col-span-full">Erreur: ${response.message}</p>`;
        }
    } catch (error) {
        console.error("Erreur lors du chargement des emprunts actuels :", error);
        hideLoading(loansLoadingSpinner);
        currentLoansContainer.innerHTML = '<p class="text-red-500 col-span-full">Une erreur est survenue lors du chargement de vos emprunts.</p>';
    }
}

/**
 * Gère l'action d'emprunter un livre.
 * @param {number} bookId - L'ID du livre à emprunter.
 */
async function handleBorrowBook(bookId) {
    if (!confirm('Voulez-vous vraiment emprunter ce livre ?')) { // Utilisation de confirm pour la démo, remplacer par une modale custom en prod
        return;
    }

    // Désactiver temporairement le bouton pour éviter les clics multiples
    const button = event.target;
    button.disabled = true;
    button.textContent = 'Emprunt en cours...';

    try {
        const response = await mockApiCall('/api/books/borrow', { bookId: bookId, userId: MOCK_USER_ID }, 'POST');
        if (response.success) {
            alert(response.message); // Utilisation d'alert pour la démo, remplacer par une modale custom
            // Recharger les sections pour mettre à jour l'état
            await loadTrendingBooks();
            await loadCurrentLoans();
        } else {
            alert(`Erreur: ${response.message}`);
        }
    } catch (error) {
        console.error("Erreur lors de l'emprunt du livre :", error);
        alert("Une erreur est survenue lors de l'emprunt.");
    } finally {
        // Réactiver le bouton (si nécessaire, ou l'état sera géré par le rechargement)
        button.disabled = false;
        button.textContent = 'Emprunter'; // Peut être écrasé si rechargement complet
    }
}

/**
 * Gère l'action de rappeler le retour d'un emprunt.
 * @param {number} loanId - L'ID de l'emprunt.
 */
async function handleRemindReturn(loanId) {
    // Désactiver temporairement le bouton
    const button = event.target;
    button.disabled = true;
    button.textContent = 'Envoi...';

    try {
        const response = await mockApiCall('/api/loans/remind', { loanId: loanId }, 'POST');
        if (response.success) {
            alert(response.message); // Remplacer par une modale custom
        } else {
            alert(`Erreur: ${response.message}`);
        }
    } catch (error) {
        console.error("Erreur lors de l'envoi du rappel :", error);
        alert("Une erreur est survenue lors de l'envoi du rappel.");
    } finally {
        button.disabled = false;
        button.textContent = 'Rappeler le retour';
    }
}

/**
 * Gère la recherche de livres.
 */
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