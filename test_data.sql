-- INSERT INTO users (nom, bio, date_naissance, email, mot_de_passe, role)
-- VALUES
-- ('Jean Dupont', 'Lecteur régulier.', 642470400, 'jean@example.com', 'mdp123', 'user'),
-- ('Marie Curie', 'Administratrice.', 500169600, 'marie@example.com', 'adminpwd', 'admin'),
-- ('Albert Camus', 'Auteur célèbre.', -1771977600, 'camus@example.com', 'camuspass', 'author');

INSERT INTO lecteurs (user_id, type)
VALUES
(1, 'abonne');

INSERT INTO administrateurs (user_id)
VALUES
(2);

INSERT INTO auteurs (nom, biographie, date_naissance, date_deces)
VALUES
('Albert Camus', 'Philosophe et écrivain français.', -1771977600, -315360000);

INSERT INTO categories (nom, description)
VALUES
('Philosophie', 'Livres de réflexion philosophique'),
('Science', 'Ouvrages scientifiques populaires');

INSERT INTO categories (nom, description)
VALUES
('Philosophie', 'Livres de réflexion philosophique'),
('Science', 'Ouvrages scientifiques populaires'),
('Fiction', 'Romans et récits fictifs'),
('Histoire', 'Livres d’histoire mondiale'),
('Biographie', 'Récits de vie de personnalités célèbres'),
('Fantastique', 'Romans de science-fiction et fantasy'),
('Enfants', 'Livres pour enfants et jeunes adultes'),
('Cuisine', 'Livres de recettes et gastronomie'),
('Art', 'Livres sur l’art et la culture');

INSERT INTO fichiers (nom, chemin, "type", taille)
VALUES
('camus_cover.jpg', '/app/files/covers/camus.jpg', 'image/jpeg', 24567),
('philosophie_cover.jpg', '/app/files/covers/philosophie.jpg', 'image/jpeg', 12345),
('science_cover.jpg', '/app/files/covers/science.jpg', 'image/jpeg', 23456),
('fiction_cover.jpg', '/app/files/covers/fiction.jpg', 'image/jpeg', 34567),
('histoire_cover.jpg', '/app/files/covers/histoire.jpg', 'image/jpeg', 45678),
('biographie_cover.jpg', '/app/files/covers/biographie.jpg', 'image/jpeg', 56789),
('fantastique_cover.jpg', '/app/files/covers/fantastique.jpg', 'image/jpeg', 67890),
('enfants_cover.jpg', '/app/files/covers/enfants.jpg', 'image/jpeg', 78901),
('cuisine_cover.jpg', '/app/files/covers/cuisine.jpg', 'image/jpeg', 89012),
('art_cover.jpg', '/app/files/covers/art.jpg', 'image/jpeg', 90123);

INSERT INTO livres (titre, auteur_id, categorie_id, cover_image_id)
VALUES
('L’Étranger', 1, 1, 1),
('Le Mythe de Sisyphe', 1, 1, 2),
('La Peste', 1, 1, 3),
('Le Premier Homme', 1, 1, 4),
('La Chute', 1, 1, 5),
('Les Justes', 1, 1, 6),
('L’Homme Révolté', 1, 1, 7),
('Le Malentendu', 1, 1, 8),
('Le Silence de la Mer', 1, 1, 9),
('Caligula', 1, 1, 10);
INSERT INTO abonnements (lecteur_id, date_debut, date_fin)
VALUES
(1, 1735689600, 1767225600);
INSERT INTO emprunts (lecteur_id, livre_id, date_emprunt, date_retour, rendu)
VALUES
(1, 1, 1748736000, 1749945600, TRUE);
INSERT INTO paiements (lecteur_id, montant, commentaire)
VALUES
(1, 15.00, 'Paiement annuel pour abonnement');
INSERT INTO notifications (lecteur_id, message)
VALUES
(1, 'Votre livre est en retard.');