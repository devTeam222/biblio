import { apiClient } from "./util/ocho-api.js";
import { showCustomModal, addLoader, removeLoader, isAuth, updateNavBar } from "./util/utils.js";
import { TimeFormatter } from "./util/formatter.js";

// Éléments du DOM pour l'en-tête et la navigation
const userNameDisplay = document.getElementById('userNameDisplay');
const userRoleDisplay = document.getElementById('userRoleDisplay');

// Éléments des onglets et de leur contenu
const profileDetailsTabBtn = document.getElementById('profileDetailsTabBtn');
const loanHistoryTabBtn = document.getElementById('loanHistoryTabBtn');
const subscriptionHistoryTabBtn = document.getElementById('subscriptionHistoryTabBtn');

const profileDetailsContent = document.getElementById('profileDetailsContent');
const loanHistoryContent = document.getElementById('loanHistoryContent');
const subscriptionHistoryContent = document.getElementById('subscriptionHistoryContent');

// Éléments pour afficher les données du profil public
const publicProfileName = document.getElementById('publicProfileName');
const publicProfileEmail = document.getElementById('publicProfileEmail');
const publicProfileRole = document.getElementById('publicProfileRole');
const publicProfileBio = document.getElementById('publicProfileBio');
const publicProfileBirthdate = document.getElementById('publicProfileBirthdate');
const publicAuthorPseudo = document.getElementById('publicAuthorPseudo');
const publicAuthorBio = document.getElementById('publicAuthorBio');

const loanHistoryTableBody = document.getElementById('loanHistoryTableBody');
const subscriptionHistoryTableBody = document.getElementById('subscriptionHistoryTableBody');

// Éléments spécifiques au profil personnel modifiable
const editProfileBtn = document.getElementById('editProfileBtn');
const editAuthorProfileBtn = document.getElementById('editAuthorProfileBtn'); // Nouveau bouton pour éditer le profil auteur
const profileUpdateFormContainer = document.getElementById('profileUpdateFormContainer');
const profileUpdateForm = document.getElementById('profileUpdateForm');
const updateUserIdInput = document.getElementById('updateUserId');
const updateUserRoleInput = document.getElementById('updateUserRole');
const updateNameInput = document.getElementById('updateName');
const updateBioTextarea = document.getElementById('updateBio');
const updateBirthdateInput = document.getElementById('updateBirthdate');

const updateAuthorFieldsDiv = document.getElementById('updateAuthorFields'); // Conteneur des champs auteur
const updateAuthorPseudoInput = document.getElementById('updateAuthorPseudo');
const updateAuthorBiographieTextarea = document.getElementById('updateAuthorBiographie');

const saveProfileChangesBtn = document.getElementById('saveProfileChangesBtn');
const cancelUpdateProfileBtn = document.getElementById('cancelUpdateProfileBtn');

const passwordForm = document.getElementById('passwordForm');
const currentPasswordInput = document.getElementById('currentPassword');
const newPasswordInput = document.getElementById('newPassword');
const confirmNewPasswordInput = document.getElementById('confirmNewPassword');
const changePasswordBtn = document.getElementById('changePasswordBtn');

const contactAdminForm = document.getElementById('contactAdminForm');
const adminMessageTextarea = document.getElementById('adminMessage');
const sendAdminMessageBtn = document.getElementById('sendAdminMessageBtn');


// Rôles pour l'affichage
const roles = {
    'admin': 'Administrateur',
    'user': 'Lecteur',
    'author': 'Auteur',
};

// Global state from PHP
let initialData = window.initialProfileData;
let isViewingOwnProfile = initialData.is_viewing_own_profile; // Dérivé du PHP
let currentLoggedInUserId = initialData.current_logged_in_user_id;
let isCurrentUserAdmin = initialData.is_current_user_admin;


