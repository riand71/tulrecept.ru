<?php
declare(strict_types=1);

function smtp_read_response($socket): array
{
    $lines = [];
    $code = 0;

    while (($line = fgets($socket, 4096)) !== false) {
        $lines[] = rtrim($line, "\r\n");

        if (preg_match('/^(\d{3})([ -])/', $line, $m)) {
            $code = (int)$m[1];
            if ($m[2] === ' ') {
                break;
            }
        }
    }

    return [$code, implode("\n", $lines)];
}

function smtp_command($socket, string $command, array $expectedCodes): void
{
    if (fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException('SMTP write failed');
    }

    [$code, $response] = smtp_read_response($socket);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP error ' . $code . ': ' . $response);
    }
}

function smtp_header_encode(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtp_send(
    array $config,
    string $to,
    string $from,
    string $fromName,
    string $subject,
    string $body,
    ?string $replyTo = null
): bool {
    $host = (string)($config['host'] ?? '');
    $port = (int)($config['port'] ?? 465);
    $username = (string)($config['username'] ?? '');
    $password = (string)($config['password'] ?? '');

    if ($host === '' || $username === '' || $password === '' || $port < 1) {
        error_log('SMTP: incomplete configuration');
        return false;
    }

    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false ||
        filter_var($from, FILTER_VALIDATE_EMAIL) === false ||
        ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL) === false)) {
        error_log('SMTP: invalid email address');
        return false;
    }

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
            'SNI_enabled' => true,
        ],
    ]);

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client(
        'ssl://' . $host . ':' . $port,
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!is_resource($socket)) {
        error_log('SMTP: connection failed (' . $errno . ')');
        return false;
    }

    stream_set_timeout($socket, 15);

    try {
        [$code, $response] = smtp_read_response($socket);
        if ($code !== 220) {
            throw new RuntimeException('SMTP greeting error ' . $code . ': ' . $response);
        }

        $helo = 'tulrecept.ru';
        smtp_command($socket, 'EHLO ' . $helo, [250]);
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($username), [334]);
        smtp_command($socket, base64_encode($password), [235]);
        smtp_command($socket, 'MAIL FROM:<' . $from . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $messageId = '<' . bin2hex(random_bytes(12)) . '@tulrecept.ru>';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: ' . $messageId,
            'From: ' . smtp_header_encode($fromName) . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . smtp_header_encode($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        if ($replyTo !== null) {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        }

        $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
        $normalizedBody = str_replace("\n", "\r\n", $normalizedBody);
        $normalizedBody = preg_replace('/(?m)^\./', '..', $normalizedBody) ?? $normalizedBody;

        $data = implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody . "\r\n.";
        if (fwrite($socket, $data . "\r\n") === false) {
            throw new RuntimeException('SMTP data write failed');
        }

        [$dataCode, $dataResponse] = smtp_read_response($socket);
        if ($dataCode !== 250) {
            throw new RuntimeException('SMTP data error ' . $dataCode . ': ' . $dataResponse);
        }

        try {
            smtp_command($socket, 'QUIT', [221]);
        } catch (Throwable) {
            // Письмо уже принято сервером; ошибка QUIT не влияет на результат.
        }

        fclose($socket);
        return true;
    } catch (Throwable $e) {
        error_log('SMTP: ' . $e->getMessage());
        fclose($socket);
        return false;
    }
}
