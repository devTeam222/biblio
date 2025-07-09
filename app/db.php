<?php
$host = 'localhost';
$dbname = 'biblio';
$user = 'postgres';
$pass = 'ochokom'; // ou 'motdepasse' selon ta config

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname;", $user, $pass);
} catch (PDOException $e) {
    $errmsg = $e->getMessage();
    die("Erreur de connexion à la base de données verifier votre console pour plus d'information <script>console.log('$errmsg');</script>");
}
