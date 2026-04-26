<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Pal
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getPendingRequests(int $palUserId): array
    {
        $stmt = $this->db->prepare(
            "SELECT vr.*, sc.category_name AS service_name, su.Fname AS senior_first_name, su.Lname AS senior_last_name,
                    hr.medical_notes, hr.allergies
             FROM visit_requests vr
             JOIN service_categories sc ON sc.category_ID = vr.category_ID
             JOIN pal_profiles pp ON pp.pal_ID = vr.pal_ID
             JOIN senior_profiles sp ON sp.senior_ID = vr.senior_ID
             JOIN users su ON su.User_ID = sp.User_ID
             LEFT JOIN health_records hr ON hr.senior_ID = sp.senior_ID
             WHERE pp.User_ID = :pal_user_id AND vr.status = 'Pending'
             ORDER BY vr.created_at DESC"
        );
        $stmt->execute(['pal_user_id' => $palUserId]);
        return $stmt->fetchAll();
    }

    public function getTodaySchedule(int $palUserId): array
    {
        $stmt = $this->db->prepare(
            "SELECT vr.*, su.Fname AS senior_first_name, su.Lname AS senior_last_name,
                    hr.medical_notes, hr.allergies
             FROM visit_requests vr
             JOIN pal_profiles pp ON pp.pal_ID = vr.pal_ID
             JOIN senior_profiles sp ON sp.senior_ID = vr.senior_ID
             JOIN users su ON su.User_ID = sp.User_ID
             LEFT JOIN health_records hr ON hr.senior_ID = sp.senior_ID
             WHERE pp.User_ID = :pal_user_id
             AND DATE(vr.scheduled_start) = CURDATE()
             ORDER BY vr.scheduled_start ASC"
        );
        $stmt->execute(['pal_user_id' => $palUserId]);
        return $stmt->fetchAll();
    }

    public function getUpcomingSchedule(int $palUserId): array
    {
        $stmt = $this->db->prepare(
            "SELECT vr.*, sc.category_name AS service_name, su.Fname AS senior_first_name, su.Lname AS senior_last_name,
                    hr.medical_notes, hr.allergies
             FROM visit_requests vr
             JOIN pal_profiles pp ON pp.pal_ID = vr.pal_ID
             JOIN senior_profiles sp ON sp.senior_ID = vr.senior_ID
             JOIN users su ON su.User_ID = sp.User_ID
             JOIN service_categories sc ON sc.category_ID = vr.category_ID
             LEFT JOIN health_records hr ON hr.senior_ID = sp.senior_ID
             WHERE pp.User_ID = :pal_user_id
             AND vr.status IN ('Accepted', 'En_Route', 'Live', 'Pending', 'Completed')
             AND vr.scheduled_start >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
             ORDER BY vr.scheduled_start ASC"
        );
        $stmt->execute(['pal_user_id' => $palUserId]);
        return $stmt->fetchAll();
    }

    public function getPalIdByUserId(int $palUserId): ?int
    {
        $stmt = $this->db->prepare('SELECT pal_ID FROM pal_profiles WHERE User_ID = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $palUserId]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (int)$value : null;
    }

    public function upsertCashoutDestination(
        int $palId,
        string $destinationType,
        string $providerName,
        string $accountIdentifier
    ): int {
        $stmt = $this->db->prepare(
            'SELECT destination_ID
             FROM cashout_destinations
             WHERE pal_ID = :pal_id AND destination_type = :destination_type AND provider_name = :provider_name AND account_identifier = :account_identifier
             ORDER BY destination_ID DESC
             LIMIT 1'
        );
        $stmt->execute([
            'pal_id' => $palId,
            'destination_type' => $destinationType,
            'provider_name' => $providerName,
            'account_identifier' => $accountIdentifier,
        ]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (int)$existing;
        }

        $insert = $this->db->prepare(
            'INSERT INTO cashout_destinations (pal_ID, destination_type, provider_name, account_identifier, is_default, created_at)
             VALUES (:pal_id, :destination_type, :provider_name, :account_identifier, 1, NOW())'
        );
        $insert->execute([
            'pal_id' => $palId,
            'destination_type' => $destinationType,
            'provider_name' => $providerName,
            'account_identifier' => $accountIdentifier,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function createCashoutRequest(int $destinationId, int $palId, float $pointsRequested, float $cashEquivalent): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO cashout_requests (destination_ID, pal_ID, points_requested, cash_equivalent, status, requested_at)
             VALUES (:destination_id, :pal_id, :points_requested, :cash_equivalent, 'Pending', NOW())"
        );
        $stmt->execute([
            'destination_id' => $destinationId,
            'pal_id' => $palId,
            'points_requested' => $pointsRequested,
            'cash_equivalent' => $cashEquivalent,
        ]);
        return (int)$this->db->lastInsertId();
    }
}
