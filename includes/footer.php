    </div> <!-- Chiude il container di header.php -->

    <footer class="bg-dark text-white mt-5">
        <div class="container py-4">
            <div class="row">
                <div class="col-md-4">
                    <h5>Influencer Marketplace</h5>
                    <p class="text-muted">
                        La piattaforma che connette influencer e brand per collaborazioni di successo.
                    </p>
                </div>
                <div class="col-md-2">
                    <h6>Link Veloci</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo BASE_URL; ?>/" class="text-muted text-decoration-none">Home</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/auth/login.php" class="text-muted text-decoration-none">Login</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/auth/register.php" class="text-muted text-decoration-none">Registrati</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>Per Influencer</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo BASE_URL; ?>/auth/register.php?type=influencer" class="text-muted text-decoration-none">Diventa Influencer</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Come Funziona</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Tariffe</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>Per Brand</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo BASE_URL; ?>/auth/register.php?type=brand" class="text-muted text-decoration-none">Diventa Partner</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Cerca Influencer</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Case Studies</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-muted mb-0">&copy; 2024 Influencer Marketplace. Tutti i diritti riservati.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-muted text-decoration-none me-3">Privacy Policy</a>
                    <a href="#" class="text-muted text-decoration-none">Termini di Servizio</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>
    
    <!-- Script per gestire messaggi flash -->
    <script>
    // Auto-hide per alert dopo 5 secondi
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
    </script>
</body>
</html>