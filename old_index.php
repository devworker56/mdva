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
        'community_impact' => '25,000+ $ collectés',
        'customer_footprint' => 'Lieu historique très fréquenté',
        'image' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=400&h=250&q=80',
        'testimonial' => 'Fiers d\'offrir à nos clients une façon simple de faire la différence.'
    ],
    [
        'name' => 'Café du Village',
        'type' => 'Café de quartier',
        'location' => 'Plateau Mont-Royal',
        'module_active_since' => 'Février 2024',
        'community_impact' => '18,000+ $ collectés',
        'customer_footprint' => 'Point de rencontre communautaire',
        'image' => 'https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&w=400&h=250&q=80',
        'testimonial' => 'Nos clients adorent transformer leur monnaie en impact social.'
    ],
    [
        'name' => 'Librairie Papyrus',
        'type' => 'Librairie indépendante',
        'location' => 'Mile-End',
        'module_active_since' => 'Janvier 2024',
        'community_impact' => '31,000+ $ collectés',
        'customer_footprint' => 'Public engagé et conscient',
        'image' => 'https://images.unsplash.com/photo-1521587760476-6c12a4b040da?auto=format&fit=crop&w=400&h=250&q=80',
        'testimonial' => 'S\'intègre parfaitement dans notre mission communautaire.'
    ],
    [
        'name' => 'Épicerie Verte Bio',
        'type' => 'Épicerie biologique',
        'location' => 'Rosemont',
        'module_active_since' => 'Mars 2024',
        'community_impact' => '28,000+ $ collectés',
        'customer_footprint' => 'Clients sensibles à l\'impact social',
        'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=400&h=250&q=80',
        'testimonial' => 'Nos valeurs écoresponsables vont de pair avec MDVA.'
    ],
    [
        'name' => 'Pharmacie Santé Plus',
        'type' => 'Pharmacie communautaire',
        'location' => 'Westmount',
        'module_active_since' => 'Février 2024',
        'community_impact' => '42,000+ $ collectés',
        'customer_footprint' => 'Trafic régulier et fidèle',
        'image' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=400&h=250&q=80',
        'testimonial' => 'Permet à nos patients de contribuer facilement.'
    ],
    [
        'name' => 'Restaurant Le Terroir',
        'type' => 'Restaurant gastronomique',
        'location' => 'Vieux-Port',
        'module_active_since' => 'Mars 2024',
        'community_impact' => '37,000+ $ collectés',
        'customer_footprint' => 'Touristes et résidents aisés',
        'image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=400&h=250&q=80',
        'testimonial' => 'Terminer par un geste généreux plaît à nos clients.'
    ]
];

// Split partners into groups of 3 for the carousel slides
$partnerChunks = array_chunk($partners, 3);
?>

