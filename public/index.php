<?php

declare(strict_types=1);

use ScadenzeFatture\ContactsRepository;
use ScadenzeFatture\DashboardService;
use ScadenzeFatture\GoogleCalendarService;
use ScadenzeFatture\InvoiceParser;

require dirname(__DIR__) . '/src/bootstrap.php';

$xmlDirectory = trim($_POST['xml_directory'] ?? $_GET['xml_directory'] ?? dirname(__DIR__) . '/samples/xml');
$contactsPath = trim($_POST['contacts_path'] ?? $_GET['contacts_path'] ?? dirname(__DIR__) . '/samples/contatti-clienti.csv');
$calendarId = trim($_POST['calendar_id'] ?? $_GET['calendar_id'] ?? 'primary');
$message = null;
$error = null;
$dues = [];
$summary = ['total_dues' => 0, 'total_amount' => 0.0, 'by_payment_type' => []];

$contactsRepository = new ContactsRepository();
$parser = new InvoiceParser();
$dashboard = new DashboardService();
$googleService = new GoogleCalendarService(
    dirname(__DIR__) . '/config/google-calendar.local.json',
    dirname(__DIR__) . '/storage/google-token.json'
);

$redirectUri = sprintf(
    '%s://%s%s?action=oauth_callback',
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
    $_SERVER['HTTP_HOST'] ?? 'localhost',
    strtok($_SERVER['REQUEST_URI'] ?? '/index.php', '?')
);

try {
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

    $contacts = $contactsRepository->loadFromCsv($contactsPath);
    $dues = $parser->parseDirectory($xmlDirectory, $contacts);
    $summary = $dashboard->summarize($dues);

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
?><!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scadenze Fatture XML</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f3f4f6; color: #111827; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 3px 15px rgba(0,0,0,.08); padding: 20px; margin-bottom: 20px; }
        .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        label { display: block; font-weight: 700; margin-bottom: 6px; }
        input { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; box-sizing: border-box; }
        button, .button { display: inline-block; border: 0; border-radius: 8px; padding: 10px 14px; background: #2563eb; color: white; text-decoration: none; cursor: pointer; }
        .button.secondary, button.secondary { background: #059669; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .pill { background: #dbeafe; color: #1d4ed8; padding: 4px 8px; border-radius: 999px; font-size: 12px; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .amount { font-weight: 700; }
    </style>
</head>
<body>
<div class="container">
    <h1>Gestione scadenze fatture XML attive</h1>
    <p>Analizza fatture elettroniche XML, suddivide i pagamenti per tipologia, genera lo scadenzario generale e invia le scadenze a Google Calendar.</p>

    <?php if ($message): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post">
            <div class="grid">
                <div>
                    <label for="xml_directory">Directory fatture XML</label>
                    <input id="xml_directory" name="xml_directory" value="<?= htmlspecialchars($xmlDirectory) ?>">
                </div>
                <div>
                    <label for="contacts_path">File contatti CSV</label>
                    <input id="contacts_path" name="contacts_path" value="<?= htmlspecialchars($contactsPath) ?>">
                </div>
                <div>
                    <label for="calendar_id">Google Calendar ID</label>
                    <input id="calendar_id" name="calendar_id" value="<?= htmlspecialchars($calendarId) ?>">
                </div>
            </div>
            <p style="margin-top: 16px; display: flex; gap: 12px; flex-wrap: wrap;">
                <button type="submit">Aggiorna scadenzario</button>
                <a class="button secondary" href="?action=connect_google&amp;xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>">Collega Google Calendar</a>
                <button class="secondary" type="submit" name="action" value="push_google">Invia scadenze a Google</button>
            </p>
        </form>
    </div>

    <div class="grid">
        <div class="card"><strong>Scadenze totali</strong><br><span class="amount"><?= (int) $summary['total_dues'] ?></span></div>
        <div class="card"><strong>Importo totale</strong><br><span class="amount"><?= euro((float) $summary['total_amount']) ?></span></div>
        <div class="card"><strong>Tipi pagamento</strong><br><span class="amount"><?= count($summary['by_payment_type']) ?></span></div>
    </div>

    <div class="card">
        <h2>Scadenzario generale</h2>
        <table>
            <thead>
                <tr>
                    <th>Scadenza</th>
                    <th>Cliente</th>
                    <th>Telefono / Email</th>
                    <th>Fattura</th>
                    <th>Pagamento</th>
                    <th>Importo</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($dues as $due): ?>
                <tr>
                    <td><?= htmlspecialchars($due->dueDate) ?></td>
                    <td><?= htmlspecialchars($due->clientName) ?></td>
                    <td>
                        <?= htmlspecialchars(($due->phone ?? '-') . ' / ' . ($due->email ?? '-')) ?>
                    </td>
                    <td><?= htmlspecialchars($due->invoiceNumber) ?></td>
                    <td><span class="pill"><?= htmlspecialchars($due->paymentTypeLabel) ?></span></td>
                    <td class="amount"><?= euro($due->amount) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Esploso per tipo di pagamento</h2>
        <?php foreach ($summary['by_payment_type'] as $code => $group): ?>
            <h3><?= htmlspecialchars($group['label']) ?> (<?= htmlspecialchars($code) ?>)</h3>
            <p>Scadenze: <strong><?= (int) $group['count'] ?></strong> — Totale: <strong><?= euro((float) $group['amount']) ?></strong></p>
            <table>
                <thead>
                    <tr>
                        <th>Scadenza</th>
                        <th>Cliente</th>
                        <th>Fattura</th>
                        <th>Importo</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($group['items'] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item->dueDate) ?></td>
                        <td><?= htmlspecialchars($item->clientName) ?></td>
                        <td><?= htmlspecialchars($item->invoiceNumber) ?></td>
                        <td class="amount"><?= euro($item->amount) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
