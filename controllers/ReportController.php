<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../models/Report.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../config/database.php';

class ReportController
{
    public function submit(): void
    {
        requireRole(['Pal']);
        $palUserId = (int)($_SESSION['user_id'] ?? 0);
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $summary = trim((string)($_POST['summary'] ?? ''));
        $reportText = trim((string)($_POST['report_text'] ?? ''));

        if ($visitId <= 0 || $reportText === '') {
            $_SESSION['error'] = 'Visit and report text are required.';
            header('Location: /senior_care/views/pal/report_visit.php?visit_id=' . $visitId);
            exit();
        }

        try {
            $reportId = (new Report())->create($visitId, $palUserId, $summary, $reportText);
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Unable to submit report. Make sure the table `visit_reports` exists (run sql/create_visit_reports.sql).';
            header('Location: /senior_care/views/pal/report_visit.php?visit_id=' . $visitId);
            exit();
        }

        // Notify an admin.
        $db = Database::getInstance()->getConnection();
        $adminId = (int)($db->query("SELECT User_ID FROM users WHERE role_type = 'admin' ORDER BY User_ID ASC LIMIT 1")->fetchColumn() ?: 0);
        if ($adminId > 0) {
            (new Notification())->create($adminId, 'New Visit Report', 'A pal submitted a visit report. Report #' . $reportId . ' (Visit #' . $visitId . ').');
        }

        $_SESSION['success'] = 'Report submitted successfully.';
        header('Location: /senior_care/views/pal/schedule.php');
        exit();
    }
}

if (($_GET['action'] ?? '') === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new ReportController())->submit();
}

