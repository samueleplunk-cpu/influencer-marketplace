<?php
// includes/footer.php
?>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Influencer Marketplace</h3>
                    <p>La piattaforma leader per connettere influencer e brand in collaborazioni di successo.</p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h4>Link Veloci</h4>
                    <ul>
                        <li><a href="<?php echo base_url('index.php'); ?>">Home</a></li>
                        <li><a href="<?php echo base_url('about.php'); ?>">Chi Siamo</a></li>
                        <li><a href="<?php echo base_url('contact.php'); ?>">Contatti</a></li>
                        <li><a href="<?php echo base_url('privacy.php'); ?>">Privacy Policy</a></li>
                        <li><a href="<?php echo base_url('terms.php'); ?>">Termini di Servizio</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Per Influencer</h4>
                    <ul>
                        <li><a href="<?php echo base_url('register.php?type=influencer'); ?>">Diventa Influencer</a></li>
                        <li><a href="<?php echo base_url('campaigns.php'); ?>">Trova Campagne</a></li>
                        <li><a href="<?php echo base_url('resources.php'); ?>">Risorse</a></li>
                        <li><a href="<?php echo base_url('help.php'); ?>">Guida</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Per Brand</h4>
                    <ul>
                        <li><a href="<?php echo base_url('register.php?type=brand'); ?>">Registra Brand</a></li>
                        <li><a href="<?php echo base_url('influencers.php'); ?>">Cerca Influencer</a></li>
                        <li><a href="<?php echo base_url('pricing.php'); ?>">Prezzi</a></li>
                        <li><a href="<?php echo base_url('case-studies.php'); ?>">Case Studies</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contatti</h4>
                    <div class="contact-info">
                        <p><i class="fas fa-envelope"></i> info@influencer-marketplace.it</p>
                        <p><i class="fas fa-phone"></i> +39 02 1234 5678</p>
                        <p><i class="fas fa-map-marker-alt"></i> Milano, Italia</p>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Influencer Marketplace. Tutti i diritti riservati.</p>
                <p>P.IVA 12345678901</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="<?php echo base_url('assets/js/script.js'); ?>"></script>
    
    <?php if (isset($_SESSION['flash_message'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showNotification('<?php echo $_SESSION['flash_message']['type']; ?>', '<?php echo $_SESSION['flash_message']['message']; ?>');
            <?php unset($_SESSION['flash_message']); ?>
        });
    </script>
    <?php endif; ?>
</body>
</html>