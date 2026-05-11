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
        $ok = $stmt->execute([
            'pal_id' => $palId,
            'badge_name' => $badgeName,
            'issued_at' => $issuedAt ?: date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt ?: null,
            'certificate_url' => $certificateUrl,
        ]);
        if (!$ok) {
            return false;
        }

        try {
            $badgeId = (int)$this->db->lastInsertId();
            $bgStmt = $this->db->prepare(
                "INSERT INTO background_checks (pal_ID, badge_ID, check_type, status, created_at)
                 VALUES (:pal_id, :badge_id, 'SkillBadge', 'Pending', NOW())"
            );
            $bgStmt->execute([
                'pal_id' => $palId,
                'badge_id' => $badgeId,
            ]);
        } catch (Throwable $e) {
            // Keep badge submission working even if migration is not applied yet.
        }

        return true;
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

    public function getAllBadges(): array
    {
        $stmt = $this->db->query(
            "SELECT sb.badge_ID, sb.badge_name, sb.verification_status, sb.certificate_url, sb.issued_at, sb.expires_at,
                    pp.pal_ID, u.User_ID AS pal_user_id, u.Fname, u.Lname, u.email
             FROM skill_badges sb
             JOIN pal_profiles pp ON pp.pal_ID = sb.pal_ID
             JOIN users u ON u.User_ID = pp.User_ID
             ORDER BY sb.badge_ID DESC"
        );
        return $stmt->fetchAll();
    }

    public function getAllBackgroundChecks(): array
    {
        $stmt = $this->db->query(
            "SELECT bc.check_ID, bc.pal_ID, bc.badge_ID, bc.check_type, bc.status, bc.notes, bc.created_at, bc.reviewed_at, bc.reviewer_user_ID,
                    u.User_ID AS pal_user_id, u.Fname, u.Lname, u.email,
                    sb.badge_name, sb.certificate_url, sb.issued_at, sb.expires_at
             FROM background_checks bc
             JOIN pal_profiles pp ON pp.pal_ID = bc.pal_ID
             JOIN users u ON u.User_ID = pp.User_ID
             LEFT JOIN skill_badges sb ON sb.badge_ID = bc.badge_ID
             ORDER BY bc.check_ID DESC"
        );
        return $stmt->fetchAll();
    }

    public function updateBackgroundCheckStatus(int $checkId, string $status, ?int $reviewerUserId = null, ?string $notes = null): bool
    {
        $allowed = ['Approved', 'Rejected', 'Pending'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE background_checks
             SET status = :status,
                 reviewer_user_ID = :reviewer_user_id,
                 notes = :notes,
                 reviewed_at = NOW()
             WHERE check_ID = :check_id'
        );
        return $stmt->execute([
            'status' => $status,
            'reviewer_user_id' => $reviewerUserId,
            'notes' => ($notes !== null && trim($notes) !== '') ? trim($notes) : null,
            'check_id' => $checkId,
        ]);
    }

    public function getBackgroundCheckById(int $checkId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT bc.check_ID, bc.pal_ID, bc.badge_ID, bc.check_type, bc.status, bc.notes,
                    u.User_ID AS pal_user_id, u.Fname, u.Lname, u.email, sb.badge_name
             FROM background_checks bc
             JOIN pal_profiles pp ON pp.pal_ID = bc.pal_ID
             JOIN users u ON u.User_ID = pp.User_ID
             LEFT JOIN skill_badges sb ON sb.badge_ID = bc.badge_ID
             WHERE bc.check_ID = :check_id
             LIMIT 1"
        );
        $stmt->execute(['check_id' => $checkId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function setBadgeStatus(int $badgeId, string $status): bool
    {
        $allowed = ['Approved', 'Rejected', 'Pending'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE skill_badges SET verification_status = :status WHERE badge_ID = :id');
        $ok = $stmt->execute(['status' => $status, 'id' => $badgeId]);
        if (!$ok) {
            return false;
        }

        try {
            $bg = $this->db->prepare(
                'UPDATE background_checks
                 SET status = :status, reviewed_at = NOW()
                 WHERE badge_ID = :badge_id'
            );
            $bg->execute(['status' => $status, 'badge_id' => $badgeId]);
        } catch (Throwable $e) {
            // Skip background check update when table does not exist yet.
        }
        return true;
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
