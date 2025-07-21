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
function send_error_page($code, $message, $details = '')
{
    http_response_code($code);
    $title = $code === 404 ? "404 - Page Non Trouvée" : "$code - Erreur Serveur";
    $subtitle = $message;
    echo <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{$title}</title>
  <script src="/app/js/tailwind.js"></script>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100 px-4">
  <div class="bg-white p-8 rounded-2xl shadow-xl text-center max-w-lg w-full">
    <h1 class="text-6xl font-extrabold text-gray-800 mb-4">{$code}</h1>
    <h2 class="text-2xl font-semibold text-gray-700 mb-4">{$subtitle}</h2>
    <p class="text-gray-600">Une erreur est survenue. Si vous êtes développeur, ouvrez la console pour plus de détails.</p>
    <script>
        console.error("{$title} : {$subtitle}");
        const details = decodeURIComponent("{$details}"
            .replace(/<br\s*\/?>/g, '%0A')
            .replace(/<[^>]*>/g, '')
            .replace(/"/g, '\\"')
            .trim()
        );
        if (details !== '') console.error(details);
    </script>
    <a href="/" class="mt-6 inline-block bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition">
      Retour à l'accueil
    </a>
  </div>
</body>
</html>
HTML;
    exit;
}

// --- Serve Static Files Directly ---
// This is crucial for CSS, images, etc.
$static_file_path = __DIR__ . $request_uri;

// If the requested URI corresponds to an existing file AND it's not a PHP file,
// let the PHP built-in server (or web server like Nginx/Apache) handle it.
// This is the most efficient way to serve static assets.
if (file_exists($static_file_path) && !is_dir($static_file_path) && pathinfo($static_file_path, PATHINFO_EXTENSION) !== 'php') {
    return false; // Tells the PHP built-in server to serve the file
}


// --- Root case ---
if ($request_uri === '/') {
    require __DIR__ . '/index.php';
    exit;
}

// --- Normalize URI for internal use ---
// Remove leading/trailing slashes for easier path manipulation
$normalized_uri = trim($request_uri, '/');
$base_dir = __DIR__;
$target_file = null;

// --- Debugging aid (uncomment to see what paths are being checked) ---
// error_log("Request URI: " . $request_uri);
// error_log("Normalized URI: " . $normalized_uri);

// --- Path Resolution Strategy: Ordered by specificity/priority ---

// 1. Check for explicit PHP or JS files in common application directories
// Priorité : pages > base > app/api

$potential_paths = [];

// --- Priorité 1 : pages/ ---
$potential_paths[] = "$base_dir/pages/$normalized_uri.php";
$potential_paths[] = "$base_dir/pages/$normalized_uri.js";

// --- Priorité 2 : base directory (fallback) ---
$potential_paths[] = "$base_dir/$normalized_uri.php";
$potential_paths[] = "$base_dir/$normalized_uri.js";

// --- Priorité 3 : api/ uniquement si URI commence par 'api/' ---
if (str_starts_with($normalized_uri, 'api/')) {
    $api_path_segment = substr($normalized_uri, 4);
    $potential_paths[] = "$base_dir/api/$api_path_segment.php";
    // $potential_paths[] = "$base_dir/api/$api_path_segment.js";
}

// --- Priorité 4 : app/ uniquement si URI commence par 'app/' ---
// Note: This 'app/' block is now primarily for PHP/JS files that are part of the *routing logic*,
// not for static assets like CSS which are handled by the 'Serve Static Files Directly' block above.
if (str_starts_with($normalized_uri, 'app/')) {
    $app_path_segment = substr($normalized_uri, 4);
    $potential_paths[] = "$base_dir/app/$app_path_segment.php";
    $potential_paths[] = "$base_dir/app/$app_path_segment.js";
}


// Iteratively check for explicit files first
foreach ($potential_paths as $path_to_check) {
    // error_log("Checking explicit path: " . $path_to_check);
    if (file_exists($path_to_check)) {
        $target_file = $path_to_check;
        break; // Found the most specific file, no need to check further
    }
}

// 2. If no explicit file found, check for directory index files
if ($target_file === null) {
    // For a request like /pages/admin/, try to find index files inside it
    $potential_directory_indexes = [
        "$base_dir/$normalized_uri/page.php",     // Specific for your 'pages' subfolders
        "$base_dir/$normalized_uri/index.php",
        "$base_dir/pages/$normalized_uri/page.php", // Handles /pages/admin -> /pages/admin/page.php
        "$base_dir/pages/$normalized_uri/index.php",
    ];

    foreach ($potential_directory_indexes as $path_to_check) {
        // error_log("Checking directory index path: " . $path_to_check);
        if (file_exists($path_to_check)) {
            $target_file = $path_to_check;
            break; // Found an index file
        }
    }
}

// --- Handle Found Target ---
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
        // This block will now primarily catch CSS files that might be found via the previous PHP/JS search
        // (e.g., if you had a custom rule to look for .css files in specific PHP/JS related paths).
        // For general static CSS, the 'return false' at the top is more effective.
    } else {
        // If it's another static file type, it would ideally be handled by 'return false' at the top.
        // This else block acts as a fallback for other extensions if they somehow reach here.
        $mime = mime_content_type($target_file);
        header("Content-Type: " . $mime);
        readfile($target_file);
    }
    exit;
}

// --- API not found ---
if (str_starts_with($normalized_uri, 'api/')) {
    if ($isAjax) {
        send_json(404, ['error' => 'Ressource API introuvable']);
    } else {
        send_error_page(404, 'API introuvable');
    }
    exit;
}

// --- Generic 404 ---
if ($isAjax) {
    send_json(404, ['error' => 'Ressource introuvable']);
} else {
    send_error_page(404, 'Page Non Trouvée', "Aucune ressource ne correspond à : <code>" . htmlspecialchars($request_uri) . "</code>");
}
