USE project_se;

START TRANSACTION;

-- 1) Ensure service categories exist and are active.
INSERT INTO service_categories (category_name, description, base_points_cost, is_active)
VALUES
    ('Grocery Assistance', 'Help with grocery shopping and delivery', 30, 1),
    ('Technology Support', 'Phone, app, and device setup support', 25, 1),
    ('Gardening Help', 'Light garden care and plant maintenance', 35, 1),
    ('Companionship Visit', 'Friendly social companionship visits', 20, 1),
    ('Medication Reminder', 'Medication and wellness reminder support', 28, 1),
    ('Doctor Appointment Escort', 'Escort to clinics and appointments', 40, 1),
    ('Light Home Support', 'Simple home organization and support tasks', 32, 1)
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    base_points_cost = VALUES(base_points_cost),
    is_active = VALUES(is_active);

-- 2) Senior profiles (placeholder fields ready for health/safety details).
INSERT INTO senior_profiles (User_ID, address, comfort_profile, emergency_contact_name, emergency_contact_phone)
SELECT
    u.User_ID,
    CONCAT('Address for ', u.Fname, ' ', u.Lname),
    'Prefers morning visits and calm communication.',
    CONCAT(u.Fname, ' Emergency Contact'),
    COALESCE(u.phone, '01000000000')
FROM users u
LEFT JOIN senior_profiles sp ON sp.User_ID = u.User_ID
WHERE u.role_type = 'senior'
  AND sp.User_ID IS NULL;

-- 3) Health placeholders for each senior profile.
SET @admin_id := (SELECT User_ID FROM users WHERE role_type = 'admin' ORDER BY User_ID ASC LIMIT 1);
INSERT INTO health_records (senior_ID, medical_notes, mobility_notes, allergies, emergency_instructions, must_acknowledge, updated_by)
SELECT
    sp.senior_ID,
    'No medical notes added yet. Please update by family/admin.',
    'Mobility notes pending update.',
    'No known allergies recorded yet.',
    'In emergency, contact family and nearest clinic immediately.',
    1,
    @admin_id
FROM senior_profiles sp
LEFT JOIN health_records hr ON hr.senior_ID = sp.senior_ID
WHERE hr.record_id IS NULL;

-- 4) Pal profile for active pal users.
INSERT INTO pal_profiles (User_ID, skills, rating_avg, verification_status, travel_radius_km, transport_mode)
SELECT
    u.User_ID,
    'Companionship, Grocery Assistance, Technology Support',
    4.80,
    'Approved',
    10,
    'Car'
FROM users u
LEFT JOIN pal_profiles pp ON pp.User_ID = u.User_ID
WHERE u.role_type = 'pal'
  AND u.is_active = 1
  AND pp.User_ID IS NULL;

-- 5) Initial SilverPoints: every senior starts with 100 points.
INSERT INTO silverpoints_ledger (User_ID, visit_ID, entry_type, points_amount, balance_after, description)
SELECT
    u.User_ID,
    NULL,
    'Credit',
    100.00,
    100.00,
    'Initial senior onboarding balance (100 points)'
FROM users u
LEFT JOIN silverpoints_ledger l
    ON l.User_ID = u.User_ID
   AND l.description = 'Initial senior onboarding balance (100 points)'
WHERE u.role_type = 'senior'
  AND l.ledger_entry_ID IS NULL;

-- 6) Setup proxy -> senior link if a proxy exists (without creating users).
SET @proxy_user_id := (SELECT User_ID FROM users WHERE role_type = 'proxy' ORDER BY User_ID ASC LIMIT 1);
SET @first_senior_id := (SELECT senior_ID FROM senior_profiles ORDER BY senior_ID ASC LIMIT 1);
INSERT INTO proxy_senior_link (proxyUser_ID, senior_ID, relationship_type)
SELECT
    @proxy_user_id,
    @first_senior_id,
    'Family'
FROM DUAL
WHERE @proxy_user_id IS NOT NULL
  AND @first_senior_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM proxy_senior_link psl
      WHERE psl.proxyUser_ID = @proxy_user_id
        AND psl.senior_ID = @first_senior_id
  );

-- 7) Seed one completed visit to demonstrate payment lifecycle.
SET @first_pal_id := (SELECT pal_ID FROM pal_profiles ORDER BY pal_ID ASC LIMIT 1);
SET @first_category_id := (SELECT category_ID FROM service_categories ORDER BY category_ID ASC LIMIT 1);

INSERT INTO visit_requests
    (senior_ID, pal_ID, proxy_ID, category_ID, status, request_type, scheduled_start, scheduled_end, actual_checkin, actual_checkout, service_address, task_details, mood_observation, points_reserved, points_paid)
