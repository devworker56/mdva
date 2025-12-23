<footer class="bg-dark text-light py-4 mt-5">
    <style>
        .footer-icon-container {
            display: inline-block;
            position: relative;
            width: 1.5em;
            height: 1.5em;
            margin-right: 8px;
            vertical-align: middle;
        }
        .footer-shield-icon {
            position: absolute;
            font-size: 1.5em;
            color: #c0c0c0; /* Couleur argent */
            text-shadow: 
                0 0 2px rgba(0,0,0,0.3), /* Ombre extérieure */
                0 0 4px rgba(255,255,255,0.5); /* Brillance intérieure pour effet métallique */
            filter: drop-shadow(0 0 2px rgba(255,255,255,0.7)); /* Brillance supplémentaire */
            top: 0;
            left: 0;
        }
        .footer-heart-icon {
            position: absolute;
            font-size: 0.8em;
            color: white;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-shadow: 0 0 1px rgba(0,0,0,0.5);
        }
    </style>
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h5>
                    <span class="footer-icon-container">
                        <i class="fas fa-shield footer-shield-icon"></i>
                        <i class="fas fa-hand-holding-heart footer-heart-icon"></i>
                    </span> MDVA
                </h5>
                <p>Écosystème de Micro-Dons Vérifiés et Attribués</p>
            </div>
            <div class="col-md-6 text-end">
                <p>&copy; 2024 MDVA. Tous droits réservés.</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>