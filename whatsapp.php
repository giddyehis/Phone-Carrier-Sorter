<?php
require_once __DIR__ . '/lib.php';

$configOk = file_exists(__DIR__ . '/config.php');
if ($configOk) {
    require __DIR__ . '/config.php';
}

function wa_api_request(string $path, array $payload): array {
    if (!defined('WHATSAPP_ACCESS_TOKEN') || !defined('WHATSAPP_PHONE_NUMBER_ID')) {
        return ['error' => 'WhatsApp API configuration missing.'];
    }

    $url = 'https://graph.facebook.com/v20.0/' . WHATSAPP_PHONE_NUMBER_ID . $path;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['error' => 'cURL error: ' . $err];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        return ['error' => 'HTTP ' . $httpCode, 'response' => $data];
    }

    return $data ?? [];
}

$waCheckInput = $_POST['wa_numbers'] ?? '';
$waCheckResults = [];

if ($waCheckInput !== '' && isset($_POST['action']) && $_POST['action'] === 'check_whatsapp') {
    $lines = preg_split('/\R+/', $waCheckInput);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        $normalized = normalize_number_za($trimmed);
        if ($normalized === null) {
            $waCheckResults[] = [
                'input' => $trimmed,
                'normalized' => null,
                'has_whatsapp' => false,
                'status' => 'Invalid number',
            ];
            continue;
        }

        if (!$configOk) {
            $waCheckResults[] = [
                'input' => $trimmed,
                'normalized' => $normalized,
                'has_whatsapp' => false,
                'status' => 'Config missing (config.php).',
            ];
            continue;
        }

        $e164 = to_e164_za($normalized);
        $payload = [
            'blocking' => 'wait',
            'contacts' => [$e164],
            'force_check' => true,
        ];
        $data = wa_api_request('/contacts', $payload);

        if (isset($data['error'])) {
            $status = 'API error';
        } else {
            $status = 'Checked';
        }

        $hasWhatsApp = false;
        if (!empty($data['contacts'][0]['status']) && $data['contacts'][0]['status'] === 'valid') {
            $hasWhatsApp = true;
            $status = 'Has WhatsApp';
        } elseif (!empty($data['contacts'][0]['status'])) {
            $status = 'No WhatsApp (' . $data['contacts'][0]['status'] . ')';
        }

        $waCheckResults[] = [
            'input' => $trimmed,
            'normalized' => $normalized,
            'has_whatsapp' => $hasWhatsApp,
            'status' => $status,
        ];
    }
}

$waSendInput = $_POST['wa_send_numbers'] ?? '';
$waMessage = $_POST['wa_message'] ?? '';
$waSendResults = [];

if ($waSendInput !== '' && $waMessage !== '' && isset($_POST['action']) && $_POST['action'] === 'send_whatsapp') {
    $lines = preg_split('/\R+/', $waSendInput);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        $normalized = normalize_number_za($trimmed);
        if ($normalized === null) {
            $waSendResults[] = [
                'input' => $trimmed,
                'normalized' => null,
                'status' => 'Invalid number',
            ];
            continue;
        }

        if (!$configOk) {
            $waSendResults[] = [
                'input' => $trimmed,
                'normalized' => $normalized,
                'status' => 'Config missing (config.php).',
            ];
            continue;
        }

        $e164 = to_e164_za($normalized);
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $e164,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $waMessage,
            ],
        ];

        $data = wa_api_request('/messages', $payload);

        if (isset($data['error'])) {
            $status = 'API error';
        } elseif (!empty($data['messages'][0]['id'])) {
            $status = 'Sent';
        } else {
            $status = 'Unknown response';
        }

        $waSendResults[] = [
            'input' => $trimmed,
            'normalized' => $normalized,
            'status' => $status,
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WhatsApp Tools</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 2rem;
            background: #f5f5f7;
        }
        h1 {
            margin-bottom: 0.5rem;
        }
        .subtitle {
            color: #555;
            margin-bottom: 1.5rem;
        }
        textarea {
            width: 100%;
            min-height: 160px;
            padding: 0.75rem;
            font-family: monospace;
            border-radius: 6px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }
        button {
            margin-top: 0.5rem;
            padding: 0.6rem 1.4rem;
            border-radius: 999px;
            border: none;
            background: #22c55e;
            color: white;
            font-size: 0.95rem;
            cursor: pointer;
        }
        button:hover {
            background: #16a34a;
        }
        .section {
            background: white;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        .results-table th,
        .results-table td {
            border: 1px solid #ddd;
            padding: 0.4rem 0.6rem;
        }
        .results-table th {
            background: #f3f4f6;
        }
        .status-ok {
            color: #16a34a;
        }
        .status-bad {
            color: #dc2626;
        }
        .nav {
            margin-bottom: 1rem;
        }
        .nav a {
            margin-right: 1rem;
            text-decoration: none;
            color: #2563eb;
        }
        .config-warning {
            color: #b45309;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="nav">
        <a href="index.php">&larr; Carrier sorter</a>
    </div>

    <h1>WhatsApp Tools</h1>
    <div class="subtitle">
        Check which South African numbers have WhatsApp and send bulk text messages via WhatsApp Cloud API.
    </div>

    <?php if (!$configOk): ?>
        <div class="config-warning">
            config.php not found. Copy config.sample.php to config.php and fill in your real
            WHATSAPP_ACCESS_TOKEN and WHATSAPP_PHONE_NUMBER_ID to enable live WhatsApp calls.
        </div>
    <?php endif; ?>

    <div class="section">
        <h2>Check which numbers have WhatsApp</h2>
        <form method="post">
            <input type="hidden" name="action" value="check_whatsapp">
            <textarea name="wa_numbers" placeholder="Paste numbers here, one per line"><?php echo htmlspecialchars($waCheckInput); ?></textarea>
            <br>
            <button type="submit">Check WhatsApp status</button>
        </form>

        <?php if ($waCheckResults): ?>
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Original</th>
                        <th>Normalized</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($waCheckResults as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['input']); ?></td>
                        <td><?php echo htmlspecialchars($row['normalized'] ?? '—'); ?></td>
                        <td class="<?php echo $row['has_whatsapp'] ?? false ? 'status-ok' : 'status-bad'; ?>">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Send bulk WhatsApp text message</h2>
        <form method="post">
            <input type="hidden" name="action" value="send_whatsapp">
            <label for="wa_send_numbers"><strong>Numbers (one per line)</strong></label><br>
            <textarea id="wa_send_numbers" name="wa_send_numbers" placeholder="Paste target numbers here"><?php echo htmlspecialchars($waSendInput); ?></textarea>
            <br><br>
            <label for="wa_message"><strong>Message</strong></label><br>
            <textarea id="wa_message" name="wa_message" style="min-height: 120px;" placeholder="Type your WhatsApp message here"><?php echo htmlspecialchars($waMessage); ?></textarea>
            <br>
            <button type="submit">Send WhatsApp messages</button>
        </form>

        <?php if ($waSendResults): ?>
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Original</th>
                        <th>Normalized</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($waSendResults as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['input']); ?></td>
                        <td><?php echo htmlspecialchars($row['normalized'] ?? '—'); ?></td>
                        <td class="<?php echo ($row['status'] ?? '') === 'Sent' ? 'status-ok' : 'status-bad'; ?>">
                            <?php echo htmlspecialchars($row['status'] ?? ''); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>

