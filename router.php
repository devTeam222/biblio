<?php

/**
 * router.php
 *
 * Ce fichier sert de contrôleur frontal pour le serveur web intégré de PHP.
 * Il gère toutes les requêtes entrantes et les redirige vers les fichiers PHP appropriés,
 * en particulier pour le dossier 'pages' selon les règles spécifiées.
 *
 * Pour l'utiliser : php -S localhost:8000 router.php
 */

// Décoder l'URI de la requête pour gérer les caractères spéciaux
$request_uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// --- Gestion de la racine ---
// Si la requête est pour la racine de l'application (ex: /), servir index.php
if ($request_uri === '/') {
    require __DIR__ . '/index.php';
    exit; // Terminer l'exécution après avoir servi le fichier
}

// --- Gestion des fichiers et répertoires existants directement ---
// Si l'URI de la requête correspond directement à un fichier ou un répertoire existant
// (ex: /app/css/test.css, /api/auth/login.php, /app/js/)
if (file_exists(__DIR__ . $request_uri)) {
    // Si c'est un répertoire, vérifier s'il contient un index.php
    if (is_dir(__DIR__ . $request_uri)) {
        if (file_exists(__DIR__ . $request_uri . '/index.php')) {
            require __DIR__ . $request_uri . '/index.php';
            exit;
        }
        // Si c'est un répertoire sans index.php, laisser le serveur intégré gérer (souvent un 404 ou une liste de fichiers)
        return false;
    } else {
        // Si c'est un fichier, laisser le serveur intégré le servir directement (ex: CSS, JS, images)
        return false;
    }
}

// --- Logique de routage spécifique pour le dossier 'pages' ---
// Cette section gère les URL propres pour les fichiers dans le dossier 'pages'.
// Ex: /login -> pages/login.php
// Ex: /admin -> pages/admin/page.php
// Ex: /admin/livres -> pages/admin/livres/page.php

// Supprimer les slashes de début et de fin pour faciliter la manipulation
$trimmed_uri = trim($request_uri, '/');
$path_segments = explode('/', $trimmed_uri);

// 1. Tenter de mapper vers pages/dossier/page.php (pour les dossiers avec page.php comme index)
// Ex: /admin/livres -> pages/admin/livres/page.php
// Ex: /admin -> pages/admin/page.php
$potential_nested_page_index = __DIR__ . '/pages/' . $trimmed_uri . '/page.php';
if (file_exists($potential_nested_page_index)) {
    require $potential_nested_page_index;
    exit;
}

// 2. Tenter de mapper vers pages/fichier.php (pour les fichiers sans extension)
// Ex: /login -> pages/login.php
$potential_page_file = __DIR__ . '/pages/' . $trimmed_uri . '.php';
if (file_exists($potential_page_file)) {
    require $potential_page_file;
    exit;
}

// --- Gestion des fichiers PHP sans extension en dehors du dossier 'pages' ---
// Ex: /api/auth/login -> api/auth/login.php
$potential_php_file_outside_pages = __DIR__ . $request_uri . '.php';
if (file_exists($potential_php_file_outside_pages)) {
    require $potential_php_file_outside_pages;
    exit;
}

// Si aucune des règles ci-dessus n'a été appliquée, laisser le serveur intégré
// gérer la requête. Il retournera un 404 si le fichier n'existe pas.
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Non Trouvée - Ma Bibliothèque</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            text-align: center;
            padding: 1rem;
        }

        .error-container {
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            padding: 2.5rem;
            max-width: 600px;
            width: 95%;
        }

        .home-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            background-color: #2563eb;
            color: white;
            font-weight: 600;
            border-radius: 0.5rem;
            text-decoration: none;
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
        }

        .home-button:hover {
            background-color: #1d4ed8;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <div class="error-container">
        <h1 class="text-6xl font-extrabold text-gray-900 mb-4">404</h1>
        <h2 class="text-3xl font-bold text-gray-800 mb-6">Page Non Trouvée</h2>
        <p class="text-lg text-gray-600 mb-8">
            Désolé, la page que vous recherchez n'existe pas ou a été déplacée.
            Veuillez vérifier l'URL ou retourner à la page d'accueil.
        </p>
        <a href="/" class="home-button">
            <svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-house-icon lucide-house">
                <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8" />
                <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
            </svg>
            Retour à l'accueil
        </a>
    </div>
</body>

</html>