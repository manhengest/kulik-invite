<?php

function kulik_storage_path(): string
{
    return dirname(__DIR__) . '/storage/requests.jsonl';
}

function kulik_ensure_storage(): void
{
    $dir = dirname(kulik_storage_path());
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $path = kulik_storage_path();
    if (!file_exists($path)) {
        touch($path);
        chmod($path, 0640);
    }
}

/**
 * @return list<array<string, mixed>>
 */
function kulik_read_all_requests(): array
{
    kulik_ensure_storage();
    $path = kulik_storage_path();
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        return [];
    }
    $rows = [];
    if (flock($fh, LOCK_SH)) {
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }
        flock($fh, LOCK_UN);
    }
    fclose($fh);
    return $rows;
}

/**
 * Append one Invitation Request. Returns false if form_token already used.
 *
 * @param array<string, mixed> $row
 */
function kulik_append_request(array $row): bool
{
    kulik_ensure_storage();
    $path = kulik_storage_path();
    $fh = fopen($path, 'c+b');
    if ($fh === false) {
        throw new RuntimeException('Cannot open request log');
    }

    try {
        if (!flock($fh, LOCK_EX)) {
            throw new RuntimeException('Cannot lock request log');
        }

        rewind($fh);
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $existing = json_decode($line, true);
            if (
                is_array($existing)
                && isset($existing['form_token'], $row['form_token'])
                && hash_equals((string) $existing['form_token'], (string) $row['form_token'])
            ) {
                return false;
            }
        }

        $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Cannot encode request');
        }
        fwrite($fh, $json . "\n");
        fflush($fh);
        return true;
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

/**
 * Rewrite the whole log with updated rows (used by retry).
 *
 * @param list<array<string, mixed>> $rows
 */
function kulik_rewrite_requests(array $rows): void
{
    kulik_ensure_storage();
    $path = kulik_storage_path();
    $tmp = $path . '.tmp';
    $fh = fopen($tmp, 'wb');
    if ($fh === false) {
        throw new RuntimeException('Cannot open temp log');
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        throw new RuntimeException('Cannot lock temp log');
    }
    foreach ($rows as $row) {
        $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            flock($fh, LOCK_UN);
            fclose($fh);
            throw new RuntimeException('Cannot encode request');
        }
        fwrite($fh, $json . "\n");
    }
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    rename($tmp, $path);
}

function kulik_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return is_string($ip) ? $ip : '0.0.0.0';
}

function kulik_rate_limited(string $ip, int $perHour): bool
{
    if ($perHour <= 0) {
        return false;
    }
    $cutoff = time() - 3600;
    $count = 0;
    foreach (kulik_read_all_requests() as $row) {
        if (($row['ip'] ?? '') !== $ip) {
            continue;
        }
        $ts = strtotime((string) ($row['submitted_at'] ?? '')) ?: 0;
        if ($ts >= $cutoff) {
            $count++;
        }
    }
    return $count >= $perHour;
}
