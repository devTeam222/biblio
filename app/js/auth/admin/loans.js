import { apiClient } from "../../util/ocho-api.js";
import { TimeFormatter } from "../../util/formatter.js";
import { showCustomModal, addLoader, removeLoader, isAuth, updateNavBar } from "../../util/utils.js";

window.modalContainer = document.getElementById('modalContainer');

const userNameDisplay = document.getElementById('userNameDisplay');
const notificationCountEl = document.getElementById('notificationCount');
const adminAvatarEl = document.getElementById('adminAvatar');
const lastUpdateTimeEl = document.getElementById('lastUpdateTime');
const loansTableBody = document.querySelector('#loansTable tbody');
const loanSearchInput = document.getElementById('loanSearchInput');

let allLoans = []; // Pour stocker tous les emprunts et permettre la recherche côté client

document.addEventListener('DOMContentLoaded', async () => {
    const authStatus = await isAuth();

    if (!authStatus.success || !authStatus.roles.includes('admin')) {
        await showCustomModal('Accès non autorisé. Vous devez être un administrateur pour accéder à cette page.', { type: 'alert' });
        window.location.href = '/login';
        return;
    }

    userNameDisplay.textContent = authStatus.user.name || 'Admin';
    updateAdminAvatar(authStatus.user.name);
    updateNavBar(authStatus, 'manage-loans'); // Mettre en surbrillance le lien actif
    updateLastModifiedTime();

    await loadLoans();

    loanSearchInput.addEventListener('input', handleSearch);

    // Délégation d'événements pour les boutons "Marquer comme retourné"
    loansTableBody.addEventListener('click', async (event) => {
        const markReturnedBtn = event.target.closest('.mark-returned-btn');
        if (markReturnedBtn) {
            const loanId = markReturnedBtn.dataset.loanId;
            const bookTitle = markReturnedBtn.dataset.bookTitle;
            const confirm = await showCustomModal(`Confirmer le retour du livre "${bookTitle}" ?`, { type: 'confirm' });
            if (confirm) {
                await markLoanAsReturned(loanId);
            }
        }
    });

    setInterval(loadLoans, 300000); // Actualiser toutes les 5 minutes
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
 * Charge la liste des emprunts depuis l'API.
 */
async function loadLoans() {
    addLoader(loansTableBody);
    try {
        const response = await apiClient.get('/api/admin/loans?action=list');
        if (response.data.success) {
            allLoans = response.data.data; // Stocker les données brutes
            renderLoans(allLoans); // Afficher toutes les données initialement
            updateNotificationCounts(allLoans);
        } else {
            showCustomModal(`Erreur chargement emprunts: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
            loansTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-red-500">Erreur: ${response.data.message || 'Impossible de charger les emprunts.'}</td></tr>`;
        }
    } catch (error) {
        console.error("Erreur lors du chargement des emprunts:", error);
        showCustomModal("Une erreur est survenue lors du chargement des emprunts.", { type: 'alert' });
        loansTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-red-500">Erreur de connexion API.</td></tr>`;
    } finally {
        removeLoader(loansTableBody);
    }
}

/**
 * Met à jour les compteurs de notifications et d'emprunts en retard.
 * @param {Array<Object>} loans - La liste complète des emprunts.
 */
function updateNotificationCounts(loans) {
    const overdueLoans = loans.filter(loan => !loan.rendu && loan.date_retour * 1000 < Date.now());
    if (notificationCountEl) notificationCountEl.textContent = overdueLoans.length;
    // Si vous avez un élément pour overdueCountEl sur la page des emprunts, mettez-le à jour aussi
    // if (overdueCountEl) overdueCountEl.textContent = overdueLoans.length;
}

/**
 * Affiche les emprunts dans le tableau.
 * @param {Array<Object>} loansToDisplay - Les emprunts à afficher.
 */
function renderLoans(loansToDisplay) {
    loansTableBody.innerHTML = ''; // Vider le tableau
    if (loansToDisplay.length === 0) {
        loansTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-gray-500">Aucun emprunt trouvé.</td></tr>`;
        return;
    }
    updateNavBar("admin")

    loansToDisplay.forEach(loan => {
        const loanDate = new TimeFormatter(loan.date_emprunt * 1000, { lang: navigator.language, long: false }).format();
        const returnDate = new TimeFormatter(loan.date_retour * 1000, { lang: navigator.language, long: false }).format();
        const isOverdue = !loan.rendu && loan.date_retour * 1000 < Date.now();
        const statusText = loan.rendu ? 'Retourné' : (isOverdue ? 'En Retard' : 'Actif');
        const statusClass = loan.rendu ? 'text-green-600' : (isOverdue ? 'text-red-600' : 'text-blue-600');

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="py-3 px-2">${loan.titre}</td>
            <td class="py-3 px-2">${loan.lecteur_nom}</td>
            <td class="py-3 px-2">${loanDate}</td>
            <td class="py-3 px-2">${returnDate}</td>
            <td class="py-3 px-2 ${statusClass}">${statusText}</td>
            <td class="py-3 px-2">
                ${!loan.rendu ? `<button class="mark-as-return action-button bg-green-100 text-green-700 hover:bg-green-200 text-sm" data-loan-id="${loan.loan_id}" data-book-title="${loan.titre}">Marquer comme retourné</button>` : ''}
            </td>
        `;
        const markAsReturnedBtn = row.querySelector(".mark-as-return ");
        markAsReturnedBtn && (markAsReturnedBtn.addEventListener('click', ()=>markLoanAsReturned(loan.loan_id, markAsReturnedBtn)));
        loansTableBody.appendChild(row);
    });
}

/**
 * Gère la recherche d'emprunts.
 */
function handleSearch() {
    const searchTerm = loanSearchInput.value.toLowerCase();
    const filteredLoans = allLoans.filter(loan =>
        loan.titre.toLowerCase().includes(searchTerm) ||
        loan.lecteur_nom.toLowerCase().includes(searchTerm) ||
        loan.auteur.toLowerCase().includes(searchTerm) // Assurez-vous que l'auteur est inclus dans les données de l'API
    );
    renderLoans(filteredLoans);
}

/**
 * Marque un emprunt comme retourné.
 * @param {string} loanId - L'ID de l'emprunt à marquer comme retourné.
 */
async function markLoanAsReturned(loanId, btn) {
    addLoader(loansTableBody);
    addLoader(btn); // Ajouter un loader au bouton pour indiquer le traitement en cours
    try {
        const response = await apiClient.post('/api/admin/loans?action=return', { body: {loan_id: loanId} });
        if (response.data.success) {
            showCustomModal('Livre marqué comme retourné avec succès !');
            await loadLoans(); // Recharger la liste des emprunts
            removeLoader(btn); // Retirer le loader du bouton
            updateNotificationCounts(allLoans); // Mettre à jour les compteurs de notifications
            btn.closest('tr').remove(); // Supprimer la ligne du tableau
        } else {
            showCustomModal(`Erreur lors du marquage du retour: ${response.data.message || 'Erreur inconnue'}`);
            removeLoader(btn); // Retirer le loader du bouton même en cas d'erreur
        }
    } catch (error) {
        console.error("Erreur lors du marquage du retour:", error);
        showCustomModal("Une erreur est survenue lors du marquage du retour.");
        removeLoader(btn); // Retirer le loader du bouton en cas d'erreur
    } finally {
        removeLoader(loansTableBody);
    }
}
