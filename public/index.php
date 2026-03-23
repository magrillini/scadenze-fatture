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

$xmlDirectory = trim($_POST['xml_directory'] ?? $_GET['xml_directory'] ?? dirname(__DIR__) . '/samples/xml');
$contactsPath = trim($_POST['contacts_path'] ?? $_GET['contacts_path'] ?? dirname(__DIR__) . '/samples/contatti-clienti.csv');
$calendarId = trim($_POST['calendar_id'] ?? $_GET['calendar_id'] ?? 'primary');
$chartGroupBy = trim($_POST['chart_group_by'] ?? $_GET['chart_group_by'] ?? 'cliente');
$message = null;
$error = null;
$dues = [];
$summary = ['total_dues' => 0, 'total_amount' => 0.0, 'collected_amount' => 0.0, 'outstanding_amount' => 0.0, 'legal_amount' => 0.0, 'by_payment_type' => [], 'customers' => [], 'charts' => ['overview' => [], 'by_customer' => []]];
$showSuperadmin = isset($_GET['superadmin']) || isset($_POST['superadmin']);
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
            'headline' => trim((string) ($_POST['headline'] ?? 'Scadenze Fatture XML')),
            'subheadline' => trim((string) ($_POST['subheadline'] ?? 'Gestione visiva della home con immagini casuali e layout dinamici.')),
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
        $paymentRegistry->updateDueStatus(
            (string) ($_POST['due_id'] ?? ''),
            isset($_POST['pagamenti']),
            isset($_POST['avvocato'])
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

function renderStars(int $stars): string
{
    return str_repeat('★', $stars) . str_repeat('☆', max(0, 5 - $stars));
}

function percentOf(float $value, float $max): float
{
    if ($max <= 0) {
        return 0.0;
    }

    return min(100, max(0, ($value / $max) * 100));
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scadenze Fatture XML</title>
    <style>
        :root { color-scheme: light; --bg: #f3f4f6; --text: #111827; --muted: #6b7280; --card: #ffffff; --primary: #2563eb; --secondary: #059669; --accent: #7c3aed; --warning: #b91c1c; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; background: var(--bg); color: var(--text); }
        .container { max-width: 1380px; margin: 0 auto; padding: 24px; }
        .card { background: var(--card); border-radius: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); padding: 20px; margin-bottom: 20px; }
        .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        label { display: block; font-weight: 700; margin-bottom: 6px; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; box-sizing: border-box; font: inherit; }
        textarea { min-height: 88px; resize: vertical; }
        button, .button { display: inline-block; border: 0; border-radius: 10px; padding: 10px 14px; background: var(--primary); color: white; text-decoration: none; cursor: pointer; }
        .button.secondary, button.secondary { background: var(--secondary); }
        .button.ghost, button.ghost { background: #e5e7eb; color: var(--text); }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
        .pill { background: #dbeafe; color: #1d4ed8; padding: 4px 8px; border-radius: 999px; font-size: 12px; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .amount { font-weight: 700; }
        .due-date-anomaly { color: var(--warning); font-weight: 700; }
        .page-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
        .muted { color: var(--muted); }
        .hero { position: relative; overflow: hidden; background: linear-gradient(135deg, #111827 0%, #1d4ed8 48%, #7c3aed 100%); color: white; }
        .hero::after { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at top right, rgba(255,255,255,.22), transparent 30%); pointer-events: none; }
        .hero-content { position: relative; z-index: 1; display: grid; gap: 18px; }
        .hero h1 { margin: 0; font-size: clamp(2rem, 4vw, 3.6rem); }
        .hero p { margin: 0; max-width: 760px; color: rgba(255,255,255,.88); }
        .hero-badges,.checkbox-grid,.inline-form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .hero-badge { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.18); border-radius: 999px; padding: 8px 12px; font-size: 13px; }
        .home-gallery { position: relative; z-index: 1; display: grid; gap: 14px; }
        .home-gallery.layout-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        .home-gallery.layout-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .home-gallery.layout-3 { grid-template-columns: 1.2fr .8fr .8fr; }
        .home-gallery.layout-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .home-gallery.layout-5 { grid-template-columns: repeat(12, minmax(0, 1fr)); }
        .home-gallery.layout-5 .home-photo:first-child { grid-column: span 6; }
        .home-gallery.layout-5 .home-photo:nth-child(2), .home-gallery.layout-5 .home-photo:nth-child(3) { grid-column: span 3; }
        .home-gallery.layout-5 .home-photo:nth-child(n+4) { grid-column: span 4; }
        .home-photo { position: relative; min-height: 220px; border-radius: 18px; overflow: hidden; box-shadow: inset 0 0 0 1px rgba(255,255,255,.08); background: rgba(255,255,255,.1); }
        .home-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .home-photo figcaption { position: absolute; inset: auto 0 0 0; padding: 16px; background: linear-gradient(180deg, transparent, rgba(17,24,39,.86)); }
        .home-photo strong, .home-photo span { display: block; }
        .home-photo span { margin-top: 4px; font-size: 14px; color: rgba(255,255,255,.82); }
        .home-empty, .image-editor { border-radius: 16px; }
        .home-empty { position: relative; z-index: 1; padding: 20px; border: 1px dashed rgba(255,255,255,.4); background: rgba(255,255,255,.1); }
        .image-editor { border: 1px solid #e5e7eb; padding: 16px; background: #f9fafb; }
        .image-preview { width: 100%; max-height: 180px; object-fit: cover; border-radius: 12px; margin-bottom: 12px; background: #e5e7eb; }
        .metric-card .amount { font-size: 1.6rem; display: inline-block; margin-top: 8px; }
        .chart { display: grid; gap: 12px; }
        .bar-row { display: grid; grid-template-columns: minmax(140px, 240px) 1fr auto; gap: 12px; align-items: center; }
        .bar-track { background: #e5e7eb; border-radius: 999px; overflow: hidden; min-height: 14px; }
        .bar-fill { min-height: 14px; border-radius: 999px; background: linear-gradient(90deg, var(--primary), var(--accent)); }
        .stars { color: #f59e0b; font-size: 1.15rem; letter-spacing: 1px; }
        .status-paid { color: #166534; font-weight: 700; }
        .status-open { color: #92400e; font-weight: 700; }
        .status-legal { color: #991b1b; font-weight: 700; }
        .compact-form { display: grid; gap: 8px; min-width: 220px; }
        .compact-form input[type='checkbox'] { width: auto; }
        @media (max-width: 768px) { .home-gallery.layout-2,.home-gallery.layout-3,.home-gallery.layout-4,.home-gallery.layout-5 { grid-template-columns: 1fr; } .home-gallery.layout-5 .home-photo { grid-column: auto !important; } .bar-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="page-actions">
        <a class="button" href="/index.php">Home</a>
        <a class="button secondary" href="?superadmin=1">Area superadmin home</a>
        <?php if (!empty($_SESSION['is_superadmin'])): ?><a class="button ghost" href="?action=superadmin_logout">Logout superadmin</a><?php endif; ?>
    </div>

    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="card hero"><div class="hero-content"><div><h1><?= htmlspecialchars($homeSettings['headline']) ?></h1><p><?= htmlspecialchars($homeSettings['subheadline']) ?></p></div><div class="hero-badges"><span class="hero-badge">Layout casuale: <?= (int) $selectedHomeVariant['layout'] ?>/5</span><span class="hero-badge">Foto mostrate: <?= count($selectedHomeVariant['images']) ?></span><span class="hero-badge">Pool immagini attive: <?= count($homeSettings['images']) ?></span></div><?php if ($selectedHomeVariant['images'] !== []): ?><div class="home-gallery layout-<?= (int) $selectedHomeVariant['layout'] ?>"><?php foreach ($selectedHomeVariant['images'] as $image): ?><figure class="home-photo"><img src="<?= htmlspecialchars($image['path']) ?>?v=<?= urlencode((string) ($image['updated_at'] ?? '1')) ?>" alt="<?= htmlspecialchars($image['title']) ?>"><figcaption><strong><?= htmlspecialchars($image['title']) ?></strong><span><?= htmlspecialchars($image['caption']) ?></span></figcaption></figure><?php endforeach; ?></div><?php else: ?><div class="home-empty"><strong>Nessuna foto configurata.</strong><p>Apri l'area superadmin per caricare da 1 a 5 immagini e attivare i 5 layout casuali della home.</p></div><?php endif; ?></div></section>

    <?php if ($showSuperadmin): ?>
    <section class="card"><h2>Area superadmin: gestione immagini home</h2><p class="muted">Puoi configurare da 1 a 5 foto attive. Ad ogni refresh la home sceglie in modo casuale il layout e l'insieme di immagini da mostrare.</p><?php if (empty($_SESSION['is_superadmin'])): ?><form method="post"><input type="hidden" name="superadmin" value="1"><input type="hidden" name="action" value="superadmin_login"><div class="grid"><div><label for="superadmin_password">Password superadmin</label><input id="superadmin_password" type="password" name="superadmin_password" placeholder="Inserisci la password superadmin"><p class="muted">Password predefinita: <code>admin123</code>.</p></div></div><p><button type="submit">Accedi</button></p></form><?php else: ?><form method="post" enctype="multipart/form-data"><input type="hidden" name="superadmin" value="1"><input type="hidden" name="action" value="save_home_settings"><div class="grid"><div><label for="headline">Titolo hero</label><input id="headline" name="headline" value="<?= htmlspecialchars($homeSettings['headline']) ?>"></div><div><label for="max_photos">Numero massimo foto da mostrare</label><select id="max_photos" name="max_photos"><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>" <?= $i === (int) $homeSettings['max_photos'] ? 'selected' : '' ?>><?= $i ?> foto</option><?php endfor; ?></select></div></div><div style="margin-top:16px;"><label for="subheadline">Sottotitolo hero</label><textarea id="subheadline" name="subheadline"><?= htmlspecialchars($homeSettings['subheadline']) ?></textarea></div><div style="margin-top:16px;"><label>Abilita uno o più layout casuali</label><div class="checkbox-grid"><?php for ($layout = 1; $layout <= 5; $layout++): ?><label><input type="checkbox" name="enabled_layouts[]" value="<?= $layout ?>" <?= in_array($layout, $homeSettings['enabled_layouts'], true) ? 'checked' : '' ?>>Layout <?= $layout ?></label><?php endfor; ?></div></div><div class="grid" style="margin-top:20px;"><?php for ($slot = 0; $slot < 5; $slot++): $image = $homeSettings['images'][$slot] ?? null; ?><div class="image-editor"><h3>Foto <?= $slot + 1 ?></h3><?php if ($image !== null): ?><img class="image-preview" src="<?= htmlspecialchars($image['path']) ?>?v=<?= urlencode((string) ($image['updated_at'] ?? '1')) ?>" alt="Anteprima foto <?= $slot + 1 ?>"><input type="hidden" name="keep_image[<?= $slot ?>]" value="<?= htmlspecialchars($image['filename']) ?>"><?php else: ?><div class="image-preview" style="display:flex;align-items:center;justify-content:center;color:#6b7280;">Nessuna immagine caricata</div><?php endif; ?><label>Carica / sostituisci immagine</label><input type="file" name="home_images[<?= $slot ?>]" accept="image/png,image/jpeg,image/webp,image/gif"><div style="margin-top:12px;"><label>Titolo foto</label><input name="image_title[<?= $slot ?>]" value="<?= htmlspecialchars($image['title'] ?? ('Foto home ' . ($slot + 1))) ?>"></div><div style="margin-top:12px;"><label>Descrizione foto</label><textarea name="image_caption[<?= $slot ?>]"><?= htmlspecialchars($image['caption'] ?? 'Immagine hero gestita da superadmin.') ?></textarea></div><?php if ($image !== null): ?><label style="margin-top:12px; display:flex; align-items:center; gap:8px; font-weight:600;"><input type="checkbox" name="remove_image[<?= $slot ?>]" value="1" style="width:auto;">Rimuovi questa foto</label><?php endif; ?></div><?php endfor; ?></div><p style="margin-top:20px; display:flex; gap:12px; flex-wrap:wrap;"><button type="submit">Salva configurazione home</button><a class="button ghost" href="/index.php">Torna alla dashboard</a></p></form><?php endif; ?></section>
    <?php endif; ?>

    <h2>Dashboard scadenze</h2>
    <p>Monitora fatturato, riscosso, da riscuotere, pratiche inviate al legale, rateizzazione e rating cliente a 5 stelle.</p>

    <div class="card"><form method="post"><div class="grid"><div><label for="xml_directory">Directory fatture XML</label><input id="xml_directory" name="xml_directory" value="<?= htmlspecialchars($xmlDirectory) ?>"></div><div><label for="contacts_path">File contatti CSV</label><input id="contacts_path" name="contacts_path" value="<?= htmlspecialchars($contactsPath) ?>"></div><div><label for="calendar_id">Google Calendar ID</label><input id="calendar_id" name="calendar_id" value="<?= htmlspecialchars($calendarId) ?>"></div><div><label for="chart_group_by">Grafici raggruppati per</label><select id="chart_group_by" name="chart_group_by"><option value="cliente" <?= $chartGroupBy === 'cliente' ? 'selected' : '' ?>>Cliente</option><option value="cf" <?= $chartGroupBy === 'cf' ? 'selected' : '' ?>>CF / P.IVA</option></select></div></div><p style="margin-top: 16px; display: flex; gap: 12px; flex-wrap: wrap;"><button type="submit">Aggiorna scadenzario</button><a class="button secondary" href="?action=connect_google&amp;xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>">Collega Google Calendar</a><button class="secondary" type="submit" name="action" value="push_google">Invia scadenze a Google</button></p></form></div>

    <div class="grid">
        <div class="card metric-card"><strong>Scadenze totali</strong><br><span class="amount"><?= (int) $summary['total_dues'] ?></span></div>
        <div class="card metric-card"><strong>Fatturato</strong><br><span class="amount"><?= euro((float) $summary['total_amount']) ?></span></div>
        <div class="card metric-card"><strong>Riscosso</strong><br><span class="amount"><?= euro((float) $summary['collected_amount']) ?></span></div>
        <div class="card metric-card"><strong>Da riscuotere</strong><br><span class="amount"><?= euro((float) $summary['outstanding_amount']) ?></span></div>
        <div class="card metric-card"><strong>Inviato al legale</strong><br><span class="amount"><?= euro((float) $summary['legal_amount']) ?></span></div>
    </div>

    <div class="card"><h2>Grafico overview</h2><div class="chart"><?php $overviewMax = max($summary['charts']['overview'] ?: [0]); foreach ($summary['charts']['overview'] as $label => $value): ?><div class="bar-row"><strong><?= htmlspecialchars($label) ?></strong><div class="bar-track"><div class="bar-fill" style="width: <?= number_format(percentOf((float) $value, (float) $overviewMax), 2, '.', '') ?>%;"></div></div><span class="amount"><?= euro((float) $value) ?></span></div><?php endforeach; ?></div></div>

    <div class="card"><h2>Grafico clienti / CF</h2><p class="muted">Ordinato per grandezza di cifra e raggruppato per <?= $chartGroupBy === 'cf' ? 'codice fiscale / partita IVA' : 'cliente' ?>.</p><div class="chart"><?php $customersMax = 0.0; foreach ($summary['customers'] as $customer) { $customersMax = max($customersMax, (float) $customer['turnover']); } foreach ($summary['customers'] as $customer): $label = $chartGroupBy === 'cf' ? ($customer['cf'] ?: '-') : $customer['client']; ?><div class="bar-row"><div><strong><?= htmlspecialchars($label) ?></strong><br><span class="muted"><?= htmlspecialchars($customer['client']) ?></span> — <span class="stars"><?= htmlspecialchars(renderStars((int) $customer['stars'])) ?></span></div><div class="bar-track"><div class="bar-fill" style="width: <?= number_format(percentOf((float) $customer['turnover'], $customersMax), 2, '.', '') ?>%;"></div></div><span class="amount"><?= euro((float) $customer['turnover']) ?></span></div><?php endforeach; ?></div></div>

    <div class="card"><h2>Rating clienti</h2><table><thead><tr><th>Cliente</th><th>CF / P.IVA</th><th>Stelle</th><th>Fatturato</th><th>Riscosso</th><th>Da riscuotere</th><th>Legale</th></tr></thead><tbody><?php foreach ($summary['customers'] as $customer): ?><tr><td><?= htmlspecialchars($customer['client']) ?></td><td><?= htmlspecialchars($customer['cf']) ?></td><td class="stars"><?= htmlspecialchars(renderStars((int) $customer['stars'])) ?></td><td class="amount"><?= euro((float) $customer['turnover']) ?></td><td class="amount"><?= euro((float) $customer['collected']) ?></td><td class="amount"><?= euro((float) $customer['outstanding']) ?></td><td class="amount"><?= euro((float) $customer['legal']) ?></td></tr><?php endforeach; ?></tbody></table></div>

    <div class="card"><h2>Scadenzario generale</h2><table><thead><tr><th>Scadenza</th><th>Cliente</th><th>Contatti</th><th>Fattura</th><th>Rata</th><th>Pagamento</th><th>Importo</th><th>Pagamenti / Avvocato</th><th>Numero rate</th></tr></thead><tbody><?php foreach ($dues as $due): $dueDateClass = isDueBeforeInvoiceDate($due->dueDate, $due->invoiceDate) ? 'due-date-anomaly' : ''; ?><tr><td class="<?= $dueDateClass ?>"><?= htmlspecialchars($due->dueDate) ?></td><td><?= htmlspecialchars($due->clientName) ?><br><span class="muted"><?= htmlspecialchars($due->clientVat ?? '-') ?></span></td><td><?= htmlspecialchars(($due->phone ?? '-') . ' / ' . ($due->email ?? '-')) ?></td><td><?= htmlspecialchars($due->invoiceNumber) ?><br><span class="muted">Data: <?= htmlspecialchars($due->invoiceDate) ?></span></td><td><?= $due->installmentNumber ?>/<?= $due->installmentCount ?></td><td><span class="pill"><?= htmlspecialchars($due->paymentTypeLabel) ?></span></td><td class="amount"><?= euro($due->amount) ?></td><td><form method="post" class="compact-form"><input type="hidden" name="action" value="save_due_status"><input type="hidden" name="due_id" value="<?= htmlspecialchars((string) $due->dueId) ?>"><label><input type="checkbox" name="pagamenti" value="1" <?= $due->paid ? 'checked' : '' ?>> Pagata</label><label><input type="checkbox" name="avvocato" value="1" <?= $due->lawyer ? 'checked' : '' ?>> Avvocato</label><button type="submit">Salva</button><div class="muted <?= $due->lawyer ? 'status-legal' : ($due->paid ? 'status-paid' : 'status-open') ?>"><?= $due->lawyer ? 'Pratica al legale' : ($due->paid ? 'Incassata' : 'Aperta') ?></div></form></td><td><form method="post" class="compact-form"><input type="hidden" name="action" value="save_installments"><input type="hidden" name="invoice_id" value="<?= htmlspecialchars((string) $due->invoiceId) ?>"><input type="number" min="1" max="24" name="numero_rate" value="<?= (int) $due->installmentCount ?>"><button type="submit">Aggiorna rate</button></form></td></tr><?php endforeach; ?></tbody></table></div>

    <div class="card"><h2>Esploso per tipo di pagamento</h2><?php foreach ($summary['by_payment_type'] as $code => $group): ?><h3><?= htmlspecialchars($group['label']) ?> (<?= htmlspecialchars($code) ?>)</h3><p>Scadenze: <strong><?= (int) $group['count'] ?></strong> — Totale: <strong><?= euro((float) $group['amount']) ?></strong></p><table><thead><tr><th>Scadenza</th><th>Cliente</th><th>Fattura</th><th>Rata</th><th>Importo</th><th>Stato</th></tr></thead><tbody><?php foreach ($group['items'] as $item): $dueDateClass = isDueBeforeInvoiceDate($item->dueDate, $item->invoiceDate) ? 'due-date-anomaly' : ''; ?><tr><td class="<?= $dueDateClass ?>"><?= htmlspecialchars($item->dueDate) ?></td><td><?= htmlspecialchars($item->clientName) ?></td><td><?= htmlspecialchars($item->invoiceNumber) ?></td><td><?= $item->installmentNumber ?>/<?= $item->installmentCount ?></td><td class="amount"><?= euro($item->amount) ?></td><td><?= $item->lawyer ? 'Avvocato' : ($item->paid ? 'Pagata' : 'Da incassare') ?></td></tr><?php endforeach; ?></tbody></table><?php endforeach; ?></div>
</div>
</body>
</html>
