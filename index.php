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

// Données pour les partenaires hôtes du module MDVA
$partners = [
    [
        'name' => 'Boulangerie de la Gare',
        'type' => 'Boulangerie artisanale',
        'location' => 'Vieux-Québec',
        'module_active_since' => 'Janvier 2024',
        'community_impact' => '25,000+ $ collectés à cet emplacement',
        'customer_footprint' => 'Lieu historique très fréquenté',
        'image' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=400&q=80',
        'testimonial' => 'Nous sommes fiers d\'offrir à nos clients une façon simple de faire la différence pendant leurs achats quotidiens.'
    ],
    [
        'name' => 'Café du Village',
        'type' => 'Café de quartier',
        'location' => 'Plateau Mont-Royal',
        'module_active_since' => 'Février 2024',
        'community_impact' => '18,000+ $ collectés à cet emplacement',
        'customer_footprint' => 'Point de rencontre communautaire',
        'image' => 'https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&w=400&q=80',
        'testimonial' => 'Nos clients adorent pouvoir transformer leur monnaie en impact social positif.'
    ],
    [
        'name' => 'Librairie Papyrus',
        'type' => 'Librairie indépendante',
        'location' => 'Mile-End',
        'module_active_since' => 'Janvier 2024',
        'community_impact' => '31,000+ $ collectés à cet emplacement',
        'customer_footprint' => 'Public engagé et conscient',
        'image' => 'https://images.unsplash.com/photo-1521587760476-6c12a4b040da?auto=format&fit=crop&w=400&q=80',
        'testimonial' => 'Le module MDVA s\'intègre parfaitement dans notre mission de soutien à la communauté.'
    ],
    [
        'name' => 'Épicerie Verte Bio',
        'type' => 'Épicerie biologique',
        'location' => 'Rosemont',
        'module_active_since' => 'Mars 2024',
        'community_impact' => '28,000+ $ collectés à cet emplacement',
        'customer_footprint' => 'Clients sensibles à l\'impact social',
        'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=400&q=80',
        'testimonial' => 'Nos valeurs écoresponsables vont de pair avec la mission de MDVA.'
    ],
    [
        'name' => 'Pharmacie Santé Plus',
        'type' => 'Pharmacie communautaire',
        'location' => 'Westmount',
        'module_active_since' => 'Février 2024',
        'community_impact' => '42,000+ $ collectés à cet emplacement',
        'customer_footprint' => 'Trafic régulier et fidèle',
        'image' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=400&q=80',
        'testimonial' => 'Un service essentiel qui permet à nos patients de contribuer facilement.'
    ],
    [
        'name' => 'Restaurant Le Terroir',
        'type' => 'Restaurant gastronomique',
        'location' => 'Vieux-Port',
        'module_active_since' => 'Mars 2024',
        'community_impact' => '37,000+ $ collectés à cet emplacement',
        'customer_footprint' => 'Touristes et résidents aisés',
        'image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=400&q=80',
        'testimonial' => 'Nos clients apprécient de terminer leur expérience culinaire par un geste généreux.'
    ]
];

// Split partners into groups of 3 for the carousel slides
$partnerChunks = array_chunk($partners, 3);
?>

