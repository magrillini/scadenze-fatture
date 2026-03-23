<?php

declare(strict_types=1);

use ScadenzeFatture\ContactsRepository;
use ScadenzeFatture\DashboardService;
use ScadenzeFatture\GoogleCalendarService;
use ScadenzeFatture\HomeSettingsRepository;
use ScadenzeFatture\InvoiceParser;
use ScadenzeFatture\PaymentRegistryRepository;

require dirname(__DIR__) . '/src/bootstrap.php';

session_start();

$storageDirectory = dirname(__DIR__) . '/storage';
$homeConfigPath = $storageDirectory . '/home-settings.json';
$paymentRegistryPath = $storageDirectory . '/payment-registry.json';
$homeUploadDirectory = __DIR__ . '/uploads/home';
$adminPassword = getenv('HOME_SUPERADMIN_PASSWORD') ?: 'admin123';
$currentPage = (($_GET['pagina'] ?? '') === 'fatture') ? 'fatture' : 'home';

$xmlDirectory = trim($_POST['xml_directory'] ?? $_GET['xml_directory'] ?? dirname(__DIR__) . '/samples/xml');
$contactsPath = trim($_POST['contacts_path'] ?? $_GET['contacts_path'] ?? dirname(__DIR__) . '/samples/contatti-clienti.csv');
$calendarId = trim($_POST['calendar_id'] ?? $_GET['calendar_id'] ?? 'primary');
$clientSearch = trim($_POST['client_search'] ?? $_GET['client_search'] ?? '');
$vatSearch = trim($_POST['vat_search'] ?? $_GET['vat_search'] ?? '');
$amountMin = trim($_POST['amount_min'] ?? $_GET['amount_min'] ?? '');
$amountMax = trim($_POST['amount_max'] ?? $_GET['amount_max'] ?? '');
$message = null;
$error = null;
$dues = [];
$summary = ['total_dues' => 0, 'total_amount' => 0.0, 'collected_amount' => 0.0, 'outstanding_amount' => 0.0, 'legal_amount' => 0.0, 'aging_buckets' => []];
$showSuperadmin = $currentPage === 'home' && (isset($_GET['superadmin']) || isset($_POST['superadmin']));
$homeRepository = new HomeSettingsRepository($homeConfigPath, $homeUploadDirectory);
$paymentRegistry = new PaymentRegistryRepository($paymentRegistryPath);
$homeSettings = $homeRepository->load();
$selectedHomeVariant = $homeRepository->selectVariant($homeSettings);

$contactsRepository = new ContactsRepository();
$parser = new InvoiceParser();
$dashboard = new DashboardService();
$googleService = new GoogleCalendarService(
    dirname(__DIR__) . '/config/google-calendar.local.json',
    $storageDirectory . '/google-token.json'
);

