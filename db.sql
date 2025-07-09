-- Table des fichiers
CREATE TABLE fichiers (
  id SERIAL PRIMARY KEY,
  nom VARCHAR(255) NOT NULL,
  chemin VARCHAR(255) NOT NULL,
  type VARCHAR(50) NOT NULL,
  taille INT NOT NULL,
  date_telechargement BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP)
);
-- Table des utilisateurs
CREATE TABLE users (
  id SERIAL PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  bio TEXT,
  date_naissance BIGINT,
  email VARCHAR(100) UNIQUE NOT NULL,
  mot_de_passe TEXT,
  role VARCHAR(20) NOT NULL CHECK (role IN ('user', 'author', 'admin')) DEFAULT 'user',
  date_inscription BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP)
);

-- Table des lecteurs
CREATE TABLE lecteurs (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(20) NOT NULL CHECK (type IN ('abonne', 'visiteur')) DEFAULT 'visiteur',
  date_inscription BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
  FOREIGN KEY (user_id) REFERENCES users(id)
);
-- Table des administrateurs
CREATE TABLE administrateurs (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL,
  date_inscription BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
  FOREIGN KEY (user_id) REFERENCES users(id)
);
-- Table des auteurs
CREATE TABLE auteurs (
  id SERIAL PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  biographie TEXT,
  date_naissance BIGINT,
  date_deces BIGINT,
  date_creation BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP)
);
-- Table des catégories de livres
CREATE TABLE categories (
  id SERIAL PRIMARY KEY,
  nom VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  date_creation BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP)
);
-- Table des livres
CREATE TABLE livres (
  id SERIAL PRIMARY KEY,
  titre VARCHAR(255) NOT NULL,
  auteur_id INT NOT NULL,
  categorie_id INT NOT NULL,
  disponible BOOLEAN DEFAULT TRUE,
  cover_image_id INT,
  date_publication BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
  FOREIGN KEY (cover_image_id) REFERENCES fichiers(id),
  FOREIGN KEY (auteur_id) REFERENCES auteurs(id),
  FOREIGN KEY (categorie_id) REFERENCES categories(id)
);

-- Table des abonnements
CREATE TABLE abonnements (
  id SERIAL PRIMARY KEY,
  lecteur_id INT NOT NULL,
  date_debut BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
  date_fin BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
  statut VARCHAR(20) DEFAULT 'actif',
  FOREIGN KEY (lecteur_id) REFERENCES lecteurs(id)
);

-- Table des emprunts
CREATE TABLE emprunts (
  id SERIAL PRIMARY KEY,
  lecteur_id INT NOT NULL,
  livre_id INT NOT NULL,
  date_emprunt BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
  date_retour BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
  rendu BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (lecteur_id) REFERENCES lecteurs(id),
  FOREIGN KEY (livre_id) REFERENCES livres(id)
);

-- Table des paiements
CREATE TABLE paiements (
  id SERIAL PRIMARY KEY,
  lecteur_id INT NOT NULL,
  montant DECIMAL(10,2) NOT NULL,
  date_paiement BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
  commentaire TEXT,
  FOREIGN KEY (lecteur_id) REFERENCES lecteurs(id)
);

-- Table des notifications
CREATE TABLE notifications (
  id SERIAL PRIMARY KEY,
  lecteur_id INT NOT NULL,
  message TEXT NOT NULL,
  date_notification BIGINT DEFAULT EXTRACT(EPOCH FROM CURRENT_TIMESTAMP),
  lu BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (lecteur_id) REFERENCES lecteurs(id)
);