SELECT
    @first_senior_id,
    @first_pal_id,
    @proxy_user_id,
    @first_category_id,
    'Completed',
    CASE WHEN @proxy_user_id IS NULL THEN 'Self' ELSE 'Proxy' END,
    DATE_SUB(NOW(), INTERVAL 2 DAY),
    DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 1 HOUR,
    DATE_SUB(NOW(), INTERVAL 2 DAY),
    DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 55 MINUTE,
    'Seeded senior address',
    'Seeded completed visit for payments and admin reporting',
    'Stable and cheerful',
    40.00,
    40.00
FROM DUAL
WHERE @first_senior_id IS NOT NULL
  AND @first_pal_id IS NOT NULL
  AND @first_category_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM visit_requests vr
      WHERE vr.task_details = 'Seeded completed visit for payments and admin reporting'
  );

SET @seed_visit_id := (
    SELECT visit_ID
    FROM visit_requests
    WHERE task_details = 'Seeded completed visit for payments and admin reporting'
    ORDER BY visit_ID DESC
    LIMIT 1
);
SET @first_pal_user_id := (SELECT User_ID FROM pal_profiles WHERE pal_ID = @first_pal_id LIMIT 1);

-- 8) Payment flow ledger:
-- Senior pays 40, pal gets 38 (after 5%), platform gets 2.
INSERT INTO silverpoints_ledger (User_ID, visit_ID, entry_type, points_amount, balance_after, description)
SELECT
    sp.User_ID,
    @seed_visit_id,
    'Debit',
    40.00,
    GREATEST(
        COALESCE((
            SELECT l2.balance_after
            FROM silverpoints_ledger l2
            WHERE l2.User_ID = sp.User_ID
            ORDER BY l2.ledger_entry_ID DESC
            LIMIT 1
        ), 100.00) - 40.00,
        0
    ),
    'Visit escrow debit (seeded)'
FROM senior_profiles sp
WHERE sp.senior_ID = @first_senior_id
  AND @seed_visit_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM silverpoints_ledger l
      WHERE l.User_ID = sp.User_ID
        AND l.visit_ID = @seed_visit_id
        AND l.description = 'Visit escrow debit (seeded)'
  );

INSERT INTO silverpoints_ledger (User_ID, visit_ID, entry_type, points_amount, balance_after, description)
SELECT
    @first_pal_user_id,
    @seed_visit_id,
    'Credit',
    38.00,
    COALESCE((
        SELECT l2.balance_after
        FROM silverpoints_ledger l2
        WHERE l2.User_ID = @first_pal_user_id
        ORDER BY l2.ledger_entry_ID DESC
        LIMIT 1
    ), 0.00) + 38.00,
    'Visit earning credit to pal (seeded)'
FROM DUAL
WHERE @first_pal_user_id IS NOT NULL
  AND @seed_visit_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM silverpoints_ledger l
      WHERE l.User_ID = @first_pal_user_id
        AND l.visit_ID = @seed_visit_id
        AND l.description = 'Visit earning credit to pal (seeded)'
  );

INSERT INTO silverpoints_ledger (User_ID, visit_ID, entry_type, points_amount, balance_after, description)
SELECT
    @admin_id,
    @seed_visit_id,
    'Credit',
    2.00,
    COALESCE((
        SELECT l2.balance_after
        FROM silverpoints_ledger l2
        WHERE l2.User_ID = @admin_id
        ORDER BY l2.ledger_entry_ID DESC
        LIMIT 1
    ), 0.00) + 2.00,
    'Platform insurance/site fee (5%) from visit (seeded)'
FROM DUAL
WHERE @admin_id IS NOT NULL
  AND @seed_visit_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM silverpoints_ledger l
      WHERE l.User_ID = @admin_id
        AND l.visit_ID = @seed_visit_id
        AND l.description = 'Platform insurance/site fee (5%) from visit (seeded)'
  );

-- 9) Proxy gift flow (only if proxy account exists).
INSERT INTO silverpoints_ledger (User_ID, visit_ID, entry_type, points_amount, balance_after, description)
SELECT
    @proxy_user_id,
    NULL,
    'Debit',
    20.00,
    COALESCE((
        SELECT l2.balance_after
        FROM silverpoints_ledger l2
        WHERE l2.User_ID = @proxy_user_id
        ORDER BY l2.ledger_entry_ID DESC
        LIMIT 1
    ), 20.00) - 20.00,
    CONCAT('Proxy gift sent to senior_ID ', @first_senior_id)
