import { apiClient } from './ocho-api.js';

function showCustomModal(message, { type = 'alert', actions = [] } = {}) {
    return new Promise((resolve, reject) => { // Ajouter reject pour gérer les erreurs
        // Vérification du type du message
        if (typeof message !== 'string') {
            console.error("Erreur: Le paramètre 'message' doit être une chaîne de caractères.");
            return reject(new TypeError("Le message doit être une chaîne de caractères."));
        }

        // Vérification du type des actions
        if (!Array.isArray(actions)) {
            console.error("Erreur: Le paramètre 'actions' doit être un tableau.");
            return reject(new TypeError("Les actions doivent être un tableau."));
        }

        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'modal-overlay';

        let buttonsHtml = '';

        // Si des actions personnalisées sont fournies, générez les boutons à partir de celles-ci.
        if (actions.length > 0) {
            buttonsHtml = actions.map((action, index) => {
                // Vérification du type de chaque action
                if (typeof action !== 'object' || action === null) {
                    console.error(`Erreur: L'action à l'index ${index} n'est pas un objet.`);
                    return reject(new TypeError(`L'action à l'index ${index} doit être un objet.`));
                }
                if (typeof action.label !== 'string' || action.label.trim() === '') {
                    console.error(`Erreur: L'action à l'index ${index} doit avoir une propriété 'label' de type chaîne non vide.`);
                    return reject(new TypeError(`L'action à l'index ${index} doit avoir un label de type chaîne.`));
                }
                if (typeof action.callback !== 'function') {
                    console.error(`Erreur: L'action à l'index ${index} doit avoir une propriété 'callback' de type fonction.`);
                    return reject(new TypeError(`L'action à l'index ${index} doit avoir une callback de type fonction.`));
                }


                const defaultClasses = "font-bold py-2 px-4 rounded-lg";
                const buttonClasses = action.className || "bg-blue-600 hover:bg-blue-700 text-white"; // Style par défaut si non spécifié

                return `<button id="modalBtn-${index}" class="${defaultClasses} ${buttonClasses}">${action.label}</button>`;
            }).join('');
        } else {
            // Si aucune action personnalisée n'est fournie, utilisez les types 'alert' ou 'confirm' par défaut.
            if (type === 'alert') {
                buttonsHtml = `<button id="modalCancelBtn" class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded-lg">Fermer</button>`;
            } else if (type === 'confirm') {
                buttonsHtml = `
                    <button id="modalOkBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">OK</button>
                    <button id="modalCancelBtn" class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded-lg">Annuler</button>
                `;
            } else {
                console.error("Erreur: Le paramètre 'type' doit être 'alert' ou 'confirm' si aucune action n'est spécifiée.");
                return reject(new Error("Type de modal invalide."));
            }
        }


        modalOverlay.innerHTML = `
            <div class="modal-content">
                <p class="text-lg mb-4">${message}</p>
                <div class="flex justify-center gap-4">
                    ${buttonsHtml}
                </div>
            </div>
        `;
        // Assurez-vous que modalContainer est défini globalement ou passé en paramètre
        // Par exemple: const modalContainer = document.getElementById('modal-container');
        if (!window.modalContainer) {
            console.error("Erreur: 'modalContainer' n'est pas défini. Veuillez vous assurer qu'un élément conteneur pour le modal existe dans le DOM.");
            return reject(new Error("'modalContainer' non défini."));
        }
        modalContainer.appendChild(modalOverlay);

        if (actions.length > 0) {
            // Attacher les écouteurs d'événements aux boutons générés
            actions.forEach((action, index) => {
                const button = document.getElementById(`modalBtn-${index}`);
                if (button) {
                    button.onclick = () => {
                        modalContainer.removeChild(modalOverlay);
                        action.callback(); // Exécute la fonction de rappel
                        resolve(action.value !== undefined ? action.value : true); // Résout avec la valeur ou true par défaut
                    };
                }
            });
        } else {
            // Gestion des types 'alert' et 'confirm' si aucune action n'est spécifiée
            const okButton = document.getElementById('modalOkBtn');
            if (okButton) {
                okButton.onclick = () => {
                    modalContainer.removeChild(modalOverlay);
                    resolve(true);
                };
            }

            const cancelButton = document.getElementById('modalCancelBtn');
            if (cancelButton) {
                cancelButton.onclick = () => {
                    modalContainer.removeChild(modalOverlay);
                    resolve(false);
                };
            }
        }
    });
}