<style>
    /* Custom Styling for the Business Section */
    .business-section {
        background: #f8f9fa;
        position: relative;
        border-top: 1px solid #dee2e6;
        border-bottom: 1px solid #dee2e6;
    }
    .partner-card {
        transition: all 0.3s ease;
        border: none;
        overflow: hidden;
        border-radius: 10px;
        background: white;
        color: #333;
        height: 100%;
        border: 1px solid #e9ecef;
        font-size: 1rem; /* Taille de base standard */
    }
    .partner-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
    }
    .host-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: linear-gradient(135deg, #0d6efd, #0b5ed7);
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem; /* Légèrement agrandi */
        font-weight: 600;
        z-index: 10;
        box-shadow: 0 2px 5px rgba(13, 110, 253, 0.3);
    }
    .partner-type {
        color: #6c757d;
        font-weight: 500;
        font-size: 0.9rem; /* Légèrement agrandi */
        margin-bottom: 6px;
    }
    .impact-stat {
        background: linear-gradient(135deg, #0d6efd 0%, #198754 100%);
        color: white;
        padding: 12px;
        border-radius: 8px;
        margin: 12px 0;
        text-align: center;
    }
    .impact-stat .number {
        font-size: 1.5rem; /* Légèrement agrandi */
        font-weight: 700;
        display: block;
        margin-bottom: 3px;
    }
    .impact-stat .label {
        font-size: 0.85rem; /* Légèrement agrandi */
        opacity: 0.9;
    }
    .location-info {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #6c757d;
        font-size: 0.9rem; /* Légèrement agrandi */
        margin-bottom: 8px;
        line-height: 1.4;
    }
    .testimonial {
        font-style: italic;
        color: #555;
        font-size: 0.9rem; /* Légèrement agrandi */
        padding: 12px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-top: 12px;
        border-left: 3px solid #0d6efd;
        line-height: 1.5;
    }
    .carousel-control-prev, .carousel-control-next {
        width: 40px;
        height: 40px;
        background: rgba(13, 110, 253, 0.1);
        border-radius: 50%;
        top: 50%;
        transform: translateY(-50%);
        border: 1px solid rgba(13, 110, 253, 0.2);
    }
    .carousel-control-prev:hover, .carousel-control-next:hover {
        background: rgba(13, 110, 253, 0.2);
    }
    .carousel-indicators [data-bs-target] {
        background-color: #0d6efd;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin: 0 4px;
        opacity: 0.3;
    }
    .carousel-indicators .active {
        opacity: 1;
    }
    .thank-you-title {
        font-size: 2.2rem; /* Légèrement agrandi */
        font-weight: 700;
        margin-bottom: 15px;
        color: #212529;
    }
    .section-subtitle {
        font-size: 1.1rem; /* Légèrement agrandi */
        color: #6c757d;
        max-width: 700px;
        margin: 0 auto 40px;
        line-height: 1.6;
    }
    .carousel-indicators {
        bottom: -40px;
    }
    .partner-card img {
        height: 180px;
        object-fit: cover;
        width: 100%;
    }
    
    /* Amélioration de la lisibilité globale */
    body {
        font-size: 1rem;
        line-height: 1.6;
    }
    
    .card-body {
        font-size: 1rem;
    }
    
    .card-title {
        font-size: 1.1rem; /* Taille standard pour les titres de cartes */
        line-height: 1.3;
    }
    
    .small {
        font-size: 0.9rem; /* Plus lisible que 0.85rem */
    }
    
    .btn-sm {
        font-size: 0.9rem;
        padding: 0.375rem 0.75rem;
    }
    
    /* Amélioration des titres de section */
    .display-5 {
        font-size: 2.5rem; /* Un peu plus grand */
    }
    
    .lead {
        font-size: 1.25rem; /* Standard pour lead */
        line-height: 1.6;
    }
</style>

<div class="hero-section text-center">
    <div class="container">
        <h1 class="display-5 fw-bold">Faites une Différence avec les Micro-Dons</h1>
        <p class="lead">MDVA transforme votre monnaie en dons vérifiables. Nous consolidons vos dons annuels en un seul reçu fiscal, vous permettant de réclamer votre crédit, tout en garantissant que les organismes reçoivent un soutien fiable et entièrement attribué.</p>
    </div>
</div>

<div class="container my-5">
    <h2 class="text-center mb-4">Nos Organismes de Bienfaisance Vérifiés</h2>
    <div class="row" id="charities-container">
        <?php foreach($charities as $charity): ?>
        <div class="col-md-4 mb-4">
            <div class="card charity-card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title fw-bold"><?php echo htmlspecialchars($charity['name']); ?></h5>
                    <p class="card-text"><?php echo htmlspecialchars($charity['description']); ?></p>
                    <?php if($charity['website']): ?>
                    <a href="<?php echo htmlspecialchars($charity['website']); ?>" class="btn btn-outline-primary btn-sm" target="_blank">
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
<div class="business-section py-5"> <!-- Retour à py-5 pour un meilleur espacement -->
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="thank-you-title">Nos Hôtes Engagés</h2>
            <p class="section-subtitle">
                Merci à ces commerces qui hébergent nos modules MDVA, permettant aux donateurs de transformer leur monnaie en impact social.
            </p>
        </div>

        <div id="partnerCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
            <div class="carousel-indicators">
                <?php foreach($partnerChunks as $index => $chunk): ?>
                <button type="button" data-bs-target="#partnerCarousel" data-bs-slide-to="<?php echo $index; ?>" 
                        class="<?php echo $index === 0 ? 'active' : ''; ?>"
                        aria-label="Slide <?php echo $index + 1; ?>"></button>
                <?php endforeach; ?>
            </div>

            <div class="carousel-inner">
                <?php foreach($partnerChunks as $index => $chunk): ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <div class="row g-4"> <!-- Retour à g-4 pour un meilleur espacement -->
                        <?php foreach($chunk as $partner): ?>
                        <div class="col-md-4">
                            <div class="card partner-card h-100">
                                <span class="host-badge">
                                    <i class="bi bi-house-heart me-1"></i> Hôte MDVA
                                </span>
                                <img src="<?php echo $partner['image']; ?>" class="card-img-top" alt="<?php echo $partner['name']; ?>">
                                <div class="card-body">
                                    <div class="partner-type">
                                        <i class="bi bi-tag me-1"></i> <?php echo $partner['type']; ?>
                                    </div>
                                    <h5 class="card-title fw-bold mb-2"><?php echo $partner['name']; ?></h5> <!-- Retour à mb-2 -->
                                    
                                    <div class="location-info">
                                        <i class="bi bi-geo-alt text-primary"></i>
                                        <?php echo $partner['location']; ?>
                                    </div>
                                    
                                    <div class="location-info">
                                        <i class="bi bi-calendar-check text-success"></i>
                                        Depuis <?php echo $partner['module_active_since']; ?>
                                    </div>
                                    
                                    <div class="impact-stat">
                                        <span class="number"><?php echo $partner['community_impact']; ?></span>
                                        <span class="label">Collecté à cet emplacement</span>
                                    </div>
                                    
                                    <div class="location-info">
                                        <i class="bi bi-people text-info"></i>
                                        <?php echo $partner['customer_footprint']; ?>
                                    </div>
                                    
                                    <div class="testimonial">
                                        <i class="bi bi-quote me-1 text-muted"></i>
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
        <div class="text-center mt-5 pt-4">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border shadow-sm">
                        <div class="card-body p-4">
                            <h4 class="card-title mb-3">Devenez un Hôte MDVA</h4>
                            <p class="card-text mb-3">
                                Rejoignez notre réseau de commerces engagés et offrez à vos clients la possibilité de faire la différence.
                            </p>
                            <div class="row text-center mb-3">
                                <div class="col-md-4 mb-2">
                                    <i class="bi bi-emoji-heart-eyes h5 text-primary mb-2 d-block"></i>
                                    <span>Engagement client</span>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <i class="bi bi-bullseye h5 text-success mb-2 d-block"></i>
                                    <span>Ancrage communautaire</span>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <i class="bi bi-trophy h5 text-warning mb-2 d-block"></i>
                                    <span>Visibilité positive</span>
                                </div>
                            </div>
                            <a href="#" class="btn btn-primary px-4">
                                <i class="bi bi-envelope me-1"></i> Devenir hôte
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
        interval: 5000
    });
});
</script>

<?php include 'includes/footer.php'; ?>