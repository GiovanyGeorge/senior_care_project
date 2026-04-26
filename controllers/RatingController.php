<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Rating.php';
require_once __DIR__ . '/../models/Notification.php';

class RatingController
{
    public function submit(): void
    {
        requireRole(['Senior', 'FamilyProxy']);
        $role = $_SESSION['role'] ?? 'Senior';
        $actorUserId = (int)($_SESSION['user_id'] ?? 0);
        $seniorUserId = $role === 'FamilyProxy'
            ? (int)($_SESSION['proxy_senior_user_id'] ?? 0)
            : $actorUserId;

        $visitId = (int)($_POST['visit_id'] ?? 0);
        $score = (float)($_POST['rating_score'] ?? 0);
        $comment = trim((string)($_POST['comment'] ?? ''));
        $returnTo = '/senior_care/views/senior/visit_history.php';

        if ($visitId <= 0 || $score < 1 || $score > 5) {
            $_SESSION['error'] = 'Invalid rating submission.';
            header('Location: ' . $returnTo);
            exit();
        }

        $db = Database::getInstance()->getConnection();
        $seniorIdStmt = $db->prepare('SELECT senior_ID FROM senior_profiles WHERE User_ID = :user_id LIMIT 1');
        $seniorIdStmt->execute(['user_id' => $seniorUserId]);
        $seniorId = (int)($seniorIdStmt->fetchColumn() ?: 0);
        if ($seniorId <= 0) {
            $_SESSION['error'] = 'Senior profile missing.';
            header('Location: ' . $returnTo);
            exit();
        }

        $visitStmt = $db->prepare(
            "SELECT vr.visit_ID, vr.status, vr.pal_ID
             FROM visit_requests vr
             WHERE vr.visit_ID = :visit_id AND vr.senior_ID = :senior_id
             LIMIT 1"
        );
        $visitStmt->execute(['visit_id' => $visitId, 'senior_id' => $seniorId]);
        $visit = $visitStmt->fetch(PDO::FETCH_ASSOC);
        if (!$visit) {
            $_SESSION['error'] = 'You cannot rate this visit.';
            header('Location: ' . $returnTo);
            exit();
        }
        if (($visit['status'] ?? '') !== 'Completed') {
            $_SESSION['error'] = 'You can rate only completed visits.';
            header('Location: ' . $returnTo);
            exit();
        }

        $palId = (int)($visit['pal_ID'] ?? 0);
        if ($palId <= 0) {
            $_SESSION['error'] = 'No pal assigned to this visit.';
            header('Location: ' . $returnTo);
            exit();
        }

        $ratingModel = new Rating();
        if ($ratingModel->ratingExistsForVisit($visitId)) {
            $_SESSION['error'] = 'This visit was already rated.';
            header('Location: ' . $returnTo);
            exit();
        }

        try {
            $db->beginTransaction();
            $ratingModel->create($visitId, $seniorId, $palId, round($score, 2), $comment);
            $ratingModel->updatePalAverage($palId);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error'] = 'Unable to submit rating.';
            header('Location: ' . $returnTo);
            exit();
        }

        // Notify pal
        $palUserStmt = $db->prepare('SELECT User_ID FROM pal_profiles WHERE pal_ID = :pal_id LIMIT 1');
        $palUserStmt->execute(['pal_id' => $palId]);
        $palUserId = (int)($palUserStmt->fetchColumn() ?: 0);
        if ($palUserId > 0) {
            (new Notification())->create($palUserId, 'New Rating', 'A senior rated your service. Your average rating has been updated.');
        }

        $_SESSION['success'] = 'Rating submitted successfully.';
        header('Location: ' . $returnTo);
        exit();
    }
}

if (($_GET['action'] ?? '') === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new RatingController())->submit();
}

