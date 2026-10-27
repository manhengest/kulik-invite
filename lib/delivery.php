<?php

/**
 * @param array<string, mixed> $config
 * @param array<string, mixed> $request
 * @return 'sent'|'failed'|'pending'
 */
function kulik_deliver_mail(array $config, array $request): string
{
    $host = trim((string) ($config['smtp_host'] ?? ''));
    $to = trim((string) ($config['mail_to'] ?? ''));
    if ($host === '' || $to === '') {
        return 'pending';
    }

    $subject = 'New KULIK SYSTEM Event RSVP — November 5';
    $lines = [
        'Full Name: ' . ($request['full_name'] ?? ''),
        'Email: ' . ($request['email'] ?? ''),
        'Phone: ' . ($request['phone'] ?? ''),
        'Profession: ' . ($request['profession'] ?? ''),
        'Company: ' . (($request['company'] ?? '') !== '' ? $request['company'] : '—'),
        'Date & Time of Submission: ' . ($request['submitted_at'] ?? ''),
        'Landing Page URL: ' . ($request['page_url'] ?? ''),
        'UTM Source: ' . ($request['utm_source'] ?? ''),
        'UTM Medium: ' . ($request['utm_medium'] ?? ''),
        'UTM Campaign: ' . ($request['utm_campaign'] ?? ''),
        'UTM Content: ' . ($request['utm_content'] ?? ''),
        'UTM Term: ' . ($request['utm_term'] ?? ''),
        'fbclid: ' . ($request['fbclid'] ?? ''),
        'Request ID: ' . ($request['id'] ?? ''),
    ];
    $body = implode("\n", $lines);

    return kulik_smtp_deliver($config, $to, $subject, $body, 'mail');
}

/**
 * Thank-you email to the Guest who submitted the Invitation Request.
 *
 * @param array<string, mixed> $config
 * @param array<string, mixed> $request
 * @return 'sent'|'failed'|'pending'
 */
function kulik_deliver_guest_mail(array $config, array $request): string
{
    $host = trim((string) ($config['smtp_host'] ?? ''));
    $to = trim((string) ($request['email'] ?? ''));
    if ($host === '' || $to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return 'pending';
    }

    $name = trim((string) ($request['full_name'] ?? ''));
    $greeting = $name !== '' ? 'Dear ' . $name . ',' : 'Dear Guest,';

    $subject = 'Thank you — KULIK SYSTEM Private Grand Opening';
    $body = implode("\n", [
        $greeting,
        '',
        'Thank you for requesting an invitation to the KULIK SYSTEM Private Grand Opening on November 5, 2026. We appreciate your interest in joining our celebration.',
        '',
        'We will call you shortly to confirm the details.',
        '',
        'Warm regards,',
        'KULIK SYSTEM Events',
    ]);

    return kulik_smtp_deliver($config, $to, $subject, $body, 'guest_mail');
}

/**
 * @param array<string, mixed> $config
 * @return 'sent'|'failed'
 */
function kulik_smtp_deliver(
    array $config,
    string $to,
    string $subject,
    string $body,
    string $channel
): string {
    $from = (string) ($config['mail_from'] ?? 'noreply@localhost');
    $fromName = (string) ($config['mail_from_name'] ?? 'KULIK SYSTEM Events');
    $host = (string) ($config['smtp_host'] ?? '');
    $port = (int) ($config['smtp_port'] ?? 587);
    $user = (string) ($config['smtp_user'] ?? '');
    $pass = (string) ($config['smtp_pass'] ?? '');
    $secure = (string) ($config['smtp_secure'] ?? 'tls');

    try {
        kulik_smtp_send($host, $port, $secure, $user, $pass, $from, $fromName, $to, $subject, $body);
        return 'sent';
    } catch (Throwable $e) {
        error_log('KULIK ' . $channel . ' failed: ' . $e->getMessage());
        return 'failed';
    }
}

/**
 * Kommo /api/v4/leads/complex body for one Invitation Request.
 *
 * @param array<string, mixed> $config
 * @param array<string, mixed> $request
 * @return list<array<string, mixed>>
 */
