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

// Données fictives pour le Donateur Vedette (À remplacer par une requête SQL plus tard)
$topDonor = [
    'name' => 'Marc-André Tremblay',
    'location' => 'Québec, QC',
    'total_donations' => 85,
    'total_amount' => '425.50',
    'favorite_cause' => 'Le Club des Petits Déjeuners',
    'image' => 'https://i.pravatar.cc/150?u=marc', // Avatar placeholder
    'quote' => "Je donne parce que je crois que chaque enfant mérite de commencer sa journée l'estomac plein pour réussir à l'école. Avec MDVA, ma petite monnaie de chaque matin à la boulangerie devient un geste concret qui s'accumule sans même que j'y pense."
];

// Données pour les partenaires hôtes du module MDVA
$partners = [
    ['name' => 'Boulangerie de la Gare', 'type' => 'Boulangerie artisanale', 'location' => 'Vieux-Québec', 'module_active_since' => 'Janvier 2024', 'community_impact' => '25,000+ $', 'customer_footprint' => 'Lieu historique', 'image' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Fiers d\'offrir à nos clients une façon simple de faire la différence.'],
    ['name' => 'Café du Village', 'type' => 'Café de quartier', 'location' => 'Plateau Mont-Royal', 'module_active_since' => 'Février 2024', 'community_impact' => '18,000+ $', 'customer_footprint' => 'Point de rencontre', 'image' => 'https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Nos clients adorent transformer leur monnaie en impact social.'],
    ['name' => 'Librairie Papyrus', 'type' => 'Librairie indépendante', 'location' => 'Mile-End', 'module_active_since' => 'Janvier 2024', 'community_impact' => '31,000+ $', 'customer_footprint' => 'Public engagé', 'image' => 'https://images.unsplash.com/photo-1521587760476-6c12a4b040da?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'S\'intègre parfaitement dans notre mission communautaire.'],
    ['name' => 'Épicerie Verte Bio', 'type' => 'Épicerie biologique', 'location' => 'Rosemont', 'module_active_since' => 'Mars 2024', 'community_impact' => '28,000+ $', 'customer_footprint' => 'Clients sensibles', 'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Nos valeurs écoresponsables vont de pair avec MDVA.'],
    ['name' => 'Pharmacie Santé Plus', 'type' => 'Pharmacie communautaire', 'location' => 'Westmount', 'module_active_since' => 'Février 2024', 'community_impact' => '42,000+ $', 'customer_footprint' => 'Trafic régulier', 'image' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Permet à nos patients de contribuer facilement.'],
    ['name' => 'Restaurant Le Terroir', 'type' => 'Restaurant gastronomique', 'location' => 'Vieux-Port', 'module_active_since' => 'Mars 2024', 'community_impact' => '37,000+ $', 'customer_footprint' => 'Touristes et résidents', 'image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Terminer par un geste généreux plaît à nos clients.']
];

$partnerChunks = array_chunk($partners, 3);
?>

<style>
    /* existing styles... */
    .business-section { background: #f8f9fa; position: relative; border-top: 1px solid #dee2e6; border-bottom: 1px solid #dee2e6; }
    .partner-card { transition: all 0.3s ease; border: none; overflow: hidden; border-radius: 10px; background: white; color: #333; height: 100%; border: 1px solid #e9ecef; font-size: 1rem; }
    .partner-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important; }
    .host-badge { position: absolute; top: 12px; right: 12px; background: linear-gradient(135deg, #0d6efd, #0b5ed7); color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; z-index: 10; }
    .impact-stat { background: linear-gradient(135deg, #0d6efd 0%, #198754 100%); color: white; padding: 12px; border-radius: 8px; margin: 12px 0; text-align: center; }
    .location-info { display: flex; align-items: center; gap: 6px; color: #6c757d; font-size: 0.9rem; margin-bottom: 8px; }
    .testimonial { font-style: italic; color: #555; font-size: 0.9rem; padding: 12px; background: #f8f9fa; border-radius: 8px; margin-top: 12px; border-left: 3px solid #0d6efd; }
    .thank-you-title { font-size: 2.2rem; font-weight: 700; margin-bottom: 15px; color: #212529; }
    .section-subtitle { font-size: 1.1rem; color: #6c757d; max-width: 700px; margin: 0 auto 40px; }
    
    /* New Donor Section Styles */
    .donor-section { background: #ffffff; border-top: 1px solid #eee; }
    .donor-card { border: none; background: #f0f7ff; border-radius: 20px; padding: 30px; position: relative; }
    .donor-avatar { width: 120px; height: 120px; border-radius: 50%; border: 5px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); object-fit: cover; }
    .impact-badge { background: #ffc107; color: #000; font-weight: bold; padding: 4px 15px; border-radius: 50px; display: inline-block; margin-bottom: 10px; }
    .donor-quote { font-size: 1.15rem; line-height: 1.7; color: #444; position: relative; z-index: 1; }
    .donor-stats-box { border-left: 2px solid #dee2e6; padding-left: 20px; }
</style>

<div class="hero-section text-center py-5 bg-light">
    <div class="container">
        <h1 class="display-5 fw-bold">Faites une Différence avec les Micro-Dons</h1>
        <p class="lead">MDVA transforme votre monnaie en dons vérifiables. Nous consolidons vos dons annuels en un seul reçu fiscal.</p>
    </div>
</div>

<div class="container my-5">
    <h2 class="text-center mb-4">Nos Organismes de Bienfaisance Vérifiés</h2>
    <div class="row">
        <?php foreach($charities as $charity): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title fw-bold text-primary"><?php echo htmlspecialchars($charity['name']); ?></h5>
                    <p class="card-text text-muted"><?php echo htmlspecialchars($charity['description']); ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="donor-section py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="thank-you-title">Les Champions de l’Impact</h2>
            <p class="section-subtitle">Découvrez nos <strong>Philanthropes du Quotidien</strong>. Des citoyens qui prouvent que la générosité n'est pas une question de montant, mais de constance.</p>
        </div>

        <div class="donor-card shadow-sm">
            <div class="row align-items-center">
                <div class="col-lg-3 text-center mb-4 mb-lg-0">
                    <img src="<?php echo $topDonor['image']; ?>" alt="Donateur" class="donor-avatar mb-3">
                    <h4 class="fw-bold mb-0"><?php echo $topDonor['name']; ?></h4>
                    <p class="text-muted small"><i class="bi bi-geo-alt"></i> <?php echo $topDonor['location']; ?></p>
                </div>
                <div class="col-lg-6 px-lg-5">
                    <div class="impact-badge shadow-sm small">⭐ DONATEUR VEDETTE DU MOIS</div>
                    <div class="donor-quote mb-3">
                        <i class="bi bi-quote fs-2 text-primary opacity-25"></i>
                        <em><?php echo $topDonor['quote']; ?></em>
                    </div>
                    <p class="fw-bold text-dark mb-0">— Cause soutenue : <span class="text-primary"><?php echo $topDonor['favorite_cause']; ?></span></p>
                </div>
                <div class="col-lg-3 mt-4 mt-lg-0">
                    <div class="donor-stats-box">
                        <div class="mb-3">
                            <span class="display-6 fw-bold text-primary"><?php echo $topDonor['total_donations']; ?></span>
                            <div class="text-muted text-uppercase small fw-bold">Micro-dons cette année</div>
                        </div>
                        <div>
                            <span class="h3 fw-bold text-success"><?php echo $topDonor['total_amount']; ?> $</span>
                            <div class="text-muted text-uppercase small fw-bold">Impact Total</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="business-section py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="thank-you-title">Nos Hôtes Engagés</h2>
            <p class="section-subtitle">Merci à ces commerces qui hébergent nos modules MDVA.</p>
        </div>

        <div id="partnerCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <?php foreach($partnerChunks as $index => $chunk): ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <div class="row g-4">
                        <?php foreach($chunk as $partner): ?>
                        <div class="col-md-4">
                            <div class="card partner-card h-100 shadow-sm">
                                <span class="host-badge">Hôte MDVA</span>
                                <img src="<?php echo $partner['image']; ?>" class="card-img-top" alt="Partner">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold"><?php echo $partner['name']; ?></h5>
                                    <div class="location-info"><i class="bi bi-geo-alt text-primary"></i><?php echo $partner['location']; ?></div>
                                    <div class="impact-stat">
                                        <span class="d-block h4 fw-bold mb-0"><?php echo $partner['community_impact']; ?></span>
                                        <small>Collecté ici</small>
                                    </div>
                                    <div class="testimonial">"<?php echo $partner['testimonial']; ?>"</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#partnerCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
            <button class="carousel-control-next" type="button" data-bs-target="#partnerCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>