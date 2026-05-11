<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Admin']);
require_once __DIR__ . '/../../models/Admin.php';

$services = (new Admin())->getAllServices();

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <h3 class="mb-3">Manage Services</h3>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="border rounded p-3 mb-3" style="background: var(--bg-secondary);">
            <h5 class="mb-3">Create Service</h5>
            <form method="POST" action="/senior_care/controllers/AdminController.php?action=createService">
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label">Service Name</label>
                        <input class="form-control" name="category_name" placeholder="Service name" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Points Cost</label>
                        <input class="form-control" type="number" min="1" name="base_points_cost" placeholder="Points" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Max Hours</label>
                        <input class="form-control" type="number" min="1" max="24" name="max_duration_hours" placeholder="Hours" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Disabled</option>
                        </select>
                    </div>
                    <div class="col-md-12 d-flex align-items-end">
                        <button class="btn btn-primary w-100" type="submit">Add</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Service Name</th>
                    <th>Points Cost</th>
                    <th>Max Hours</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($services)): ?>
                    <tr><td colspan="6">No services found.</td></tr>
                <?php else: ?>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td>#<?= (int)$service['category_ID'] ?></td>
                            <td><?= htmlspecialchars((string)$service['category_name']) ?></td>
                            <td><?= (int)$service['base_points_cost'] ?></td>
                            <td><?= (int)($service['max_duration_hours'] ?? 4) ?> h</td>
                            <td>
                                <?php if ((int)$service['is_active'] === 1): ?>
                                    <span class="badge text-bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-edit-toggle="edit-service-<?= (int)$service['category_ID'] ?>">Edit</button>
                                    <form method="POST" action="/senior_care/controllers/AdminController.php?action=deleteService" onsubmit="return confirm('Delete this service?');">
                                        <input type="hidden" name="category_id" value="<?= (int)$service['category_ID'] ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr id="edit-service-<?= (int)$service['category_ID'] ?>" style="display:none;">
                            <td colspan="6">
                                <div class="border rounded p-3" style="background: var(--bg-secondary);">
                                    <h6 class="mb-2">Edit Service #<?= (int)$service['category_ID'] ?></h6>
                                    <form method="POST" action="/senior_care/controllers/AdminController.php?action=updateService">
                                        <input type="hidden" name="category_id" value="<?= (int)$service['category_ID'] ?>">
                                        <div class="row g-2">
                                            <div class="col-md-5">
                                                <label class="form-label">Service Name</label>
                                                <input class="form-control form-control-sm" name="category_name" value="<?= htmlspecialchars((string)$service['category_name']) ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Points Cost</label>
                                                <input class="form-control form-control-sm" type="number" min="1" name="base_points_cost" value="<?= (int)$service['base_points_cost'] ?>" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Max Hours</label>
                                                <input class="form-control form-control-sm" type="number" min="1" max="24" name="max_duration_hours" value="<?= (int)($service['max_duration_hours'] ?? 4) ?>" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Status</label>
                                                <select class="form-select form-select-sm" name="is_active">
                                                    <option value="1" <?= (int)$service['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
                                                    <option value="0" <?= (int)$service['is_active'] === 0 ? 'selected' : '' ?>>Disabled</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button class="btn btn-sm btn-primary w-100" type="submit">Save</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    (function () {
        const buttons = document.querySelectorAll('[data-edit-toggle]');
        buttons.forEach((btn) => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-edit-toggle');
                const row = document.getElementById(id);
                if (!row) return;
                const hidden = row.style.display === 'none' || row.style.display === '';
                row.style.display = hidden ? 'table-row' : 'none';
                this.textContent = hidden ? 'Close' : 'Edit';
            });
        });
    })();
</script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
