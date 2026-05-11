<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../patterns/State/VisitStateRegistry.php';

class Visit
{
    private PDO $db;
    private const DEFAULT_MAX_PAL_DAILY_HOURS = 8;
    private const DEFAULT_SERVICE_MAX_DURATION_HOURS = 4;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getServiceCategories(): array
    {
        $stmt = $this->db->query('SELECT category_ID, category_name, base_points_cost, max_duration_hours FROM service_categories WHERE is_active = 1 ORDER BY category_name ASC');
        return $stmt->fetchAll();
    }

    public function getPointsCost(int $categoryId): int
    {
        $stmt = $this->db->prepare('SELECT base_points_cost FROM service_categories WHERE category_ID = :id LIMIT 1');
        $stmt->execute(['id' => $categoryId]);
        $row = $stmt->fetch();
        return (int)($row['base_points_cost'] ?? 0);
    }

    public function getServiceMaxDurationHours(int $categoryId): int
    {
        try {
            $stmt = $this->db->prepare('SELECT max_duration_hours FROM service_categories WHERE category_ID = :id LIMIT 1');
            $stmt->execute(['id' => $categoryId]);
            $value = $stmt->fetchColumn();
            $hours = (int)($value !== false ? $value : self::DEFAULT_SERVICE_MAX_DURATION_HOURS);
            return $hours > 0 ? $hours : self::DEFAULT_SERVICE_MAX_DURATION_HOURS;
        } catch (Throwable $e) {
            return self::DEFAULT_SERVICE_MAX_DURATION_HOURS;
        }
    }

    public function getAvailablePals(): array
    {
        $stmt = $this->db->query(
            "SELECT u.User_ID AS id, u.Fname AS first_name, u.Lname AS last_name, pp.travel_radius_km AS radius_km, pp.rating_avg AS avg_rating
             FROM users u
             JOIN pal_profiles pp ON pp.User_ID = u.User_ID
             WHERE u.role_type = 'pal' AND u.is_active = 1 AND pp.verification_status = 'Approved'
             ORDER BY pp.rating_avg DESC, u.Fname ASC"
        );
        return $stmt->fetchAll();
    }