document.addEventListener('DOMContentLoaded', async () => {
    const authResult = await isAuth();
    
    // Mettre à jour la barre de navigation, même si non connecté pour les vues publiques
    updateNavBar(authResult?.user?.role || 'guest', window.location.pathname);

    // Mettre à jour les informations dans l'en-tête si connecté
    if (authResult.success) {
        if (userNameDisplay) userNameDisplay.textContent = authResult.user.name || 'Utilisateur';
        if (userRoleDisplay) userRoleDisplay.textContent = authResult.user.role || 'Inconnu';
    }

    // Rendre les détails du profil
    renderProfileDetails(initialData.user, initialData.author);
    
    // Rendre l'historique des emprunts et abonnements si les données sont disponibles
    if (initialData.loan_history && (isViewingOwnProfile || isCurrentUserAdmin)) {
        renderLoanHistory(initialData.loan_history);
    }
    if (initialData.subscription_history && (isViewingOwnProfile || isCurrentUserAdmin)) {
        renderSubscriptionHistory(initialData.subscription_history);
    }

    // Écouteurs d'événements pour les onglets
    if (profileDetailsTabBtn) profileDetailsTabBtn.addEventListener('click', () => switchTab('details'));
    if (loanHistoryTabBtn && (isViewingOwnProfile || isCurrentUserAdmin)) loanHistoryTabBtn.addEventListener('click', () => switchTab('loans'));
    if (subscriptionHistoryTabBtn && (isViewingOwnProfile || isCurrentUserAdmin)) subscriptionHistoryTabBtn.addEventListener('click', () => switchTab('subscriptions'));

    // Gérer les formulaires et boutons de modification UNIQUEMENT si c'est le profil de l'utilisateur connecté
    if (isViewingOwnProfile) {
        if (editProfileBtn) editProfileBtn.addEventListener('click', toggleProfileUpdateForm);
        if (cancelUpdateProfileBtn) cancelUpdateProfileBtn.addEventListener('click', toggleProfileUpdateForm);
        if (profileUpdateForm) profileUpdateForm.addEventListener('submit', handleProfileUpdate);
        if (passwordForm) passwordForm.addEventListener('submit', handleChangePasswordSubmit);
        if (contactAdminForm) contactAdminForm.addEventListener('submit', handleContactAdmin);
        
        // Si l'utilisateur est aussi un auteur, le bouton "Gérer mon profil auteur" déclenche le même formulaire
        if (editAuthorProfileBtn) {
             editAuthorProfileBtn.addEventListener('click', toggleProfileUpdateForm);
        }
    }

    // Affichage de l'onglet initial
    switchTab('details');
});

/**
 * Bascule entre les onglets de la page de profil.
 * @param {string} tabName - 'details', 'loans', ou 'subscriptions'.
 */
function switchTab(tabName) {
    // Supprimer les classes actives de tous les boutons d'onglet
    const allTabButtons = document.querySelectorAll('.tab-button');
    allTabButtons.forEach(btn => {
        btn.classList.remove('active', 'bg-white', 'text-green-700', 'border-green-500');
        btn.classList.add('bg-gray-100', 'text-gray-700');
    });

    // Cacher tous les contenus d'onglet
    const allTabContents = document.querySelectorAll('.tab-content');
    allTabContents.forEach(content => content.classList.add('hidden'));

    // Afficher le contenu de l'onglet actif et ajouter la classe active au bouton
    if (tabName === 'details') {
        if (profileDetailsTabBtn) {
            profileDetailsTabBtn.classList.add('active', 'bg-white', 'text-green-700', 'border-green-500');
        }
        if (profileDetailsContent) profileDetailsContent.classList.remove('hidden');
    } else if (tabName === 'loans') {
        if (loanHistoryTabBtn) {
            loanHistoryTabBtn.classList.add('active', 'bg-white', 'text-green-700', 'border-green-500');
        }
        if (loanHistoryContent) loanHistoryContent.classList.remove('hidden');
    } else if (tabName === 'subscriptions') {
        if (subscriptionHistoryTabBtn) {
            subscriptionHistoryTabBtn.classList.add('active', 'bg-white', 'text-green-700', 'border-green-500');
        }
        if (subscriptionHistoryContent) subscriptionHistoryContent.classList.remove('hidden');
    }
}

/**
 * Rend les détails du profil utilisateur.
 * @param {object} user - L'objet utilisateur.
 * @param {object|null} author - L'objet auteur ou null.
 */
