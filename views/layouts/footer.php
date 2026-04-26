<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/senior_care/public/js/main.js"></script>
<?php if (!empty($GLOBALS['layout_has_main_wrapper'])): ?>
</div>
<?php endif; ?>

<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="footer-brand">CareNest</div>
                <div class="footer-text">Senior Care & Neighborly Assistance Network — safer, warmer communities.</div>
                <div class="footer-text mt-2"><i class="fa-solid fa-shield-heart me-2"></i>Trusted local helpers • Secure support</div>
            </div>
            <div class="col-lg-4">
                <div class="footer-title">Quick Links</div>
                <a class="footer-link d-block ms-0" href="/senior_care/views/shared/about.php"><i class="fa-solid fa-circle-info me-2"></i>About CareNest</a>
                <a class="footer-link d-block ms-0" href="/senior_care/views/auth/register.php"><i class="fa-solid fa-user-plus me-2"></i>Create Account</a>
                <a class="footer-link d-block ms-0" href="/senior_care/views/auth/login.php"><i class="fa-solid fa-right-to-bracket me-2"></i>Login</a>
            </div>
            <div class="col-lg-4">
                <div class="footer-title">Contact</div>
                <a class="footer-link d-block ms-0" href="mailto:support@carenest.local"><i class="fa-solid fa-envelope me-2"></i>support@carenest.local</a>
                <span class="footer-link d-block ms-0"><i class="fa-solid fa-phone me-2"></i>+20 100 000 0000</span>
                <span class="footer-link d-block ms-0"><i class="fa-solid fa-location-dot me-2"></i>Community-based (Demo)</span>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© <?= date('Y') ?> CareNest. All rights reserved.</span>
            <span class="footer-sep">•</span>
            <span><i class="fa-solid fa-lock me-2"></i>Privacy-first design</span>
            <span class="footer-sep">•</span>
            <span><i class="fa-solid fa-server me-2"></i>Local demo on XAMPP</span>
        </div>
    </div>
</footer>
</body>
</html>
