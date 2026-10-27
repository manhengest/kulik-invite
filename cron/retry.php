<?php

declare(strict_types=1);

/**
 * Retry pending/failed Deliveries.
 * CLI: php cron/retry.php
 * HTTP: /cron/retry.php?key=RETRY_KEY
 */

require_once dirname(__DIR__) . '/lib/storage.php';
require_once dirname(__DIR__) . '/lib/delivery.php';

$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "config.php missing\n");
    exit(1);
}

/** @var array<string, mixed> $config */
$config = require $configPath;

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $key = (string) ($_GET['key'] ?? '');
    $expected = (string) ($config['retry_key'] ?? '');
    if ($expected === '' || !hash_equals($expected, $key)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden\n";
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
}

$rows = kulik_read_all_requests();
$changed = 0;
$retried = 0;

foreach ($rows as $i => $row) {
    $delivery = $row['delivery'] ?? [];
    $needs = false;
    foreach (['mail', 'guest_mail', 'crm', 'telegram'] as $channel) {
        $status = $delivery[$channel] ?? 'pending';
        if ($status === 'pending' || $status === 'failed') {
            $needs = true;
            break;
        }
    }
    if (!$needs) {
        continue;
    }
    $retried++;
    $updated = kulik_run_deliveries($config, $row);
    if ($updated !== $row) {
        $rows[$i] = $updated;
        $changed++;
    }
}

if ($changed > 0) {
    kulik_rewrite_requests($rows);
}

$result = [
    'ok' => true,
    'retried' => $retried,
    'updated' => $changed,
];

if ($isCli) {
    echo json_encode($result) . PHP_EOL;
} else {
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
