<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class BackgroundCheck
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getPalIdByUserId(int $userId): ?int
    {
        $stmt = $this->db->prepare('SELECT pal_ID FROM pal_profiles WHERE User_ID = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (int)$value : null;
    }

    public function submitSkillBadge(int $palId, string $badgeName, ?string $issuedAt, ?string $expiresAt, string $certificateUrl): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO skill_badges (pal_ID, badge_name, verification_status, issued_at, expires_at, certificate_url)
             VALUES (:pal_id, :badge_name, 'Pending', :issued_at, :expires_at, :certificate_url)"
        );
        return $stmt->execute([
            'pal_id' => $palId,
            'badge_name' => $badgeName,
            'issued_at' => $issuedAt ?: date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt ?: null,
            'certificate_url' => $certificateUrl,
        ]);
    }

    public function getPalBadges(int $palId): array
    {
        $stmt = $this->db->prepare(
            'SELECT badge_ID, badge_name, verification_status, issued_at, expires_at, certificate_url
             FROM skill_badges
             WHERE pal_ID = :pal_id
             ORDER BY badge_ID DESC'
        );
        $stmt->execute(['pal_id' => $palId]);
        return $stmt->fetchAll();
    }

    public function getPendingBadges(): array
    {
        $stmt = $this->db->query(
            "SELECT sb.badge_ID, sb.badge_name, sb.verification_status, sb.certificate_url, sb.issued_at,
                    pp.pal_ID, u.Fname, u.Lname, u.email
             FROM skill_badges sb
             JOIN pal_profiles pp ON pp.pal_ID = sb.pal_ID
             JOIN users u ON u.User_ID = pp.User_ID
             WHERE sb.verification_status = 'Pending'
             ORDER BY sb.badge_ID DESC"
        );
        return $stmt->fetchAll();
    }

    public function setBadgeStatus(int $badgeId, string $status): bool
    {
        $allowed = ['Approved', 'Rejected', 'Pending'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE skill_badges SET verification_status = :status WHERE badge_ID = :id');
        return $stmt->execute(['status' => $status, 'id' => $badgeId]);
    }

    public function getBadgeById(int $badgeId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT sb.badge_ID, sb.pal_ID, sb.badge_name, sb.verification_status, sb.issued_at, sb.expires_at, sb.certificate_url,
                    u.User_ID AS pal_user_id, u.Fname, u.Lname, u.email
             FROM skill_badges sb
             JOIN pal_profiles pp ON pp.pal_ID = sb.pal_ID
             JOIN users u ON u.User_ID = pp.User_ID
             WHERE sb.badge_ID = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $badgeId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
