import { apiClient } from "../util/ocho-api.js";
import { showCustomModal } from "../util/utils.js";

const registerForm = document.getElementById('registerForm');
const nameInput = document.getElementById('name');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirmPassword');
const errorMessage = document.getElementById('errorMessage');
const modalContainer = document.getElementById('modalContainer');

window.modalContainer = modalContainer; // Assurez-vous que modalContainer est accessible globalement

registerForm.addEventListener('submit', async (e) => {
    e.preventDefault(); // Empêche le rechargement de la page

    const name = nameInput.value.trim();
    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();
    const confirmPassword = confirmPasswordInput.value.trim();

    if (!name || !email || !password || !confirmPassword) {
        errorMessage.textContent = "Veuillez remplir tous les champs.";
        errorMessage.classList.remove('hidden');
        return;
    }

    if (password !== confirmPassword) {
        errorMessage.textContent = "Les mots de passe ne correspondent pas.";
        errorMessage.classList.remove('hidden');
        return;
    }

    errorMessage.classList.add('hidden'); // Cacher le message d'erreur précédent

    try {
        const response = await apiClient.post('/api/auth/register.php', {
            body: {
                name: name,
                email: email,
                password: password
            }
        }, { throwHttpErrors: false });

        if (response.data.success) {
            showCustomModal("Inscription réussie ! Vous pouvez maintenant vous connecter avec votre email et votre mot de passe.");
            // Rediriger vers la page de connexion ou une autre page appropriée
            location.replace('/'); // Remplacez par la page de destination appropriée
        } else {
            errorMessage.textContent = response.data.message || "Erreur d'inscription. Veuillez réessayer.";
            errorMessage.classList.remove('hidden');
            console.log("Erreur lors de l'inscription :", response.data);
        }
    } catch (error) {
        console.error("Erreur lors de l'inscription :", error);
        errorMessage.textContent = "Une erreur est survenue. Veuillez réessayer plus tard.";
        errorMessage.classList.remove('hidden');
    }
});