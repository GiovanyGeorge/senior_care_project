<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /senior_care/index.php');
    exit();
}
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card text-center p-4">
                <h1 class="navbar-brand mb-2">CareNest</h1>
                <h3 class="mb-4">Welcome Back to CareNest</h3>
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                <form action="/senior_care/controllers/AuthController.php?action=login" method="POST">
                    <div class="mb-3 text-start">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-4 text-start">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input id="login-password" type="password" name="password" class="form-control" required>
                            <button class="btn btn-outline-secondary" type="button" id="toggle-login-password" aria-label="Show password">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Login</button>
                </form>
                <p class="mt-4 mb-0">No account? <a href="/senior_care/views/auth/register.php">Register</a></p>
            </div>
        </div>
    </div>
    <div class="row justify-content-center mt-2">
        <div class="col-lg-8">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="quick-action-card">
                        <div class="quick-action-icon"><i class="fa-solid fa-user-shield"></i></div>
                        <div class="quick-action-label">Safe & Verified</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="quick-action-card">
                        <div class="quick-action-icon"><i class="fa-solid fa-bell"></i></div>
                        <div class="quick-action-label">Instant Alerts</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="quick-action-card">
                        <div class="quick-action-icon"><i class="fa-solid fa-hand-holding-heart"></i></div>
                        <div class="quick-action-label">Senior Friendly</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
<script>
    (function () {
        const input = document.getElementById('login-password');
        const button = document.getElementById('toggle-login-password');
        if (!input || !button) return;
        button.addEventListener('click', function () {
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            this.innerHTML = isHidden
                ? '<i class="fa-solid fa-eye-slash"></i>'
                : '<i class="fa-solid fa-eye"></i>';
        });
    })();
</script>
