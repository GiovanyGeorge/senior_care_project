<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Admin
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getDashboardStats(): array
    {
        $users = (int)$this->db->query('SELECT COUNT(*) AS total FROM users')->fetch()['total'];
        $todayVisits = (int)$this->db->query('SELECT COUNT(*) AS total FROM visit_requests WHERE DATE(scheduled_start)=CURDATE()')->fetch()['total'];
        $pendingApprovals = (int)$this->db->query('SELECT COUNT(*) AS total FROM users WHERE is_active = 0')->fetch()['total'];
        $openEmergencies = (int)$this->db->query('SELECT COUNT(*) AS total FROM emergency_threads WHERE status="Open"')->fetch()['total'];
        $platformRevenue = (float)$this->db->query(
            "SELECT COALESCE(SUM(l.points_amount), 0) AS total
             FROM silverpoints_ledger l
             JOIN users u ON u.User_ID = l.User_ID
             WHERE u.role_type = 'admin' AND l.entry_type = 'Credit'"
        )->fetch()['total'];

        return [
            'users' => $users,
            'today_visits' => $todayVisits,
            'pending_approvals' => $pendingApprovals,
            'open_emergencies' => $openEmergencies,
            'platform_revenue' => $platformRevenue,
        ];
    }

    public function getPendingUsers(): array
    {
        $stmt = $this->db->query(
            'SELECT User_ID, Fname, Lname, email, role_type, created_at
             FROM users
             WHERE is_active = 0
             ORDER BY created_at DESC'
        );
        return $stmt->fetchAll();
    }
}
