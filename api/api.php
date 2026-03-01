<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('APP_ALLOWED_ORIGINS') ?: '')));
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$route = isset($_GET['route']) ? (string) $_GET['route'] : '';

if ($pathInfo === '' && $route !== '') {
    $pathInfo = '/' . ltrim($route, '/');
}
if ($pathInfo === '') {
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (strpos($requestUri, 'api.php/') !== false) {
        $after = explode('api.php/', $requestUri, 2)[1] ?? '';
        $after = explode('?', $after, 2)[0];
        $pathInfo = '/' . ltrim($after, '/');
    }
}
if ($pathInfo === '') {
    $pathInfo = '/';
}

$segments = array_values(array_filter(explode('/', trim($pathInfo, '/'))));
$body = get_json_body();

try {
    route($method, $segments, $body);
} catch (Throwable $e) {
    // log the exception so developers can inspect server logs
    error_log('API exception: ' . $e->getMessage());
    json_response([
        'status' => 'error',
        'message' => APP_DEBUG === '1' ? ('Server error: ' . $e->getMessage()) : 'Server error',
    ], 500);
}

function route(string $method, array $segments, array $body): void
{
    if (count($segments) === 0) {
        json_response([
            'status' => 'success',
            'message' => 'Smart Influencing API is running',
        ]);
    }

    $pdo = db();
    if (APP_AUTO_MIGRATE === '1') {
        ensure_schema($pdo);
    }

    if ($method === 'POST' && $segments === ['brand', 'login']) {
        brand_login($pdo, $body);
    }
    if ($method === 'POST' && $segments === ['brand', 'register']) {
        brand_register($pdo, $body);
    }
    if ($method === 'POST' && $segments === ['influencer', 'login']) {
        influencer_login($pdo, $body);
    }
    if ($method === 'POST' && $segments === ['influencer', 'register']) {
        influencer_register($pdo, $body);
    }

    $actor = require_auth();

    if ($method === 'GET' && $segments === ['brand', 'influencers']) {
        require_role($actor, 'brand');
        get_influencers_catalog($pdo);
    }
    if (count($segments) === 3 && $method === 'GET' && $segments[0] === 'brand' && $segments[1] === 'shortlist') {
        require_role($actor, 'brand');
        $brandId = (int) $segments[2];
        ensure_actor_id($actor, $brandId);
        get_brand_shortlist($pdo, $brandId);
    }
    if ($method === 'POST' && $segments === ['brand', 'shortlist', 'toggle']) {
        require_role($actor, 'brand');
        toggle_brand_shortlist($pdo, (int) $actor['id'], (int) ($body['influencer_id'] ?? 0));
    }

    if (count($segments) === 3 && $segments[0] === 'brand' && $segments[1] === 'profile') {
        $id = (int) $segments[2];
        if ($method === 'GET') {
            get_brand_profile($pdo, $id);
        }
        if ($method === 'PUT') {
            require_role($actor, 'brand');
            ensure_actor_id($actor, $id);
            update_brand_profile($pdo, $id, $body);
        }
    }
    if (count($segments) === 3 && $segments[0] === 'influencer' && $segments[1] === 'profile') {
        $id = (int) $segments[2];
        if ($method === 'GET') {
            get_influencer_profile($pdo, $id);
        }
        if ($method === 'PUT') {
            require_role($actor, 'influencer');
            ensure_actor_id($actor, $id);
            update_influencer_profile($pdo, $id, $body);
        }
    }
    if (count($segments) === 3 && $segments[0] === 'influencer' && $segments[1] === 'wallet') {
        require_role($actor, 'influencer');
        $influencerId = (int) $segments[2];
        ensure_actor_id($actor, $influencerId);
        if ($method === 'GET') {
            get_wallet($pdo, $influencerId);
        }
        if ($method === 'PUT') {
            update_wallet($pdo, $influencerId, $body);
        }
    }

    if ($method === 'GET' && $segments === ['campaign']) {
        get_campaigns($pdo);
    }
    if ($method === 'POST' && $segments === ['campaign', 'create']) {
        require_role($actor, 'brand');
        create_campaign($pdo, $body, (int) $actor['id']);
    }
    if (count($segments) === 3 && $method === 'PUT' && $segments[0] === 'campaign' && $segments[1] === 'update') {
        require_role($actor, 'brand');
        update_campaign($pdo, (int) $segments[2], $body, (int) $actor['id']);
    }
    if ($method === 'POST' && $segments === ['campaign', 'apply']) {
        require_role($actor, 'influencer');
        apply_campaign($pdo, $body, (int) $actor['id']);
    }
    if (count($segments) === 3 && $method === 'GET' && $segments[0] === 'campaign' && $segments[1] === 'details') {
        get_campaign_details($pdo, (int) $segments[2]);
    }
    if (count($segments) === 3 && $method === 'GET' && $segments[0] === 'campaign' && $segments[1] === 'my-campaigns') {
        require_role($actor, 'brand');
        ensure_actor_id($actor, (int) $segments[2]);
        get_my_campaigns($pdo, (int) $actor['id']);
    }
    if (count($segments) === 3 && $method === 'GET' && $segments[0] === 'campaign' && $segments[1] === 'applicants') {
        require_role($actor, 'brand');
        get_campaign_applicants($pdo, (int) $segments[2], (int) $actor['id']);
    }
    if (count($segments) === 3 && $method === 'PUT' && $segments[0] === 'campaign' && $segments[1] === 'application') {
        require_role($actor, 'brand');
        update_application_status($pdo, (int) $segments[2], $body, (int) $actor['id']);
    }
    if (count($segments) === 3 && $method === 'PUT' && $segments[0] === 'campaign' && $segments[1] === 'application-progress') {
        require_role($actor, 'influencer');
        update_application_progress($pdo, (int) $segments[2], $body, (int) $actor['id']);
    }
    if (count($segments) === 3 && $method === 'GET' && $segments[0] === 'influencer' && $segments[1] === 'applied-campaigns') {
        require_role($actor, 'influencer');
        ensure_actor_id($actor, (int) $segments[2]);
        get_influencer_applied_campaigns($pdo, (int) $actor['id']);
    }
    if (count($segments) === 2 && $method === 'DELETE' && $segments[0] === 'campaigns') {
        require_role($actor, 'brand');
        delete_campaign($pdo, (int) $segments[1], (int) $actor['id']);
    }

    if (count($segments) === 3 && $method === 'GET' && $segments[0] === 'chat' && $segments[1] === 'list') {
        $userId = (int) $segments[2];
        $type = (string) ($_GET['type'] ?? '');
        ensure_actor_id($actor, $userId);
        if ($type !== $actor['type']) {
            json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
        }
        get_chat_list($pdo, $userId, $type);
    }
    if ($method === 'POST' && $segments === ['chat', 'get']) {
        get_or_create_chat($pdo, $body, $actor);
    }
    if (count($segments) === 3 && $method === 'GET' && $segments[0] === 'chat' && $segments[1] === 'messages') {
        get_chat_messages($pdo, (int) $segments[2], $actor);
    }
    if ($method === 'POST' && $segments === ['chat', 'message']) {
        send_chat_message($pdo, $body, $actor);
    }

    if (count($segments) === 3 && $method === 'GET' && $segments[0] === 'notification' && $segments[1] === 'list') {
        $userId = (int) $segments[2];
        $type = (string) ($_GET['type'] ?? '');
        ensure_actor_id($actor, $userId);
        if ($type !== $actor['type']) {
            json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
        }
        get_notifications($pdo, $userId, $type);
    }
    if (count($segments) === 3 && $method === 'PUT' && $segments[0] === 'notification' && $segments[1] === 'read') {
        mark_notification_read($pdo, (int) $segments[2], $actor);
    }
    if ($method === 'PUT' && $segments === ['notification', 'read-all']) {
        mark_all_notifications_read($pdo, $actor);
    }

    json_response(['status' => 'error', 'message' => 'Route not found'], 404);
}