function addLoader(el) {
    el.innerHTML += `<span class="loader"></span>`;
}
function removeLoader(el) {
    const loader = el.querySelector('.loader');
    if (loader) {
        el.removeChild(loader);
    }
}

function showLoading(spinnerElement) {
    spinnerElement.classList.remove('hidden');
}

function hideLoading(spinnerElement) {
    spinnerElement.classList.add('hidden');
}

function isAuth() {
    return new Promise(async (resolve) => {
        const response = await apiClient.get('/api/auth/check.php', { throwHttpErrors: true });
        const userDetails = response?.data?.user || null; // Détails de l'utilisateur authentifié
        const authEvent = new CustomEvent('authchange', {
            detail: {
                userId: userDetails?.id || null, // ID de l'utilisateur authentifié
                userName: userDetails?.name || null, // Nom de l'utilisateur authentifié
                role: userDetails?.role || null // Rôle de l'utilisateur authentifié
            }
        });
        document.dispatchEvent(authEvent); // Déclenche l'événement d'authentification
        resolve(!!response?.data?.success ? response.data : null); // Retourne true si l'utilisateur est authentifié
    });
}
// Create a custom event to handle user authentication state
const userAuthEvent = new CustomEvent('authchange', {
    detail: {
        userId: null,
        userName: null
    }
});
document.dispatchEvent(userAuthEvent); // Dispatch the event initially
function updateNavBar(type = 'user') {
    const mainNav = document.getElementById('mainNav');
    if (!mainNav) {
        return; // Sortir si l'élément n'existe pas
    }
    mainNav.innerHTML = ''; // Vider la navigation existante
    let navHtml; // Initialiser la variable navHtml
    // Vérifier le type d'utilisateur
    switch (type) {
        case 'admin':
            navHtml = `
                <a href="/admin" class="nav-link bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard-icon lucide-layout-dashboard">
                        <rect width="7" height="9" x="3" y="3" rx="1" />
                        <rect width="7" height="5" x="14" y="3" rx="1" />
                        <rect width="7" height="9" x="14" y="12" rx="1" />
                        <rect width="7" height="5" x="3" y="16" rx="1" />
                    </svg>
                    Tableau de bord
                </a>
                <a href="/" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                    <svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-house-icon lucide-house">
                        <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8" />
                        <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                    </svg>
                    Accueil
                </a>
                <a href="#" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5s3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18s-3.332.477-4.5 1.253" />
                    </svg>
                    Gérer Livres
                </a>
                <a href="#" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-cog-icon lucide-user-round-cog">
                        <path d="m14.305 19.53.923-.382" />
                        <path d="m15.228 16.852-.923-.383" />
                        <path d="m16.852 15.228-.383-.923" />
                        <path d="m16.852 20.772-.383.924" />
                        <path d="m19.148 15.228.383-.923" />
                        <path d="m19.53 21.696-.382-.924" />
                        <path d="M2 21a8 8 0 0 1 10.434-7.62" />
                        <path d="m20.772 16.852.924-.383" />
                        <path d="m20.772 19.148.924.383" />
                        <circle cx="10" cy="8" r="5" />
                        <circle cx="18" cy="18" r="3" />
                    </svg>
                    Gérer Utilisateurs
                </a>
                <button id="logoutButton" class="nav-link bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Déconnexion
                </button>
            `;
            break;
        case 'author':
            navHtml = `
            <a href="author-dashboard.html" class="nav-link bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard-icon lucide-layout-dashboard">
                    <rect width="7" height="9" x="3" y="3" rx="1" />
                    <rect width="7" height="5" x="14" y="3" rx="1" />
                    <rect width="7" height="9" x="14" y="12" rx="1" />
                    <rect width="7" height="5" x="3" y="16" rx="1" />
                </svg>
                Tableau de bord
            </a>
            <a href="#" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5s3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18s-3.332.477-4.5 1.253" />
                </svg>
                Mes Livres
            </a>
            <a href="#" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                Statistiques Livres
            </a>
            <button id="logoutButton" class="nav-link bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Déconnexion
            </button>`;
            break;
        case 'user':
            navHtml = `
            <a href="/" class="nav-link bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-house-icon lucide-house">
                    <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8" />
                    <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                </svg>
                Accueil
            </a>
            <a href="livres-user.html" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg"  class="h-5 w-5 mr-2" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-icon lucide-search">
                    <path d="m21 21-4.34-4.34" />
                    <circle cx="11" cy="11" r="8" />
                </svg>
                Rechercher Livres
            </a>
            `;
            break;
        default:
            navHtml = `
                <a href="/" class="nav-link bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 9.414V17a1 1 0 01-1.447.894l-3-1A1 1 0 017 15V9.414L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                    </svg>
                    Accueil
                </a>
            `;
            break;
    }
    mainNav.innerHTML = navHtml; // Met à jour la barre de navigation avec le contenu approprié
    const logoutButton = document.getElementById('logoutButton');
    // Gestion de la déconnexion
    logoutButton && (logoutButton.addEventListener('click', handleLogout));
}

