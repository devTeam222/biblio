<?php
$host = 'localhost';
$dbname = 'biblio';
$user = 'postgres';
$pass = 'ochokom'; // ou 'motdepasse' selon ta config

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname;", $user, $pass);
} catch (PDOException $e) {
    $errmsg = $e->getMessage();
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur de connexion à la base de données. Veuillez vérifier votre console pour plus d\'informations.',
        'error' => $errmsg
    ]);
    exit;
}