function require_auth(): array
{
    $token = get_bearer_token();
    if ($token === '') {
        json_response(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }

    $payload = verify_token($token);
    if (!$payload) {
        json_response(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }

    return [
        'id' => (int) ($payload['id'] ?? 0),
        'type' => (string) ($payload['type'] ?? ''),
    ];
}

function get_bearer_token(): string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '');
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }
    if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        return '';
    }
    return trim($matches[1]);
}

function require_role(array $actor, string $role): void
{
    if (($actor['type'] ?? '') !== $role) {
        json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
    }
}

function ensure_actor_id(array $actor, int $expectedId): void
{
    if ((int) ($actor['id'] ?? 0) !== $expectedId) {
        json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
    }
}

function brand_login(PDO $pdo, array $body): void
{
    $email = trim((string) ($body['email'] ?? ''));
    $password = (string) ($body['password'] ?? '');

    $stmt = $pdo->prepare('SELECT id, brand_name, email, password FROM brands WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $brand = $stmt->fetch();

    if (!$brand || !password_verify($password, (string) $brand['password'])) {
        json_response(['status' => 'error', 'message' => 'Invalid credentials'], 401);
    }

    json_response([
        'status' => 'success',
        'data' => [
            'id' => (int) $brand['id'],
            'brand_name' => (string) $brand['brand_name'],
            'email' => (string) $brand['email'],
            'token' => create_token(['id' => (int) $brand['id'], 'type' => 'brand']),
        ],
    ]);
}

function brand_register(PDO $pdo, array $body): void
{
    $brandName = trim((string) ($body['brand_name'] ?? ''));
    $email = trim((string) ($body['email'] ?? ''));
    $password = (string) ($body['password'] ?? '');

    if ($brandName === '' || $email === '' || strlen($password) < 6) {
        json_response(['status' => 'error', 'message' => 'Invalid input'], 400);
    }

    $check = $pdo->prepare('SELECT id FROM brands WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) {
        json_response(['status' => 'error', 'message' => 'Email already exists'], 400);
    }

    $stmt = $pdo->prepare('INSERT INTO brands (brand_name, email, password) VALUES (?, ?, ?)');
    try {
        $stmt->execute([$brandName, $email, password_hash($password, PASSWORD_BCRYPT)]);
    } catch (PDOException $e) {
        // duplicate email will throw SQLSTATE 23000 due to unique constraint
        if ($e->getCode() === '23000') {
            json_response(['status' => 'error', 'message' => 'Email already exists'], 400);
        }
        throw $e;
    }
    $id = (int) $pdo->lastInsertId();

    json_response([
        'status' => 'success',
        'data' => [
            'id' => $id,
            'brand_name' => $brandName,
            'email' => $email,
            'token' => create_token(['id' => $id, 'type' => 'brand']),
        ],
    ], 201);
}

function influencer_login(PDO $pdo, array $body): void
{
    $email = trim((string) ($body['email'] ?? ''));
    $password = (string) ($body['password'] ?? '');

    $stmt = $pdo->prepare('SELECT id, name, email, password FROM influencers WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $influencer = $stmt->fetch();

    if (!$influencer || !password_verify($password, (string) $influencer['password'])) {
        json_response(['status' => 'error', 'message' => 'Invalid credentials'], 401);
    }

    json_response([
        'status' => 'success',
        'data' => [
            'id' => (int) $influencer['id'],
            'name' => (string) $influencer['name'],
            'email' => (string) $influencer['email'],
            'token' => create_token(['id' => (int) $influencer['id'], 'type' => 'influencer']),
        ],
    ]);
}

function influencer_register(PDO $pdo, array $body): void
{
    $name = trim((string) ($body['name'] ?? ''));
    $email = trim((string) ($body['email'] ?? ''));
    $password = (string) ($body['password'] ?? '');

    if ($name === '' || $email === '' || strlen($password) < 6) {
        json_response(['status' => 'error', 'message' => 'Invalid input'], 400);
    }

    $check = $pdo->prepare('SELECT id FROM influencers WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) {
        json_response(['status' => 'error', 'message' => 'Email already exists'], 400);
    }

    $stmt = $pdo->prepare('INSERT INTO influencers (name, email, password) VALUES (?, ?, ?)');
    try {
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT)]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            json_response(['status' => 'error', 'message' => 'Email already exists'], 400);
        }
        throw $e;
    }
    $id = (int) $pdo->lastInsertId();

    $walletStmt = $pdo->prepare('INSERT INTO wallets (influencer_id, total_earnings, account_number, ifsc_code) VALUES (?, 0, "", "")');
    try {
        $walletStmt->execute([$id]);
    } catch (PDOException $e) {
        // ignore wallet creation errors; user can update later
    }

    json_response([
        'status' => 'success',
        'data' => [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'token' => create_token(['id' => $id, 'type' => 'influencer']),
        ],
    ], 201);
}

