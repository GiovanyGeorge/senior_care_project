<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Rating
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function ratingExistsForVisit(int $visitId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM ratings WHERE visit_ID = :visit_id LIMIT 1');
        $stmt->execute(['visit_id' => $visitId]);
        return (bool)$stmt->fetchColumn();
    }

    public function create(int $visitId, int $seniorId, int $palId, float $score, string $comment): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ratings (visit_ID, senior_ID, pal_ID, rating_score, comment, created_at)
             VALUES (:visit_id, :senior_id, :pal_id, :score, :comment, NOW())'
        );
        $stmt->execute([
            'visit_id' => $visitId,
            'senior_id' => $seniorId,
            'pal_id' => $palId,
            'score' => $score,
            'comment' => $comment !== '' ? $comment : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updatePalAverage(int $palId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE pal_profiles
             SET rating_avg = (
                 SELECT ROUND(AVG(rating_score), 2)
                 FROM ratings
                 WHERE pal_ID = :pal_id
             )
             WHERE pal_ID = :pal_id'
        );
        $stmt->execute(['pal_id' => $palId]);
    }
}

