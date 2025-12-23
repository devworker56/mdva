<?php
session_start();
require_once '../config/database.php';

// Activer le rapport d'erreurs détaillé
error_reporting(E_ALL);
ini_set('display_errors', 1);

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $user_type = $_POST['user_type'];

    echo "<h3>Processus de débogage de connexion :</h3>";
    echo "Type d'utilisateur : " . htmlspecialchars($user_type) . "<br>";
    echo "Email : " . htmlspecialchars($email) . "<br>";
    echo "Longueur du mot de passe : " . strlen($password) . "<br>";

    if($user_type == 'charity') {
        echo "Traitement en tant qu'Organisme...<br>";
        $query = "SELECT * FROM charities WHERE email = ? AND approved = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if($user) {
            echo "Organisme trouvé : " . $user['name'] . "<br>";
            if(password_verify($password, $user['password'])) {
                echo "✓ Mot de passe d'organisme vérifié !<br>";
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = 'charity';
                $_SESSION['charity_name'] = $user['name'];
                echo "Redirection vers le tableau de bord de l'organisme...<br>";
                header("Location: ../charity/dashboard.php");
                exit();
            } else {
                echo "✗ Mot de passe d'organisme incorrect<br>";
            }
        } else {
            echo "✗ Organisme non trouvé ou non approuvé<br>";
        }

    } elseif($user_type == 'admin') {
        echo "Traitement en tant qu'Administrateur...<br>";
        $query = "SELECT * FROM admins WHERE email = ? AND active = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if($user) {
            echo "Administrateur trouvé : " . $user['email'] . "<br>";
            $password_valid = password_verify($password, $user['password']);
            echo "Mot de passe valide : " . ($password_valid ? 'OUI' : 'NON') . "<br>";
            
            if($password_valid) {
                echo "✓ Connexion administrateur réussie !<br>";
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = 'admin';
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_name'] = $user['full_name'];
                
                // Débogage des données de session
                echo "Données de session définies :<br>";
                echo "- user_id : " . $_SESSION['user_id'] . "<br>";
                echo "- user_type : " . $_SESSION['user_type'] . "<br>";
                echo "- admin_email : " . $_SESSION['admin_email'] . "<br>";
                
                echo "Redirection vers le tableau de bord administrateur...<br>";
                header("Location: ../admin/dashboard.php");
                exit();
            } else {
                echo "✗ Mot de passe administrateur incorrect<br>";
            }
        } else {
            echo "✗ Administrateur non trouvé ou inactif<br>";
        }
    } else {
        echo "✗ Type d'utilisateur invalide : " . htmlspecialchars($user_type) . "<br>";
    }

    $_SESSION['error'] = "Identifiants invalides ou compte non approuvé";
    echo "Définition du message d'erreur et redirection vers la connexion...<br>";
    header("Location: login.php");
    exit();
} else {
    echo "✗ Pas une requête POST<br>";
    header("Location: login.php");
    exit();
}
?>