<?php
session_start();
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="welcome-banner">
        <h3>About CareNest</h3>
        <p class="mb-0">CareNest connects seniors with trusted neighbors (Pals) and supportive family proxies to make daily life safer and easier.</p>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <h4 class="card-header-title"><i class="fa-solid fa-hand-holding-heart me-2"></i>Our Mission</h4>
                <p class="mb-0 text-muted">Create a warm, secure assistance network for seniors—simple to use, high trust, and community-powered.</p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <h4 class="card-header-title"><i class="fa-solid fa-shield-heart me-2"></i>Safety & Trust</h4>
                <ul class="mb-0">
                    <li>Role-based access (Senior / Proxy / Pal / Admin)</li>
                    <li>Prepared statements (PDO) to prevent injection</li>
                    <li>Password hashing (bcrypt)</li>
                    <li>Real-time notifications for important events</li>
                </ul>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <h4 class="card-header-title"><i class="fa-solid fa-circle-nodes me-2"></i>How It Works</h4>
                <div class="visit-item">
                    <div class="visit-avatar"><i class="fa-solid fa-user"></i></div>
                    <div class="visit-info">
                        <div class="visit-name">Senior / Proxy</div>
                        <div class="visit-details">Book services using SilverPoints</div>
                    </div>
                </div>
                <div class="visit-item">
                    <div class="visit-avatar"><i class="fa-solid fa-people-carry-box"></i></div>
                    <div class="visit-info">
                        <div class="visit-name">Pal</div>
                        <div class="visit-details">Accept, deliver, complete services</div>
                    </div>
                </div>
                <div class="visit-item">
                    <div class="visit-avatar"><i class="fa-solid fa-user-gear"></i></div>
                    <div class="visit-info">
                        <div class="visit-name">Admin</div>
                        <div class="visit-details">Approve accounts, monitor safety & finance</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <h4 class="card-header-title"><i class="fa-solid fa-address-card me-2"></i>Contact</h4>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="profile-info-row">
                    <span class="profile-info-label"><i class="fa-solid fa-envelope"></i> Email</span>
                    <span class="profile-info-value"><a href="mailto:support@carenest.local">support@carenest.local</a></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label"><i class="fa-solid fa-phone"></i> Phone</span>
                    <span class="profile-info-value">+20 100 000 0000</span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label"><i class="fa-solid fa-location-dot"></i> Location</span>
                    <span class="profile-info-value">Community-based (Demo)</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-primary" href="/senior_care/views/auth/register.php"><i class="fa-solid fa-user-plus me-2"></i>Create Account</a>
                    <a class="btn btn-secondary-outline" href="/senior_care/views/auth/login.php"><i class="fa-solid fa-right-to-bracket me-2"></i>Login</a>
                </div>
                <p class="text-muted mt-3 mb-0">This is a local project demo built with PHP (MVC), MySQL, Bootstrap 5, and a senior-friendly UI.</p>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