document.addEventListener('authchange', (event) => {
    updateNavBar();
    const mainNav = document.getElementById('mainNav');
    const { userId, userName } = event.detail;
    const userNameDisplay = document.getElementById('userNameDisplay');
    const CURRENT_USER_ID = userId || null; // Utilisateur authentifié
    const CURRENT_USER_NAME = userName || null; // Nom de l'utilisateur authentifié


    if (CURRENT_USER_ID) {
        // Liens pour utilisateur authentifié
        userNameDisplay.textContent = `Bonjour, ${CURRENT_USER_NAME || 'Lecteur'}!`;
        mainNav.innerHTML += `
                        <a href="loans-history.html" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-history-icon lucide-history">
                                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                                <path d="M3 3v5h5" />
                                <path d="M12 7v5l4 2" />
                            </svg>
                            Mes Emprunts
                        </a>
                        <a href="profile.html" class="nav-link bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-icon lucide-user-round">
                                <circle cx="12" cy="8" r="5" />
                                <path d="M20 21a8 8 0 0 0-16 0" />
                            </svg>
                            Mon Compte
                        </a>
                        <button id="logoutButton" class="nav-link bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Déconnexion
                        </button>
                    `;
        document.getElementById('logoutButton').addEventListener('click', handleLogout);

    } else {
        // Liens pour utilisateur non authentifié (invité)
        userNameDisplay && (userNameDisplay.textContent = ''); // Cacher le nom d'utilisateur
        mainNav && (mainNav.innerHTML += `
                        <a href="/login" class="nav-link bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Se connecter
                        </a>
                        <a href="/register" class="nav-link bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-2 px-4 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                            S'inscrire
                        </a>
                    `);
    }
})
updateNavBar(); // Appel initial pour mettre à jour la barre de navigation

async function handleLogout() {
    const logoutButton = document.getElementById('logoutButton');
    await showCustomModal('Êtes-vous sûr de vouloir vous déconnecter ?', {
        type: 'confirm',
        actions: [
            {
                label: 'Oui, déconnecter',
                callback: async () => {
                    addLoader(logoutButton); // Ajouter un loader au bouton de déconnexion
                    await logout();
                    removeLoader(logoutButton); // Retirer le loader après la déconnexion
                },
                className: 'bg-red-600 hover:bg-red-700 text-white',
                value: true  // valeur de retour explicite
            },
            {
                label: 'Annuler',
                callback: () => { },
                className: 'bg-gray-400 hover:bg-gray-500 text-white',
                value: false // valeur de retour explicite
            }
        ]
    });

}
async function logout() {
    try {
        const response = await apiClient.post('/api/auth/logout.php');
        if (!response?.data?.success) {
            console.log('Échec de la déconnexion:', response?.data?.message || 'Erreur inconnue');
            showCustomModal('Échec de la déconnexion. Veuillez réessayer.');
            return;
        }
        // Déconnexion réussie, réinitialiser l'état de l'utilisateur
        const authEvent = new CustomEvent('authchange', {
            detail: {
                userId: null, // Réinitialiser l'ID de l'utilisateur
                userName: null, // Réinitialiser le nom de l'utilisateur
                role: null // Réinitialiser le rôle de l'utilisateur
            }
        });
        document.dispatchEvent(authEvent); // Déclenche l'événement d'authentification
        showCustomModal('Vous avez été déconnecté avec succès.', { type: 'alert' });
        // Rediriger vers la page de connexion ou d'accueil
        window.location.href = '/'; // Redirection vers la page de connexion
    } catch (error) {
        console.error('Erreur lors de la déconnexion:', error);
        showCustomModal('Une erreur est survenue lors de la déconnexion. Veuillez réessayer.');
    }
}


export { showCustomModal, showLoading, hideLoading, isAuth, updateNavBar, addLoader, removeLoader, logout, handleLogout, userAuthEvent }; // Exporter les fonctions et variables nécessaires