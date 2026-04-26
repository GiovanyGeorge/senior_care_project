<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Emergency
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function createThread(int $seniorId, int $actorUserId, string $message): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO emergency_threads (visit_ID, senior_ID, user_ID, status, priority_level, created_at)
             VALUES (NULL, :senior_id, :user_id, "Open", "High", NOW())'
        );
        $stmt->execute([
            'senior_id' => $seniorId,
            'user_id' => $actorUserId,
        ]);
        $threadId = (int) $this->db->lastInsertId();

        $msgStmt = $this->db->prepare(
            'INSERT INTO emergency_message (sender_user_ID, emergency_ID, message_text, sent_at)
             VALUES (:sender_user_id, :emergency_id, :message, NOW())'
        );
        $msgStmt->execute([
            'emergency_id' => $threadId,
            'sender_user_id' => $actorUserId,
            'message' => $message,
        ]);

        return $threadId;
    }

    public function getSeniorIdByUserId(int $userId): ?int
    {
        $stmt = $this->db->prepare('SELECT senior_ID FROM senior_profiles WHERE User_ID = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (int)$value : null;
    }

    public function getProxyUserIdsForSenior(int $seniorId): array
    {
        $stmt = $this->db->prepare(
            'SELECT proxyUser_ID
             FROM proxy_senior_link
             WHERE senior_ID = :senior_id'
        );
        $stmt->execute(['senior_id' => $seniorId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_unique(array_map('intval', $rows ?: [])));
    }

    public function getNearbyPalUserIds(int $limit = 6): array
    {
        // No geo coordinates in schema. "Nearby" is approximated by active + approved pals,
        // ranked by travel radius and rating.
        $stmt = $this->db->prepare(
            "SELECT u.User_ID
             FROM pal_profiles pp
             JOIN users u ON u.User_ID = pp.User_ID
             WHERE u.is_active = 1
               AND u.role_type = 'pal'
               AND pp.verification_status = 'Approved'
             ORDER BY pp.travel_radius_km DESC, pp.rating_avg DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_unique(array_map('intval', $rows ?: [])));
    }
}