FROM DUAL
WHERE @proxy_user_id IS NOT NULL
  AND @first_senior_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM silverpoints_ledger l
      WHERE l.User_ID = @proxy_user_id
        AND l.description = CONCAT('Proxy gift sent to senior_ID ', @first_senior_id)
  );

INSERT INTO silverpoints_ledger (User_ID, visit_ID, entry_type, points_amount, balance_after, description)
SELECT
    sp.User_ID,
    NULL,
    'Credit',
    20.00,
    COALESCE((
        SELECT l2.balance_after
        FROM silverpoints_ledger l2
        WHERE l2.User_ID = sp.User_ID
        ORDER BY l2.ledger_entry_ID DESC
        LIMIT 1
    ), 100.00) + 20.00,
    CONCAT('Gift received from proxy user_ID ', @proxy_user_id)
FROM senior_profiles sp
WHERE sp.senior_ID = @first_senior_id
  AND @proxy_user_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM silverpoints_ledger l
      WHERE l.User_ID = sp.User_ID
        AND l.description = CONCAT('Gift received from proxy user_ID ', @proxy_user_id)
  );

-- 10) Ratings for seeded completed visit.
INSERT INTO ratings (visit_ID, senior_ID, pal_ID, rating_score, comment)
SELECT
    @seed_visit_id,
    @first_senior_id,
    @first_pal_id,
    4.80,
    'Helpful and respectful support.'
FROM DUAL
WHERE @seed_visit_id IS NOT NULL
  AND @first_senior_id IS NOT NULL
  AND @first_pal_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM ratings r
      WHERE r.visit_ID = @seed_visit_id
  );

-- 11) Pal cashout setup + request (points -> money).
INSERT INTO cashout_destinations (pal_ID, destination_type, provider_name, account_identifier, is_default)
SELECT
    @first_pal_id,
    'Wallet',
    'Vodafone Cash',
    '01012345678',
    1
FROM DUAL
WHERE @first_pal_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM cashout_destinations cd
      WHERE cd.pal_ID = @first_pal_id
        AND cd.account_identifier = '01012345678'
  );

SET @cashout_destination_id := (
    SELECT destination_ID
    FROM cashout_destinations
    WHERE pal_ID = @first_pal_id
    ORDER BY is_default DESC, destination_ID ASC
    LIMIT 1
);

INSERT INTO cashout_requests (destination_ID, pal_ID, points_requested, cash_equivalent, status, processed_at, processed_by)
SELECT
    @cashout_destination_id,
    @first_pal_id,
    30.00,
    30.00,
    'Approved',
    NOW(),
    @admin_id
FROM DUAL
WHERE @cashout_destination_id IS NOT NULL
  AND @first_pal_id IS NOT NULL
  AND @admin_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM cashout_requests cr
      WHERE cr.pal_ID = @first_pal_id
        AND cr.points_requested = 30.00
        AND cr.status = 'Approved'
  );

INSERT INTO silverpoints_ledger (User_ID, visit_ID, entry_type, points_amount, balance_after, description)
SELECT
    @first_pal_user_id,
    NULL,
    'Debit',
    30.00,
    COALESCE((
        SELECT l2.balance_after
        FROM silverpoints_ledger l2
        WHERE l2.User_ID = @first_pal_user_id
        ORDER BY l2.ledger_entry_ID DESC
        LIMIT 1
    ), 38.00) - 30.00,
    'Cashout approved to wallet (seeded)'
FROM DUAL
WHERE @first_pal_user_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM silverpoints_ledger l
      WHERE l.User_ID = @first_pal_user_id
        AND l.description = 'Cashout approved to wallet (seeded)'
  );

-- 12) Admin visibility: broadcast + notifications.
INSERT INTO admin_broadcasts (admin_ID, title, message_body, target_role, severity_level, expires_at)
SELECT
    @admin_id,
    'CareNest Billing Rules Active',
    'Seniors start with 100 points, visit fee split with 5% platform insurance, proxy gifting and pal cashout are enabled.',
    'all',
    'Info',
    DATE_ADD(NOW(), INTERVAL 30 DAY)
FROM DUAL
WHERE @admin_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM admin_broadcasts ab
      WHERE ab.title = 'CareNest Billing Rules Active'
  );

INSERT INTO notifications (usersUser_ID, type, title, message_body, entity_ID, entity_type)
SELECT
    @admin_id,
    'Finance',
    'Platform revenue updated',
    'A seeded completed visit and cashout flow were posted. Review dashboard reports.',
    @seed_visit_id,
    'visit_requests'
FROM DUAL
WHERE @admin_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM notifications n
      WHERE n.usersUser_ID = @admin_id
        AND n.title = 'Platform revenue updated'
  );

COMMIT;