function get_influencers_catalog(PDO $pdo): void
{
    $rows = $pdo->query('SELECT id, name, email, contact, about, experience, hourly_rate, instagram, youtube, facebook, twitter, rating FROM influencers ORDER BY id DESC')->fetchAll();
    json_response(['status' => 'success', 'data' => $rows]);
}

function get_brand_profile(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('SELECT id, brand_name, email, contact, about, owner_name, owner_linkedin, instagram, facebook, twitter FROM brands WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $profile = $stmt->fetch();
    if (!$profile) {
        json_response(['status' => 'error', 'message' => 'Brand not found'], 404);
    }
    json_response(['status' => 'success', 'data' => $profile]);
}

function update_brand_profile(PDO $pdo, int $id, array $body): void
{
    $stmt = $pdo->prepare('UPDATE brands SET brand_name = ?, contact = ?, about = ?, owner_name = ?, owner_linkedin = ?, instagram = ?, facebook = ?, twitter = ? WHERE id = ?');
    $stmt->execute([
        (string) ($body['brand_name'] ?? ''),
        (string) ($body['contact'] ?? ''),
        (string) ($body['about'] ?? ''),
        (string) ($body['owner_name'] ?? ''),
        (string) ($body['owner_linkedin'] ?? ''),
        (string) ($body['instagram'] ?? ''),
        (string) ($body['facebook'] ?? ''),
        (string) ($body['twitter'] ?? ''),
        $id,
    ]);
    get_brand_profile($pdo, $id);
}

function get_influencer_profile(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('SELECT id, name, email, contact, about, experience, hourly_rate, instagram, youtube, facebook, twitter, rating FROM influencers WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $profile = $stmt->fetch();
    if (!$profile) {
        json_response(['status' => 'error', 'message' => 'Influencer not found'], 404);
    }
    json_response(['status' => 'success', 'data' => $profile]);
}

function update_influencer_profile(PDO $pdo, int $id, array $body): void
{
    $stmt = $pdo->prepare('UPDATE influencers SET name = ?, contact = ?, about = ?, experience = ?, hourly_rate = ?, instagram = ?, youtube = ?, facebook = ?, twitter = ? WHERE id = ?');
    $stmt->execute([
        (string) ($body['name'] ?? ''),
        (string) ($body['contact'] ?? ''),
        (string) ($body['about'] ?? ''),
        (string) ($body['experience'] ?? ''),
        (float) ($body['hourly_rate'] ?? 0),
        (string) ($body['instagram'] ?? ''),
        (string) ($body['youtube'] ?? ''),
        (string) ($body['facebook'] ?? ''),
        (string) ($body['twitter'] ?? ''),
        $id,
    ]);
    get_influencer_profile($pdo, $id);
}

function get_wallet(PDO $pdo, int $influencerId): void
{
    $stmt = $pdo->prepare('SELECT * FROM wallets WHERE influencer_id = ? LIMIT 1');
    $stmt->execute([$influencerId]);
    $wallet = $stmt->fetch();
    if (!$wallet) {
        $insert = $pdo->prepare('INSERT INTO wallets (influencer_id, total_earnings, account_number, ifsc_code) VALUES (?, 0, "", "")');
        $insert->execute([$influencerId]);
        $stmt->execute([$influencerId]);
        $wallet = $stmt->fetch();
    }
    json_response(['status' => 'success', 'data' => $wallet]);
}

function update_wallet(PDO $pdo, int $influencerId, array $body): void
{
    $stmt = $pdo->prepare('UPDATE wallets SET account_number = ?, ifsc_code = ? WHERE influencer_id = ?');
    $stmt->execute([
        (string) ($body['account_number'] ?? ''),
        (string) ($body['ifsc_code'] ?? ''),
        $influencerId,
    ]);
    get_wallet($pdo, $influencerId);
}

function get_campaigns(PDO $pdo): void
{
    $status = $_GET['status'] ?? null;
    if ($status !== null) {
        $stmt = $pdo->prepare('SELECT c.*, b.brand_name FROM campaigns c LEFT JOIN brands b ON b.id = c.brand_id WHERE c.status = ? ORDER BY c.id DESC');
        $stmt->execute([(string) $status]);
    } else {
        $stmt = $pdo->query('SELECT c.*, b.brand_name FROM campaigns c LEFT JOIN brands b ON b.id = c.brand_id ORDER BY c.id DESC');
    }
    json_response(['status' => 'success', 'data' => $stmt->fetchAll()]);
}

function create_campaign(PDO $pdo, array $body, int $brandId): void
{
    $field = trim((string) ($body['field'] ?? ''));
    $overview = trim((string) ($body['overview'] ?? ''));
    if ($field === '' || $overview === '') {
        json_response(['status' => 'error', 'message' => 'Field and overview are required'], 400);
    }

    $stmt = $pdo->prepare('INSERT INTO campaigns (brand_id, field, overview, work_details, duration, payout, status, created_at) VALUES (?, ?, ?, ?, ?, ?, "active", NOW())');
    $stmt->execute([
        $brandId,
        $field,
        $overview,
        (string) ($body['work_details'] ?? ''),
        (string) ($body['duration'] ?? ''),
        (float) ($body['payout'] ?? 0),
    ]);

    json_response([
        'status' => 'success',
        'data' => ['id' => (int) $pdo->lastInsertId()],
    ], 201);
}

function update_campaign(PDO $pdo, int $campaignId, array $body, int $brandId): void
{
    $stmt = $pdo->prepare('UPDATE campaigns SET field = ?, overview = ?, work_details = ?, duration = ?, payout = ? WHERE id = ? AND brand_id = ?');
    $stmt->execute([
        (string) ($body['field'] ?? ''),
        (string) ($body['overview'] ?? ''),
        (string) ($body['work_details'] ?? ''),
        (string) ($body['duration'] ?? ''),
        (float) ($body['payout'] ?? 0),
        $campaignId,
        $brandId,
    ]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT id FROM campaigns WHERE id = ? AND brand_id = ? LIMIT 1');
        $check->execute([$campaignId, $brandId]);
        if (!$check->fetch()) {
            json_response(['status' => 'error', 'message' => 'Campaign not found'], 404);
        }
    }

    get_campaign_details($pdo, $campaignId);
}

function apply_campaign(PDO $pdo, array $body, int $influencerId): void
{
    $campaignId = (int) ($body['campaign_id'] ?? 0);
    if ($campaignId <= 0) {
        json_response(['status' => 'error', 'message' => 'Invalid campaign'], 400);
    }

    $campaignStmt = $pdo->prepare('SELECT id, brand_id, field FROM campaigns WHERE id = ? LIMIT 1');
    $campaignStmt->execute([$campaignId]);
    $campaign = $campaignStmt->fetch();
    if (!$campaign) {
        json_response(['status' => 'error', 'message' => 'Campaign not found'], 404);
    }

    $check = $pdo->prepare('SELECT id FROM applications WHERE campaign_id = ? AND influencer_id = ? LIMIT 1');
    $check->execute([$campaignId, $influencerId]);
    if ($check->fetch()) {
        json_response(['status' => 'error', 'message' => 'Already applied'], 400);
    }

    $stmt = $pdo->prepare('INSERT INTO applications (campaign_id, influencer_id, status, created_at) VALUES (?, ?, "waiting", NOW())');
    $stmt->execute([$campaignId, $influencerId]);
    $applicationId = (int) $pdo->lastInsertId();

    $infStmt = $pdo->prepare('SELECT name FROM influencers WHERE id = ? LIMIT 1');
    $infStmt->execute([$influencerId]);
    $influencer = $infStmt->fetch();
    $influencerName = (string) ($influencer['name'] ?? 'An influencer');

    create_notification(
        $pdo,
        (int) $campaign['brand_id'],
        'brand',
        'New Campaign Application',
        $influencerName . ' applied to "' . (string) $campaign['field'] . '"',
        'application',
        $applicationId
    );

    json_response(['status' => 'success', 'data' => ['id' => $applicationId]], 201);
}

function get_campaign_details(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('SELECT c.*, b.brand_name FROM campaigns c LEFT JOIN brands b ON b.id = c.brand_id WHERE c.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $campaign = $stmt->fetch();
    if (!$campaign) {
        json_response(['status' => 'error', 'message' => 'Campaign not found'], 404);
    }
    json_response(['status' => 'success', 'data' => $campaign]);
}

function get_my_campaigns(PDO $pdo, int $brandId): void
{
    $stmt = $pdo->prepare('SELECT c.*, 
        (SELECT COUNT(*) FROM applications a WHERE a.campaign_id = c.id) AS total_applications,
        (SELECT COUNT(*) FROM applications a WHERE a.campaign_id = c.id AND a.status = "accepted") AS accepted_count,
        (SELECT COUNT(*) FROM applications a WHERE a.campaign_id = c.id AND a.status = "accepted" AND a.progress >= 100) AS completed_influencers,
        (SELECT COALESCE(ROUND(AVG(a.progress), 0), 0) FROM applications a WHERE a.campaign_id = c.id AND a.status = "accepted") AS avg_progress
        FROM campaigns c WHERE c.brand_id = ? ORDER BY c.id DESC');
    $stmt->execute([$brandId]);
    json_response(['status' => 'success', 'data' => $stmt->fetchAll()]);
}

function get_campaign_applicants(PDO $pdo, int $campaignId, int $brandId): void
{
    $ownerCheck = $pdo->prepare('SELECT id FROM campaigns WHERE id = ? AND brand_id = ? LIMIT 1');
    $ownerCheck->execute([$campaignId, $brandId]);
    if (!$ownerCheck->fetch()) {
        json_response(['status' => 'error', 'message' => 'Campaign not found'], 404);
    }

    $stmt = $pdo->prepare('SELECT a.id, a.status, a.progress, a.progress_note, a.progress_updated_at, a.campaign_id, a.influencer_id, i.name, i.about, i.rating, i.hourly_rate
        FROM applications a
        INNER JOIN influencers i ON i.id = a.influencer_id
        WHERE a.campaign_id = ?
        ORDER BY a.id DESC');
    $stmt->execute([$campaignId]);
    json_response(['status' => 'success', 'data' => $stmt->fetchAll()]);
}

function update_application_status(PDO $pdo, int $applicationId, array $body, int $brandId): void
{
    $status = (string) ($body['status'] ?? 'waiting');
    if (!in_array($status, ['waiting', 'accepted', 'rejected'], true)) {
        json_response(['status' => 'error', 'message' => 'Invalid status'], 400);
    }

    $appStmt = $pdo->prepare('SELECT a.id, a.influencer_id, a.campaign_id, c.field
        FROM applications a
        INNER JOIN campaigns c ON c.id = a.campaign_id
        WHERE a.id = ? AND c.brand_id = ?
        LIMIT 1');
    $appStmt->execute([$applicationId, $brandId]);
    $app = $appStmt->fetch();
    if (!$app) {
        json_response(['status' => 'error', 'message' => 'Application not found'], 404);
    }

    $stmt = $pdo->prepare('UPDATE applications SET status = ? WHERE id = ?');
    $stmt->execute([$status, $applicationId]);

    create_notification(
        $pdo,
        (int) $app['influencer_id'],
        'influencer',
        'Application Updated',
        'Your application for "' . (string) $app['field'] . '" is now ' . $status,
        'application',
        $applicationId
    );

    json_response(['status' => 'success', 'data' => ['id' => $applicationId, 'status' => $status]]);
}

function update_application_progress(PDO $pdo, int $applicationId, array $body, int $influencerId): void
{
    $progress = (int) ($body['progress'] ?? -1);
    $progressNote = trim((string) ($body['progress_note'] ?? ''));

    if ($progress < 0 || $progress > 100) {
        json_response(['status' => 'error', 'message' => 'Invalid progress payload'], 400);
    }

    if (strlen($progressNote) > 500) {
        $progressNote = substr($progressNote, 0, 500);
    }

    $check = $pdo->prepare('SELECT a.id, a.status, c.brand_id, c.field
        FROM applications a
        INNER JOIN campaigns c ON c.id = a.campaign_id
        WHERE a.id = ? AND a.influencer_id = ?
        LIMIT 1');
    $check->execute([$applicationId, $influencerId]);
    $application = $check->fetch();
    if (!$application) {
        json_response(['status' => 'error', 'message' => 'Application not found'], 404);
    }
    if ((string) $application['status'] !== 'accepted') {
        json_response(['status' => 'error', 'message' => 'Only accepted applications can update progress'], 400);
    }

    $stmt = $pdo->prepare('UPDATE applications SET progress = ?, progress_note = ?, progress_updated_at = NOW() WHERE id = ?');
    $stmt->execute([$progress, $progressNote, $applicationId]);

    create_notification(
        $pdo,
        (int) $application['brand_id'],
        'brand',
        'Progress Updated',
        'Influencer progress for "' . (string) $application['field'] . '" is now ' . $progress . '%',
        'application',
        $applicationId
    );

    json_response([
        'status' => 'success',
        'data' => [
            'id' => $applicationId,
            'progress' => $progress,
            'progress_note' => $progressNote,
        ],
    ]);
}

function get_influencer_applied_campaigns(PDO $pdo, int $influencerId): void
{
    $stmt = $pdo->prepare('SELECT c.*, b.brand_name, a.id AS application_id, a.status AS application_status, a.progress, a.progress_note, a.progress_updated_at
        FROM applications a
        INNER JOIN campaigns c ON c.id = a.campaign_id
        LEFT JOIN brands b ON b.id = c.brand_id
        WHERE a.influencer_id = ?
        ORDER BY a.id DESC');
    $stmt->execute([$influencerId]);
    json_response(['status' => 'success', 'data' => $stmt->fetchAll()]);
}

function delete_campaign(PDO $pdo, int $campaignId, int $brandId): void
{
    $check = $pdo->prepare('SELECT id FROM campaigns WHERE id = ? AND brand_id = ? LIMIT 1');
    $check->execute([$campaignId, $brandId]);
    if (!$check->fetch()) {
        json_response(['status' => 'error', 'message' => 'Campaign not found'], 404);
    }

    $pdo->prepare('DELETE FROM applications WHERE campaign_id = ?')->execute([$campaignId]);
    $pdo->prepare('DELETE FROM campaigns WHERE id = ? AND brand_id = ?')->execute([$campaignId, $brandId]);
    json_response(['status' => 'success', 'data' => ['deleted' => true]]);
}

function get_chat_list(PDO $pdo, int $userId, string $type): void
{
    if ($type === 'brand') {
        $stmt = $pdo->prepare('SELECT c.id, c.influencer_id, i.name
            FROM chats c
            INNER JOIN influencers i ON i.id = c.influencer_id
            WHERE c.brand_id = ?
            ORDER BY c.id DESC');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['unread_count'] = 0;
        }
        json_response(['status' => 'success', 'data' => $rows]);
    }

    $stmt = $pdo->prepare('SELECT c.id, c.brand_id, b.brand_name
        FROM chats c
        INNER JOIN brands b ON b.id = c.brand_id
        WHERE c.influencer_id = ?
        ORDER BY c.id DESC');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['unread_count'] = 0;
    }
    json_response(['status' => 'success', 'data' => $rows]);
}

function get_or_create_chat(PDO $pdo, array $body, array $actor): void
{
    $influencerId = (int) ($body['influencer_id'] ?? 0);
    $brandId = (int) ($body['brand_id'] ?? 0);
    if ($influencerId <= 0 || $brandId <= 0) {
        json_response(['status' => 'error', 'message' => 'Invalid chat payload'], 400);
    }

    if ($actor['type'] === 'influencer' && (int) $actor['id'] !== $influencerId) {
        json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
    }
    if ($actor['type'] === 'brand' && (int) $actor['id'] !== $brandId) {
        json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
    }

    $find = $pdo->prepare('SELECT id FROM chats WHERE influencer_id = ? AND brand_id = ? LIMIT 1');
    $find->execute([$influencerId, $brandId]);
    $chat = $find->fetch();
    if ($chat) {
        json_response(['status' => 'success', 'data' => ['chat_id' => (int) $chat['id']]]);
    }

    $stmt = $pdo->prepare('INSERT INTO chats (influencer_id, brand_id, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([$influencerId, $brandId]);
    json_response(['status' => 'success', 'data' => ['chat_id' => (int) $pdo->lastInsertId()]], 201);
}

function get_chat_messages(PDO $pdo, int $chatId, array $actor): void
{
    $chat = get_chat_if_participant($pdo, $chatId, $actor);
    if (!$chat) {
        json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
    }

    $stmt = $pdo->prepare('SELECT id, chat_id, sender_id, sender_type, message, created_at FROM messages WHERE chat_id = ? ORDER BY id ASC');
    $stmt->execute([$chatId]);
    json_response(['status' => 'success', 'data' => $stmt->fetchAll()]);
}

function send_chat_message(PDO $pdo, array $body, array $actor): void
{
    $chatId = (int) ($body['chat_id'] ?? 0);
    $senderId = (int) ($body['sender_id'] ?? 0);
    $senderType = (string) ($body['sender_type'] ?? '');
    $message = trim((string) ($body['message'] ?? ''));

    if ($chatId <= 0 || $senderId <= 0 || $message === '' || !in_array($senderType, ['brand', 'influencer'], true)) {
        json_response(['status' => 'error', 'message' => 'Invalid message payload'], 400);
    }
    if ($senderId !== (int) $actor['id'] || $senderType !== (string) $actor['type']) {
        json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
    }

    $chat = get_chat_if_participant($pdo, $chatId, $actor);
    if (!$chat) {
        json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
    }

    $stmt = $pdo->prepare('INSERT INTO messages (chat_id, sender_id, sender_type, message, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$chatId, $senderId, $senderType, $message]);

    $id = (int) $pdo->lastInsertId();
    $fetch = $pdo->prepare('SELECT id, chat_id, sender_id, sender_type, message, created_at FROM messages WHERE id = ? LIMIT 1');
    $fetch->execute([$id]);
    $created = $fetch->fetch();

    if ($senderType === 'brand') {
        create_notification($pdo, (int) $chat['influencer_id'], 'influencer', 'New Message', 'You received a new message from a brand.', 'chat', $chatId);
    } else {
        create_notification($pdo, (int) $chat['brand_id'], 'brand', 'New Message', 'You received a new message from an influencer.', 'chat', $chatId);
    }

    json_response(['status' => 'success', 'data' => $created], 201);
}

function get_chat_if_participant(PDO $pdo, int $chatId, array $actor): ?array
{
    if ($actor['type'] === 'brand') {
        $stmt = $pdo->prepare('SELECT id, influencer_id, brand_id FROM chats WHERE id = ? AND brand_id = ? LIMIT 1');
        $stmt->execute([$chatId, (int) $actor['id']]);
        $chat = $stmt->fetch();
        return $chat ?: null;
    }
    $stmt = $pdo->prepare('SELECT id, influencer_id, brand_id FROM chats WHERE id = ? AND influencer_id = ? LIMIT 1');
    $stmt->execute([$chatId, (int) $actor['id']]);
    $chat = $stmt->fetch();
    return $chat ?: null;
}

function create_notification(PDO $pdo, int $userId, string $userType, string $title, string $message, string $entityType = '', int $entityId = 0): void
{
    if ($userId <= 0 || !in_array($userType, ['brand', 'influencer'], true)) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, user_type, title, message, entity_type, entity_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())');
    $stmt->execute([$userId, $userType, $title, $message, $entityType, $entityId > 0 ? $entityId : null]);
}

function get_notifications(PDO $pdo, int $userId, string $userType): void
{
    $stmt = $pdo->prepare('SELECT id, title, message, entity_type, entity_id, is_read, created_at
        FROM notifications
        WHERE user_id = ? AND user_type = ?
        ORDER BY id DESC
        LIMIT 80');
    $stmt->execute([$userId, $userType]);
    $rows = $stmt->fetchAll();
    $unread = 0;
    foreach ($rows as $row) {
        if ((int) $row['is_read'] === 0) {
            $unread++;
        }
    }
    json_response(['status' => 'success', 'data' => $rows, 'meta' => ['unread_count' => $unread]]);
}

function mark_notification_read(PDO $pdo, int $notificationId, array $actor): void
{
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND user_type = ?');
    $stmt->execute([$notificationId, (int) $actor['id'], (string) $actor['type']]);
    json_response(['status' => 'success', 'data' => ['id' => $notificationId]]);
}

function mark_all_notifications_read(PDO $pdo, array $actor): void
{
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_type = ?');
    $stmt->execute([(int) $actor['id'], (string) $actor['type']]);
    json_response(['status' => 'success', 'data' => ['updated' => $stmt->rowCount()]]);
}

function get_brand_shortlist(PDO $pdo, int $brandId): void
{
    $stmt = $pdo->prepare('SELECT s.id, s.created_at, i.id AS influencer_id, i.name, i.about, i.rating, i.hourly_rate, i.instagram, i.youtube
        FROM brand_shortlists s
        INNER JOIN influencers i ON i.id = s.influencer_id
        WHERE s.brand_id = ?
        ORDER BY s.id DESC');
    $stmt->execute([$brandId]);
    json_response(['status' => 'success', 'data' => $stmt->fetchAll()]);
}

function toggle_brand_shortlist(PDO $pdo, int $brandId, int $influencerId): void
{
    if ($influencerId <= 0) {
        json_response(['status' => 'error', 'message' => 'Invalid influencer'], 400);
    }

    $exists = $pdo->prepare('SELECT id FROM brand_shortlists WHERE brand_id = ? AND influencer_id = ? LIMIT 1');
    $exists->execute([$brandId, $influencerId]);
    $row = $exists->fetch();
    if ($row) {
        $pdo->prepare('DELETE FROM brand_shortlists WHERE id = ?')->execute([(int) $row['id']]);
        json_response(['status' => 'success', 'data' => ['action' => 'removed']]);
    }

    $checkInfluencer = $pdo->prepare('SELECT id FROM influencers WHERE id = ? LIMIT 1');
    $checkInfluencer->execute([$influencerId]);
    if (!$checkInfluencer->fetch()) {
        json_response(['status' => 'error', 'message' => 'Influencer not found'], 404);
    }

    $stmt = $pdo->prepare('INSERT INTO brand_shortlists (brand_id, influencer_id, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([$brandId, $influencerId]);
    json_response(['status' => 'success', 'data' => ['action' => 'added']]);
}

function ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    ensure_column($pdo, 'applications', 'progress', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');
    ensure_column($pdo, 'applications', 'progress_note', 'TEXT NULL');
    ensure_column($pdo, 'applications', 'progress_updated_at', 'DATETIME NULL');

    $pdo->exec('CREATE TABLE IF NOT EXISTS brand_shortlists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        brand_id INT NOT NULL,
        influencer_id INT NOT NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY uniq_brand_influencer (brand_id, influencer_id),
        CONSTRAINT fk_shortlist_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
        CONSTRAINT fk_shortlist_influencer FOREIGN KEY (influencer_id) REFERENCES influencers(id) ON DELETE CASCADE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_type ENUM("brand","influencer") NOT NULL,
        title VARCHAR(160) NOT NULL,
        message TEXT NOT NULL,
        entity_type VARCHAR(40) DEFAULT "",
        entity_id INT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        INDEX idx_notifications_user (user_id, user_type, is_read, id)
    )');
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $check = $pdo->prepare(sprintf('SHOW COLUMNS FROM `%s` LIKE ?', $table));
    $check->execute([$column]);
    if ($check->fetch()) {
        return;
    }
    $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
}
