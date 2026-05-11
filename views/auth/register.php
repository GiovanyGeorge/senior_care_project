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
                            <div class="input-group">
                                <input id="register-password" type="password" name="password" class="form-control" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggle-register-password" aria-label="Show password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" min="1" max="120" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">National ID</label>
                            <input type="text" name="national_id" class="form-control" maxlength="30" required>
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
                        <div class="col-12" id="senior-health-block">
                            <div class="card" style="background: var(--bg-secondary);">
                                <h5 class="mb-2">Senior Details</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Address</label>
                                        <input type="text" class="form-control" name="address" placeholder="Home address">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Comfort Profile</label>
                                        <input type="text" class="form-control" name="comfort_profile" placeholder="Preferences, routines, mobility comfort">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Emergency Contact Name</label>
                                        <input type="text" class="form-control" name="emergency_contact_name" placeholder="Contact person">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Emergency Contact Phone</label>
                                        <input type="text" class="form-control" name="emergency_contact_phone" placeholder="Emergency phone">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Medical Notes</label>
                                        <textarea class="form-control" name="medical_notes" rows="3" placeholder="Chronic conditions, medications, mobility notes..."></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Allergies</label>
                                        <textarea class="form-control" name="allergies" rows="3" placeholder="Food, medicine, environmental allergies..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12" id="pal-skills-block" style="display:none;">
                            <div class="card" style="background: var(--bg-secondary);">
                                <h5 class="mb-2">Pal Skills & Certificate</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Skills</label>
                                        <textarea class="form-control" name="pal_skills" rows="3" placeholder="Companionship, medication reminder, mobility support..."></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Skill Badge Name</label>
                                        <input type="text" name="pal_badge_name" class="form-control" placeholder="e.g. First Aid Certified">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Certificate File</label>
                                        <input type="file" name="pal_certificate" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                                    </div>
                                </div>
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
        const seniorHealthBlock = document.getElementById('senior-health-block');
        const palBlock = document.getElementById('pal-skills-block');
        const registerPassword = document.getElementById('register-password');
        const toggleRegisterPassword = document.getElementById('toggle-register-password');

        function sync() {
            const roleValue = role ? role.value : 'Senior';
            const isProxy = roleValue === 'FamilyProxy';
            const isSenior = roleValue === 'Senior';
            const isPal = roleValue === 'Pal';
            if (block) block.style.display = isProxy ? '' : 'none';
            if (select) select.required = !!isProxy;
            if (seniorHealthBlock) seniorHealthBlock.style.display = isSenior ? '' : 'none';
            if (palBlock) palBlock.style.display = isPal ? '' : 'none';
        }

        if (role) role.addEventListener('change', sync);
        sync();

        if (registerPassword && toggleRegisterPassword) {
            toggleRegisterPassword.addEventListener('click', function () {
                const isHidden = registerPassword.type === 'password';
                registerPassword.type = isHidden ? 'text' : 'password';
                this.innerHTML = isHidden
                    ? '<i class="fa-solid fa-eye-slash"></i>'
                    : '<i class="fa-solid fa-eye"></i>';
            });
        }
    })();
</script>
