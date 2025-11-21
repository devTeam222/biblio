<?php
session_start();

require_once __DIR__ . '/../../app/db.php'; // Chemin vers votre fichier de connexion DB

// L'ID de l'utilisateur à afficher.
// Il peut venir de /profile/(?<userid>[0-9]+) via $_GET['userid']
// Ou de /@( ?<username>[a-zA-Z0-9_]+) via $_GET['username']
// Si aucune des deux, et que l'utilisateur est connecté, alors c'est son propre profil.
$user_id_from_url = intval($_GET['userid'] ?? 0);
$username_from_url = htmlspecialchars($_GET['username'] ?? '');

$current_logged_in_user_id = $_SESSION['user_id'] ?? 0;
$is_logged_in = !empty($current_logged_in_user_id);
$is_admin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');

$user_id_to_display = 0; // L'ID final de l'utilisateur dont on affiche le profil

if ($user_id_from_url > 0) {
    // Cas: /profile/{userid}
    $user_id_to_display = $user_id_from_url;
} elseif (!empty($username_from_url)) {
    // Cas: /@{username}
    $stmt_by_username = $pdo->prepare("SELECT id FROM users WHERE nom = :username");
    $stmt_by_username->bindParam(':username', $username_from_url);
    $stmt_by_username->execute();
    $found_user_id = $stmt_by_username->fetchColumn();
    if ($found_user_id) {
        $user_id_to_display = intval($found_user_id);
    } else {
        send_error_page(404, 'Utilisateur non trouvé', "L'utilisateur '@" . $username_from_url . "' n'existe pas.");
    }
} elseif ($is_logged_in) {
    // Cas: /profile (pas d'ID ni de username dans l'URL, mais l'utilisateur est connecté)
    $user_id_to_display = $current_logged_in_user_id;
} else {
    // Si pas d'ID, pas de username, et pas connecté
    send_error_page(400, 'ID utilisateur ou nom d\'utilisateur manquant.', 'Aucun identifiant d\'utilisateur n\'a été fourni pour afficher le profil.');
}


// Déterminer si la page affichée est le profil de l'utilisateur connecté
$is_viewing_own_profile = ($is_logged_in && $user_id_to_display === $current_logged_in_user_id);

$profile_data = null;
$error_message = '';

// Données initiales pour le JavaScript
$initial_profile_data = [
    'user' => null,
    'author' => null,
    'loan_history' => [],
    'subscription_history' => [],
    'is_viewing_own_profile' => $is_viewing_own_profile,
    'current_logged_in_user_id' => $current_logged_in_user_id,
    'is_current_user_admin' => $is_admin
];

