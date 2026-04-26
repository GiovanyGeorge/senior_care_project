<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Report
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create(int $visitId, int $palUserId, string $summary, string $reportText): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO visit_reports (visit_ID, pal_user_ID, summary, report_text, created_at)
             VALUES (:visit_id, :pal_user_id, :summary, :report_text, NOW())'
        );
        $stmt->execute([
            'visit_id' => $visitId,
            'pal_user_id' => $palUserId,
            'summary' => $summary !== '' ? $summary : null,
            'report_text' => $reportText,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getAll(int $limit = 200): array
    {
        $stmt = $this->db->prepare(
            "SELECT vr.report_ID, vr.visit_ID, vr.pal_user_ID, vr.summary, vr.report_text, vr.created_at,
                    u.Fname AS pal_fname, u.Lname AS pal_lname,
                    sc.category_name, v.scheduled_start, v.status,
                    su.Fname AS senior_fname, su.Lname AS senior_lname
             FROM visit_reports vr
             JOIN users u ON u.User_ID = vr.pal_user_ID
             JOIN visit_requests v ON v.visit_ID = vr.visit_ID
             JOIN service_categories sc ON sc.category_ID = v.category_ID
             JOIN senior_profiles sp ON sp.senior_ID = v.senior_ID
             JOIN users su ON su.User_ID = sp.User_ID
             ORDER BY vr.report_ID DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