$redirectUri = sprintf(
    '%s://%s%s?action=oauth_callback',
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
    $_SERVER['HTTP_HOST'] ?? 'localhost',
    strtok($_SERVER['REQUEST_URI'] ?? '/index.php', '?')
);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'superadmin_login') {
        if (hash_equals($adminPassword, (string) ($_POST['superadmin_password'] ?? ''))) {
            $_SESSION['is_superadmin'] = true;
            $message = 'Accesso superadmin eseguito correttamente.';
        } else {
            $error = 'Password superadmin non valida.';
        }
    }

    if (isset($_GET['action']) && $_GET['action'] === 'superadmin_logout') {
        unset($_SESSION['is_superadmin']);
        $message = 'Sessione superadmin terminata.';
    }

    if (isset($_GET['action']) && $_GET['action'] === 'connect_google') {
        if (!$googleService->isConfigured()) {
            throw new RuntimeException('Config Google mancante: crea config/google-calendar.local.json partendo dal file example.');
        }

        header('Location: ' . $googleService->getAuthUrl($redirectUri));
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'oauth_callback' && isset($_GET['code'])) {
        $googleService->fetchAndStoreAccessToken((string) $_GET['code'], $redirectUri);
        $message = 'Google Calendar collegato correttamente.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_home_settings') {
        if (empty($_SESSION['is_superadmin'])) {
            throw new RuntimeException('Accesso superadmin richiesto per modificare la home.');
        }

        $homeSettings = $homeRepository->saveSettings([
            'headline' => trim((string) ($_POST['headline'] ?? 'CONTROLLO FATTURE e RICONCILIAZIONE')),
            'subheadline' => trim((string) ($_POST['subheadline'] ?? 'Cruscotto iniziale per monitorare solo le fatture non pagate.')),
            'max_photos' => (int) ($_POST['max_photos'] ?? 1),
            'enabled_layouts' => array_map('intval', $_POST['enabled_layouts'] ?? []),
            'image_title' => $_POST['image_title'] ?? [],
            'image_caption' => $_POST['image_caption'] ?? [],
            'keep_image' => $_POST['keep_image'] ?? [],
            'remove_image' => $_POST['remove_image'] ?? [],
        ], $_FILES['home_images'] ?? null);

        $selectedHomeVariant = $homeRepository->selectVariant($homeSettings);
        $message = 'Impostazioni home aggiornate con successo.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_due_status') {
        $paymentMethod = trim((string) ($_POST['payment_method'] ?? ''));
        $paymentAmountRaw = trim((string) ($_POST['payment_amount'] ?? ''));
        $paymentAmount = $paymentAmountRaw !== '' ? (float) str_replace(',', '.', $paymentAmountRaw) : null;
        $paymentDate = trim((string) ($_POST['payment_date'] ?? '')) ?: null;
        $paymentNote = trim((string) ($_POST['payment_note'] ?? '')) ?: null;

        $paymentRegistry->updateDueStatus(
            (string) ($_POST['due_id'] ?? ''),
            isset($_POST['pagamenti']),
            isset($_POST['avvocato']),
            $paymentMethod !== '' ? $paymentMethod : null,
            $paymentDate,
            $paymentAmount,
            $paymentNote
        );
        $message = 'Stato pagamento aggiornato.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_installments') {
        $paymentRegistry->updateInvoiceInstallments(
            (string) ($_POST['invoice_id'] ?? ''),
            max(1, (int) ($_POST['numero_rate'] ?? 1))
        );
        $message = 'Numero rate aggiornato con successo.';
    }

    $contacts = $contactsRepository->loadFromCsv($contactsPath);
    $rawDues = $parser->parseDirectory($xmlDirectory, $contacts);
    $summary = $dashboard->summarize($rawDues, $paymentRegistry->load());
    $dues = $summary['dues'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'push_google') {
        $inserted = $googleService->pushEvents($dues, $calendarId);
        $message = sprintf('Inseriti %d eventi in Google Calendar (%s).', $inserted, $calendarId);
    }
} catch (Throwable $throwable) {
    $error = $throwable->getMessage();
}

$filteredDues = array_values(array_filter($dues, static function ($due) use ($clientSearch, $vatSearch, $amountMin, $amountMax): bool {
    if ($clientSearch !== '' && !str_contains(mb_strtolower($due->clientName), mb_strtolower($clientSearch))) {
        return false;
    }

    if ($vatSearch !== '' && !str_contains(mb_strtolower((string) ($due->clientVat ?? '')), mb_strtolower($vatSearch))) {
        return false;
    }

    $min = $amountMin !== '' ? (float) str_replace(',', '.', $amountMin) : null;
    $max = $amountMax !== '' ? (float) str_replace(',', '.', $amountMax) : null;

    if ($min !== null && $due->amount < $min) {
        return false;
    }

    if ($max !== null && $due->amount > $max) {
        return false;
    }

    return true;
}));

function euro(float $amount): string
{
    return number_format($amount, 2, ',', '.') . ' €';
}

