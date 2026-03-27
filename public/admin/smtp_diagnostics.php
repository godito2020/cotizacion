<?php
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isLoggedIn() || !$auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$companyId = $auth->getCompanyId();
$companySettings = new CompanySettings();
$emailSettings = $companySettings->getEmailSettings($companyId);

$diagnostics = [];

try {
    $host = $emailSettings['smtp_host'];
    $port = $emailSettings['smtp_port'];
    $encryption = $emailSettings['smtp_encryption'];

    $diagnostics['config'] = [
        'host' => $host,
        'port' => $port,
        'encryption' => $encryption,
        'username' => $emailSettings['smtp_username'],
        'has_password' => !empty($emailSettings['smtp_password'])
    ];

    // Test 1: Basic TCP connection
    $diagnostics['tcp_test'] = testTcpConnection($host, $port);

    // Test 2: SSL/TLS connection if enabled
    if ($encryption === 'ssl') {
        $diagnostics['ssl_test'] = testSslConnection($host, $port);
    }

    // Test 3: SMTP protocol basic check
    $diagnostics['smtp_test'] = testSmtpProtocol($host, $port, $encryption);

    echo json_encode([
        'success' => true,
        'diagnostics' => $diagnostics
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'diagnostics' => $diagnostics
    ]);
}

function testTcpConnection($host, $port) {
    $start = microtime(true);
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    $time = round((microtime(true) - $start) * 1000);

    if ($socket) {
        fclose($socket);
        return [
            'success' => true,
            'message' => "TCP connection successful in {$time}ms",
            'time_ms' => $time
        ];
    } else {
        return [
            'success' => false,
            'message' => "TCP connection failed: $errstr ($errno)",
            'error_code' => $errno,
            'error_message' => $errstr
        ];
    }
}

function testSslConnection($host, $port) {
    $start = microtime(true);

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @stream_socket_client("ssl://$host:$port", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
    $time = round((microtime(true) - $start) * 1000);

    if ($socket) {
        $crypto = stream_get_meta_data($socket);
        fclose($socket);

        return [
            'success' => true,
            'message' => "SSL connection successful in {$time}ms",
            'time_ms' => $time,
            'crypto_info' => isset($crypto['crypto']) ? 'SSL/TLS enabled' : 'No crypto info'
        ];
    } else {
        return [
            'success' => false,
            'message' => "SSL connection failed: $errstr ($errno)",
            'error_code' => $errno,
            'error_message' => $errstr
        ];
    }
}

function testSmtpProtocol($host, $port, $encryption) {
    $messages = [];
    $start = microtime(true);

    try {
        // Create connection
        if ($encryption === 'ssl') {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            $socket = @stream_socket_client("ssl://$host:$port", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
        } else {
            $socket = @fsockopen($host, $port, $errno, $errstr, 5);
        }

        if (!$socket) {
            throw new Exception("Connection failed: $errstr ($errno)");
        }

        stream_set_timeout($socket, 5);
        $messages[] = "Connected to $host:$port";

        // Try to read welcome message
        $welcome = fgets($socket, 512);
        $info = stream_get_meta_data($socket);

        if ($info['timed_out']) {
            $messages[] = "WARNING: Timeout reading welcome message";
            fclose($socket);
            return [
                'success' => false,
                'message' => 'Server connected but no welcome message (timeout)',
                'details' => $messages,
                'time_ms' => round((microtime(true) - $start) * 1000)
            ];
        }

        if ($welcome === false) {
            $messages[] = "ERROR: Failed to read welcome message";
            fclose($socket);
            return [
                'success' => false,
                'message' => 'Server connected but failed to read welcome',
                'details' => $messages,
                'time_ms' => round((microtime(true) - $start) * 1000)
            ];
        }

        $messages[] = "Welcome: " . trim($welcome);

        // Check if it's a valid SMTP response
        if (substr($welcome, 0, 3) === '220') {
            $messages[] = "Valid SMTP server response";

            // Try EHLO
            fputs($socket, "EHLO test\r\n");
            $ehlo = fgets($socket, 512);
            $messages[] = "EHLO response: " . trim($ehlo);

            // Quit gracefully
            fputs($socket, "QUIT\r\n");
            $quit = fgets($socket, 512);
            $messages[] = "QUIT response: " . trim($quit);
        } else {
            $messages[] = "WARNING: Not a standard SMTP response";
        }

        fclose($socket);

        return [
            'success' => true,
            'message' => 'SMTP protocol test successful',
            'details' => $messages,
            'time_ms' => round((microtime(true) - $start) * 1000)
        ];

    } catch (Exception $e) {
        if (isset($socket)) {
            fclose($socket);
        }

        return [
            'success' => false,
            'message' => $e->getMessage(),
            'details' => $messages,
            'time_ms' => round((microtime(true) - $start) * 1000)
        ];
    }
}
?>