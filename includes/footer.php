<footer class="bg-navy-light text-light py-4 mt-5">
    <style>
        .footer-logo { height: 25px; width: auto; margin-right: 10px; vertical-align: middle; }
        
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
            color: #c0c0c0;
            text-shadow: 
                0 0 2px rgba(0,0,0,0.3), 
                0 0 4px rgba(255,255,255,0.5);
            filter: drop-shadow(0 0 2px rgba(255,255,255,0.7));
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
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5>
                    <img src="<?php echo BASE_URL; ?>images/favicon.png" alt="Logo MDVA" class="footer-logo">
                    MDVA
                </h5>
                <p class="mb-0">Écosystème de Micro-Dons Vérifiés et Attribués</p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-0">&copy; <?php echo date("Y"); ?> MDVA. Tous droits réservés.</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>