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

    public function getAllServices(): array
    {
        $stmt = $this->db->query(
            'SELECT category_ID, category_name, base_points_cost, max_duration_hours, is_active
             FROM service_categories
             ORDER BY category_ID DESC'
        );
        return $stmt->fetchAll();
    }

    public function createService(string $name, int $cost, int $maxDurationHours, int $isActive): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO service_categories (category_name, base_points_cost, max_duration_hours, is_active)
             VALUES (:name, :cost, :max_duration_hours, :is_active)'
        );
        return $stmt->execute([
            'name' => $name,
            'cost' => $cost,
            'max_duration_hours' => $maxDurationHours,
            'is_active' => $isActive,
        ]);
    }

    public function updateService(int $categoryId, string $name, int $cost, int $maxDurationHours, int $isActive): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE service_categories
             SET category_name = :name, base_points_cost = :cost, max_duration_hours = :max_duration_hours, is_active = :is_active
             WHERE category_ID = :category_id'
        );
        return $stmt->execute([
            'name' => $name,
            'cost' => $cost,
            'max_duration_hours' => $maxDurationHours,
            'is_active' => $isActive,
            'category_id' => $categoryId,
        ]);
    }

    public function isServiceUsedInVisits(int $categoryId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM visit_requests WHERE category_ID = :category_id LIMIT 1');
        $stmt->execute(['category_id' => $categoryId]);
        return (bool)$stmt->fetchColumn();
    }

    public function deleteService(int $categoryId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM service_categories WHERE category_ID = :category_id');
        return $stmt->execute(['category_id' => $categoryId]);
    }
}
