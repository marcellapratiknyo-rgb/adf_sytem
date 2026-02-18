    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-section">
                <h4>Tentang Narayana</h4>
                <p><?php echo htmlize(getConfig('hotel_description')); ?></p>
            </div>
            
            <div class="footer-section">
                <h4>Hubungi Kami</h4>
                <p>
                    <i data-feather="phone" class="icon-small"></i>
                    <a href="tel:<?php echo htmlize(getConfig('phone')); ?>">
                        <?php echo htmlize(getConfig('phone')); ?>
                    </a>
                </p>
                <p>
                    <i data-feather="mail" class="icon-small"></i>
                    <a href="mailto:<?php echo htmlize(getConfig('email')); ?>">
                        <?php echo htmlize(getConfig('email')); ?>
                    </a>
                </p>
                <p>
                    <i data-feather="map-pin" class="icon-small"></i>
                    <?php echo htmlize(getConfig('address')); ?>
                </p>
            </div>
            
            <div class="footer-section">
                <h4>Layanan</h4>
                <ul>
                    <li><a href="<?php echo baseUrl('rooms.php'); ?>">Lihat Kamar</a></li>
                    <li><a href="<?php echo baseUrl('booking.php'); ?>">Pesan Sekarang</a></li>
                    <li><a href="<?php echo baseUrl('faqs.php'); ?>">FAQ</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4>Ikuti Kami</h4>
                <div class="social-links">
                    <a href="#" title="Instagram"><i data-feather="instagram"></i></a>
                    <a href="#" title="Facebook"><i data-feather="facebook"></i></a>
                    <a href="#" title="WhatsApp"><i data-feather="phone-alt"></i></a>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlize(BUSINESS_NAME); ?>. All Rights Reserved.</p>
        </div>
    </footer>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script src="<?php echo assetUrl('js/main.js'); ?>"></script>
    
    <?php if (isset($additionalJS)): foreach ((array)$additionalJS as $js): ?>
    <script src="<?php echo assetUrl($js); ?>"></script>
    <?php endforeach; endif; ?>
    
    <script>
        // Initialize Feather icons
        feather.replace();
    </script>
</body>
</html>