function kulik_kommo_lead_payload(array $config, array $request): array
{
    $fullName = trim((string) ($request['full_name'] ?? ''));
    $email = trim((string) ($request['email'] ?? ''));
    $phone = trim((string) ($request['phone'] ?? ''));
    $profession = trim((string) ($request['profession'] ?? ''));
    $company = trim((string) ($request['company'] ?? ''));
    $requestId = (string) ($request['id'] ?? '');
    $tag = (string) ($config['kommo_tag'] ?? 'KULIK_EVENT_NOV_03');

    $leadName = $fullName !== '' ? $fullName : 'Invitation Request';
    if ($profession !== '') {
        $leadName .= ' — ' . $profession;
    }
    if ($requestId !== '') {
        $leadName .= ' [' . $requestId . ']';
    }

    $customFields = [];
    $utmMap = [
        'utm_source' => (int) ($config['kommo_field_utm_source'] ?? 445644),
        'utm_medium' => (int) ($config['kommo_field_utm_medium'] ?? 445640),
        'utm_campaign' => (int) ($config['kommo_field_utm_campaign'] ?? 445642),
        'utm_content' => (int) ($config['kommo_field_utm_content'] ?? 445638),
        'utm_term' => (int) ($config['kommo_field_utm_term'] ?? 445646),
        'fbclid' => (int) ($config['kommo_field_fbclid'] ?? 445656),
    ];
    foreach ($utmMap as $key => $fieldId) {
        $value = trim((string) ($request[$key] ?? ''));
        if ($value !== '' && $fieldId > 0) {
            $customFields[] = [
                'field_id' => $fieldId,
                'values' => [['value' => $value]],
            ];
        }
    }

    $contact = [
        'name' => $fullName !== '' ? $fullName : 'Guest',
        'custom_fields_values' => [],
    ];
    if ($email !== '') {
        $contact['custom_fields_values'][] = [
            'field_code' => 'EMAIL',
            'values' => [['value' => $email, 'enum_code' => 'WORK']],
        ];
    }
    if ($phone !== '') {
        $contact['custom_fields_values'][] = [
            'field_code' => 'PHONE',
            'values' => [['value' => $phone, 'enum_code' => 'WORK']],
        ];
    }

    $lead = [
        'name' => $leadName,
        'pipeline_id' => (int) ($config['kommo_pipeline_id'] ?? 0),
        'status_id' => (int) ($config['kommo_status_id'] ?? 0),
        'request_id' => $requestId,
        'tags_to_add' => [['name' => $tag]],
        '_embedded' => [
            'contacts' => [$contact],
        ],
    ];
    if ($customFields !== []) {
        $lead['custom_fields_values'] = $customFields;
    }
    if ($company !== '') {
        $lead['_embedded']['companies'] = [['name' => $company]];
    }

    return [$lead];
}

/**
 * @param array<string, mixed> $config
 * @param array<string, mixed> $request
 * @return 'sent'|'failed'|'pending'
 */
function kulik_deliver_crm(array $config, array $request): string
{
    $url = trim((string) ($config['crm_url'] ?? ''));
    if ($url === '') {
        return 'pending';
    }

    $token = trim((string) ($config['crm_token'] ?? ''));
    $isKommo = str_contains($url, 'kommo.com') || str_contains($url, 'amocrm.');

    if ($isKommo) {
        $payload = kulik_kommo_lead_payload($config, $request);
    } else {
        $payload = [
            'id' => $request['id'] ?? '',
            'full_name' => $request['full_name'] ?? '',
            'email' => $request['email'] ?? '',
            'phone' => $request['phone'] ?? '',
            'profession' => $request['profession'] ?? '',
            'company' => $request['company'] ?? '',
            'submitted_at' => $request['submitted_at'] ?? '',
            'source' => 'Event Landing Page',
            'event_name' => 'KULIK SYSTEM Grand Opening — November 5, 2026',
            'tag' => 'KULIK_EVENT_NOV_03',
            'page_url' => $request['page_url'] ?? '',
            'utm_source' => $request['utm_source'] ?? '',
            'utm_medium' => $request['utm_medium'] ?? '',
            'utm_campaign' => $request['utm_campaign'] ?? '',
            'utm_content' => $request['utm_content'] ?? '',
            'utm_term' => $request['utm_term'] ?? '',
            'fbclid' => $request['fbclid'] ?? '',
        ];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return 'failed';
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        error_log('KULIK CRM failed: HTTP ' . $status . ' ' . $err . ' ' . (string) $response);
        return 'failed';
    }
    return 'sent';
}

