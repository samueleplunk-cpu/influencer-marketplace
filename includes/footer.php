<?php
// includes/footer.php - VERSIONE SEMPLIFICATA E FUNZIONANTE
?>
    </main> <!-- Chiude il <main> aperto in header.php -->

    <!-- Footer Semplificato -->
    <footer class="bg-dark text-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Influencer Marketplace</h5>
                    <p class="mb-2">La piattaforma per connettere influencer e brand.</p>
                </div>
                <div class="col-md-3">
                    <h6>Link Utili</h6>
                    <ul class="list-unstyled">
                        <li><a href="/infl/" class="text-light text-decoration-none">Home</a></li>
                        <li><a href="/infl/auth/login.php" class="text-light text-decoration-none">Login</a></li>
                        <li><a href="/infl/auth/register.php" class="text-light text-decoration-none">Registrati</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>Contatti</h6>
                    <ul class="list-unstyled">
                        <li>Email: info@marketplace.it</li>
                        <li>Tel: +39 02 1234 5678</li>
                    </ul>
                </div>
            </div>
            <hr class="my-3 bg-light">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Influencer Marketplace. Tutti i diritti riservati.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>