import { apiClient } from "./util/ocho-api.js";
import { showCustomModal, addLoader, removeLoader, isAuth, updateNavBar } from "./util/utils.js";
import { TimeFormatter } from "./util/formatter.js";

// DOM Elements
const profileName = document.getElementById('profileName');
const profileEmail = document.getElementById('profileEmail');
const profileRole = document.getElementById('profileRole');
const profileBio = document.getElementById('profileBio');
const profileBirthdate = document.getElementById('profileBirthdate');
const editProfileBtn = document.getElementById('editProfileBtn');
const profileUpdateFormContainer = document.getElementById('profileUpdateFormContainer');
const profileUpdateForm = document.getElementById('profileUpdateForm');
const updateName = document.getElementById('updateName');
const updateBio = document.getElementById('updateBio');
const updateBirthdate = document.getElementById('updateBirthdate');
const updatePassword = document.getElementById('updatePassword');
const cancelUpdateProfileBtn = document.getElementById('cancelUpdateProfileBtn');
const contactAdminForm = document.getElementById('contactAdminForm');
const adminMessage = document.getElementById('adminMessage');

const profileDetailsTabBtn = document.getElementById('profileDetailsTabBtn');
const loanHistoryTabBtn = document.getElementById('loanHistoryTabBtn');
const subscriptionHistoryTabBtn = document.getElementById('subscriptionHistoryTabBtn');
const profileDetailsContent = document.getElementById('profileDetailsContent');
const loanHistoryContent = document.getElementById('loanHistoryContent');
const subscriptionHistoryContent = document.getElementById('subscriptionHistoryContent');
const loanHistoryTableBody = document.getElementById('loanHistoryTableBody');
const subscriptionHistoryTableBody = document.getElementById('subscriptionHistoryTableBody');

// Global state
let currentUser = null;
const roles = {
    'admin': 'Administrateur',
    'user': 'Lecteur',
    'author': 'Auteur',
};

document.addEventListener('DOMContentLoaded', async () => {
    const authResult = await isAuth();
    if (!authResult || !authResult.user || !authResult.user.id) {
        window.location.href = '/login'; // Rediriger si non connecté
        return;
    }

    currentUser = authResult.user;
    updateNavBar(currentUser.role, window.location.pathname); // Mettre à jour la barre de navigation

    loadProfileData();

    // Event Listeners for tabs
    profileDetailsTabBtn.addEventListener('click', () => switchTab('details'));
    loanHistoryTabBtn.addEventListener('click', () => switchTab('loans'));
    subscriptionHistoryTabBtn.addEventListener('click', () => switchTab('subscriptions'));

    // Event Listeners for profile update
    editProfileBtn.addEventListener('click', toggleProfileUpdateForm);
    cancelUpdateProfileBtn.addEventListener('click', toggleProfileUpdateForm);
    profileUpdateForm.addEventListener('submit', handleProfileUpdate);

    // Event Listener for contact admin form
    contactAdminForm.addEventListener('submit', handleContactAdmin);

    // Initial tab display
    switchTab('details');
});

/**
 * Bascule entre les onglets de la page de profil.
 * @param {string} tabName - 'details', 'loans', ou 'subscriptions'.
 */