/**
 * @param array<string, mixed> $config
 * @param array<string, mixed> $request
 * @return 'sent'|'failed'|'pending'
 */
function kulik_deliver_telegram(array $config, array $request): string
{
    $bot = trim((string) ($config['telegram_bot_token'] ?? ''));
    $chat = trim((string) ($config['telegram_chat_id'] ?? ''));
    if ($bot === '' || $chat === '') {
        return 'pending';
    }

    $text = sprintf(
        "New Invitation Request\n%s\n%s\n%s\n%s\n%s\nID: %s",
        $request['full_name'] ?? '',
        $request['email'] ?? '',
        $request['phone'] ?? '',
        $request['profession'] ?? '',
        $request['company'] ?? '—',
        $request['id'] ?? ''
    );

    $url = 'https://api.telegram.org/bot' . rawurlencode($bot) . '/sendMessage';
    $ch = curl_init($url);
    if ($ch === false) {
        return 'failed';
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'chat_id' => $chat,
            'text' => $text,
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        return 'failed';
    }
    return 'sent';
}

/**
 * @param array<string, mixed> $config
 * @param array<string, mixed> $request
 * @return array<string, mixed>
 */
function kulik_run_deliveries(array $config, array $request): array
{
    $delivery = $request['delivery'] ?? [
        'mail' => 'pending',
        'guest_mail' => 'pending',
        'crm' => 'pending',
        'telegram' => 'pending',
    ];

    foreach (['mail', 'guest_mail', 'crm', 'telegram'] as $channel) {
        $status = $delivery[$channel] ?? 'pending';
        if ($status === 'sent') {
            continue;
        }
        if ($channel === 'mail') {
            $delivery[$channel] = kulik_deliver_mail($config, $request);
        } elseif ($channel === 'guest_mail') {
            $delivery[$channel] = kulik_deliver_guest_mail($config, $request);
        } elseif ($channel === 'crm') {
            $delivery[$channel] = kulik_deliver_crm($config, $request);
        } else {
            $delivery[$channel] = kulik_deliver_telegram($config, $request);
        }
    }

    $request['delivery'] = $delivery;
    return $request;
}

function kulik_smtp_send(
    string $host,
    int $port,
    string $secure,
    string $user,
    string $pass,
    string $from,
    string $fromName,
    string $to,
    string $subject,
    string $body
): void {
    $remote = $host;
    if ($secure === 'ssl') {
        $remote = 'ssl://' . $host;
    }

    $fp = @stream_socket_client(
        $remote . ':' . $port,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT
    );
    if ($fp === false) {
        throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
    }
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): string {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $expect = function (string $prefix) use ($read): void {
        $resp = $read();
        if (strncmp($resp, $prefix, strlen($prefix)) !== 0) {
            throw new RuntimeException('SMTP unexpected: ' . trim($resp));
        }
    };

    $write = function (string $cmd) use ($fp): void {
        fwrite($fp, $cmd . "\r\n");
    };

    $expect('220');
    $write('EHLO kulik-invite');
    $ehlo = $read();
    if (strncmp($ehlo, '250', 3) !== 0) {
        throw new RuntimeException('SMTP EHLO failed: ' . trim($ehlo));
    }

    if ($secure === 'tls') {
        $write('STARTTLS');
        $expect('220');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('SMTP STARTTLS failed');
        }
        $write('EHLO kulik-invite');
        $ehlo = $read();
        if (strncmp($ehlo, '250', 3) !== 0) {
            throw new RuntimeException('SMTP EHLO after TLS failed');
        }
    }

    if ($user !== '') {
        $write('AUTH LOGIN');
        $expect('334');
        $write(base64_encode($user));
        $expect('334');
        $write(base64_encode($pass));
        $expect('235');
    }

    $write('MAIL FROM:<' . $from . '>');
    $expect('250');
    $write('RCPT TO:<' . $to . '>');
    $expect('250');
    $write('DATA');
    $expect('354');

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFrom = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';
    $payload = [
        'Date: ' . date('r'),
        'From: ' . $encodedFrom,
        'To: <' . $to . '>',
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        str_replace(["\r\n", "\r"], "\n", $body),
        '.',
    ];
    $write(implode("\r\n", $payload));
    $expect('250');
    $write('QUIT');
    fclose($fp);
}
