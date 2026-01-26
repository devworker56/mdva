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
    'total_donations' => 85,
    'total_amount' => '425.50',
    'favorite_cause' => "Fondation de l'Hôpital pour enfants",
    'image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=300&h=300&q=80', 
    'quote' => "Ayant moi-même vu l'impact des soins pédiatriques, je donne pour offrir aux enfants malades les meilleures chances de guérison. Avec MDVA, chaque transaction devient une petite pierre à l'édifice pour soutenir les familles et financer des équipements de pointe sans alourdir mon budget quotidien."
];

// Données pour les partenaires hôtes
$partners = [
    ['name' => 'Boulangerie de la Gare', 'type' => 'Boulangerie artisanale', 'location' => 'Vieux-Québec', 'community_impact' => '25,000+ $ collectés', 'image' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Fiers d\'offrir à nos clients une façon simple de faire la différence.'],
    ['name' => 'Café du Village', 'type' => 'Café de quartier', 'location' => 'Plateau Mont-Royal', 'community_impact' => '18,000+ $ collectés', 'image' => 'https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Nos clients adorent transformer leur monnaie en impact social.'],
    ['name' => 'Librairie Papyrus', 'type' => 'Librairie indépendante', 'location' => 'Mile-End', 'community_impact' => '31,000+ $ collectés', 'image' => 'https://images.unsplash.com/photo-1521587760476-6c12a4b0400da?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'S\'intègre parfaitement dans notre mission communautaire.'],
    ['name' => 'Épicerie Verte Bio', 'type' => 'Épicerie biologique', 'location' => 'Rosemont', 'community_impact' => '28,000+ $ collectés', 'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Nos valeurs écoresponsables vont de pair avec MDVA.'],
    ['name' => 'Pharmacie Santé Plus', 'type' => 'Pharmacie communautaire', 'location' => 'Westmount', 'community_impact' => '42,000+ $ collectés', 'image' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Permet à nos patients de contribuer facilement.'],
    ['name' => 'Restaurant Le Terroir', 'type' => 'Restaurant gastronomique', 'location' => 'Vieux-Port', 'community_impact' => '37,000+ $ collectés', 'image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=400&h=250&q=80', 'testimonial' => 'Terminer par un geste généreux plaît à nos clients.']
];

$partnerChunks = array_chunk($partners, 3);
?>

<style>
    /* Hero Section - Sky Blue Theme */
    .hero-section { 
        background-color: #87CEEB !important; 
        border-bottom: 1px solid #dee2e6;
        padding: 80px 0;
    }
    .hero-title { color: #1a3a5f; font-weight: 800; }
    .hero-lead { color: #2c5282; font-size: 1.25rem; margin-bottom: 2rem; }

    /* Terminal Image Styling & Animation */
    .terminal-container {
        position: relative;
        display: inline-block;
    }
    .hero-image {
        max-width: 320px;
        height: auto;
        filter: drop-shadow(0 15px 30px rgba(0,0,0,0.3));
        border-radius: 15px;
        animation: float 4s ease-in-out infinite;
    }
    @keyframes float {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-15px); }
        100% { transform: translateY(0px); }
    }

    /* Partner & Charity Styling */
    .business-section { background: #f8f9fa; border-top: 1px solid #dee2e6; border-bottom: 1px solid #dee2e6; }
    .partner-card { transition: all 0.3s ease; border-radius: 10px; background: white; height: 100%; border: 1px solid #e9ecef; position: relative; }
    .partner-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important; }
    .host-badge { position: absolute; top: 12px; right: 12px; background: linear-gradient(135deg, #1a3a5f, #2c5282); color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; z-index: 10; }
    .impact-stat { background: linear-gradient(135deg, #1a3a5f 0%, #198754 100%); color: white; padding: 12px; border-radius: 8px; margin: 12px 0; text-align: center; }
    .location-info { display: flex; align-items: center; gap: 6px; color: #6c757d; font-size: 0.9rem; margin-bottom: 8px; }
    .testimonial-bubble { font-style: italic; color: #555; font-size: 0.9rem; padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 3px solid #1a3a5f; }
    
    /* Donor Section */
    .donor-section { background: #ffffff; border-top: 1px solid #eee; }
    .donor-card { border: none; background: #f0f7ff; border-radius: 20px; padding: 40px; position: relative; }
    .donor-avatar { width: 140px; height: 140px; border-radius: 50%; border: 6px solid #fff; box-shadow: 0 8px 20px rgba(0,0,0,0.1); object-fit: cover; }
    .impact-badge { background: #ffc107; color: #000; font-weight: 800; padding: 5px 15px; border-radius: 50px; display: inline-block; margin-bottom: 15px; font-size: 0.75rem; letter-spacing: 1px; }
    .donor-quote { font-size: 1.15rem; line-height: 1.7; color: #333; }
    .stats-divider { border-left: 2px solid #cbdcf0; padding-left: 25px; }
    
    /* Typography */
    .thank-you-title { font-size: 2.2rem; font-weight: 700; color: #1a3a5f; }
    .section-subtitle { font-size: 1.1rem; color: #6c757d; max-width: 700px; margin: 0 auto 40px; }
    .charity-title-link { text-decoration: none; color: #212529; transition: color 0.2s; font-weight: 700; }
    .charity-title-link:hover { color: #1a3a5f; }
</style>

<div class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7 text-center text-lg-start">
                <h1 class="display-4 hero-title mb-3">Faites une Différence avec les Micro-Dons</h1>
                <p class="hero-lead">MDVA transforme votre monnaie en dons vérifiables. Nous consolidons vos dons annuels en un seul reçu fiscal.</p>
                <div class="d-grid d-md-flex justify-content-lg-start justify-content-center gap-3">
                    <a href="<?php echo BASE_URL; ?>auth/register.php" class="btn btn-primary btn-lg px-5 shadow">S'inscrire</a>
                    <a href="#charities" class="btn btn-outline-dark btn-lg px-5">Voir les causes</a>
                </div>
            </div>
            <div class="col-lg-5 text-center mt-5 mt-lg-0">
                <div class="terminal-container">
                    <img src="<?php echo BASE_URL; ?>images/terminal.png" alt="Module MDVA" class="hero-image">
                </div>
            </div>
        </div>
    </div>
</div>

<div id="charities" class="container my-5 py-4">
    <div class="text-center mb-5">
        <h2 class="thank-you-title">Nos Organismes de Bienfaisance Vérifiés</h2>
        <p class="section-subtitle">Chaque organisme sur notre plateforme est rigoureusement vérifié pour garantir l'impact de vos dons.</p>
    </div>
    <div class="row">
        <?php foreach($charities as $charity): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <?php if(!empty($charity['website'])): ?>
                            <a href="<?php echo htmlspecialchars($charity['website']); ?>" target="_blank" class="charity-title-link">
                                <?php echo htmlspecialchars($charity['name']); ?> <i class="fa-solid fa-arrow-up-right-from-square ms-1" style="font-size: 0.8rem;"></i>
                            </a>
                        <?php else: ?>
                            <?php echo htmlspecialchars($charity['name']); ?>
                        <?php endif; ?>
                    </h5>
                    <p class="card-text text-muted flex-grow-1"><?php echo htmlspecialchars($charity['description']); ?></p>
                    <div class="mt-3">
                        <span class="badge bg-light text-dark border"><i class="fa-solid fa-check-circle text-success me-1"></i> Vérifié par MDVA</span>
                    </div>
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
            <p class="section-subtitle">Merci à nos philanthropes du quotidien qui changent le monde, un centime à la fois.</p>
        </div>

        <div class="donor-card shadow-sm">
            <div class="row align-items-center">
                <div class="col-lg-3 text-center mb-4 mb-lg-0">
                    <img src="<?php echo $topDonor['image']; ?>" alt="Avatar" class="donor-avatar mb-3">
                    <h4 class="fw-bold mb-1"><?php echo $topDonor['name']; ?></h4>
                    <p class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i> <?php echo $topDonor['location']; ?></p>
                </div>
                
                <div class="col-lg-6 px-lg-5">
                    <div class="impact-badge">⭐ DONATEUR VEDETTE DU MOIS</div>
                    <div class="donor-quote mb-3">
                        <em>"<?php echo $topDonor['quote']; ?>"</em>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-heart text-danger"></i>
                        <span class="fw-bold">Cause de cœur : <span class="text-primary"><?php echo $topDonor['favorite_cause']; ?></span></span>
                    </div>
                </div>
                
                <div class="col-lg-3 mt-4 mt-lg-0">
                    <div class="stats-divider">
                        <div class="mb-4">
                            <span class="display-6 fw-bold text-primary"><?php echo $topDonor['total_donations']; ?></span>
                            <div class="text-muted text-uppercase small fw-bold">Micro-dons (2024)</div>
                        </div>
                        <div>
                            <span class="h2 fw-bold text-success"><?php echo number_format($topDonor['total_amount'], 2); ?> $</span>
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
                            <div class="card partner-card shadow-sm border-0">
                                <span class="host-badge"><i class="fa-solid fa-shop me-1"></i> Hôte MDVA</span>
                                <img src="<?php echo $partner['image']; ?>" class="card-img-top" alt="Partner" style="height: 200px; object-fit: cover;">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold mb-2"><?php echo $partner['name']; ?></h5>
                                    <div class="location-info"><i class="fa-solid fa-location-dot text-primary"></i> <?php echo $partner['location']; ?></div>
                                    <div class="impact-stat">
                                        <span class="d-block h4 fw-bold mb-0"><?php echo $partner['community_impact']; ?></span>
                                    </div>
                                    <div class="testimonial-bubble mt-3">"<?php echo $partner['testimonial']; ?>"</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button class="carousel-control-prev" type="button" data-bs-target="#partnerCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon bg-dark rounded-circle p-3"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#partnerCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon bg-dark rounded-circle p-3"></span>
            </button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>