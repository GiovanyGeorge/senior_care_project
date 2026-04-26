<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Senior
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getDashboardData(int $userId): array
    {
        $visitsStmt = $this->db->prepare(
            "SELECT vr.*, sc.category_name AS service_name, u.Fname AS pal_first_name, u.Lname AS pal_last_name
             FROM visit_requests vr
             LEFT JOIN service_categories sc ON sc.category_ID = vr.category_ID
             LEFT JOIN users u ON u.User_ID = vr.pal_ID
             WHERE vr.senior_ID = :user_id
             AND vr.status IN ('Pending', 'Accepted', 'En_Route', 'Live')
             ORDER BY vr.scheduled_start ASC
             LIMIT 5"
        );
        $visitsStmt->execute(['user_id' => $userId]);

        return [
            'upcoming_visits' => $visitsStmt->fetchAll(),
        ];
    }
}
