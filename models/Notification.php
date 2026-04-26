<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Notification
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getLatestByUser(int $userId, int $limit = 6): array
    {
        $stmt = $this->db->prepare(
            'SELECT notification_ID, type, title, message_body AS message, is_read, created_at
             FROM notifications
             WHERE usersUser_ID = :user_id
             ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(int $userId, string $title, string $message): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (usersUser_ID, type, title, message_body, created_at) VALUES (:user_id, :type, :title, :message, NOW())'
        );
        return $stmt->execute([
            'user_id' => $userId,
            'type' => 'System',
            'title' => $title,
            'message' => $message,
        ]);
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications
             SET is_read = 1, read_at = NOW()
             WHERE notification_ID = :id AND usersUser_ID = :user_id'
        );
        return $stmt->execute([
            'id' => $notificationId,
            'user_id' => $userId,
        ]);
    }
}
