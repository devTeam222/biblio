
-- Table des fichiers : Stocke les informations sur les fichiers téléchargés, comme les images de couverture.
CREATE TABLE IF NOT EXISTS fichiers (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    chemin VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    taille INT NOT NULL,
    date_telechargement BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP)
);

-- Table des utilisateurs : Contient les informations de base pour tous les types d'utilisateurs (lecteurs, administrateurs, auteurs).
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    bio TEXT,
    date_naissance BIGINT,
    email VARCHAR(100) UNIQUE NOT NULL,
    mot_de_passe TEXT, -- Le mot de passe devrait toujours être NOT NULL
    role VARCHAR(20) NOT NULL CHECK (role IN ('user', 'author', 'admin')) DEFAULT 'user',
    date_inscription BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP)
);

-- Table des lecteurs : Représente les utilisateurs ayant le rôle de lecteur.
CREATE TABLE IF NOT EXISTS lecteurs (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL UNIQUE, -- Un utilisateur ne peut être qu'un seul lecteur
    type VARCHAR(20) NOT NULL CHECK (type IN ('abonne', 'visiteur')) DEFAULT 'visiteur',
    date_inscription BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE -- Si un utilisateur est supprimé, le lecteur associé est aussi supprimé
);

-- Table des administrateurs : Représente les utilisateurs ayant le rôle d'administrateur.
CREATE TABLE IF NOT EXISTS administrateurs (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL UNIQUE, -- Un utilisateur ne peut être qu'un seul administrateur
    date_inscription BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des auteurs : Représente les utilisateurs ou entités qui écrivent des livres.
CREATE TABLE IF NOT EXISTS auteurs (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    biographie TEXT,
    date_naissance BIGINT,
    date_deces BIGINT,
    date_creation BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
    user_id INT UNIQUE, -- Un auteur peut être lié à un utilisateur, mais ce n'est pas obligatoire
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL -- Si l'utilisateur est supprimé, le champ user_id de l'auteur est mis à NULL
);

-- Table des catégories de livres : Permet de classer les livres.
CREATE TABLE IF NOT EXISTS categories (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    date_creation BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP)
);

-- Table des livres : Contient les informations détaillées sur chaque livre.
CREATE TABLE IF NOT EXISTS livres (
    id SERIAL PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    auteur_id INT NOT NULL,
    categorie_id INT NOT NULL,
    disponible BOOLEAN DEFAULT TRUE,
    cover_image_id INT, -- Image de couverture du livre
    date_publication BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
    FOREIGN KEY (auteur_id) REFERENCES auteurs(id) ON DELETE RESTRICT, -- Empêche la suppression d'un auteur s'il y a des livres associés
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE RESTRICT, -- Empêche la suppression d'une catégorie si elle contient des livres
    FOREIGN KEY (cover_image_id) REFERENCES fichiers(id) ON DELETE SET NULL -- Si l'image est supprimée, la référence est mise à NULL
);

-- Table des abonnements : Gère les abonnements des lecteurs aux services de la bibliothèque.
CREATE TABLE IF NOT EXISTS abonnements (
    id SERIAL PRIMARY KEY,
    lecteur_id INT NOT NULL,
    date_debut BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
    date_fin BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
    statut VARCHAR(20) DEFAULT 'actif',
    FOREIGN KEY (lecteur_id) REFERENCES lecteurs(id) ON DELETE CASCADE
);

-- Table des emprunts : Enregistre chaque emprunt de livre par un lecteur.
CREATE TABLE IF NOT EXISTS emprunts (
    id SERIAL PRIMARY KEY,
    lecteur_id INT NOT NULL,
    livre_id INT NOT NULL,
    date_emprunt BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
    date_retour BIGINT, -- Peut être NULL si le livre n'est pas encore rendu
    rendu BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (lecteur_id) REFERENCES lecteurs(id) ON DELETE CASCADE,
    FOREIGN KEY (livre_id) REFERENCES livres(id) ON DELETE RESTRICT -- Empêche la suppression d'un livre s'il est encore emprunté
);

-- Table des paiements : Enregistre les transactions financières des lecteurs.
CREATE TABLE IF NOT EXISTS paiements (
    id SERIAL PRIMARY KEY,
    lecteur_id INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    date_paiement BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
    commentaire TEXT,
    FOREIGN KEY (lecteur_id) REFERENCES lecteurs(id) ON DELETE CASCADE
);

-- Table des notifications : Gère les messages envoyés aux lecteurs.
CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    lecteur_id INT NOT NULL,
    message TEXT NOT NULL,
    date_notification BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
    lu BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (lecteur_id) REFERENCES lecteurs(id) ON DELETE CASCADE
);

COMMIT;