    public function createVisit(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO visit_requests
             (senior_ID, pal_ID, proxy_ID, category_ID, request_type, scheduled_start, scheduled_end, task_details, points_reserved, status, created_at)
             VALUES (:senior_user_id, :pal_user_id, :proxy_id, :service_category_id, :request_type, :scheduled_start, :scheduled_end, :task_description, :points_reserved, 'Pending', NOW())"
        );
        $start = new DateTime($data['scheduled_at']);
        $durationHours = (float)($data['duration_hours'] ?? 1);
        if ($durationHours <= 0) {
            $durationHours = 1;
        }
        $minutes = (int)round($durationHours * 60);
        $end = clone $start;
        $end->modify('+' . $minutes . ' minutes');
        $stmt->execute([
            'senior_user_id' => $data['senior_user_id'],
            'pal_user_id' => $data['pal_user_id'],
            'proxy_id' => $data['proxy_id'] ?? null,
            'service_category_id' => $data['service_category_id'],
            'request_type' => $data['request_type'] ?? 'Direct',
            'scheduled_start' => $start->format('Y-m-d H:i:s'),
            'scheduled_end' => $end->format('Y-m-d H:i:s'),
            'task_description' => $data['task_description'],
            'points_reserved' => $data['points_reserved'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getStatus(int $visitId): ?string
    {
        $stmt = $this->db->prepare('SELECT status FROM visit_requests WHERE visit_ID = :id LIMIT 1');
        $stmt->execute(['id' => $visitId]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return null;
        }

        return (string)$value;
    }

    /**
     * State pattern: only allowed lifecycle transitions update the row.
     */
    public function applyStatusTransition(int $visitId, string $newStatusRaw): bool
    {
        $current = $this->getStatus($visitId);
        if ($current === null) {
            return false;
        }
        $newStatus = VisitStateRegistry::normalize($newStatusRaw);
        if (!VisitStateRegistry::canTransition($current, $newStatus)) {
            return false;
        }

        return $this->setStatus($visitId, $newStatus);
    }

    public function setStatus(int $visitId, string $status): bool
    {
        $allowed = ['Pending', 'Accepted', 'En_Route', 'Live', 'Completed', 'Rated', 'Rejected', 'Cancelled'];
        $status = VisitStateRegistry::normalize($status);
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE visit_requests SET status = :status WHERE visit_ID = :id');
        return $stmt->execute(['status' => $status, 'id' => $visitId]);
    }

    public function getSeniorIdByUserId(int $userId): ?int
    {
        $stmt = $this->db->prepare('SELECT senior_ID FROM senior_profiles WHERE User_ID = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (int)$value : null;
    }

    public function getPalUserIdByPalId(int $palId): ?int
    {
        $stmt = $this->db->prepare('SELECT User_ID FROM pal_profiles WHERE pal_ID = :pal_id LIMIT 1');
        $stmt->execute(['pal_id' => $palId]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (int)$value : null;
    }

    public function isVisitAssignedToPalUser(int $visitId, int $palUserId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM visit_requests vr
             JOIN pal_profiles pp ON pp.pal_ID = vr.pal_ID
             WHERE vr.visit_ID = :visit_id
               AND pp.User_ID = :pal_user_id
             LIMIT 1'
        );
        $stmt->execute([
            'visit_id' => $visitId,
            'pal_user_id' => $palUserId,
        ]);
        return (bool)$stmt->fetchColumn();
    }

    public function getVisitSettlementData(int $visitId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT vr.visit_ID, vr.status, vr.points_reserved, vr.points_paid, vr.senior_ID, vr.pal_ID,
                    sp.User_ID AS senior_user_id, pp.User_ID AS pal_user_id
             FROM visit_requests vr
             JOIN senior_profiles sp ON sp.senior_ID = vr.senior_ID
             JOIN pal_profiles pp ON pp.pal_ID = vr.pal_ID
             WHERE vr.visit_ID = :visit_id
             LIMIT 1"
        );
        $stmt->execute(['visit_id' => $visitId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function isVisitSettled(int $visitId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1
             FROM silverpoints_ledger
             WHERE visit_ID = :visit_id
               AND entry_type = 'Credit'
               AND description LIKE 'Visit earning credit to pal%'
             LIMIT 1"
        );
        $stmt->execute(['visit_id' => $visitId]);
        return (bool)$stmt->fetchColumn();
    }

    public function markVisitPaid(int $visitId, float $pointsPaid): bool
    {
        $stmt = $this->db->prepare('UPDATE visit_requests SET points_paid = :points_paid WHERE visit_ID = :visit_id');
        return $stmt->execute([
            'points_paid' => $pointsPaid,
            'visit_id' => $visitId,
        ]);
    }

    public function getVisitForCancel(int $visitId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT vr.visit_ID, vr.status, vr.scheduled_start, vr.created_at, vr.points_reserved, vr.senior_ID, vr.pal_ID,
                    vr.scheduled_end,
                    sp.User_ID AS senior_user_id, pp.User_ID AS pal_user_id
             FROM visit_requests vr
             JOIN senior_profiles sp ON sp.senior_ID = vr.senior_ID
             LEFT JOIN pal_profiles pp ON pp.pal_ID = vr.pal_ID
             WHERE vr.visit_ID = :visit_id
             LIMIT 1"
        );
        $stmt->execute(['visit_id' => $visitId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function isVisitOwnedBySeniorUser(int $visitId, int $seniorUserId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM visit_requests vr
             JOIN senior_profiles sp ON sp.senior_ID = vr.senior_ID
             WHERE vr.visit_ID = :visit_id
               AND sp.User_ID = :senior_user_id
             LIMIT 1'
        );
        $stmt->execute([
            'visit_id' => $visitId,
            'senior_user_id' => $seniorUserId,
        ]);
        return (bool)$stmt->fetchColumn();
    }

    public function cancelVisit(int $visitId, string $reason): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE visit_requests
             SET status = 'Cancelled',
                 cancelled_at = NOW(),
                 cancellation_reason = :reason
             WHERE visit_ID = :visit_id
               AND status NOT IN ('Completed','Cancelled')"
        );
        return $stmt->execute([
            'reason' => $reason,
            'visit_id' => $visitId,
        ]);
    }

    public function getPalDailyHours(int $palId, string $dateYmd, ?int $excludeVisitId = null): float
    {
        $sql =
            "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, scheduled_start, scheduled_end)), 0) / 60 AS total_hours
             FROM visit_requests
             WHERE pal_ID = :pal_id
               AND DATE(scheduled_start) = :work_date
               AND status NOT IN ('Cancelled', 'Rejected')";
        $params = [
            'pal_id' => $palId,
            'work_date' => $dateYmd,
        ];

        if ($excludeVisitId !== null && $excludeVisitId > 0) {
            $sql .= ' AND visit_ID <> :exclude_visit_id';
            $params['exclude_visit_id'] = $excludeVisitId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    public function getPalMaxDailyHours(int $palId): int
    {
        try {
            $stmt = $this->db->prepare('SELECT max_daily_hours FROM pal_profiles WHERE pal_ID = :pal_id LIMIT 1');
            $stmt->execute(['pal_id' => $palId]);
            $value = $stmt->fetchColumn();
            $hours = (int)($value !== false ? $value : self::DEFAULT_MAX_PAL_DAILY_HOURS);
            return $hours > 0 ? $hours : self::DEFAULT_MAX_PAL_DAILY_HOURS;
        } catch (Throwable $e) {
            // Fallback for databases that do not yet have max_daily_hours column.
            return self::DEFAULT_MAX_PAL_DAILY_HOURS;
        }
    }

    public function canPalTakeVisitOnDate(int $palId, string $scheduledAt, float $visitHours = 1.0, ?int $excludeVisitId = null): bool
    {
        try {
            $date = (new DateTime($scheduledAt))->format('Y-m-d');
        } catch (Throwable $e) {
            return false;
        }

        $currentHours = $this->getPalDailyHours($palId, $date, $excludeVisitId);
        $limit = $this->getPalMaxDailyHours($palId);
        return ($currentHours + $visitHours) <= $limit;
    }
}
