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
</head>

<body class="flex flex-col min-h-screen">
    <!-- En-tête de la page -->
    <header class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center mb-4 md:mb-0 gap-1">
                    <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5s3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18s-3.332.477-4.5 1.253" />
                    </svg>
                    <h1 class="text-2xl md:text-3xl font-bold">GéoLib</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if ($is_logged_in): ?>
                    <div class="text-right hidden md:block">
                        <p id="userNameDisplay" class="font-medium"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Utilisateur'); ?></p>
                        <p class="text-xs text-indigo-200" id="userRoleDisplay"><?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Inconnu'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Barre de navigation -->
    <nav class="bg-white shadow-sm">
        <div class="container mx-auto p-4">
            <div class="container mx-auto flex flex-wrap justify-center md:justify-start gap-4 px-4" id="mainNav">
                <!-- Les liens seront injectés ici par JavaScript -->
            </div>
        </div>
    </nav>

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
                    <button id="profileDetailsTabBtn" class="tab-button active bg-white text-indigo-700 border-indigo-500">Détails du Profil</button>
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
                                <button id="editProfileBtn" class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
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
                                    <button id="editAuthorProfileBtn" class="mt-4 px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
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
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                        value="<?php echo htmlspecialchars($initial_profile_data['user']['nom'] ?? ''); ?>" required>
                                </div>
                                <div>
                                    <label for="updateBio" class="block text-sm font-medium text-gray-700 text-left">Biographie (optionnel)</label>
                                    <textarea id="updateBio" rows="3"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($initial_profile_data['user']['bio'] ?? ''); ?></textarea>
                                </div>
                                <div>
                                    <label for="updateBirthdate" class="block text-sm font-medium text-gray-700 text-left">Date de Naissance (optionnel)</label>
                                    <input type="date" id="updateBirthdate"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                        value="<?php echo htmlspecialchars($initial_profile_data['user']['date_naissance'] ? date('Y-m-d', $initial_profile_data['user']['date_naissance']) : ''); ?>">
                                </div>

                                <?php if ($initial_profile_data['user']['role'] === 'author'): ?>
                                    <div id="updateAuthorFields" class="space-y-4">
                                        <h4 class="text-lg font-semibold text-gray-700 pt-2 border-t mt-4">Informations d'Auteur</h4>
                                        <div>
                                            <label for="updateAuthorPseudo" class="block text-sm font-medium text-gray-700 text-left">Pseudonyme</label>
                                            <input type="text" id="updateAuthorPseudo"
                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                value="<?php echo htmlspecialchars($initial_profile_data['author']['pseudo'] ?? ''); ?>" required>
                                        </div>
                                        <div>
                                            <label for="updateAuthorBiographie" class="block text-sm font-medium text-gray-700 text-left">Biographie d'auteur</label>
                                            <textarea id="updateAuthorBiographie" rows="4"
                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($initial_profile_data['author']['biographie'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="flex justify-end space-x-3">
                                    <button type="button" id="cancelUpdateProfileBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Annuler</button>
                                    <button type="submit" id="saveProfileChangesBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 flex items-center justify-center gap-2">
                                        <span class="loader hidden"></span> Enregistrer
                                    </button>
                                </div>
                            </form>

                            <h3 class="text-xl font-semibold text-gray-800 pt-4 border-t mt-8 mb-4">Changer le mot de passe</h3>
                            <form id="passwordForm" class="space-y-4">
                                <div>
                                    <label for="currentPassword" class="block text-sm font-medium text-gray-700 text-left">Mot de passe actuel</label>
                                    <input type="password" id="currentPassword"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                        required>
                                </div>
                                <div>
                                    <label for="newPassword" class="block text-sm font-medium text-gray-700 text-left">Nouveau mot de passe</label>
                                    <input type="password" id="newPassword"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                        required>
                                </div>
                                <div>
                                    <label for="confirmNewPassword" class="block text-sm font-medium text-gray-700 text-left">Confirmer le nouveau mot de passe</label>
                                    <input type="password" id="confirmNewPassword"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                        required>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" id="changePasswordBtn"
                                        class="px-6 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 flex items-center justify-center gap-2">
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
                                    <textarea id="adminMessage" rows="5" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="Décrivez votre demande ici..." required></textarea>
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
                                                <td class="text-sm font-medium text-gray-900"><a href="/livre/<?php echo htmlspecialchars($loan['livre_id']); ?>" class="text-indigo-600 hover:underline"><?php echo htmlspecialchars($loan['livre_titre']); ?></a></td>
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