function renderProfileDetails(user, author) {
    if (!user) return;

    publicProfileName.textContent = user.nom || 'N/A';
    publicProfileEmail.textContent = user.email || 'N/A';
    publicProfileRole.textContent = roles[user.role] || user.role || 'N/A';
    publicProfileBio.textContent = user.bio || 'Aucune bio.';
    publicProfileBirthdate.textContent = user.date_naissance ? new TimeFormatter(user.date_naissance * 1000).format() : 'N/A';

    // Remplir le formulaire de mise à jour si c'est le profil de l'utilisateur connecté
    if (isViewingOwnProfile && profileUpdateFormContainer) {
        updateUserIdInput.value = user.id || '';
        updateUserRoleInput.value = user.role || '';
        updateNameInput.value = user.nom || '';
        updateBioTextarea.value = user.bio || '';
        updateBirthdateInput.value = user.date_naissance ? new Date(user.date_naissance * 1000).toISOString().split('T')[0] : '';
    }

    // Gérer l'affichage et les valeurs de l'auteur
    const authorCard = document.querySelector('.card:has(#publicAuthorPseudo)'); // Sélectionnez la div parent si elle existe
    if (user.role === 'author') {
        if (authorCard) authorCard.classList.remove('hidden');
        if (publicAuthorPseudo) publicAuthorPseudo.textContent = author ? author.pseudo || 'N/A' : 'N/A';
        if (publicAuthorBio) publicAuthorBio.textContent = author ? author.biographie || 'Aucune biographie d\'auteur.' : 'Aucune biographie d\'auteur.';

        if (isViewingOwnProfile && updateAuthorFieldsDiv) {
            updateAuthorFieldsDiv.classList.remove('hidden');
            updateAuthorPseudoInput.value = author ? author.pseudo || '' : '';
            updateAuthorBiographieTextarea.value = author ? author.biographie || '' : '';
        }
    } else {
        if (authorCard) authorCard.classList.add('hidden');
        if (updateAuthorFieldsDiv) updateAuthorFieldsDiv.classList.add('hidden');
    }
}

/**
 * Affiche ou masque le formulaire de mise à jour du profil.
 */
function toggleProfileUpdateForm() {
    if (profileUpdateFormContainer) profileUpdateFormContainer.classList.toggle('hidden');
    if (editProfileBtn) editProfileBtn.classList.toggle('hidden');
    if (editAuthorProfileBtn) editAuthorProfileBtn.classList.toggle('hidden');
    
    // Si on ouvre le formulaire, pré-remplir avec les données actuelles
    if (profileUpdateFormContainer && !profileUpdateFormContainer.classList.contains('hidden')) {
        updateNameInput.value = publicProfileName.textContent === 'N/A' ? '' : publicProfileName.textContent;
        updateBioTextarea.value = publicProfileBio.textContent === 'Aucune bio.' ? '' : publicProfileBio.textContent;
        updateBirthdateInput.value = publicProfileBirthdate.textContent === 'N/A' ? '' : (initialData.user.date_naissance ? new Date(initialData.user.date_naissance * 1000).toISOString().split('T')[0] : '');

        if (initialData.user.role === 'author' && initialData.author) {
            updateAuthorPseudoInput.value = publicAuthorPseudo.textContent === 'N/A' ? '' : publicAuthorPseudo.textContent;
            updateAuthorBiographieTextarea.value = publicAuthorBio.textContent === 'Aucune biographie d\'auteur.' ? '' : publicAuthorBio.textContent;
        }
        currentPasswordInput.value = ''; // Toujours vider les champs de mot de passe
        newPasswordInput.value = '';
        confirmNewPasswordInput.value = '';
    }
}


/**
 * Gère la soumission du formulaire de mise à jour du profil.
 * @param {Event} event - L'événement de soumission du formulaire.
 */
