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

// Données pour le Donateur Vedette
$topDonor = [
    'name' => 'Marc-André Tremblay',
    'location' => 'Québec, QC',
    'total_donations' => 425,
    'total_amount' => '125.50',
    'favorite_cause' => "Fondation de l'Hôpital pour enfants",
    'image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=300&h=300&q=80', 
    'quote' => "Ayant moi-même vu l'impact des soins pédiatriques, je donne pour offrir aux enfants malades les meilleures chances de guérison. Avec MDVA, chaque transaction devient une petite pierre à l'édifice pour soutenir les familles et financer des équipements de pointe sans alourdir mon budget quotidien."
];

// Données pour les partenaires hôtes
$partners = [
    ['name' => 'Boulangerie de la Gare', 'location' => 'Vieux-Québec', 'community_impact' => '25,000+ $ collectés', 'image' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Fiers d\'offrir à nos clients une façon simple de faire la différence.'],
    ['name' => 'Café du Village', 'location' => 'Plateau Mont-Royal', 'community_impact' => '18,000+ $ collectés', 'image' => 'https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Nos clients adorent transformer leur monnaie en impact social.'],
    ['name' => 'Librairie Papyrus', 'location' => 'Mile-End', 'community_impact' => '31,000+ $ collectés', 'image' => 'https://images.unsplash.com/photo-1524578271613-d550eacf6090?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'S\'intègre parfaitement dans notre mission communautaire.'],
    ['name' => 'Épicerie Verte Bio', 'location' => 'Rosemont', 'community_impact' => '28,000+ $ collectés', 'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Nos valeurs écoresponsables vont de pair avec MDVA.'],
    ['name' => 'Pharmacie Santé Plus', 'location' => 'Westmount', 'community_impact' => '42,000+ $ collectés', 'image' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Permet à nos patients de contribuer facilement.'],
    ['name' => 'Restaurant Le Terroir', 'location' => 'Vieux-Port', 'community_impact' => '37,000+ $ collectés', 'image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Terminer par un geste généreux plaît à nos clients.']
];

$partnerChunks = array_chunk($partners, 3);
?>

<style>
    /* Hero Section - Sky Blue with White Text */
    .hero-section { 
        background-color: #87CEEB !important; 
        padding: 50px 0;
        border-bottom: 1px solid #dee2e6;
    }
    .hero-title { 
        color: white !important; 
        font-weight: 800; 
        text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }
    .hero-lead { 
        color: white !important; 
        font-weight: 500;
        font-size: 1.25rem;
    }
    .hero-image {
        max-height: 280px;
        width: auto;
    }

    /* General Styles */
    .business-section { background: #f8f9fa; border-top: 1px solid #dee2e6; border-bottom: 1px solid #dee2e6; }
    .partner-card { transition: all 0.3s ease; border-radius: 10px; background: white; height: 100%; border: 1px solid #e9ecef; }
    .host-badge { position: absolute; top: 12px; right: 12px; background: #1a3a5f; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; z-index: 10; }
    .impact-stat { background: #1a3a5f; color: white; padding: 10px; border-radius: 8px; margin: 12px 0; text-align: center; }
    .testimonial-bubble { font-style: italic; color: #555; font-size: 0.9rem; padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 3px solid #1a3a5f; }
    
    .donor-card { border: none; background: #f0f7ff; border-radius: 20px; padding: 30px; }
    .donor-avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid white; }
    .thank-you-title { font-weight: 700; color: #1a3a5f; }
    .charity-title-link { text-decoration: none; color: #212529; font-weight: 700; }
</style>

<div class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h1 class="display-5 hero-title">Faites une Différence avec les Micro-Dons</h1>
                <p class="hero-lead">MDVA transforme votre monnaie en dons vérifiables. Nous consolidons vos dons annuels en un seul reçu fiscal.</p>
            </div>
            <div class="col-md-5 text-center">
                <img src="<?php echo BASE_URL; ?>images/terminal.png" alt="Module MDVA" class="hero-image">
            </div>
        </div>
    </div>
</div>

<div class="container my-5">
    <h2 class="text-center thank-you-title mb-5">Nos Organismes de Bienfaisance Vérifiés</h2>
    <div class="row">
        <?php foreach($charities as $charity): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <?php if(!empty($charity['website'])): ?>
                            <a href="<?php echo htmlspecialchars($charity['website']); ?>" target="_blank" class="charity-title-link">
                                <?php echo htmlspecialchars($charity['name']); ?>
                            </a>
                        <?php else: ?>
                            <?php echo htmlspecialchars($charity['name']); ?>
                        <?php endif; ?>
                    </h5>
                    <p class="card-text text-muted flex-grow-1"><?php echo htmlspecialchars($charity['description']); ?></p>
                    
                    <?php if(!empty($charity['website'])): ?>
                    <div class="mt-3">
                        <a href="<?php echo htmlspecialchars($charity['website']); ?>" class="btn btn-sm btn-outline-primary w-100" target="_blank">
                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Visiter le Site Web
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="donor-section py-5 bg-white border-top">
    <div class="container">
        <div class="donor-card shadow-sm">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <img src="<?php echo $topDonor['image']; ?>" alt="Avatar" class="donor-avatar mb-2">
                    <h5 class="fw-bold"><?php echo $topDonor['name']; ?></h5>
                </div>
                <div class="col-md-6">
                    <span class="badge bg-warning text-dark mb-2">DONATEUR VEDETTE</span>
                    <p class="mb-2"><em>"<?php echo $topDonor['quote']; ?>"</em></p>
                    <p class="small mb-0"><strong>Cause de cœur :</strong> <span class="text-primary"><?php echo $topDonor['favorite_cause']; ?></span></p>
                </div>
                <div class="col-md-3 border-start">
                    <div class="ps-3">
                        <h3 class="text-primary mb-0"><?php echo $topDonor['total_donations']; ?></h3>
                        <p class="small text-muted text-uppercase fw-bold">Micro-dons</p>
                        <h3 class="text-success mb-0"><?php echo number_format($topDonor['total_amount'], 2); ?> $</h3>
                        <p class="small text-muted text-uppercase fw-bold">Impact Total</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="business-section py-5">
    <div class="container">
        <h2 class="text-center thank-you-title mb-5">Nos Hôtes Engagés</h2>
        <div id="partnerCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <?php foreach($partnerChunks as $index => $chunk): ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <div class="row g-4">
                        <?php foreach($chunk as $partner): ?>
                        <div class="col-md-4">
                            <div class="card partner-card shadow-sm border-0">
                                <span class="host-badge">Hôte MDVA</span>
                                <img src="<?php echo $partner['image']; ?>" class="card-img-top" style="height: 180px; object-fit: cover;" alt="<?php echo $partner['name']; ?>">
                                <div class="card-body">
                                    <h5 class="fw-bold mb-1"><?php echo $partner['name']; ?></h5>
                                    <p class="small text-muted mb-2"><i class="fa-solid fa-location-dot me-1"></i> <?php echo $partner['location']; ?></p>
                                    <div class="impact-stat">
                                        <span class="fw-bold h5 mb-0"><?php echo $partner['community_impact']; ?></span>
                                    </div>
                                    <div class="testimonial-bubble">"<?php echo $partner['testimonial']; ?>"</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#partnerCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon bg-dark rounded-circle"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#partnerCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon bg-dark rounded-circle"></span>
            </button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>