<?php

declare(strict_types=1);

require __DIR__ . '/dashboard-bootstrap.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scadenzario generale</title>
    <style>
        :root { color-scheme: light; --bg: #f3f4f6; --text: #111827; --muted: #6b7280; --card: #ffffff; --primary: #2563eb; --secondary: #059669; --accent: #7c3aed; --warning: #b91c1c; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; background: var(--bg); color: var(--text); }
        .container { max-width: 1380px; margin: 0 auto; padding: 24px; }
        .card { background: var(--card); border-radius: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); padding: 20px; margin-bottom: 20px; }
        label { display: block; font-weight: 700; margin-bottom: 6px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; box-sizing: border-box; font: inherit; }
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
        .page-actions, .table-tools { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between; }
        .muted { color: var(--muted); }
        .compact-form { display: grid; gap: 8px; min-width: 220px; }
        .compact-form input[type='checkbox'] { width: auto; }
        .search-form-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); align-items: end; }
        .status-paid { color: #166534; font-weight: 700; }
        .status-open { color: #92400e; font-weight: 700; }
        .status-legal { color: #991b1b; font-weight: 700; }
    </style>
</head>
<body>
<div class="container">
    <div class="page-actions">
        <div>
            <a class="button" href="index.php">Home</a>
            <a class="button secondary" href="controllo.php?xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>&amp;chart_group_by=<?= urlencode($chartGroupBy) ?>">CONTROLLO</a>
            <a class="button ghost" href="scadenzario.php?xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>&amp;chart_group_by=<?= urlencode($chartGroupBy) ?>&amp;client_search=<?= urlencode($clientSearch) ?>&amp;amount_min=<?= urlencode($amountMin) ?>&amp;amount_max=<?= urlencode($amountMax) ?>">Scadenzario generale</a>
        </div>
        <span class="pill">Lettura dedicata dello scadenzario completo</span>
    </div>

    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="card">
        <h1>Scadenzario generale</h1>
        <p class="muted">Pagina dedicata alla consultazione completa delle scadenze, con filtri, stato pagamenti e gestione rate in una vista più leggibile.</p>
    </section>

    <section class="card">
        <div class="table-tools">
            <div>
                <h2>Ricerca fatture</h2>
                <p class="muted">Filtra per cliente, partita IVA, numero fattura o fascia di importo IVA inclusa.</p>
            </div>
            <div class="pill"><?= count($filteredDues) ?> risultati / <?= count($dues) ?> totali</div>
        </div>
        <form method="get" class="search-form-grid">
            <div><label for="client_search">Cliente / P.IVA / fattura</label><input id="client_search" name="client_search" value="<?= htmlspecialchars($clientSearch) ?>" placeholder="Es. Rossi, IT12345678901, 24/PA"></div>
            <div><label for="amount_min">Importo minimo IVA inclusa</label><input id="amount_min" name="amount_min" type="number" step="0.01" min="0" value="<?= htmlspecialchars($amountMin) ?>" placeholder="0,00"></div>
            <div><label for="amount_max">Importo massimo IVA inclusa</label><input id="amount_max" name="amount_max" type="number" step="0.01" min="0" value="<?= htmlspecialchars($amountMax) ?>" placeholder="1000,00"></div>
            <div>
                <input type="hidden" name="xml_directory" value="<?= htmlspecialchars($xmlDirectory) ?>">
                <input type="hidden" name="contacts_path" value="<?= htmlspecialchars($contactsPath) ?>">
                <input type="hidden" name="calendar_id" value="<?= htmlspecialchars($calendarId) ?>">
                <input type="hidden" name="chart_group_by" value="<?= htmlspecialchars($chartGroupBy) ?>">
                <button type="submit">Filtra fatture</button>
                <a class="button ghost" href="?xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>&amp;chart_group_by=<?= urlencode($chartGroupBy) ?>">Reset filtri</a>
            </div>
        </form>
        <table><thead><tr><th>Scadenza</th><th>Cliente</th><th>Contatti</th><th>Fattura</th><th>Rata</th><th>Pagamento</th><th>Importo</th><th>Pagamenti / Avvocato</th><th>Numero rate</th></tr></thead><tbody><?php foreach ($filteredDues as $due): $dueDateClass = isDueBeforeInvoiceDate($due->dueDate, $due->invoiceDate) ? 'due-date-anomaly' : ''; ?><tr><td class="<?= $dueDateClass ?>"><?= htmlspecialchars($due->dueDate) ?></td><td><?= htmlspecialchars($due->clientName) ?><br><span class="muted"><?= htmlspecialchars($due->clientVat ?? '-') ?></span></td><td><?= htmlspecialchars(($due->phone ?? '-') . ' / ' . ($due->email ?? '-')) ?></td><td><?= htmlspecialchars($due->invoiceNumber) ?><br><span class="muted">Data: <?= htmlspecialchars($due->invoiceDate) ?></span></td><td><?= $due->installmentNumber ?>/<?= $due->installmentCount ?></td><td><span class="pill"><?= htmlspecialchars($due->paymentTypeLabel) ?></span></td><td class="amount"><?= euro($due->amount) ?></td><td><form method="post" class="compact-form"><input type="hidden" name="action" value="save_due_status"><input type="hidden" name="due_id" value="<?= htmlspecialchars((string) $due->dueId) ?>"><input type="hidden" name="xml_directory" value="<?= htmlspecialchars($xmlDirectory) ?>"><input type="hidden" name="contacts_path" value="<?= htmlspecialchars($contactsPath) ?>"><input type="hidden" name="calendar_id" value="<?= htmlspecialchars($calendarId) ?>"><input type="hidden" name="chart_group_by" value="<?= htmlspecialchars($chartGroupBy) ?>"><input type="hidden" name="client_search" value="<?= htmlspecialchars($clientSearch) ?>"><input type="hidden" name="amount_min" value="<?= htmlspecialchars($amountMin) ?>"><input type="hidden" name="amount_max" value="<?= htmlspecialchars($amountMax) ?>"><label><input type="checkbox" name="pagamenti" value="1" <?= $due->paid ? 'checked' : '' ?>> Pagata</label><label><input type="checkbox" name="avvocato" value="1" <?= $due->lawyer ? 'checked' : '' ?>> Avvocato</label><button type="submit">Salva</button><div class="muted <?= $due->lawyer ? 'status-legal' : ($due->paid ? 'status-paid' : 'status-open') ?>"><?= $due->lawyer ? 'Pratica al legale' : ($due->paid ? 'Incassata' : 'Aperta') ?></div></form></td><td><form method="post" class="compact-form"><input type="hidden" name="action" value="save_installments"><input type="hidden" name="invoice_id" value="<?= htmlspecialchars((string) $due->invoiceId) ?>"><input type="hidden" name="xml_directory" value="<?= htmlspecialchars($xmlDirectory) ?>"><input type="hidden" name="contacts_path" value="<?= htmlspecialchars($contactsPath) ?>"><input type="hidden" name="calendar_id" value="<?= htmlspecialchars($calendarId) ?>"><input type="hidden" name="chart_group_by" value="<?= htmlspecialchars($chartGroupBy) ?>"><input type="hidden" name="client_search" value="<?= htmlspecialchars($clientSearch) ?>"><input type="hidden" name="amount_min" value="<?= htmlspecialchars($amountMin) ?>"><input type="hidden" name="amount_max" value="<?= htmlspecialchars($amountMax) ?>"><input type="number" min="1" max="24" name="numero_rate" value="<?= (int) $due->installmentCount ?>"><button type="submit">Aggiorna rate</button></form></td></tr><?php endforeach; ?><?php if ($filteredDues === []): ?><tr><td colspan="9" class="muted">Nessuna fattura trovata con i filtri selezionati.</td></tr><?php endif; ?></tbody></table>
    </section>
</div>
</body>
</html>