try {
    // Récupérer les données de l'utilisateur
    $stmt = $pdo->prepare("SELECT u.id, u.nom, u.email, u.bio, u.date_naissance, u.role, a.nom AS pseudo, a.biographie as author_biographie, a.id as author_db_id
                            FROM users u
                            LEFT JOIN auteurs a ON u.id = a.user_id
                            WHERE u.id = :id");
    $stmt->bindParam(':id', $user_id_to_display, PDO::PARAM_INT);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        send_error_page(404, 'Utilisateur non trouvé', "Le profil de l'utilisateur avec l'ID {$user_id_to_display} n'existe pas.");
    }

    $initial_profile_data['user'] = $user_data;
    if ($user_data['role'] === 'author' && $user_data['pseudo']) {
        $initial_profile_data['author'] = [
            'id' => $user_data['author_db_id'],
            'pseudo' => $user_data['pseudo'],
            'biographie' => $user_data['author_biographie']
        ];
    }

    // Récupérer l'historique des emprunts et abonnements
    // Ceci est visible uniquement pour le propriétaire du profil ou un administrateur.
    if ($is_viewing_own_profile || $is_admin) {
        // Trouver le lecteur_id associé
        $stmt_lecteur = $pdo->prepare("SELECT id FROM lecteurs WHERE user_id = :user_id");
        $stmt_lecteur->bindParam(':user_id', $user_id_to_display, PDO::PARAM_INT);
        $stmt_lecteur->execute();
        $lecteur_id = $stmt_lecteur->fetchColumn();

        if ($lecteur_id) {
            // Historique des emprunts
            $stmt_loans = $pdo->prepare("SELECT e.date_emprunt, e.date_retour, e.rendu, l.titre AS livre_titre, l.id AS livre_id
                                        FROM emprunts e
                                        JOIN livres l ON e.livre_id = l.id
                                        WHERE e.lecteur_id = :lecteur_id
                                        ORDER BY e.date_emprunt DESC");
            $stmt_loans->bindParam(':lecteur_id', $lecteur_id, PDO::PARAM_INT);
            $stmt_loans->execute();
            $initial_profile_data['loan_history'] = $stmt_loans->fetchAll(PDO::FETCH_ASSOC);

            // Historique des abonnements
            $stmt_subscriptions = $pdo->prepare("SELECT s.id AS abonnement_id, s.date_debut, s.date_fin, s.statut
                                                FROM abonnements s
                                                WHERE s.lecteur_id = :lecteur_id
                                                ORDER BY s.date_debut DESC");
            $stmt_subscriptions->bindParam(':lecteur_id', $lecteur_id, PDO::PARAM_INT);
            $stmt_subscriptions->execute();
            $initial_profile_data['subscription_history'] = $stmt_subscriptions->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (PDOException $e) {
    $error_message = "Erreur de base de données : " . $e->getMessage();
    send_error_page(500, 'Erreur interne du serveur', "Erreur BDD lors du chargement du profil: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil de <?php echo htmlspecialchars($initial_profile_data['user']['nom'] ?? 'Utilisateur'); ?> - GéoLib</title>
    <!-- Tailwind CSS CDN -->
    <script src="/app/js/tailwind.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/app/css/modal.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        .card {
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1.5rem;
        }

        .nav-link {
            transition: all 0.2s ease-in-out;
        }

        .nav-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .tab-button {
            padding: 10px 20px;
            border-radius: 0.5rem 0.5rem 0 0;
            font-weight: 500;
            transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }

        .tab-button.active {
            background-color: #ffffff;
            color: #4f46e5;
            border-color: #4f46e5;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
        }
    </style>
<style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .card {
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1.5rem;
        }

        .nav-link {
            padding: 10px 20px;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
            cursor: pointer;
        }

        .book-cover {
            width: 100%;
            height: 200px;
            /* Fixed height for covers */
            object-fit: cover;
            border-radius: 0.5rem;
        }

        /* Style pour le spinner de chargement */
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-left: 8px;
            /* Adjusted margin */
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Overlay pour les messages modaux (déjà dans modal.css mais inclus ici pour visibilité) */
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
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: {
                            750: '#2d3748',
                            850: '#1a202c',
                            950: '#0f172a'
                        }
                    },
                    zIndex: {
                        'nav': '1050',
                    }
                }
            }
        }
    </script>
</head>

<body class="flex flex-col min-h-screen bg-slate-200 dark:bg-slate-950">
    <!-- HEADER -->
    <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 shadow-sm z-[1100] shrink-0 relative">
        <!-- Logo & Titre -->
        <a class="flex items-center gap-3 dark:text-white" href="/">
            <div class="bg-blue-600 text-white p-1.5 rounded-lg shadow-sm">
                <i data-lucide="map" class="w-6 h-6"></i>
            </div>
            <h1 class="text-lg font-bold tracking-tight hidden sm:block">GeoLib <span class="text-slate-400 font-normal text-sm">Accueil</span></h1>
            <h1 class="text-lg font-bold tracking-tight sm:hidden">GeoLib</h1>
        </a>
        
        <!-- Menu de Navigation -->
        <div id="nav-menu" class="hidden md:flex flex-col md:flex-row absolute md:static top-16 left-0 w-full md:w-auto bg-white dark:bg-slate-900 md:bg-transparent shadow-xl md:shadow-none border-b md:border-0 border-slate-200 dark:border-slate-700 p-4 md:p-0 gap-4 md:gap-2 items-stretch md:items-center z-nav transition-all duration-200 ease-in-out">
            <!-- Apparence + Utilisateur -->
            <div class="flex flex-col md:flex-row md:items-center md:gap-2 gap-3">
                <div class="flex items-center justify-between md:justify-start">
                    <span class="md:hidden text-sm font-bold text-slate-500 uppercase tracking-wider">Apparence</span>
                    <div class="relative group">
                        <button id="btnDarkModeToggle" class="p-2 mr-1 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 transition-colors">
                            <i id="darkModeIcon" data-lucide="moon" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <nav class="md:flex flex-col md:flex-row gap-2" id="mainNav">
                        <a href="/" class="inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-transparent bg-blue-500 text-white shadow-md transition-colors">
                            <i data-lucide="home" class="w-5 h-5"></i>
                            <span class="hidden md:inline">Accueil</span>
                        </a>
                        <a href="/" class="inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-transparent hover:border-slate-200 dark:hover:border-slate-700 text-sm font-semibold text-slate-600 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                            <i data-lucide="home" class="w-5 h-5"></i>
                            <span class="hidden md:inline">Accueil</span>
                        </a>
                    </nav>
                    
                    <span id="userNameChip" class="text-xs font-semibold text-slate-400 hidden md:inline">Invité</span>
                    <div class="relative ml-2" id="profileContainer">
                    <!-- Statut/Nom de l'utilisateur (optionnel, affiché avant l'avatar sur desktop) -->

                    <!-- Bouton Avatar qui ouvre le menu -->
                    <button id="userAvatar" aria-expanded="false" aria-controls="userDropdown" class="w-9 h-9 rounded-full border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-200 bg-white dark:bg-slate-800 shadow-sm transition-colors hover:border-blue-500 dark:hover:border-blue-500">
                        <i data-lucide="user-round" class="w-4 h-4"></i>
                    </button>

                    <!-- Menu Popover (initialement caché) -->
                    <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl py-1 hidden z-20">
                        <div class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300 border-b dark:border-slate-700 font-medium truncate" id="dropdownUserName">
                            Invité
                        </div>
                        
                        <!-- Bouton Profil -->
                        <a href="/profile" id="profileButton" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <i data-lucide="settings" class="w-4 h-4"></i>
                            Mon Compte
                        </a>
                        
                        <!-- Bouton Déconnexion (Affiché uniquement si connecté, géré par JS) -->
                        <button id="logoutButton" class="flex items-center gap-2 w-full text-left px-3 py-2 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            Déconnexion
                        </button>
                    </div>
                </div>
                </div>
            </div>           
        </div>
        
        <!-- Bouton Hamburger (Mobile) -->
        <button id="btnMobileMenu" class="md:hidden p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 focus:outline-none">
            <i id="menuIcon" data-lucide="menu" class="w-6 h-6"></i>
        </button>
    </header>

    <!-- Contenu principal -->
    <main class="flex-grow container mx-auto px-4 py-6">
        <div class="bg-white rounded-xl shadow p-6 mb-6 max-w-4xl mx-auto">
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Erreur!</strong>
                    <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php else: ?>
                <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Profil de <?php echo htmlspecialchars($initial_profile_data['user']['nom']); ?></h2>

                <!-- Onglets de navigation -->
                <div class="flex border-b border-gray-200 mb-4">
                    <button id="profileDetailsTabBtn" class="tab-button active bg-white text-green-700 border-green-500">Détails du Profil</button>
                    <?php if ($is_viewing_own_profile || $is_admin): ?>
                        <button id="loanHistoryTabBtn" class="tab-button bg-gray-100 text-gray-700">Historique des Emprunts</button>
                        <button id="subscriptionHistoryTabBtn" class="tab-button bg-gray-100 text-gray-700">Abonnements</button>
                    <?php endif; ?>
                </div>

                <!-- Contenu des onglets -->
                <div id="profileDetailsContent" class="tab-content">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Informations de l'utilisateur -->
                        <div class="card p-4">
                            <h3 class="font-semibold text-gray-700 mb-3">Informations Personnelles</h3>
                            <p class="text-gray-700 mb-2"><strong>Nom:</strong> <span id="publicProfileName"><?php echo htmlspecialchars($initial_profile_data['user']['nom'] ?? 'N/A'); ?></span></p>
                            <p class="text-gray-700 mb-2"><strong>Email:</strong> <span id="publicProfileEmail"><?php echo htmlspecialchars($initial_profile_data['user']['email'] ?? 'N/A'); ?></span></p>
                            <p class="text-gray-700 mb-2"><strong>Rôle:</strong> <span id="publicProfileRole"><?php echo htmlspecialchars($initial_profile_data['user']['role'] ?? 'N/A'); ?></span></p>
                            <p class="text-gray-700 mb-2"><strong>Date de naissance:</strong> <span id="publicProfileBirthdate"><?php echo ($initial_profile_data['user']['date_naissance'] ? date('d/m/Y', $initial_profile_data['user']['date_naissance']) : 'N/A'); ?></span></p>
                            <p class="text-gray-700 mb-2"><strong>Bio:</strong> <span id="publicProfileBio"><?php echo htmlspecialchars($initial_profile_data['user']['bio'] ?? 'Aucune bio.'); ?></span></p>
                            
                            <?php if ($is_viewing_own_profile): ?>
                                <button id="editProfileBtn" class="mt-4 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                    <span class="loader hidden"></span> Modifier le profil
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if ($initial_profile_data['user']['role'] === 'author'): ?>
                            <!-- Informations de l'auteur -->
                            <div class="card p-4">
                                <h3 class="font-semibold text-gray-700 mb-3">Informations d'Auteur</h3>
                                <p class="text-gray-700 mb-2"><strong>Pseudonyme:</strong> <span id="publicAuthorPseudo"><?php echo htmlspecialchars($initial_profile_data['author']['pseudo'] ?? 'N/A'); ?></span></p>
                                <p class="text-gray-700 mb-2"><strong>Biographie d'auteur:</strong> <span id="publicAuthorBio"><?php echo htmlspecialchars($initial_profile_data['author']['biographie'] ?? 'Aucune biographie d\'auteur.'); ?></span></p>
                                <?php if ($is_viewing_own_profile): ?>
                                    <button id="editAuthorProfileBtn" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                        Gérer mon profil auteur
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_viewing_own_profile): ?>
                        <!-- Formulaire de mise à jour du profil -->
                        <div class="card p-4 mt-6 hidden" id="profileUpdateFormContainer">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4">Mettre à jour mon profil</h3>
                            <form id="profileUpdateForm" class="space-y-4">
                                <input type="hidden" id="updateUserId" value="<?php echo htmlspecialchars($user_id_to_display); ?>">
                                <input type="hidden" id="updateUserRole" value="<?php echo htmlspecialchars($initial_profile_data['user']['role']); ?>">

                                <div>
                                    <label for="updateName" class="block text-sm font-medium text-gray-700 text-left">Nom</label>
                                    <input type="text" id="updateName"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"
                                        value="<?php echo htmlspecialchars($initial_profile_data['user']['nom'] ?? ''); ?>" required>
                                </div>
                                <div>
                                    <label for="updateBio" class="block text-sm font-medium text-gray-700 text-left">Biographie (optionnel)</label>
                                    <textarea id="updateBio" rows="3"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"><?php echo htmlspecialchars($initial_profile_data['user']['bio'] ?? ''); ?></textarea>
                                </div>
                                <div>
                                    <label for="updateBirthdate" class="block text-sm font-medium text-gray-700 text-left">Date de Naissance (optionnel)</label>
                                    <input type="date" id="updateBirthdate"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"
                                        value="<?php echo htmlspecialchars($initial_profile_data['user']['date_naissance'] ? date('Y-m-d', $initial_profile_data['user']['date_naissance']) : ''); ?>">
                                </div>

                                <?php if ($initial_profile_data['user']['role'] === 'author'): ?>
                                    <div id="updateAuthorFields" class="space-y-4">
                                        <h4 class="text-lg font-semibold text-gray-700 pt-2 border-t mt-4">Informations d'Auteur</h4>
                                        <div>
                                            <label for="updateAuthorPseudo" class="block text-sm font-medium text-gray-700 text-left">Pseudonyme</label>
                                            <input type="text" id="updateAuthorPseudo"
                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"
                                                value="<?php echo htmlspecialchars($initial_profile_data['author']['pseudo'] ?? ''); ?>" required>
                                        </div>
                                        <div>
                                            <label for="updateAuthorBiographie" class="block text-sm font-medium text-gray-700 text-left">Biographie d'auteur</label>
                                            <textarea id="updateAuthorBiographie" rows="4"
                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"><?php echo htmlspecialchars($initial_profile_data['author']['biographie'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="flex justify-end space-x-3">
                                    <button type="button" id="cancelUpdateProfileBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Annuler</button>
                                    <button type="submit" id="saveProfileChangesBtn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center justify-center gap-2">
                                        <span class="loader hidden"></span> Enregistrer
                                    </button>
                                </div>
                            </form>

                            <h3 class="text-xl font-semibold text-gray-800 pt-4 border-t mt-8 mb-4">Changer le mot de passe</h3>
                            <form id="passwordForm" class="space-y-4">
                                <div>
                                    <label for="currentPassword" class="block text-sm font-medium text-gray-700 text-left">Mot de passe actuel</label>
                                    <input type="password" id="currentPassword"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"
                                        required>
                                </div>
                                <div>
                                    <label for="newPassword" class="block text-sm font-medium text-gray-700 text-left">Nouveau mot de passe</label>
                                    <input type="password" id="newPassword"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"
                                        required>
                                </div>
                                <div>
                                    <label for="confirmNewPassword" class="block text-sm font-medium text-gray-700 text-left">Confirmer le nouveau mot de passe</label>
                                    <input type="password" id="confirmNewPassword"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"
                                        required>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" id="changePasswordBtn"
                                        class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center justify-center gap-2">
                                        <span class="loader hidden"></span> Changer le mot de passe
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Section Contacter l'administrateur (uniquement pour l'utilisateur qui consulte son propre profil) -->
                        <div class="card p-4 mt-6">
                            <h3 class="font-semibold text-gray-700 mb-3">Contacter l'administrateur</h3>
                            <p class="text-gray-600 mb-4">Si vous avez des questions concernant votre abonnement, un problème avec un livre, ou toute autre demande, utilisez ce formulaire pour envoyer un message à l'administrateur.</p>
                            <form id="contactAdminForm" class="space-y-4">
                                <div>
                                    <label for="adminMessage" class="block text-sm font-medium text-gray-700 text-left">Votre message</label>
                                    <textarea id="adminMessage" rows="5" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500" placeholder="Décrivez votre demande ici..." required></textarea>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" id="sendAdminMessageBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center justify-center gap-2">
                                        <span class="loader hidden"></span> Envoyer le message
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($is_viewing_own_profile || $is_admin): ?>
                    <div id="loanHistoryContent" class="tab-content hidden mt-6">
                        <h3 class="font-semibold text-gray-700 mb-3">Historique des Emprunts</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white rounded-md overflow-hidden">
                                <thead>
                                    <tr>
                                        <th>Livre</th>
                                        <th>Date d'emprunt</th>
                                        <th>Date de retour prévue</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody id="loanHistoryTableBody">
                                    <?php if (empty($initial_profile_data['loan_history'])): ?>
                                        <tr><td colspan="4" class="text-center py-4 text-gray-500">Aucun emprunt trouvé.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($initial_profile_data['loan_history'] as $loan): ?>
                                            <tr>
                                                <td class="text-sm font-medium text-gray-900"><a href="/livre/<?php echo htmlspecialchars($loan['livre_id']); ?>" class="text-green-600 hover:underline"><?php echo htmlspecialchars($loan['livre_titre']); ?></a></td>
                                                <td class="text-sm text-gray-600"><?php echo date('d/m/Y', $loan['date_emprunt']); ?></td>
                                                <td class="text-sm text-gray-600"><?php echo date('d/m/Y', $loan['date_retour']); ?></td>
                                                <td class="text-sm <?php echo $loan['rendu'] ? 'text-green-600' : 'text-orange-600'; ?>"><?php echo $loan['rendu'] ? 'Rendu' : 'En cours'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="subscriptionHistoryContent" class="tab-content hidden mt-6">
                        <h3 class="font-semibold text-gray-700 mb-3">Mes Abonnements</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white rounded-md overflow-hidden">
                                <thead>
                                    <tr>
                                        <th>ID Abonnement</th>
                                        <th>Date de début</th>
                                        <th>Date de fin</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody id="subscriptionHistoryTableBody">
                                    <?php if (empty($initial_profile_data['subscription_history'])): ?>
                                        <tr><td colspan="4" class="text-center py-4 text-gray-500">Aucun abonnement trouvé.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($initial_profile_data['subscription_history'] as $sub): ?>
                                            <tr>
                                                <td class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($sub['abonnement_id']); ?></td>
                                                <td class="text-sm text-gray-600"><?php echo date('d/m/Y', $sub['date_debut']); ?></td>
                                                <td class="text-sm text-gray-600"><?php echo date('d/m/Y', $sub['date_fin']); ?></td>
                                                <td class="text-sm <?php echo $sub['statut'] === 'actif' ? 'text-green-600' : ($sub['statut'] === 'expire' ? 'text-red-600' : 'text-gray-600'); ?> capitalize"><?php echo htmlspecialchars($sub['statut']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="bg-gray-800 text-white py-4 shadow-inner">
        <div class="container mx-auto px-4 text-center text-sm">
            <p>&copy; 2025 Base des données Dimart Géosciences. Tous droits réservés.</p>
        </div>
    </footer>
    <!-- Conteneur pour les modales -->
    <div id="modalContainer"></div>
    <script type="module">
        // Passer les données initiales du PHP au JavaScript
        window.initialProfileData = <?php echo json_encode($initial_profile_data); ?>;
    </script>
    <script type="module" src="/app/js/user.js"></script>
</body>

</html>