function isDueBeforeInvoiceDate(string $dueDate, string $invoiceDate): bool
{
    $dueTimestamp = strtotime($dueDate);
    $invoiceTimestamp = strtotime($invoiceDate);

    if ($dueTimestamp === false || $invoiceTimestamp === false) {
        return false;
    }

    return $dueTimestamp < $invoiceTimestamp;
}

function buildPieGradient(array $segments): string
{
    $total = array_reduce($segments, static fn (float $carry, array $segment): float => $carry + (float) ($segment['value'] ?? 0), 0.0);
    if ($total <= 0) {
        return 'conic-gradient(#d1d5db 0 100%)';
    }

    $start = 0.0;
    $parts = [];
    foreach ($segments as $segment) {
        $value = (float) ($segment['value'] ?? 0);
        if ($value <= 0) {
            continue;
        }

        $end = $start + (($value / $total) * 100);
        $parts[] = sprintf('%s %.2f%% %.2f%%', $segment['color'], $start, $end);
        $start = $end;
    }

    return 'conic-gradient(' . implode(', ', $parts) . ')';
}

$pieSegments = array_map(
    static fn (array $bucket): array => [
        'label' => (string) ($bucket['label'] ?? ''),
        'color' => (string) ($bucket['color'] ?? '#d1d5db'),
        'amount' => (float) ($bucket['amount'] ?? 0.0),
        'count' => (int) ($bucket['count'] ?? 0),
    ],
    array_values($summary['aging_buckets'] ?? [])
);
$pieByAmount = array_map(static fn (array $segment): array => ['color' => $segment['color'], 'value' => $segment['amount']], $pieSegments);
$pieByCount = array_map(static fn (array $segment): array => ['color' => $segment['color'], 'value' => (float) $segment['count']], $pieSegments);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $currentPage === 'fatture' ? 'Elenco Fatture' : 'CONTROLLO FATTURE e RICONCILIAZIONE' ?></title>
    <style>
        :root { color-scheme: light; --bg: #f3f4f6; --text: #111827; --muted: #6b7280; --card: #ffffff; --primary: #2563eb; --secondary: #059669; --warning: #dc2626; --orange: #f97316; --success: #16a34a; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; background: var(--bg); color: var(--text); }
        .container { max-width: 1380px; margin: 0 auto; padding: 24px; }
        .card { background: var(--card); border-radius: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); padding: 20px; margin-bottom: 20px; }
        .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .page-actions, .summary-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .button, button { display: inline-block; border: 0; border-radius: 10px; padding: 10px 14px; background: var(--primary); color: white; text-decoration: none; cursor: pointer; }
        .button.secondary, button.secondary { background: var(--secondary); }
        .button.ghost, button.ghost { background: #e5e7eb; color: var(--text); }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font: inherit; }
        textarea { min-height: 80px; resize: vertical; }
        label { display: block; font-weight: 700; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .success-box { background: #dcfce7; color: #166534; }
        .error-box { background: #fee2e2; color: #991b1b; }
        .hero { background: linear-gradient(135deg, #111827 0%, #1d4ed8 48%, #0f766e 100%); color: white; }
        .hero h1 { margin-top: 0; font-size: clamp(2rem, 4vw, 3.2rem); }
        .hero p { color: rgba(255,255,255,.88); }
        .muted { color: var(--muted); }
        .detector { color: white; border-radius: 18px; padding: 24px; min-height: 150px; display: grid; align-content: center; gap: 8px; }
        .detector.red { background: linear-gradient(135deg, #b91c1c, #ef4444); }
        .detector.orange { background: linear-gradient(135deg, #c2410c, #fb923c); }
        .detector.green { background: linear-gradient(135deg, #166534, #22c55e); }
        .detector .value { font-size: 2rem; font-weight: 700; }
        .pill { background: #dbeafe; color: #1d4ed8; padding: 4px 8px; border-radius: 999px; font-size: 12px; display: inline-block; }
        .compact-form { display: grid; gap: 8px; min-width: 280px; }
        .compact-form input[type='checkbox'] { width: auto; }
        .search-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); align-items: end; }
        .pie-grid { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .pie-card { border: 1px solid #e5e7eb; border-radius: 16px; padding: 18px; background: #f9fafb; }
        .pie-wrapper { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
        .pie-chart { width: 160px; height: 160px; border-radius: 50%; position: relative; flex: 0 0 auto; }
        .pie-chart::after { content: ''; position: absolute; inset: 24px; border-radius: 50%; background: #fff; box-shadow: inset 0 0 0 1px #e5e7eb; }
        .legend { display: grid; gap: 10px; min-width: 180px; flex: 1; }
        .legend-item { display: grid; grid-template-columns: auto 1fr auto; gap: 10px; align-items: center; }
        .legend-color { width: 14px; height: 14px; border-radius: 999px; }
        .home-gallery { display: grid; gap: 14px; }
        .home-gallery.layout-1 { grid-template-columns: 1fr; }
        .home-gallery.layout-2 { grid-template-columns: repeat(2, 1fr); }
        .home-gallery.layout-3 { grid-template-columns: 1.2fr .8fr .8fr; }
        .home-gallery.layout-4 { grid-template-columns: repeat(4, 1fr); }
        .home-photo { position: relative; min-height: 220px; border-radius: 18px; overflow: hidden; }
        .home-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .home-photo figcaption { position: absolute; inset: auto 0 0 0; padding: 16px; color: white; background: linear-gradient(180deg, transparent, rgba(17,24,39,.86)); }
        .amount { font-weight: 700; }
        .due-date-anomaly { color: #b91c1c; font-weight: 700; }
        @media (max-width: 768px) { .home-gallery.layout-2, .home-gallery.layout-3, .home-gallery.layout-4 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="page-actions">
        <a class="button" href="/index.php">Home</a>
        <a class="button secondary" href="/fatture.php">Elenco fatture</a>
        <?php if ($currentPage === 'home'): ?><a class="button ghost" href="?superadmin=1">Area superadmin home</a><?php endif; ?>
        <?php if (!empty($_SESSION['is_superadmin']) && $currentPage === 'home'): ?><a class="button ghost" href="?action=superadmin_logout">Logout superadmin</a><?php endif; ?>
    </div>

    <?php if ($message): ?><div class="alert success-box"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error-box"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($currentPage === 'home'): ?>
        <section class="card hero">
            <h1>CONTROLLO FATTURE e RICONCILIAZIONE</h1>
            <p>Cruscotto iniziale dedicato alle sole fatture non pagate. L'elenco dettagliato delle fatture e la registrazione dei pagamenti sono disponibili nella pagina separata.</p>
            <p><a class="button secondary" href="/fatture.php">Apri elenco fatture</a></p>
            <?php if ($selectedHomeVariant['images'] !== []): ?><div class="home-gallery layout-<?= (int) $selectedHomeVariant['layout'] ?>"><?php foreach ($selectedHomeVariant['images'] as $image): ?><figure class="home-photo"><img src="<?= htmlspecialchars($image['path']) ?>?v=<?= urlencode((string) ($image['updated_at'] ?? '1')) ?>" alt="<?= htmlspecialchars($image['title']) ?>"><figcaption><strong><?= htmlspecialchars($image['title']) ?></strong><br><?= htmlspecialchars($image['caption']) ?></figcaption></figure><?php endforeach; ?></div><?php endif; ?>
        </section>

        <div class="grid">
            <?php $overdue = $summary['aging_buckets']['overdue_unpaid'] ?? ['amount' => 0.0, 'count' => 0]; ?>
            <?php $currentMonth = $summary['aging_buckets']['due_soon'] ?? ['amount' => 0.0, 'count' => 0]; ?>
            <?php $nextMonth = $summary['aging_buckets']['future'] ?? ['amount' => 0.0, 'count' => 0]; ?>
            <div class="detector red"><div>Scadute e non pagate</div><div class="value"><?= euro((float) $overdue['amount']) ?></div><div><?= (int) $overdue['count'] ?> fatture</div></div>
            <div class="detector orange"><div>Non pagate entro il mese corrente</div><div class="value"><?= euro((float) $currentMonth['amount']) ?></div><div><?= (int) $currentMonth['count'] ?> fatture</div></div>
            <div class="detector green"><div>Non pagate dal mese successivo</div><div class="value"><?= euro((float) $nextMonth['amount']) ?></div><div><?= (int) $nextMonth['count'] ?> fatture</div></div>
        </div>

        <section class="card">
            <h2>Grafici a torta stato fatture non pagate</h2>
            <p class="muted">I grafici mostrano la ripartizione delle fatture non pagate per stato: scadute, entro il mese corrente e dal mese successivo.</p>
            <div class="pie-grid">
                <div class="pie-card">
                    <h3>Distribuzione per importo</h3>
                    <div class="pie-wrapper">
                        <div class="pie-chart" style="background: <?= htmlspecialchars(buildPieGradient($pieByAmount)) ?>;"></div>
                        <div class="legend">
                            <?php foreach ($pieSegments as $segment): ?>
                                <div class="legend-item">
                                    <span class="legend-color" style="background: <?= htmlspecialchars($segment['color']) ?>;"></span>
                                    <span><?= htmlspecialchars($segment['label']) ?></span>
                                    <strong><?= euro((float) $segment['amount']) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="pie-card">
                    <h3>Distribuzione per numero fatture</h3>
                    <div class="pie-wrapper">
                        <div class="pie-chart" style="background: <?= htmlspecialchars(buildPieGradient($pieByCount)) ?>;"></div>
                        <div class="legend">
                            <?php foreach ($pieSegments as $segment): ?>
                                <div class="legend-item">
                                    <span class="legend-color" style="background: <?= htmlspecialchars($segment['color']) ?>;"></span>
                                    <span><?= htmlspecialchars($segment['label']) ?></span>
                                    <strong><?= (int) $segment['count'] ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($showSuperadmin): ?>
            <section class="card"><h2>Area superadmin: gestione immagini home</h2><p class="muted">Puoi configurare da 1 a 5 foto attive per la home.</p><?php if (empty($_SESSION['is_superadmin'])): ?><form method="post"><input type="hidden" name="superadmin" value="1"><input type="hidden" name="action" value="superadmin_login"><div class="grid"><div><label for="superadmin_password">Password superadmin</label><input id="superadmin_password" type="password" name="superadmin_password" placeholder="Inserisci la password superadmin"><p class="muted">Password predefinita: <code>admin123</code>.</p></div></div><p><button type="submit">Accedi</button></p></form><?php else: ?><form method="post" enctype="multipart/form-data"><input type="hidden" name="superadmin" value="1"><input type="hidden" name="action" value="save_home_settings"><div class="grid"><div><label for="headline">Titolo hero</label><input id="headline" name="headline" value="<?= htmlspecialchars($homeSettings['headline']) ?>"></div><div><label for="max_photos">Numero massimo foto da mostrare</label><select id="max_photos" name="max_photos"><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>" <?= $i === (int) $homeSettings['max_photos'] ? 'selected' : '' ?>><?= $i ?> foto</option><?php endfor; ?></select></div></div><div style="margin-top:16px;"><label for="subheadline">Sottotitolo hero</label><textarea id="subheadline" name="subheadline"><?= htmlspecialchars($homeSettings['subheadline']) ?></textarea></div><p style="margin-top:20px;"><button type="submit">Salva configurazione home</button></p></form><?php endif; ?></section>
        <?php endif; ?>
    <?php else: ?>
        <section class="card">
            <h1>Elenco fatture</h1>
            <p class="muted">Ricerca per cliente, P.IVA e intervalli di importo. I pagamenti possono essere registrati come bonifico, RiBa o contanti; i contanti non possono superare 3.000,00 €.</p>
        </section>

        <section class="card">
            <form method="post">
                <div class="grid">
                    <div><label for="xml_directory">Directory fatture XML</label><input id="xml_directory" name="xml_directory" value="<?= htmlspecialchars($xmlDirectory) ?>"></div>
                    <div><label for="contacts_path">File contatti CSV</label><input id="contacts_path" name="contacts_path" value="<?= htmlspecialchars($contactsPath) ?>"></div>
                    <div><label for="calendar_id">Google Calendar ID</label><input id="calendar_id" name="calendar_id" value="<?= htmlspecialchars($calendarId) ?>"></div>
                    <div><label>&nbsp;</label><a class="button secondary" href="?action=connect_google&amp;pagina=fatture&amp;xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>">Collega Google Calendar</a></div>
                </div>
                <p style="margin-top:16px; display:flex; gap:12px; flex-wrap:wrap;"><input type="hidden" name="pagina" value="fatture"><button type="submit">Aggiorna scadenzario</button><button class="secondary" type="submit" name="action" value="push_google">Invia scadenze a Google</button></p>
            </form>
        </section>

        <section class="card">
            <div class="summary-row">
                <span class="pill"><?= count($filteredDues) ?> risultati</span>
                <span class="muted">Rate di default: 1, modificabili secondo i termini concordati.</span>
            </div>
            <form method="get" class="search-grid">
                <input type="hidden" name="pagina" value="fatture">
                <div><label for="client_search">Cliente</label><input id="client_search" name="client_search" value="<?= htmlspecialchars($clientSearch) ?>"></div>
                <div><label for="vat_search">P.IVA</label><input id="vat_search" name="vat_search" value="<?= htmlspecialchars($vatSearch) ?>"></div>
                <div><label for="amount_min">Da importo</label><input id="amount_min" name="amount_min" type="number" step="0.01" min="0" value="<?= htmlspecialchars($amountMin) ?>"></div>
                <div><label for="amount_max">A importo</label><input id="amount_max" name="amount_max" type="number" step="0.01" min="0" value="<?= htmlspecialchars($amountMax) ?>"></div>
                <div>
                    <input type="hidden" name="xml_directory" value="<?= htmlspecialchars($xmlDirectory) ?>">
                    <input type="hidden" name="contacts_path" value="<?= htmlspecialchars($contactsPath) ?>">
                    <input type="hidden" name="calendar_id" value="<?= htmlspecialchars($calendarId) ?>">
                    <button type="submit">Filtra</button>
                    <a class="button ghost" href="?pagina=fatture&amp;xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>">Reset</a>
                </div>
            </form>

            <table>
                <thead>
                    <tr><th>Scadenza</th><th>Cliente</th><th>Fattura</th><th>Rata</th><th>Importo</th><th>Stato attuale</th><th>Gestione pagamento</th><th>Numero rate</th></tr>
                </thead>
                <tbody>
                <?php foreach ($filteredDues as $due): $dueDateClass = isDueBeforeInvoiceDate($due->dueDate, $due->invoiceDate) ? 'due-date-anomaly' : ''; ?>
                    <tr>
                        <td class="<?= $dueDateClass ?>"><?= htmlspecialchars($due->dueDate) ?></td>
                        <td><?= htmlspecialchars($due->clientName) ?><br><span class="muted"><?= htmlspecialchars($due->clientVat ?? '-') ?></span></td>
                        <td><?= htmlspecialchars($due->invoiceNumber) ?><br><span class="muted">Data: <?= htmlspecialchars($due->invoiceDate) ?></span></td>
                        <td><?= $due->installmentNumber ?>/<?= $due->installmentCount ?></td>
                        <td class="amount"><?= euro($due->amount) ?></td>
                        <td>
                            <span class="pill"><?= $due->paid ? 'Pagata' : 'Non pagata' ?></span><br>
                            <span class="muted">Metodo: <?= htmlspecialchars($due->paymentMethod ?? '-') ?></span><br>
                            <span class="muted">Incasso: <?= $due->paymentAmount !== null ? euro($due->paymentAmount) : '-' ?></span>
                        </td>
                        <td>
                            <form method="post" class="compact-form">
                                <input type="hidden" name="pagina" value="fatture">
                                <input type="hidden" name="action" value="save_due_status">
                                <input type="hidden" name="due_id" value="<?= htmlspecialchars((string) $due->dueId) ?>">
                                <input type="hidden" name="xml_directory" value="<?= htmlspecialchars($xmlDirectory) ?>">
                                <input type="hidden" name="contacts_path" value="<?= htmlspecialchars($contactsPath) ?>">
                                <input type="hidden" name="calendar_id" value="<?= htmlspecialchars($calendarId) ?>">
                                <input type="hidden" name="client_search" value="<?= htmlspecialchars($clientSearch) ?>">
                                <input type="hidden" name="vat_search" value="<?= htmlspecialchars($vatSearch) ?>">
                                <input type="hidden" name="amount_min" value="<?= htmlspecialchars($amountMin) ?>">
                                <input type="hidden" name="amount_max" value="<?= htmlspecialchars($amountMax) ?>">
                                <label><input type="checkbox" name="pagamenti" value="1" <?= $due->paid ? 'checked' : '' ?>> Pagata</label>
                                <label><input type="checkbox" name="avvocato" value="1" <?= $due->lawyer ? 'checked' : '' ?>> Avvocato</label>
                                <div><label>Metodo pagamento</label><select name="payment_method"><option value="">Seleziona</option><option value="bonifico" <?= $due->paymentMethod === 'bonifico' ? 'selected' : '' ?>>Bonifico</option><option value="riba" <?= $due->paymentMethod === 'riba' ? 'selected' : '' ?>>RiBa</option><option value="contanti" <?= $due->paymentMethod === 'contanti' ? 'selected' : '' ?>>Contanti</option></select></div>
                                <div><label>Data pagamento</label><input type="date" name="payment_date" value="<?= htmlspecialchars((string) $due->paymentDate) ?>"></div>
                                <div><label>Importo pagato</label><input type="number" step="0.01" min="0" name="payment_amount" value="<?= htmlspecialchars($due->paymentAmount !== null ? number_format($due->paymentAmount, 2, '.', '') : '') ?>"></div>
                                <div><label>Note</label><textarea name="payment_note"><?= htmlspecialchars((string) $due->paymentNote) ?></textarea></div>
                                <button type="submit">Salva pagamento</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" class="compact-form">
                                <input type="hidden" name="pagina" value="fatture">
                                <input type="hidden" name="action" value="save_installments">
                                <input type="hidden" name="invoice_id" value="<?= htmlspecialchars((string) $due->invoiceId) ?>">
                                <input type="hidden" name="xml_directory" value="<?= htmlspecialchars($xmlDirectory) ?>">
                                <input type="hidden" name="contacts_path" value="<?= htmlspecialchars($contactsPath) ?>">
                                <input type="hidden" name="calendar_id" value="<?= htmlspecialchars($calendarId) ?>">
                                <input type="hidden" name="client_search" value="<?= htmlspecialchars($clientSearch) ?>">
                                <input type="hidden" name="vat_search" value="<?= htmlspecialchars($vatSearch) ?>">
                                <input type="hidden" name="amount_min" value="<?= htmlspecialchars($amountMin) ?>">
                                <input type="hidden" name="amount_max" value="<?= htmlspecialchars($amountMax) ?>">
                                <input type="number" min="1" max="24" name="numero_rate" value="<?= (int) $due->installmentCount ?>">
                                <button type="submit">Aggiorna rate</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($filteredDues === []): ?><tr><td colspan="8" class="muted">Nessuna fattura trovata con i filtri selezionati.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</div>
</body>
</html>
