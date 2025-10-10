<?php
// router.php – Unified Front Controller

$request_uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$method = $_SERVER['REQUEST_METHOD'];
$isAjax = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
);

// JSON Response
function send_json($code, $data)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Styled Error HTML Page
function send_error_page(int $code, $message, $details = '', $bookTitle = null)
{
    http_response_code($code);

    // Définition des titres par défaut pour chaque code d'erreur
    $errorTitles = [
        400 => "Requête Invalide",
        403 => "Accès Interdit",
        404 => "Page Non Trouvée",
        500 => "Erreur Interne du Serveur"
    ];

    // Définition des sous-titres par défaut pour chaque code d'erreur
    $errorSubtitles = [
        400 => "Votre requête n'a pas pu être traitée en raison d'informations manquantes ou incorrectes.",
        403 => "Vous n'êtes pas autorisé à accéder à cette ressource.",
        404 => "La page demandée n'a pas pu être trouvée.",
        500 => "Une erreur inattendue est survenue sur le serveur."
    ];

    // Récupérer le titre et le sous-titre par défaut, ou une valeur générique si le code n'est pas reconnu
    $pageTitle = $errorTitles[$code] ?? "$code - Erreur";
    $subtitle = $errorSubtitles[$code] ?? "Une erreur est survenue.";

    // Personnalisation spécifique pour l'erreur 404 si un titre de livre est fourni
    if ($code === 404 && $bookTitle) {
        $pageTitle = "Livre non trouvé - " . htmlspecialchars($bookTitle);
        $subtitle = "L'ouvrage '" . htmlspecialchars($bookTitle) . "' n'a pas pu être trouvé ou n'existe pas.";
    }

    // Utiliser le message fourni si celui-ci est plus spécifique que le sous-titre par défaut
    if (!empty($message) && $message !== ($errorSubtitles[$code] ?? '')) {
        $subtitle = $message;
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{$pageTitle}</title>
    <!-- Tailwind CSS CDN -->
    <script src="/app/js/tailwind.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100 px-4">
    <div class="bg-white p-8 rounded-2xl shadow-xl text-center max-w-lg w-full">
        <h1 class="text-6xl font-extrabold text-gray-800 mb-4">{$code}</h1>
        <h2 class="text-2xl font-semibold text-gray-700 mb-4">{$subtitle}</h2>
        <p class="text-gray-600">Une erreur est survenue. Si vous êtes développeur, ouvrez la console pour plus de détails.</p>
        <script>
            console.error("{$pageTitle} : {$subtitle}");
            const details = `{$details}`
                .replace(/<br\s*\/?>/g, '%0A')
                .replace(/<[^>]*>/g, '')
                .replace(/"/g, '\\"')
                .trim();
            if (details !== '') console.error(details);
        </script>
        <a href="/" class="mt-6 inline-block bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition">
            Retour à l'accueil
        </a>
    </div>
</body>
</html>
HTML;
    exit;
}

// --- Serve Static Files Directly ---
// Ceci est crucial pour les fichiers CSS, images, etc.
$static_file_path = __DIR__ . $request_uri;

if (file_exists($static_file_path) && !is_dir($static_file_path) && pathinfo($static_file_path, PATHINFO_EXTENSION) !== 'php') {
    return false; // Indique au serveur PHP intégré de servir le fichier
}

// --- Cas de la racine ---
if ($request_uri === '/') {
    require __DIR__ . '/index.php';
    exit;
}

$normalized_uri = trim($request_uri, '/');
$base_dir = __DIR__;
$target_file = null;

// --- D'abord, vérifier le routage basé sur le système de fichiers (prioritaire) ---

// 1. Chercher des fichiers PHP ou JS explicites dans les répertoires d'application courants
$potential_fs_paths = [];

// --- Priorité 1 : pages/ ---
$potential_fs_paths[] = "$base_dir/pages/$normalized_uri.php";
$potential_fs_paths[] = "$base_dir/pages/$normalized_uri.js";

// --- Priorité 2 : répertoire de base (fallback) ---
$potential_fs_paths[] = "$base_dir/$normalized_uri.php";
$potential_fs_paths[] = "$base_dir/$normalized_uri.js";

// --- Priorité 3 : api/ uniquement si l'URI commence par 'api/' ---
if (str_starts_with($normalized_uri, 'api/')) {
    $api_path_segment = substr($normalized_uri, 4);
    $potential_fs_paths[] = "$base_dir/api/$api_path_segment.php";
}

// --- Priorité 4 : app/ uniquement si l'URI commence par 'app/' ---
if (str_starts_with($normalized_uri, 'app/')) {
    $app_path_segment = substr($normalized_uri, 4);
    $potential_fs_paths[] = "$base_dir/app/$app_path_segment.php";
    $potential_fs_paths[] = "$base_dir/app/$app_path_segment.js";
}

// Vérifier itérativement les fichiers explicites en premier
foreach ($potential_fs_paths as $path_to_check) {
    if (file_exists($path_to_check)) {
        $target_file = $path_to_check;
        break; 
    }
}

// 2. Si aucun fichier explicite n'est trouvé, chercher les fichiers d'index de répertoire
if ($target_file === null) {
    $potential_directory_indexes = [
        "$base_dir/$normalized_uri/page.php",     // Spécifique pour vos sous-dossiers 'pages'
        "$base_dir/$normalized_uri/index.php",
        "$base_dir/pages/$normalized_uri/page.php", // Gère /pages/admin -> /pages/admin/page.php
        "$base_dir/pages/$normalized_uri/index.php",
    ];

    foreach ($potential_directory_indexes as $path_to_check) {
        if (file_exists($path_to_check)) {
            $target_file = $path_to_check;
            break; 
        }
    }
}

// --- Traiter la cible trouvée (du système de fichiers) ---
if ($target_file !== null) {
    $ext = pathinfo($target_file, PATHINFO_EXTENSION);

    if ($ext === 'php') {
        require $target_file;
    } elseif ($ext === 'js') {
        header("Content-Type: application/javascript");
        readfile($target_file);
    } elseif ($ext === 'css') {
        header("Content-Type: text/css");
        readfile($target_file);
    } else {
        $mime = mime_content_type($target_file);
        header("Content-Type: " . $mime);
        readfile($target_file);
    }
    exit; // Arrêter l'exécution, car une route basée sur le système de fichiers a été trouvée et servie.
}


// --- Ensuite, traiter les routes personnalisées du fichier de configuration (si aucune route système de fichiers n'a été trouvée) ---

// Charger la configuration des routes
$routes_config_path = __DIR__ . '/routes.json';
$routes_config = [];
if (file_exists($routes_config_path)) {
    $routes_json = file_get_contents($routes_config_path);
    $routes_config = json_decode($routes_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Erreur de décodage de routes.json: " . json_last_error_msg());
        if ($isAjax) {
            send_json(500, ['error' => 'Erreur de configuration du routeur', 'details' => 'Le fichier routes.json est mal formé.']);
            exit;
        }else{
            send_error_page(500, 'Erreur de configuration du routeur', 'Le fichier routes.json est mal formé.');
        }
    }
}

// Traiter les routes personnalisées du fichier de configuration (La première route qui correspond gagne)
// L'ordre des routes dans routes.json est crucial : les routes plus spécifiques doivent être définies avant les routes plus génériques.
if (isset($routes_config['routes']) && is_array($routes_config['routes'])) {
    foreach ($routes_config['routes'] as $route) {
        $pattern = $route['pattern'] ?? null;
        $target_php = $route['target'] ?? null;
        $query_params_map = $route['query_params'] ?? [];

        if ($pattern && $target_php) {
            $full_regex_pattern = '~^' . str_replace('~', '\\~', $pattern) . '$~';
            
            if (preg_match($full_regex_pattern, $request_uri, $matches)) {
                // Route trouvée ! Cette route est prioritaire sur les autres routes personnalisées.
                
                // Définir les paramètres $_GET basés sur la map query_params_map
                foreach ($query_params_map as $get_key => $param_source) {
                    if (str_starts_with($param_source, '$')) {
                        $param_name = substr($param_source, 1); 
                        if (isset($matches[$param_name])) {
                            $_GET[$get_key] = $matches[$param_name];
                        }
                    } else {
                        $_GET[$get_key] = $param_source;
                    }
                }
                
                $target_file = $base_dir . '/' . $target_php;
                if (file_exists($target_file)) {
                    require $target_file;
                    exit; 
                } else {
                    error_log("Fichier cible pour la route personnalisée '{$pattern}' non trouvé : {$target_file}");
                    send_error_page(500, 'Erreur de configuration de route', "Le fichier cible '{$target_php}' pour la route '{$pattern}' est introuvable.");
                }
            }
        }
    }
}


// --- Gestion des API non trouvées ---
if (str_starts_with($normalized_uri, 'api/')) {
    if ($isAjax) {
        send_json(404, ['error' => 'Ressource API introuvable']);
    } else {
        send_error_page(404, 'API introuvable');
    }
    exit;
}

// --- 404 Générique si aucune route ne correspond ---
if ($isAjax) {
    send_json(404, ['error' => 'Ressource introuvable']);
} else {
    send_error_page(404, 'Page Non Trouvée', "Aucune ressource ne correspond à : <code>" . htmlspecialchars($request_uri) . "</code>");
}
