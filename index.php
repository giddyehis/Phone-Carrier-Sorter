<?php
require_once __DIR__ . '/lib.php';

function get_carrier(string $number, string $country): string {
    // Note: due to number portability, this indicates the ORIGINAL network only.

    static $zaPrefixMap = null;
    static $gbPrefixMap = null;
    static $iePrefixMap = null;
    static $auPrefixMap = null;

    if ($country === 'ZA') {
        if ($zaPrefixMap === null) {
            $zaPrefixMap = [
            // Vodacom primary
            '082' => 'Vodacom',
            '072' => 'Vodacom',
            '076' => 'Vodacom',
            '079' => 'Vodacom',

            // MTN primary
            '083' => 'MTN',
            '073' => 'MTN',
            '078' => 'MTN',

            // Cell C primary
            '084' => 'Cell C',
            '074' => 'Cell C',

            // Telkom primary
            '081' => 'Telkom',
        ];

            // Extended 06x / other ranges (common allocations)

            // MTN: 0603–0605, 0630–0635, 0638–0640, 0655–0657
            foreach (['0603', '0604', '0605'] as $p) {
                $zaPrefixMap[$p] = 'MTN';
            }
            foreach (range(30, 35) as $x) {
                $zaPrefixMap['063' . $x] = 'MTN';
            }
            foreach (['0638', '0639', '0640'] as $p) {
                $zaPrefixMap[$p] = 'MTN';
            }
            foreach (['0655', '0656', '0657'] as $p) {
                $zaPrefixMap[$p] = 'MTN';
            }

            // Vodacom: 0606–0609, 0636–0637, 0646–0649, 0660–0665
            foreach (['0606', '0607', '0608', '0609'] as $p) {
                $zaPrefixMap[$p] = 'Vodacom';
            }
            foreach (['0636', '0637'] as $p) {
                $zaPrefixMap[$p] = 'Vodacom';
            }
            foreach (['0646', '0647', '0648', '0649'] as $p) {
                $zaPrefixMap[$p] = 'Vodacom';
            }
            foreach (['0660', '0661', '0662', '0663', '0664', '0665'] as $p) {
                $zaPrefixMap[$p] = 'Vodacom';
            }

            // Cell C: 0610–0613, 0615–0619, 062, 0641–0645
            foreach (['0610', '0611', '0612', '0613'] as $p) {
                $zaPrefixMap[$p] = 'Cell C';
            }
            foreach (['0615', '0616', '0617', '0618', '0619'] as $p) {
                $zaPrefixMap[$p] = 'Cell C';
            }
            $zaPrefixMap['062'] = 'Cell C';
            foreach (['0641', '0642', '0643', '0644', '0645'] as $p) {
                $zaPrefixMap[$p] = 'Cell C';
            }

            // Telkom Mobile: 0614, 0658–0659, 0670–0672
            $zaPrefixMap['0614'] = 'Telkom';
            foreach (['0658', '0659'] as $p) {
                $zaPrefixMap[$p] = 'Telkom';
            }
            foreach (['0670', '0671', '0672'] as $p) {
                $zaPrefixMap[$p] = 'Telkom';
            }
        }

        $p4 = substr($number, 0, 4);
        $p3 = substr($number, 0, 3);
        if (isset($zaPrefixMap[$p4])) {
            return $zaPrefixMap[$p4];
        }
        if (isset($zaPrefixMap[$p3])) {
            return $zaPrefixMap[$p3];
        }
        return 'Unknown';
    }

    if ($country === 'GB') {
        // United Kingdom – very simplified mapping using 4-digit mobile prefixes.
        if ($gbPrefixMap === null) {
            $gbPrefixMap = [
                // EE (includes ex-Orange/T-Mobile ranges; sample only)
                '0797' => 'EE',
                '0777' => 'EE',
                // O2 (sample)
                '0797' => 'O2',
                '0743' => 'O2',
                // Vodafone (sample)
                '0777' => 'Vodafone',
                '0740' => 'Vodafone',
                // Three (sample)
                '0740' => 'Three',
                '0745' => 'Three',
            ];
        }
        $p4 = substr($number, 0, 4);
        return $gbPrefixMap[$p4] ?? 'Unknown';
    }

    if ($country === 'IE') {
        // Ireland – classic 083/085/086/087/089 allocations.
        if ($iePrefixMap === null) {
            $iePrefixMap = [
                '083' => 'Three',
                '085' => 'Meteor',
                '086' => 'O2',
                '087' => 'Vodafone',
                '089' => 'Tesco Mobile',
            ];
        }
        $p3 = substr($number, 0, 3);
        return $iePrefixMap[$p3] ?? 'Unknown';
    }

    if ($country === 'AU') {
        // Australia – mobile numbers start 04; use 4-digit prefixes.
        if ($auPrefixMap === null) {
            $auPrefixMap = [
                // Telstra (sample)
                '0400' => 'Telstra',
                '0407' => 'Telstra',
                '0417' => 'Telstra',
                '0427' => 'Telstra',
                // Optus (sample)
                '0401' => 'Optus',
                '0402' => 'Optus',
                '0411' => 'Optus',
                '0421' => 'Optus',
                // Vodafone (sample)
                '0404' => 'Vodafone AU',
                '0405' => 'Vodafone AU',
                '0410' => 'Vodafone AU',
                '0424' => 'Vodafone AU',
            ];
        }
        $p4 = substr($number, 0, 4);
        return $auPrefixMap[$p4] ?? 'Unknown';
    }

    return 'Unknown';
}

