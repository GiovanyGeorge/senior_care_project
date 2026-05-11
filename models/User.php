<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return (bool)$stmt->fetchColumn();
    }

    public function phoneExists(string $phone): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE phone = :phone LIMIT 1');
        $stmt->execute(['phone' => $phone]);
        return (bool)$stmt->fetchColumn();
    }

    public function emailExistsForOther(string $email, int $excludeUserId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = :email AND User_ID <> :exclude_id LIMIT 1');
        $stmt->execute([
            'email' => $email,
            'exclude_id' => $excludeUserId,
        ]);
        return (bool)$stmt->fetchColumn();
    }

    public function phoneExistsForOther(string $phone, int $excludeUserId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE phone = :phone AND User_ID <> :exclude_id LIMIT 1');
        $stmt->execute([
            'phone' => $phone,
            'exclude_id' => $excludeUserId,
        ]);
        return (bool)$stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE User_ID = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (Fname, Lname, email, password_hash, phone, role_type, age, national_id, profile_photo_url, is_active, created_at)
             VALUES (:first_name, :last_name, :email, :password, :phone, :role, :age, :national_id, :profile_photo, :is_active, NOW())'
        );

        $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'],
            'role' => $data['role'],
            'age' => $data['age'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'profile_photo' => $data['profile_photo'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateProfilePhoto(int $userId, string $photoPath): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET profile_photo_url = :profile_photo WHERE User_ID = :id');
        return $stmt->execute(['profile_photo' => $photoPath, 'id' => $userId]);
    }

    public function updateAdminProfile(int $userId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET Fname = :first_name, Lname = :last_name, email = :email, phone = :phone
             WHERE User_ID = :id AND role_type = :role'
        );

        return $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'id' => $userId,
            'role' => 'admin',
        ]);
    }

    public function getUsersForManagement(): array
    {
        $stmt = $this->db->query(
            'SELECT User_ID, Fname, Lname, email, role_type, phone, age, national_id, is_active, created_at
             FROM users
             ORDER BY created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public function nationalIdExists(string $nationalId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE national_id = :national_id LIMIT 1');
        $stmt->execute(['national_id' => $nationalId]);
        return (bool)$stmt->fetchColumn();
    }

    public function nationalIdExistsForOther(string $nationalId, int $excludeUserId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE national_id = :national_id AND User_ID <> :exclude_id LIMIT 1');
        $stmt->execute([
            'national_id' => $nationalId,
            'exclude_id' => $excludeUserId,
        ]);
        return (bool)$stmt->fetchColumn();
    }

    public function setUserActiveStatus(int $userId, int $isActive): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET is_active = :is_active WHERE User_ID = :id');
        return $stmt->execute([
            'is_active' => $isActive,
            'id' => $userId,
        ]);
    }

    public function changePassword(int $userId, string $newPasswordHash): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :password_hash WHERE User_ID = :id');
        return $stmt->execute([
            'password_hash' => $newPasswordHash,
            'id' => $userId,
        ]);
    }

    public function deleteById(int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE User_ID = :id');
        return $stmt->execute(['id' => $userId]);
    }

    public function deleteUserAndAllRelatedData(int $userId): bool
    {
        $this->db->beginTransaction();
        try {
            $seniorId = null;
            $palId = null;

            $seniorStmt = $this->db->prepare('SELECT senior_ID FROM senior_profiles WHERE User_ID = :user_id LIMIT 1');
            $seniorStmt->execute(['user_id' => $userId]);
            $seniorValue = $seniorStmt->fetchColumn();
            if ($seniorValue !== false) {
                $seniorId = (int)$seniorValue;
            }

            $palStmt = $this->db->prepare('SELECT pal_ID FROM pal_profiles WHERE User_ID = :user_id LIMIT 1');
            $palStmt->execute(['user_id' => $userId]);
            $palValue = $palStmt->fetchColumn();
            if ($palValue !== false) {
                $palId = (int)$palValue;
            }

            // Rows that directly reference users.
            $this->db->prepare('DELETE FROM notifications WHERE usersUser_ID = :user_id')->execute(['user_id' => $userId]);
            $this->db->prepare('DELETE FROM silverpoints_ledger WHERE User_ID = :user_id')->execute(['user_id' => $userId]);
            $this->db->prepare('DELETE FROM emergency_message WHERE sender_user_ID = :user_id')->execute(['user_id' => $userId]);
            // If the user owns emergency threads, remove their messages first then threads.
            $this->db->prepare(
                'DELETE em FROM emergency_message em
                 INNER JOIN emergency_threads et ON et.thread_ID = em.emergency_ID
                 WHERE et.user_ID = :user_id'
            )->execute(['user_id' => $userId]);
            $this->db->prepare('DELETE FROM emergency_threads WHERE user_ID = :user_id')->execute(['user_id' => $userId]);
            $this->db->prepare('DELETE FROM admin_broadcasts WHERE admin_ID = :user_id')->execute(['user_id' => $userId]);
            $this->db->prepare('DELETE FROM proxy_senior_link WHERE proxyUser_ID = :user_id')->execute(['user_id' => $userId]);
            try {
                $this->db->prepare('DELETE FROM escrow_holds WHERE user_ID = :user_id')->execute(['user_id' => $userId]);
            } catch (Throwable $e) {
                // Table may not exist before migration.
            }
            try {
                $this->db->prepare('DELETE FROM proxy_profiles WHERE User_ID = :user_id')->execute(['user_id' => $userId]);
            } catch (Throwable $e) {
                // Table may not exist before migration.
            }

            // Remove visits where user acted as proxy.
            $this->deleteVisitDependenciesByClause('vr.proxy_ID = :proxy_user_id', ['proxy_user_id' => $userId]);
            $this->db->prepare('DELETE FROM visit_requests WHERE proxy_ID = :user_id')->execute(['user_id' => $userId]);

            if ($seniorId !== null) {
                // Remove dependencies of all visits created for this senior.
                $this->db->prepare(
                    'DELETE em FROM emergency_message em
                     INNER JOIN emergency_threads et ON et.thread_ID = em.emergency_ID
                     INNER JOIN visit_requests vr ON vr.visit_ID = et.visit_ID
                     WHERE vr.senior_ID = :senior_id'
                )->execute(['senior_id' => $seniorId]);

                $this->db->prepare(
                    'DELETE et FROM emergency_threads et
                     INNER JOIN visit_requests vr ON vr.visit_ID = et.visit_ID
                     WHERE vr.senior_ID = :senior_id'
                )->execute(['senior_id' => $seniorId]);

                $this->db->prepare(
                    'DELETE ppr FROM pal_passed_requests ppr
                     INNER JOIN visit_requests vr ON vr.visit_ID = ppr.visit_ID
                     WHERE vr.senior_ID = :senior_id'
                )->execute(['senior_id' => $seniorId]);

                $this->db->prepare(
                    'DELETE r FROM ratings r
                     INNER JOIN visit_requests vr ON vr.visit_ID = r.visit_ID
                     WHERE vr.senior_ID = :senior_id'
                )->execute(['senior_id' => $seniorId]);

                $this->db->prepare(
                    'DELETE sl FROM silverpoints_ledger sl
                     INNER JOIN visit_requests vr ON vr.visit_ID = sl.visit_ID
                     WHERE vr.senior_ID = :senior_id'
                )->execute(['senior_id' => $seniorId]);

                $this->db->prepare('DELETE FROM visit_requests WHERE senior_ID = :senior_id')->execute(['senior_id' => $seniorId]);

                // Remove senior-level dependencies.
                $this->db->prepare('DELETE FROM emergency_threads WHERE senior_ID = :senior_id')->execute(['senior_id' => $seniorId]);
                $this->db->prepare('DELETE FROM health_records WHERE senior_ID = :senior_id')->execute(['senior_id' => $seniorId]);
                $this->db->prepare('DELETE FROM ratings WHERE senior_ID = :senior_id')->execute(['senior_id' => $seniorId]);
                $this->db->prepare('DELETE FROM proxy_senior_link WHERE senior_ID = :senior_id')->execute(['senior_id' => $seniorId]);
                $this->db->prepare('DELETE FROM senior_profiles WHERE senior_ID = :senior_id')->execute(['senior_id' => $seniorId]);
            }

            if ($palId !== null) {
                // Remove dependencies of all visits assigned to this pal.
                $this->db->prepare(
                    'DELETE em FROM emergency_message em
                     INNER JOIN emergency_threads et ON et.thread_ID = em.emergency_ID
                     INNER JOIN visit_requests vr ON vr.visit_ID = et.visit_ID
                     WHERE vr.pal_ID = :pal_id'
                )->execute(['pal_id' => $palId]);

                $this->db->prepare(
                    'DELETE et FROM emergency_threads et
                     INNER JOIN visit_requests vr ON vr.visit_ID = et.visit_ID
                     WHERE vr.pal_ID = :pal_id'
                )->execute(['pal_id' => $palId]);

                $this->db->prepare(
                    'DELETE ppr FROM pal_passed_requests ppr
                     INNER JOIN visit_requests vr ON vr.visit_ID = ppr.visit_ID
                     WHERE vr.pal_ID = :pal_id'
                )->execute(['pal_id' => $palId]);

                $this->db->prepare(
                    'DELETE r FROM ratings r
                     INNER JOIN visit_requests vr ON vr.visit_ID = r.visit_ID
                     WHERE vr.pal_ID = :pal_id'
                )->execute(['pal_id' => $palId]);

                $this->db->prepare(
                    'DELETE sl FROM silverpoints_ledger sl
                     INNER JOIN visit_requests vr ON vr.visit_ID = sl.visit_ID
                     WHERE vr.pal_ID = :pal_id'
                )->execute(['pal_id' => $palId]);

                $this->db->prepare('DELETE FROM visit_requests WHERE pal_ID = :pal_id')->execute(['pal_id' => $palId]);

                // Remove pal-level dependencies.
                $this->db->prepare('DELETE FROM cashout_requests WHERE pal_ID = :pal_id')->execute(['pal_id' => $palId]);
                $this->db->prepare('DELETE FROM cashout_destinations WHERE pal_ID = :pal_id')->execute(['pal_id' => $palId]);
                $this->db->prepare('DELETE FROM skill_badges WHERE pal_ID = :pal_id')->execute(['pal_id' => $palId]);
                try {
                    $this->db->prepare('DELETE FROM background_checks WHERE pal_ID = :pal_id')->execute(['pal_id' => $palId]);
                } catch (Throwable $e) {
                    // Table may not exist before migration.
                }
                $this->db->prepare('DELETE FROM pal_passed_requests WHERE pal_ID = :pal_id')->execute(['pal_id' => $palId]);
                $this->db->prepare('DELETE FROM ratings WHERE pal_ID = :pal_id')->execute(['pal_id' => $palId]);
                $this->db->prepare('DELETE FROM pal_profiles WHERE pal_ID = :pal_id')->execute(['pal_id' => $palId]);
            }

            // Finally remove the user account itself.
            $this->db->prepare('DELETE FROM users WHERE User_ID = :user_id')->execute(['user_id' => $userId]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function deleteVisitDependenciesByClause(string $visitWhereSql, array $params): void
    {
        $queries = [
            // Messages inside emergency threads linked to target visits.
            'DELETE em FROM emergency_message em
             INNER JOIN emergency_threads et ON et.thread_ID = em.emergency_ID
             INNER JOIN visit_requests vr ON vr.visit_ID = et.visit_ID
             WHERE ' . $visitWhereSql,

            // Emergency threads linked to target visits.
            'DELETE et FROM emergency_threads et
             INNER JOIN visit_requests vr ON vr.visit_ID = et.visit_ID
             WHERE ' . $visitWhereSql,

            // Pal pass history linked to target visits.
            'DELETE ppr FROM pal_passed_requests ppr
             INNER JOIN visit_requests vr ON vr.visit_ID = ppr.visit_ID
             WHERE ' . $visitWhereSql,

            // Ratings linked to target visits.
            'DELETE r FROM ratings r
             INNER JOIN visit_requests vr ON vr.visit_ID = r.visit_ID
             WHERE ' . $visitWhereSql,

            // Ledger records linked to target visits.
            'DELETE sl FROM silverpoints_ledger sl
             INNER JOIN visit_requests vr ON vr.visit_ID = sl.visit_ID
             WHERE ' . $visitWhereSql,
        ];

        foreach ($queries as $sql) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }

        // Optional escrow table from newer migration.
        try {
            $escrow = $this->db->prepare(
                'DELETE eh FROM escrow_holds eh
                 INNER JOIN visit_requests vr ON vr.visit_ID = eh.visit_ID
                 WHERE ' . $visitWhereSql
            );
            $escrow->execute($params);
        } catch (Throwable $e) {
            // Table may not exist before migration.
        }
    }

    public function getActiveSeniors(): array
    {
        $stmt = $this->db->query(
            "SELECT u.User_ID, u.Fname, u.Lname, u.email
             FROM users u
             WHERE u.role_type = 'senior' AND u.is_active = 1
             ORDER BY u.Fname ASC, u.Lname ASC"
        );
        return $stmt->fetchAll();
    }

    public function getSeniorIdByUserId(int $seniorUserId): ?int
    {
        $stmt = $this->db->prepare('SELECT senior_ID FROM senior_profiles WHERE User_ID = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $seniorUserId]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (int)$value : null;
    }

    public function ensureSeniorProfile(int $seniorUserId): int
    {
        $existing = $this->getSeniorIdByUserId($seniorUserId);
        if ($existing !== null) {
            return $existing;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO senior_profiles (User_ID, address, comfort_profile, emergency_contact_name, emergency_contact_phone)
             VALUES (:user_id, NULL, NULL, NULL, NULL)'
        );
        $stmt->execute(['user_id' => $seniorUserId]);
        return (int)$this->db->lastInsertId();
    }

    public function ensurePalProfile(int $palUserId): int
    {
        $stmt = $this->db->prepare('SELECT pal_ID FROM pal_profiles WHERE User_ID = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $palUserId]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (int)$existing;
        }

        $insert = $this->db->prepare(
            "INSERT INTO pal_profiles (User_ID, skills, verification_status, rating_avg, travel_radius_km, transport_mode)
             VALUES (:user_id, NULL, 'Pending', 0, 5, 'Walking')"
        );
        $insert->execute(['user_id' => $palUserId]);
        return (int)$this->db->lastInsertId();
    }

    public function setPalVerificationStatusByUserId(int $palUserId, string $status): bool
    {
        $allowed = ['Pending', 'Approved', 'Rejected'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE pal_profiles
             SET verification_status = :status
             WHERE User_ID = :user_id'
        );
        return $stmt->execute([
            'status' => $status,
            'user_id' => $palUserId,
        ]);
    }

    public function upsertHealthRecord(int $seniorId, string $medicalNotes, string $allergies): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM health_records WHERE senior_ID = :senior_id LIMIT 1');
        $stmt->execute(['senior_id' => $seniorId]);
        $exists = (bool)$stmt->fetchColumn();

        if ($exists) {
            $u = $this->db->prepare(
                'UPDATE health_records
                 SET medical_notes = :medical_notes, allergies = :allergies
                 WHERE senior_ID = :senior_id'
            );
            return $u->execute([
                'medical_notes' => $medicalNotes !== '' ? $medicalNotes : null,
                'allergies' => $allergies !== '' ? $allergies : null,
                'senior_id' => $seniorId,
            ]);
        }

        $i = $this->db->prepare(
            'INSERT INTO health_records (senior_ID, medical_notes, allergies)
             VALUES (:senior_id, :medical_notes, :allergies)'
        );
        return $i->execute([
            'senior_id' => $seniorId,
            'medical_notes' => $medicalNotes !== '' ? $medicalNotes : null,
            'allergies' => $allergies !== '' ? $allergies : null,
        ]);
    }

    public function updateSeniorRegistrationDetails(int $userId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE senior_profiles
             SET address = :address,
                 comfort_profile = :comfort_profile,
                 emergency_contact_name = :ec_name,
                 emergency_contact_phone = :ec_phone
             WHERE User_ID = :user_id'
        );
        return $stmt->execute([
            'address' => $data['address'] !== '' ? $data['address'] : null,
            'comfort_profile' => $data['comfort_profile'] !== '' ? $data['comfort_profile'] : null,
            'ec_name' => $data['emergency_contact_name'] !== '' ? $data['emergency_contact_name'] : null,
            'ec_phone' => $data['emergency_contact_phone'] !== '' ? $data['emergency_contact_phone'] : null,
            'user_id' => $userId,
        ]);
    }

    public function linkProxyToSenior(int $proxyUserId, int $seniorId, string $relationshipType): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO proxy_senior_link (proxyUser_ID, senior_ID, relationship_type)
             VALUES (:proxy_user_id, :senior_id, :relationship_type)
             ON DUPLICATE KEY UPDATE relationship_type = VALUES(relationship_type)'
        );
        return $stmt->execute([
            'proxy_user_id' => $proxyUserId,
            'senior_id' => $seniorId,
            'relationship_type' => $relationshipType,
        ]);
    }

    public function getProxyLinkedSenior(int $proxyUserId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT psl.senior_ID, sp.User_ID AS senior_user_id, u.Fname, u.Lname, u.email
             FROM proxy_senior_link psl
             JOIN senior_profiles sp ON sp.senior_ID = psl.senior_ID
             JOIN users u ON u.User_ID = sp.User_ID
             WHERE psl.proxyUser_ID = :proxy_user_id
             LIMIT 1"
        );
        $stmt->execute(['proxy_user_id' => $proxyUserId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getProxyLinkedSeniors(int $proxyUserId): array
    {
        $stmt = $this->db->prepare(
            "SELECT psl.senior_ID, sp.User_ID AS senior_user_id, u.Fname, u.Lname, u.email, psl.relationship_type
             FROM proxy_senior_link psl
             JOIN senior_profiles sp ON sp.senior_ID = psl.senior_ID
             JOIN users u ON u.User_ID = sp.User_ID
             WHERE psl.proxyUser_ID = :proxy_user_id
             ORDER BY u.Fname ASC, u.Lname ASC"
        );
        $stmt->execute(['proxy_user_id' => $proxyUserId]);
        return $stmt->fetchAll() ?: [];
    }

    public function getProxyUserIdsForSeniorId(int $seniorId): array
    {
        $stmt = $this->db->prepare('SELECT proxyUser_ID FROM proxy_senior_link WHERE senior_ID = :senior_id');
        $stmt->execute(['senior_id' => $seniorId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_unique(array_map('intval', $rows ?: [])));
    }

    public function updateBasicProfile(int $userId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET Fname = :fname, Lname = :lname, phone = :phone WHERE User_ID = :user_id'
        );
        return $stmt->execute([
            'fname' => $data['first_name'],
            'lname' => $data['last_name'],
            'phone' => $data['phone'],
            'user_id' => $userId,
        ]);
    }

    public function updateUserByAdmin(int $userId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET Fname = :first_name,
                 Lname = :last_name,
                 email = :email,
                 phone = :phone,
                 age = :age,
                 national_id = :national_id,
                 role_type = :role_type,
                 is_active = :is_active
             WHERE User_ID = :user_id'
        );
        return $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'age' => $data['age'],
            'national_id' => $data['national_id'],
            'role_type' => $data['role_type'],
            'is_active' => $data['is_active'],
            'user_id' => $userId,
        ]);
    }

    public function updateSeniorProfileByUserId(int $userId, array $data): bool
    {
        $this->db->beginTransaction();
        try {
            $u = $this->db->prepare(
                'UPDATE users SET Fname = :fname, Lname = :lname, phone = :phone WHERE User_ID = :user_id'
            );
            $u->execute([
                'fname' => $data['first_name'],
                'lname' => $data['last_name'],
                'phone' => $data['phone'],
                'user_id' => $userId,
            ]);

            $sp = $this->db->prepare(
                'UPDATE senior_profiles
                 SET address = :address, comfort_profile = :comfort_profile, emergency_contact_name = :ec_name, emergency_contact_phone = :ec_phone
                 WHERE User_ID = :user_id'
            );
            $sp->execute([
                'address' => $data['address'],
                'comfort_profile' => $data['comfort_profile'],
                'ec_name' => $data['emergency_contact_name'],
                'ec_phone' => $data['emergency_contact_phone'],
                'user_id' => $userId,
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function updatePalProfileByUserId(int $userId, array $data): bool
    {
        $this->db->beginTransaction();
        try {
            $u = $this->db->prepare(
                'UPDATE users SET Fname = :fname, Lname = :lname, phone = :phone WHERE User_ID = :user_id'
            );
            $u->execute([
                'fname' => $data['first_name'],
                'lname' => $data['last_name'],
                'phone' => $data['phone'],
                'user_id' => $userId,
            ]);

            $pp = $this->db->prepare(
                'UPDATE pal_profiles
                 SET skills = :skills, travel_radius_km = :radius, transport_mode = :transport
                 WHERE User_ID = :user_id'
            );
            $pp->execute([
                'skills' => $data['skills'],
                'radius' => $data['travel_radius_km'],
                'transport' => $data['transport_mode'],
                'user_id' => $userId,
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function getUserFullSnapshot(int $userId): ?array
    {
        $userStmt = $this->db->prepare('SELECT * FROM users WHERE User_ID = :user_id LIMIT 1');
        $userStmt->execute(['user_id' => $userId]);
        $user = $userStmt->fetch();
        if (!$user) {
            return null;
        }

        $seniorStmt = $this->db->prepare('SELECT * FROM senior_profiles WHERE User_ID = :user_id LIMIT 1');
        $seniorStmt->execute(['user_id' => $userId]);
        $senior = $seniorStmt->fetch() ?: null;

        $palStmt = $this->db->prepare('SELECT * FROM pal_profiles WHERE User_ID = :user_id LIMIT 1');
        $palStmt->execute(['user_id' => $userId]);
        $pal = $palStmt->fetch() ?: null;

        $proxy = null;
        try {
            $proxyStmt = $this->db->prepare('SELECT * FROM proxy_profiles WHERE User_ID = :user_id LIMIT 1');
            $proxyStmt->execute(['user_id' => $userId]);
            $proxy = $proxyStmt->fetch() ?: null;
        } catch (Throwable $e) {
            $proxy = null;
        }

        $health = null;
        if (!empty($senior['senior_ID'])) {
            $healthStmt = $this->db->prepare('SELECT * FROM health_records WHERE senior_ID = :senior_id LIMIT 1');
            $healthStmt->execute(['senior_id' => (int)$senior['senior_ID']]);
            $health = $healthStmt->fetch() ?: null;
        }

        $badges = [];
        if (!empty($pal['pal_ID'])) {
            $badgeStmt = $this->db->prepare('SELECT * FROM skill_badges WHERE pal_ID = :pal_id ORDER BY badge_ID DESC');
            $badgeStmt->execute(['pal_id' => (int)$pal['pal_ID']]);
            $badges = $badgeStmt->fetchAll();
        }

        return [
            'user' => $user,
            'senior_profile' => $senior,
            'pal_profile' => $pal,
            'proxy_profile' => $proxy,
            'health_record' => $health,
            'skill_badges' => $badges,
        ];
    }
}
