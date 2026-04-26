<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /senior_care/index.php');
    exit();
}
require_once __DIR__ . '/../../models/User.php';
$seniors = (new User())->getActiveSeniors();
require_once __DIR__ . '/../layouts/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <h2 class="mb-4 text-center">Create Your CareNest Account</h2>
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                <form action="/senior_care/controllers/AuthController.php?action=register" method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" id="role" class="form-select" required>
                                <option value="Senior">Senior</option>
                                <option value="Pal">Pal</option>
                                <option value="FamilyProxy">Family Proxy</option>
                            </select>
                        </div>
                        <div class="col-12" id="proxy-senior-block" style="display:none;">
                            <div class="card" style="background: var(--bg-secondary);">
                                <h5 class="mb-2">Who are you helping?</h5>
                                <p class="text-muted mb-3">As a Family Proxy, you’ll book visits and support a specific senior.</p>
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label">Select Senior</label>
                                        <select name="linked_senior_user_id" id="linked_senior_user_id" class="form-select">
                                            <option value="">Select a Senior</option>
                                            <?php foreach ($seniors as $s): ?>
                                                <option value="<?= (int)$s['User_ID'] ?>">
                                                    <?= htmlspecialchars(trim((string)$s['Fname'] . ' ' . (string)$s['Lname'])) ?> — <?= htmlspecialchars((string)$s['email']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Relationship</label>
                                        <select name="relationship_type" class="form-select">
                                            <option value="Family">Family</option>
                                            <option value="Caregiver">Caregiver</option>
                                            <option value="Neighbor">Neighbor</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2">If the senior isn’t listed yet, ask them to register first (or admin can add them).</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" name="profile_photo" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 mt-4" type="submit">Register</button>
                </form>
                <p class="mt-3 mb-0 text-muted">Your account will be activated after admin approval.</p>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
<script>
    (function () {
        const role = document.getElementById('role');
        const block = document.getElementById('proxy-senior-block');
        const select = document.getElementById('linked_senior_user_id');

        function sync() {
            const isProxy = role && role.value === 'FamilyProxy';
            if (block) block.style.display = isProxy ? '' : 'none';
            if (select) select.required = !!isProxy;
        }

        if (role) role.addEventListener('change', sync);
        sync();
    })();
</script>
