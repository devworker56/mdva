<?php
// Use dirname(__FILE__) to ensure the config is found regardless of which page calls the header
require_once dirname(__FILE__) . '/config.php';
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>images/favicon.ico?v=2">
    </head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>index.php">
                <img src="<?php echo BASE_URL; ?>images/favicon.png" alt="Logo" class="navbar-logo">
                MDVA
            </a>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>index.php">Accueil</a>
                    </li>
                    
                    <?php if(isset($_SESSION['user_type'])): ?>
                        <?php if($_SESSION['user_type'] == 'charity'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>charity/dashboard.php">Tableau de bord</a>
                            </li>
                        <?php elseif($_SESSION['user_type'] == 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>admin/dashboard.php">Admin</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>auth/logout.php">Déconnexion</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>auth/register.php">S'inscrire</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>auth/login.php">Connexion</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>