$country = $_POST['country'] ?? 'ZA';
$input = $_POST['numbers'] ?? '';
$grouped = [
    'Vodacom' => [],
    'MTN' => [],
    'Cell C' => [],
    'Telkom' => [],
    'Unknown' => [],
    'Invalid' => [],
];

$labels = [
    'ZA' => 'South Africa',
    'GB' => 'United Kingdom',
    'IE' => 'Ireland',
    'AU' => 'Australia',
];
$countryLabel = $labels[$country] ?? $country;

if ($input !== '') {
    $lines = preg_split('/\R+/', $input);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        // For now, normalization is ZA-specific. When you add other countries,
        // plug in different normalization per country here.
        $normalized = normalize_number_za($trimmed);
        if ($normalized === null) {
            $grouped['Invalid'][] = $trimmed;
            continue;
        }
        $carrier = get_carrier($normalized, $country);
        $grouped[$carrier][] = $normalized;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Global Mobile Carrier Sorter</title>
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
        .nav {
            margin-bottom: 1rem;
        }
        .nav a {
            margin-right: 1rem;
            text-decoration: none;
            color: #2563eb;
        }
        textarea {
            width: 100%;
            min-height: 200px;
            padding: 0.75rem;
            font-family: monospace;
            border-radius: 6px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }
        button {
            margin-top: 1rem;
            padding: 0.6rem 1.4rem;
            border-radius: 999px;
            border: none;
            background: #2563eb;
            color: white;
            font-size: 0.95rem;
            cursor: pointer;
        }
        button:hover {
            background: #1d4ed8;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .card h2 {
            margin: 0 0 0.25rem;
            font-size: 1.05rem;
        }
        .count {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        .numbers {
            font-family: monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
        }
        .copy-btn {
            float: right;
            font-size: 0.8rem;
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            border: none;
            background: #e5e7eb;
            color: #111827;
            cursor: pointer;
        }
        .copy-btn:hover {
            background: #d1d5db;
        }
        .copy-btn.copied {
            background: #22c55e;
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="nav">
        <a href="whatsapp.php">WhatsApp tools &rarr;</a>
    </div>
    <h1>Carrier Sorter (<?php echo htmlspecialchars($countryLabel); ?>)</h1>
    <div class="subtitle">
        Paste mobile numbers below (one per line). For South Africa, supports formats like 082..., 072..., +27..., 0027....
    </div>

    <form method="post">
        <label for="country"><strong>Country</strong></label><br>
        <select id="country" name="country">
            <option value="ZA" <?php echo $country === 'ZA' ? 'selected' : ''; ?>>South Africa (ZA)</option>
            <option value="GB" <?php echo $country === 'GB' ? 'selected' : ''; ?>>United Kingdom (GB)</option>
            <option value="IE" <?php echo $country === 'IE' ? 'selected' : ''; ?>>Ireland (IE)</option>
            <option value="AU" <?php echo $country === 'AU' ? 'selected' : ''; ?>>Australia (AU)</option>
        </select>
        <br><br>
        <textarea name="numbers" placeholder="Example:
0821234567
+27831234567
0027821234567
0712345678"><?php echo htmlspecialchars($input); ?></textarea>
        <br>
        <button type="submit">Sort by Carrier</button>
    </form>

    <?php if ($input !== ''): ?>
        <div class="grid">
            <?php foreach ($grouped as $carrier => $numbers): ?>
                <div class="card">
                    <h2>
                        <?php echo htmlspecialchars($carrier); ?>
                        <button
                            type="button"
                            class="copy-btn"
                            data-copy-target="carrier-<?php echo htmlspecialchars($carrier); ?>"
                        >Copy</button>
                    </h2>
                    <div class="count">
                        <?php echo count($numbers); ?> number<?php echo count($numbers) === 1 ? '' : 's'; ?>
                    </div>
                    <div class="numbers" id="carrier-<?php echo htmlspecialchars($carrier); ?>">
                        <?php
                        if ($numbers) {
                            echo htmlspecialchars(implode("\n", $numbers));
                        } else {
                            echo '—';
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <script>
        (function () {
            var buttons = document.querySelectorAll('.copy-btn');
            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var targetId = btn.getAttribute('data-copy-target');
                    var el = document.getElementById(targetId);
                    if (!el) return;
                    var text = el.innerText || el.textContent || '';
                    if (!text) return;

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function () {
                            btn.classList.add('copied');
                            btn.textContent = 'Copied';
                            setTimeout(function () {
                                btn.classList.remove('copied');
                                btn.textContent = 'Copy';
                            }, 1500);
                        });
                    } else {
                        var textarea = document.createElement('textarea');
                        textarea.value = text;
                        document.body.appendChild(textarea);
                        textarea.select();
                        try {
                            document.execCommand('copy');
                            btn.classList.add('copied');
                            btn.textContent = 'Copied';
                            setTimeout(function () {
                                btn.classList.remove('copied');
                                btn.textContent = 'Copy';
                            }, 1500);
                        } catch (e) {}
                        document.body.removeChild(textarea);
                    }
                });
            });
        })();
    </script>
</body>
</html>