async function handleProfileUpdate(event) {
    event.preventDefault();
    const submitButton = saveProfileChangesBtn;
    addLoader(submitButton);
    submitButton.disabled = true;

    const userData = {
        nom: updateNameInput.value.trim(),
        bio: updateBioTextarea.value.trim(),
        date_naissance: updateBirthdateInput.value,
        role: updateUserRoleInput.value // Le rôle est envoyé pour la logique backend de l'auteur
    };

    // Ajouter les données spécifiques de l'auteur si le rôle est 'author'
    if (updateUserRoleInput.value === 'author') {
        userData.author_pseudo = updateAuthorPseudoInput.value.trim();
        userData.author_biographie = updateAuthorBiographieTextarea.value.trim();

        if (!userData.author_pseudo) {
            showCustomModal("Le pseudonyme de l'auteur est requis.", { type: 'alert' });
            removeLoader(submitButton);
            submitButton.disabled = false;
            return;
        }
    }

    try {
        const response = await apiClient.post('/api/users/user?action=update', { body: userData });
        if (response.data.success) {
            showCustomModal('Profil mis à jour avec succès !', { type: 'success' });
            // Recharger les données pour mettre à jour l'affichage
            const updatedProfileResponse = await apiClient.get(`/api/users/user?action=get&userid=${updateUserIdInput.value}`);
            if (updatedProfileResponse.data.success) {
                initialData.user = updatedProfileResponse.data.data.user;
                initialData.author = updatedProfileResponse.data.data.author;
                renderProfileDetails(initialData.user, initialData.author); // Mettre à jour l'affichage
                toggleProfileUpdateForm(); // Masquer le formulaire
                // Mettre à jour la navbar aussi car le nom a pu changer
                await isAuth(true); // Forcer un rechargement des infos d'auth
                if (userNameDisplay) userNameDisplay.textContent = initialData.user.nom || 'Utilisateur';
            } else {
                showCustomModal(`Erreur lors du rechargement du profil: ${updatedProfileResponse.data.message || 'Inconnu'}`, { type: 'alert' });
            }
            
        } else {
            showCustomModal(`Erreur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors de la mise à jour du profil:", error);
        showCustomModal("Une erreur est survenue lors de la mise à jour du profil.", { type: 'alert' });
    } finally {
        removeLoader(submitButton);
        submitButton.disabled = false;
    }
}

/**
 * Gère la soumission du formulaire de changement de mot de passe.
 * @param {Event} event - L'événement de soumission.
 */
async function handleChangePasswordSubmit(event) {
    event.preventDefault();

    const submitButton = changePasswordBtn;
    addLoader(submitButton);
    submitButton.disabled = true;

    const currentPassword = currentPasswordInput.value;
    const newPassword = newPasswordInput.value;
    const confirmNewPassword = confirmNewPasswordInput.value;

    if (newPassword !== confirmNewPassword) {
        showCustomModal('Les nouveaux mots de passe ne correspondent pas.', { type: 'alert' });
        removeLoader(submitButton);
        submitButton.disabled = false;
        return;
    }
    if (newPassword.length < 6) {
        showCustomModal('Le nouveau mot de passe doit contenir au moins 6 caractères.', { type: 'alert' });
        removeLoader(submitButton);
        submitButton.disabled = false;
        return;
    }

    try {
        const response = await apiClient.post('/api/users/user?action=change_password', {
            body: {
                current_password: currentPassword,
                new_password: newPassword
            }
        });

        if (response.data.success) {
            showCustomModal('Mot de passe mis à jour avec succès !', { type: 'success' });
            passwordForm.reset(); // Réinitialiser le formulaire de mot de passe
        } else {
            showCustomModal(`Erreur: ${response.data.message || 'Erreur inconnue'}`, { type: 'alert' });
        }
    } catch (error) {
        console.error("Erreur lors du changement de mot de passe:", error);
        showCustomModal("Une erreur est survenue lors du changement de mot de passe. Veuillez réessayer.", { type: 'alert' });
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
            <td class="text-sm font-medium text-gray-900"><a href="/livre/${loan.livre_id}" class="text-green-600 hover:underline">${loan.livre_titre}</a></td>
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
        const subStatuses = {
            'actif': 'Actif',
            'expire': 'Expiré',
            'annule': 'Annulé',
            'inconnu': 'Inconnu'
        };
        const status = subStatuses[sub.statut] ?? subStatuses.inconnu

        row.innerHTML = `
            <td class="text-sm font-medium text-gray-900">${sub.abonnement_id}</td>
            <td class="text-sm text-gray-600">${startDate}</td>
            <td class="text-sm text-gray-600">${endDate}</td>
            <td class="text-sm ${statusClass} capitalize">${status}</td>
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
    const submitButton = sendAdminMessageBtn;
    addLoader(submitButton);
    submitButton.disabled = true;

    const messageContent = adminMessageTextarea.value.trim();
    if (!messageContent) {
        showCustomModal('Veuillez écrire un message.', { type: 'alert' });
        removeLoader(submitButton);
        submitButton.disabled = false;
        return;
    }

    try {
        const response = await apiClient.post('/api/users/user?action=send_admin_message', { body: { message: messageContent } });

        if (response.data.success) {
            showCustomModal('Votre message a été envoyé à l\'administrateur avec succès !', { type: 'success' });
            adminMessageTextarea.value = ''; // Vider le champ message
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
