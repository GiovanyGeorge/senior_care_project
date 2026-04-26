<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Points
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getBalance(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT balance_after
             FROM silverpoints_ledger
             WHERE User_ID = :user_id
             ORDER BY ledger_entry_ID DESC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return (int) ($row['balance_after'] ?? 0);
    }

    public function getTopupPackages(): array
    {
        return [
            'starter' => ['name' => 'Starter Pack', 'points' => 100, 'price' => 50],
            'care' => ['name' => 'Care Pack', 'points' => 250, 'price' => 120],
            'support' => ['name' => 'Support Pack', 'points' => 600, 'price' => 270],
            'premium' => ['name' => 'Premium Pack', 'points' => 1200, 'price' => 500],
        ];
    }

    public function addLedgerEntry(int $userId, int $points, string $direction, string $description, ?int $visitId = null): bool
    {
        $entryType = strtolower($direction) === 'credit' ? 'Credit' : 'Debit';
        $currentBalance = $this->getBalance($userId);
        $newBalance = $entryType === 'Credit' ? $currentBalance + $points : $currentBalance - $points;

        $stmt = $this->db->prepare(
            "INSERT INTO silverpoints_ledger (User_ID, visit_ID, entry_type, points_amount, balance_after, description, created_at)
             VALUES (:user_id, :visit_id, :entry_type, :points_amount, :balance_after, :description, NOW())"
        );
        return $stmt->execute([
            'user_id' => $userId,
            'visit_id' => $visitId,
            'entry_type' => $entryType,
            'points_amount' => $points,
            'balance_after' => $newBalance,
            'description' => $description,
        ]);
    }

    public function ledgerEntryExists(?int $visitId, int $userId, string $description): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM silverpoints_ledger WHERE visit_ID <=> :visit_id AND User_ID = :user_id AND description = :description LIMIT 1'
        );
        $stmt->execute([
            'visit_id' => $visitId,
            'user_id' => $userId,
            'description' => $description,
        ]);
        return (bool)$stmt->fetchColumn();
    }
}