function switchTab(tabName) {
    // Remove active classes from all tab buttons
    profileDetailsTabBtn.classList.remove('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
    profileDetailsTabBtn.classList.add('bg-gray-100', 'text-gray-700');
    loanHistoryTabBtn.classList.remove('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
    loanHistoryTabBtn.classList.add('bg-gray-100', 'text-gray-700');
    subscriptionHistoryTabBtn.classList.remove('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
    subscriptionHistoryTabBtn.classList.add('bg-gray-100', 'text-gray-700');

    // Hide all tab contents
    profileDetailsContent.classList.add('hidden');
    loanHistoryContent.classList.add('hidden');
    subscriptionHistoryContent.classList.add('hidden');

    // Show active tab content and add active class to button
    if (tabName === 'details') {
        profileDetailsTabBtn.classList.add('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
        profileDetailsContent.classList.remove('hidden');
    } else if (tabName === 'loans') {
        loanHistoryTabBtn.classList.add('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
        loanHistoryContent.classList.remove('hidden');
    } else if (tabName === 'subscriptions') {
        subscriptionHistoryTabBtn.classList.add('active', 'bg-white', 'text-indigo-700', 'border-indigo-500');
        subscriptionHistoryContent.classList.remove('hidden');
    }
}

/**
 * Charge les données du profil, l'historique des emprunts et des abonnements.
 */
async function loadProfileData() {
    // Afficher des loaders pendant le chargement
    addLoader(profileDetailsContent, "absolute top-[50%] left-[50%] translate-x-[-50%] translate-y-[-50%]");
    
    loanHistoryTableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500"><span class="loader"></span> Chargement de l\'historique des emprunts...</td></tr>';
    subscriptionHistoryTableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500"><span class="loader"></span> Chargement de l\'historique des abonnements...</td></tr>';

    try {
        const response = await apiClient.get('/api/profile?action=details', { throwHttpErrors: false });

        if (response.data.success) {
            
            const { user, loan_history, subscription_history } = response.data.data;
            currentUser = user; // Mettre à jour l'objet utilisateur global
            renderProfileDetails(user);
            renderLoanHistory(loan_history);
            renderSubscriptionHistory(subscription_history);
        } else {
            showCustomModal(`Erreur lors du chargement du profil: ${response.data.message || 'Inconnu'}`, { type: 'alert' });
            profileDetailsContent.innerHTML = `<p class="text-red-500 text-center py-4">${response.data.message || 'Erreur lors du chargement du profil.'}</p>`;
            loanHistoryTableBody.innerHTML = `<tr><td colspan="4" class="text-red-500 text-center py-4">${response.data.message || 'Erreur lors du chargement de l\'historique.'}</td></tr>`;
            subscriptionHistoryTableBody.innerHTML = `<tr><td colspan="4" class="text-red-500 text-center py-4">${response.data.message || 'Erreur lors du chargement de l\'historique.'}</td></tr>`;
        }
    } catch (error) {
        console.error('Erreur lors de la récupération des données du profil:', error);
        showCustomModal('Une erreur est survenue lors du chargement de votre profil. Veuillez réessayer.', { type: 'alert' });
        profileDetailsContent.innerHTML = '<p class="text-red-500 text-center py-4">Erreur de connexion au serveur.</p>';
        loanHistoryTableBody.innerHTML = '<tr><td colspan="4" class="text-red-500 text-center py-4">Erreur de connexion au serveur.</td></tr>';
        subscriptionHistoryTableBody.innerHTML = '<tr><td colspan="4" class="text-red-500 text-center py-4">Erreur de connexion au serveur.</td></tr>';
    }finally{
        // Retirer les loaders après le chargement
        removeLoader(profileDetailsContent);
    }
}

/**
 * Rend les détails du profil utilisateur.
 * @param {object} user - L'objet utilisateur.
 */
function renderProfileDetails(user) {
    
    profileName.textContent = user.nom || 'N/A';
    profileEmail.textContent = user.email || 'N/A';
    profileRole.textContent = roles[user.role] || user.role || 'N/A';
    profileBio.textContent = user.bio || 'Aucune bio.';
    profileBirthdate.textContent = user.date_naissance ? new TimeFormatter(user.date_naissance * 1000).format() : 'N/A';

    // Pré-remplir le formulaire de mise à jour
    updateName.value = user.nom || '';
    updateBio.value = user.bio || '';
    updateBirthdate.value = user.date_naissance ? new Date(user.date_naissance * 1000).toISOString().split('T')[0] : '';
}

/**
 * Affiche ou masque le formulaire de mise à jour du profil.
 */
function toggleProfileUpdateForm() {
    profileUpdateFormContainer.classList.toggle('hidden');
    editProfileBtn.classList.toggle('hidden');
}

/**
 * Gère la soumission du formulaire de mise à jour du profil.
 * @param {Event} event - L'événement de soumission du formulaire.
 */
async function handleProfileUpdate(event) {
    event.preventDefault();
    const submitButton = profileUpdateForm.querySelector('button[type="submit"]');
    addLoader(submitButton);
    submitButton.disabled = true;

    const updatedData = {
        nom: updateName.value,
        bio: updateBio.value,
        date_naissance: updateBirthdate.value,
        password: updatePassword.value // Le backend gérera si c'est vide ou non
    };

    try {
        const response = await apiClient.post('/api/profile?action=update', { body: updatedData });

        if (response.data.success) {
            showCustomModal('Profil mis à jour avec succès !', { type: 'success' });
            toggleProfileUpdateForm(); // Masquer le formulaire
            loadProfileData(); // Recharger les données pour afficher les mises à jour
            updatePassword.value = ''; // Vider le champ mot de passe après succès
        } else {
            showCustomModal(`Erreur lors de la mise à jour: ${response.data.message || 'Inconnu'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error('Erreur lors de la mise à jour du profil:', error);
        showCustomModal('Une erreur est survenue lors de la mise à jour de votre profil. Veuillez réessayer.', { type: 'alert' });
    } finally {
        removeLoader(submitButton);
        submitButton.disabled = false;
    }
}

/**
 * Rend l'historique des emprunts.
 * @param {Array<object>} loans - Tableau des objets emprunts.
 */
function renderLoanHistory(loans) {
    loanHistoryTableBody.innerHTML = '';
    if (loans.length === 0) {
        loanHistoryTableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">Aucun emprunt trouvé.</td></tr>';
        return;
    }

    loans.forEach(loan => {
        const row = document.createElement('tr');
        const borrowDate = new TimeFormatter(loan.date_emprunt * 1000).format();
        const returnDate = new TimeFormatter(loan.date_retour * 1000).format();
        const statusText = loan.rendu ? 'Rendu' : 'En cours';
        const statusClass = loan.rendu ? 'text-green-600' : 'text-orange-600';

        row.innerHTML = `
            <td class="text-sm font-medium text-gray-900">${loan.livre_titre}</td>
            <td class="text-sm text-gray-600">${borrowDate}</td>
            <td class="text-sm text-gray-600">${returnDate}</td>
            <td class="text-sm ${statusClass}">${statusText}</td>
        `;
        loanHistoryTableBody.appendChild(row);
    });
}

/**
 * Rend l'historique des abonnements.
 * @param {Array<object>} subscriptions - Tableau des objets abonnements.
 */
function renderSubscriptionHistory(subscriptions) {
    subscriptionHistoryTableBody.innerHTML = '';
    if (subscriptions.length === 0) {
        subscriptionHistoryTableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">Aucun abonnement trouvé.</td></tr>';
        return;
    }

    subscriptions.forEach(sub => {
        const row = document.createElement('tr');
        const startDate = new TimeFormatter(sub.date_debut * 1000).format();
        const endDate = new TimeFormatter(sub.date_fin * 1000).format();
        const statusClass = sub.statut === 'actif' ? 'text-green-600' : (sub.statut === 'expire' ? 'text-red-600' : 'text-gray-600');

        row.innerHTML = `
            <td class="text-sm font-medium text-gray-900">${sub.abonnement_id}</td>
            <td class="text-sm text-gray-600">${startDate}</td>
            <td class="text-sm text-gray-600">${endDate}</td>
            <td class="text-sm ${statusClass} capitalize">${sub.statut}</td>
        `;
        subscriptionHistoryTableBody.appendChild(row);
    });
}

/**
 * Gère la soumission du formulaire de contact administrateur.
 * @param {Event} event - L'événement de soumission du formulaire.
 */
async function handleContactAdmin(event) {
    event.preventDefault();
    const submitButton = contactAdminForm.querySelector('button[type="submit"]');
    addLoader(submitButton);
    submitButton.disabled = true;

    const messageContent = adminMessage.value.trim();
    if (!messageContent) {
        showCustomModal('Veuillez écrire un message.', { type: 'alert' });
        removeLoader(submitButton);
        submitButton.disabled = false;
        return;
    }

    try {
        const response = await apiClient.post('/api/profile?action=contact_admin', { body: { message: messageContent } });

        if (response.data.success) {
            showCustomModal('Votre message a été envoyé à l\'administrateur avec succès !', { type: 'success' });
            adminMessage.value = ''; // Vider le champ message
        } else {
            showCustomModal(`Erreur lors de l'envoi du message: ${response.data.message || 'Inconnu'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error('Erreur lors de l\'envoi du message à l\'administrateur:', error);
        showCustomModal('Une erreur est survenue lors de l\'envoi du message. Veuillez réessayer.', { type: 'alert' });
    } finally {
        removeLoader(submitButton);
        submitButton.disabled = false;
    }
}
