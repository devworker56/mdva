<?php include 'includes/header.php'; ?>
<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Obtenir les organismes de bienfaisance approuvés
$query = "SELECT * FROM charities WHERE approved = 1 ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$charities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtenir le total des dons
$query = "SELECT SUM(amount) as total_donations FROM donations";
$stmt = $db->prepare($query);
$stmt->execute();
$total_donations = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<div class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 fw-bold">Faites une Différence avec les Micro-Dons</h1>
            <p class="lead">MDVA transforme votre monnaie en dons vérifiables. Nous consolidons vos dons annuels en un seul reçu fiscal, vous permettant de réclamer votre crédit, tout en garantissant que les organismes reçoivent un soutien fiable et entièrement attribué.</p>
        </div>
    </div>

<div class="container my-5">
    <h2 class="text-center mb-4">Nos Organismes de Bienfaisance Vérifiés</h2>
    <div class="row" id="charities-container">
        <?php foreach($charities as $charity): ?>
        <div class="col-md-4">
            <div class="card charity-card">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($charity['name']); ?></h5>
                    <p class="card-text"><?php echo htmlspecialchars($charity['description']); ?></p>
                    <?php if($charity['website']): ?>
                    <a href="<?php echo htmlspecialchars($charity['website']); ?>" class="btn btn-outline-primary" target="_blank">
                        Visiter le Site Web
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// Connexion WebSocket pour les mises à jour en temps réel
const ws = new WebSocket('ws://localhost:8080');
ws.onmessage = function(event) {
    const data = JSON.parse(event.data);
    if(data.type === 'new_charity') {
        // Recharger la page pour afficher le nouvel organisme
        location.reload();
    }
};
</script>

<?php include 'includes/footer.php'; ?>