<?php
    // Si l'utilisateur est déjà authentifié, rediriger vers la page d'accueil
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        header('Location: /'); // Rediriger vers la page d'accueil
        exit();
    }

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Ma Bibliothèque</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-card {
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            padding: 2.5rem;
            max-width: 450px;
            width: 90%;
            text-align: center;
        }
        .input-field {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        .submit-button {
            width: 100%;
            padding: 0.75rem;
            background-color: #2563eb;
            color: white;
            font-weight: 600;
            border-radius: 0.5rem;
            transition: background-color 0.2s ease-in-out;
        }
        .submit-button:hover {
            background-color: #1d4ed8;
        }
        .link-text {
            color: #2563eb;
            text-decoration: none;
            transition: color 0.2s ease-in-out;
        }
        .link-text:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }
        .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: -0.5rem;
            margin-bottom: 1rem;
        }
        /* Overlay pour les messages modaux */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">Connexion</h2>
        <form id="loginForm">
            <input type="email" id="email" placeholder="Adresse email" class="input-field" required>
            <input type="password" id="password" placeholder="Mot de passe" class="input-field" required>
            <p id="errorMessage" class="error-message hidden"></p>
            <button type="submit" class="submit-button">Se connecter</button>
        </form>
        <p class="mt-4 text-gray-600">Pas encore de compte ? <a href="/register" class="link-text">S'inscrire</a></p>
    </div>

    <!-- Conteneur pour les modales -->
    <div id="modalContainer"></div>
    <script type="module" src="/app/js/auth/login.js"></script>
</body>
</html>
