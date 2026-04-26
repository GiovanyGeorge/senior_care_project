<?php
session_start();
require_once __DIR__ . '/../layouts/header.php';
$message = $_GET['message'] ?? 'Something went wrong.';
?>
<div class="container py-5">
    <div class="card">
        <h3 class="text-danger">Error</h3>
        <p><?= htmlspecialchars($message) ?></p>
        <a class="btn btn-primary" href="/senior_care/index.php">Back Home</a>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
