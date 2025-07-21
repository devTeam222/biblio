import { apiClient } from '../util/ocho-api.js';
import { isAuth, showCustomModal } from '../util/utils.js';

const loginForm = document.getElementById('loginForm');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const errorMessage = document.getElementById('errorMessage');
const modalContainer = document.getElementById('modalContainer');

window.modalContainer = modalContainer; // Assurez-vous que modalContainer est accessible globalement


loginForm.addEventListener('submit', async (e) => {
    e.preventDefault(); // Empêche le rechargement de la page

    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();

    if (!email || !password) {
        errorMessage.textContent = "Veuillez remplir tous les champs.";
        errorMessage.classList.remove('hidden');
        return;
    }

    errorMessage.classList.add('hidden'); // Cacher le message d'erreur précédent

    try {
        const response = await apiClient.post('/api/auth/login', {
            body: {
                email,
                password
            }, 
        }); // Ne pas lancer d'erreur pour gérer les 401/403

        if (response.data.success) {
            showCustomModal("Connexion réussie ! Redirection...");
            // Rediriger vers la page d'accueil de l'utilisateur ou le tableau de bord
            location.replace('/'); // Remplacez par la page de destination appropriée
        } else {
            errorMessage.textContent = response.data.message || "Erreur de connexion. Veuillez vérifier vos identifiants.";
            errorMessage.classList.remove('hidden');
        }
    } catch (error) {
        console.error("Erreur lors de la connexion :", error);
        errorMessage.textContent = "Une erreur est survenue. Veuillez réessayer plus tard.";
        errorMessage.classList.remove('hidden');
    }
});

async function checkAuth() {
    const isAuthenticated = await isAuth();
    
    if (!!isAuthenticated) {
        console.log(`Utilisateur authentifié : ${isAuthenticated}`);
        // Si l'utilisateur est déjà authentifié, rediriger vers la page d'accueil
        location.replace('/'); // Remplacez par la page de destination appropriée
    }   
}
addEventListener('load', checkAuth);
addEventListener('popstate', checkAuth); // Pour gérer le cas où l'utilisateur navigue en arrière