<style>
    /* Custom Styling for the Business Section */
    .business-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        position: relative;
        overflow: hidden;
    }
    .business-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100"><path fill="rgba(255,255,255,0.03)" d="M0,50 C150,100 350,0 500,50 S850,0 1000,50 L1000,100 L0,100 Z"></path></svg>');
        background-size: cover;
    }
    .partner-card {
        transition: all 0.3s ease;
        border: none;
        overflow: hidden;
        border-radius: 15px;
        background: white;
        color: #333;
        height: 100%;
    }
    .partner-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: 0 20px 40px rgba(0,0,0,0.15) !important;
    }
    .host-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 8px 15px;
        border-radius: 25px;
        font-size: 0.8rem;
        font-weight: 600;
        z-index: 10;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    .partner-type {
        color: #764ba2;
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 5px;
    }
    .impact-stat {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        padding: 15px;
        border-radius: 10px;
        margin: 15px 0;
        text-align: center;
    }
    .impact-stat .number {
        font-size: 1.8rem;
        font-weight: 800;
        display: block;
        margin-bottom: 5px;
    }
    .impact-stat .label {
        font-size: 0.85rem;
        opacity: 0.9;
    }
    .location-info {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 10px;
    }
    .testimonial {
        font-style: italic;
        color: #555;
        font-size: 0.9rem;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 10px;
        margin-top: 15px;
        border-left: 4px solid #667eea;
    }
    .carousel-control-prev, .carousel-control-next {
        width: 50px;
        height: 50px;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        top: 50%;
        transform: translateY(-50%);
        backdrop-filter: blur(10px);
    }
    .carousel-indicators [data-bs-target] {
        background-color: white;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin: 0 5px;
        opacity: 0.5;
    }
    .carousel-indicators .active {
        opacity: 1;
    }
    .thank-you-title {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 20px;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
    }
    .section-subtitle {
        font-size: 1.2rem;
        opacity: 0.9;
        max-width: 700px;
        margin: 0 auto 50px;
    }
    .module-icon-container {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 60px;
        height: 60px;
        background: white;
        border-radius: 50%;
        margin-bottom: 20px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    .module-icon {
        font-size: 2rem;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
</style>

<div class="hero-section text-center">
    <div class="container">
        <h1 class="display-4 fw-bold">Faites une Différence avec les Micro-Dons</h1>
        <p class="lead">MDVA transforme votre monnaie en dons vérifiables. Nous consolidons vos dons annuels en un seul reçu fiscal, vous permettant de réclamer votre crédit, tout en garantissant que les organismes reçoivent un soutien fiable et entièrement attribué.</p>
    </div>
</div>

<div class="container my-5">
    <h2 class="text-center mb-4 section-title">Nos Organismes de Bienfaisance Vérifiés</h2>
    <div class="row" id="charities-container">
        <?php foreach($charities as $charity): ?>
        <div class="col-md-4 mb-4">
            <div class="card charity-card h-100 shadow-sm">
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

<!-- Section des Partenaires Hôtes -->
<div class="business-section py-5">
    <div class="container position-relative">
        <div class="text-center mb-5">
            <div class="module-icon-container mx-auto">
                <i class="bi bi-shop-window module-icon"></i>
            </div>
            <h2 class="thank-you-title">Nos Hôtes Engagés</h2>
            <p class="section-subtitle">Un merci spécial à ces commerces qui hébergent nos modules MDVA, permettant à des milliers de donateurs de transformer leur monnaie en impact social.</p>
        </div>

        <div id="partnerCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="6000">
            <div class="carousel-indicators" style="bottom: -60px;">
                <?php foreach($partnerChunks as $index => $chunk): ?>
                <button type="button" data-bs-target="#partnerCarousel" data-bs-slide-to="<?php echo $index; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?>"></button>
                <?php endforeach; ?>
            </div>

            <div class="carousel-inner">
                <?php foreach($partnerChunks as $index => $chunk): ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <div class="row g-4">
                        <?php foreach($chunk as $partner): ?>
                        <div class="col-md-4">
                            <div class="card partner-card h-100 shadow-lg">
                                <span class="host-badge">
                                    <i class="bi bi-house-heart-fill me-1"></i> Hôte MDVA
                                </span>
                                <img src="<?php echo $partner['image']; ?>" class="card-img-top" alt="<?php echo $partner['name']; ?>" style="height: 200px; object-fit: cover;">
                                <div class="card-body">
                                    <div class="partner-type">
                                        <i class="bi bi-tag-fill me-1"></i> <?php echo $partner['type']; ?>
                                    </div>
                                    <h5 class="card-title fw-bold mb-2"><?php echo $partner['name']; ?></h5>
                                    
                                    <div class="location-info">
                                        <i class="bi bi-geo-alt-fill text-primary"></i>
                                        <?php echo $partner['location']; ?>
                                    </div>
                                    
                                    <div class="location-info">
                                        <i class="bi bi-calendar-check-fill text-success"></i>
                                        Module actif depuis <?php echo $partner['module_active_since']; ?>
                                    </div>
                                    
                                    <div class="impact-stat">
                                        <span class="number"><?php echo $partner['community_impact']; ?></span>
                                        <span class="label">Impact total à cet emplacement</span>
                                    </div>
                                    
                                    <div class="location-info">
                                        <i class="bi bi-people-fill text-info"></i>
                                        <?php echo $partner['customer_footprint']; ?>
                                    </div>
                                    
                                    <div class="testimonial">
                                        <i class="bi bi-chat-quote-fill me-2 text-primary"></i>
                                        "<?php echo $partner['testimonial']; ?>"
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button class="carousel-control-prev" type="button" data-bs-target="#partnerCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Précédent</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#partnerCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Suivant</span>
            </button>
        </div>
        
        <!-- CTA pour devenir hôte -->
        <div class="text-center mt-5 pt-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-lg" style="background: rgba(255,255,255,0.95);">
                        <div class="card-body p-5">
                            <h3 class="card-title mb-4">Devenez un Hôte MDVA</h3>
                            <p class="card-text mb-4">
                                Rejoignez notre réseau de commerces engagés et offrez à vos clients la possibilité de faire la différence.
                                En hébergeant un module MDVA, vous :
                            </p>
                            <div class="row text-center">
                                <div class="col-md-4 mb-3">
                                    <i class="bi bi-emoji-heart-eyes display-6 text-primary mb-3"></i>
                                    <h6>Renforcez l'engagement client</h6>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <i class="bi bi-bullseye display-6 text-success mb-3"></i>
                                    <h6>Ancrez votre commerce dans la communauté</h6>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <i class="bi bi-trophy display-6 text-warning mb-3"></i>
                                    <h6>Bénéficiez d'une visibilité positive</h6>
                                </div>
                            </div>
                            <a href="#" class="btn btn-lg btn-primary mt-3 px-5">
                                <i class="bi bi-envelope-fill me-2"></i> Devenir hôte
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Connexion WebSocket pour les mises à jour en temps réel
const ws = new WebSocket('ws://localhost:8080');
ws.onmessage = function(event) {
    const data = JSON.parse(event.data);
    if(data.type === 'new_charity') {
        location.reload();
    }
};

// Initialize Carousel
document.addEventListener('DOMContentLoaded', function() {
    const el = document.getElementById('partnerCarousel');
    new bootstrap.Carousel(el, {
        pause: 'hover',
        wrap: true,
        interval: 6000
    });
});
</script>

<?php include 'includes/footer.php'; ?>