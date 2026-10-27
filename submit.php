<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/lib/storage.php';
require_once __DIR__ . '/lib/delivery.php';

const KULIK_PROFESSIONS = [
    'Business Owner / Founder',
    'Executive / Partner',
    'Attorney / Legal Professional',
    'Doctor / Medical Professional',
    'Architect / Interior Designer',
    'Real Estate / Developer',
    'Jewelry / Luxury Retail',
    'Other',
];

function kulik_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    kulik_json(405, ['ok' => false, 'error' => 'Method not allowed.']);
}

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    kulik_json(500, ['ok' => false, 'error' => 'Server is not configured yet.']);
}

/** @var array<string, mixed> $config */
$config = require $configPath;

$honeypot = trim((string) ($_POST['website'] ?? ''));
if ($honeypot !== '') {
    kulik_json(200, ['ok' => true]);
}

$formToken = trim((string) ($_POST['form_token'] ?? ''));
if ($formToken === '' || strlen($formToken) > 80) {
    kulik_json(400, ['ok' => false, 'error' => 'Please refresh the page and try again.']);
}

$startedAt = (int) ($_POST['form_started_at'] ?? 0);
$minFill = (int) ($config['min_fill_seconds'] ?? 3);
if ($startedAt <= 0 || (time() * 1000 - $startedAt) < $minFill * 1000) {
    kulik_json(400, ['ok' => false, 'error' => 'Please take a moment and try again.']);
}

$ip = kulik_client_ip();
$limit = (int) ($config['rate_limit_per_hour'] ?? 8);
if (kulik_rate_limited($ip, $limit)) {
    kulik_json(429, ['ok' => false, 'error' => 'Too many requests. Please try again later.']);
}

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$profession = trim((string) ($_POST['profession'] ?? ''));
$company = trim((string) ($_POST['company'] ?? ''));

if ($fullName === '' || mb_strlen($fullName) > 120) {
    kulik_json(400, ['ok' => false, 'error' => 'Please enter your full name.']);
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 200) {
    kulik_json(400, ['ok' => false, 'error' => 'Please enter a valid email.']);
}

if ($phone === '' || mb_strlen($phone) > 40 || !preg_match('/\d{6,}/', preg_replace('/\D+/', '', $phone) ?? '')) {
    kulik_json(400, ['ok' => false, 'error' => 'Please enter a valid phone number.']);
}

if (!in_array($profession, KULIK_PROFESSIONS, true)) {
    kulik_json(400, ['ok' => false, 'error' => 'Please select your profession.']);
}

if (mb_strlen($company) > 160) {
    kulik_json(400, ['ok' => false, 'error' => 'Company name is too long.']);
}

$track = static function (string $key): string {
    $value = trim((string) ($_POST[$key] ?? ''));
    return mb_substr($value, 0, 200);
};

$row = [
    'id' => bin2hex(random_bytes(16)),
    'full_name' => $fullName,
    'email' => $email,
    'phone' => $phone,
    'profession' => $profession,
    'company' => $company,
    'submitted_at' => gmdate('c'),
    'page_url' => $track('page_url'),
    'utm_source' => $track('utm_source'),
    'utm_medium' => $track('utm_medium'),
    'utm_campaign' => $track('utm_campaign'),
    'utm_content' => $track('utm_content'),
    'utm_term' => $track('utm_term'),
    'fbclid' => $track('fbclid'),
    'form_token' => $formToken,
    'ip' => $ip,
    'delivery' => [
        'mail' => 'pending',
        'guest_mail' => 'pending',
        'crm' => 'pending',
        'telegram' => 'pending',
    ],
];

try {
    $inserted = kulik_append_request($row);
} catch (Throwable $e) {
    error_log('KULIK append failed: ' . $e->getMessage());
    kulik_json(500, ['ok' => false, 'error' => 'Could not save your request. Please try again.']);
}

if (!$inserted) {
    // Same form token — treat as success so the Guest is not blocked, no duplicate row
    kulik_json(200, ['ok' => true, 'duplicate' => true]);
}

try {
    $updated = kulik_run_deliveries($config, $row);
    $all = kulik_read_all_requests();
    foreach ($all as $i => $existing) {
        if (($existing['id'] ?? '') === $updated['id']) {
            $all[$i] = $updated;
            break;
        }
    }
    kulik_rewrite_requests($all);
} catch (Throwable $e) {
    error_log('KULIK delivery failed after save: ' . $e->getMessage());
}

kulik_json(200, [
    'ok' => true,
    'id' => $row['id'],
    'message' => 'Thank You! Your Invitation Request Has Been Received.',
]);
