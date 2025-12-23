<?php
session_start();
require_once '../config/database.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    $name = $_POST['name'];
    $description = $_POST['description'];
    $email = $_POST['email'];
    $website = $_POST['website'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if($password !== $confirm_password) {
        $_SESSION['error'] = "Les mots de passe ne correspondent pas";
        header("Location: register.php");
        exit();
    }

    // Vérifier si l'email existe déjà
    $query = "SELECT id FROM charities WHERE email = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$email]);
    
    if($stmt->rowCount() > 0) {
        $_SESSION['error'] = "Email déjà enregistré";
        header("Location: register.php");
        exit();
    }

    // Insérer l'organisme
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $query = "INSERT INTO charities (name, description, email, password, website) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    
    if($stmt->execute([$name, $description, $email, $hashed_password, $website])) {
        $_SESSION['success'] = "Inscription réussie ! En attente de l'approbation de l'administrateur.";
        header("Location: login.php");
    } else {
        $_SESSION['error'] = "Échec de l'inscription. Veuillez réessayer.";
        header("Location: register.php");
    }
}
?>