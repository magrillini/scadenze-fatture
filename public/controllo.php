<?php

declare(strict_types=1);

require __DIR__ . '/dashboard-bootstrap.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CONTROLLO</title>
    <style>
        :root { color-scheme: light; --bg: #f3f4f6; --text: #111827; --muted: #6b7280; --card: #ffffff; --primary: #2563eb; --secondary: #059669; --accent: #7c3aed; --warning: #b91c1c; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; background: var(--bg); color: var(--text); }
        .container { max-width: 1380px; margin: 0 auto; padding: 24px; }
        .card { background: var(--card); border-radius: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); padding: 20px; margin-bottom: 20px; }
        .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        label { display: block; font-weight: 700; margin-bottom: 6px; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; box-sizing: border-box; font: inherit; }
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
        .chart { display: grid; gap: 12px; }
        .bar-row { display: grid; grid-template-columns: minmax(140px, 240px) 1fr auto; gap: 12px; align-items: center; }
        .bar-track { background: #e5e7eb; border-radius: 999px; overflow: hidden; min-height: 14px; }
        .bar-fill { min-height: 14px; border-radius: 999px; background: linear-gradient(90deg, var(--primary), var(--accent)); }
        .stars { color: #f59e0b; font-size: 1.15rem; letter-spacing: 1px; }
        .status-paid { color: #166534; font-weight: 700; }
        .status-open { color: #92400e; font-weight: 700; }
        .status-legal { color: #991b1b; font-weight: 700; }
        @media (max-width: 768px) { .bar-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="page-actions">
        <div>
            <a class="button" href="index.php">Home</a>
            <a class="button secondary" href="controllo.php">CONTROLLO</a>
            <a class="button ghost" href="scadenzario.php?xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>&amp;chart_group_by=<?= urlencode($chartGroupBy) ?>&amp;client_search=<?= urlencode($clientSearch) ?>&amp;amount_min=<?= urlencode($amountMin) ?>&amp;amount_max=<?= urlencode($amountMax) ?>">Scadenzario generale</a>
            <a class="button ghost" href="pagate.php?xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>&amp;chart_group_by=<?= urlencode($chartGroupBy) ?>&amp;client_search=<?= urlencode($clientSearch) ?>&amp;amount_min=<?= urlencode($amountMin) ?>&amp;amount_max=<?= urlencode($amountMax) ?>">Pagate</a>
            <a class="button ghost" href="avvocato.php?xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>&amp;chart_group_by=<?= urlencode($chartGroupBy) ?>&amp;client_search=<?= urlencode($clientSearch) ?>&amp;amount_min=<?= urlencode($amountMin) ?>&amp;amount_max=<?= urlencode($amountMax) ?>">Avvocato</a>
        </div>
        <span class="pill">Monitoraggi dettagliati e filtri operativi</span>
    </div>

    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="card">
        <h1>CONTROLLO</h1>
        <p class="muted">In questa pagina trovi i vari monitoraggi, la ricerca fatture, la gestione pagamenti, le rate e l'analisi per cliente.</p>
    </section>

    <section class="card"><form method="post"><div class="grid"><div><label for="xml_directory">Directory fatture XML</label><input id="xml_directory" name="xml_directory" value="<?= htmlspecialchars($xmlDirectory) ?>"></div><div><label for="contacts_path">File contatti CSV</label><input id="contacts_path" name="contacts_path" value="<?= htmlspecialchars($contactsPath) ?>"></div><div><label for="calendar_id">Google Calendar ID</label><input id="calendar_id" name="calendar_id" value="<?= htmlspecialchars($calendarId) ?>"></div><div><label for="chart_group_by">Grafici raggruppati per</label><select id="chart_group_by" name="chart_group_by"><option value="cliente" <?= $chartGroupBy === 'cliente' ? 'selected' : '' ?>>Cliente</option><option value="cf" <?= $chartGroupBy === 'cf' ? 'selected' : '' ?>>CF / P.IVA</option></select></div></div><p style="margin-top: 16px; display: flex; gap: 12px; flex-wrap: wrap;"><button type="submit">Aggiorna scadenzario</button><a class="button secondary" href="?action=connect_google&amp;xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>">Collega Google Calendar</a><button class="secondary" type="submit" name="action" value="push_google">Invia scadenze a Google</button></p></form></section>

    <section class="card"><h2>Grafico clienti / CF</h2><p class="muted">Ordinato per grandezza di cifra e raggruppato per <?= $chartGroupBy === 'cf' ? 'codice fiscale / partita IVA' : 'cliente' ?>.</p><div class="chart"><?php $customersMax = 0.0; foreach ($summary['customers'] as $customer) { $customersMax = max($customersMax, (float) $customer['turnover']); } foreach ($summary['customers'] as $customer): $label = $chartGroupBy === 'cf' ? ($customer['cf'] ?: '-') : $customer['client']; ?><div class="bar-row"><div><strong><?= htmlspecialchars($label) ?></strong><br><span class="muted"><?= htmlspecialchars($customer['client']) ?></span> — <span class="stars"><?= htmlspecialchars(renderStars((int) $customer['stars'])) ?></span></div><div class="bar-track"><div class="bar-fill" style="width: <?= number_format(percentOf((float) $customer['turnover'], $customersMax), 2, '.', '') ?>%;"></div></div><span class="amount"><?= euro((float) $customer['turnover']) ?></span></div><?php endforeach; ?></div></section>

    <section class="card"><h2>Rating clienti</h2><table><thead><tr><th>Cliente</th><th>CF / P.IVA</th><th>Stelle</th><th>Fatturato</th><th>Riscosso</th><th>Da riscuotere</th><th>Legale</th></tr></thead><tbody><?php foreach ($summary['customers'] as $customer): ?><tr><td><?= htmlspecialchars($customer['client']) ?></td><td><?= htmlspecialchars($customer['cf']) ?></td><td class="stars"><?= htmlspecialchars(renderStars((int) $customer['stars'])) ?></td><td class="amount"><?= euro((float) $customer['turnover']) ?></td><td class="amount"><?= euro((float) $customer['collected']) ?></td><td class="amount"><?= euro((float) $customer['outstanding']) ?></td><td class="amount"><?= euro((float) $customer['legal']) ?></td></tr><?php endforeach; ?></tbody></table></section>

    <section class="card">
        <div class="table-tools">
            <div>
                <h2>Scadenzario generale su pagina dedicata</h2>
                <p class="muted">Per una lettura più ampia e ordinata, le pratiche aperte, pagate e affidate all'avvocato sono ora divise in pagine dedicate con gli stessi filtri.</p>
            </div>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <a class="button secondary" href="scadenzario.php?xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>&amp;chart_group_by=<?= urlencode($chartGroupBy) ?>&amp;client_search=<?= urlencode($clientSearch) ?>&amp;amount_min=<?= urlencode($amountMin) ?>&amp;amount_max=<?= urlencode($amountMax) ?>">Apri scadenzario generale</a>
                <a class="button ghost" href="pagate.php?xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>&amp;chart_group_by=<?= urlencode($chartGroupBy) ?>&amp;client_search=<?= urlencode($clientSearch) ?>&amp;amount_min=<?= urlencode($amountMin) ?>&amp;amount_max=<?= urlencode($amountMax) ?>">Apri pagate</a>
                <a class="button ghost" href="avvocato.php?xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>&amp;chart_group_by=<?= urlencode($chartGroupBy) ?>&amp;client_search=<?= urlencode($clientSearch) ?>&amp;amount_min=<?= urlencode($amountMin) ?>&amp;amount_max=<?= urlencode($amountMax) ?>">Apri avvocato</a>
            </div>
        </div>
    </section>

    <section class="card"><h2>Esploso per tipo di pagamento</h2><?php foreach ($summary['by_payment_type'] as $code => $group): ?><h3><?= htmlspecialchars($group['label']) ?> (<?= htmlspecialchars($code) ?>)</h3><p>Scadenze: <strong><?= (int) $group['count'] ?></strong> — Totale: <strong><?= euro((float) $group['amount']) ?></strong></p><table><thead><tr><th>Scadenza</th><th>Cliente</th><th>Fattura</th><th>Rata</th><th>Importo</th><th>Stato</th></tr></thead><tbody><?php foreach ($group['items'] as $item): $dueDateClass = isDueBeforeInvoiceDate($item->dueDate, $item->invoiceDate) ? 'due-date-anomaly' : ''; ?><tr><td class="<?= $dueDateClass ?>"><?= htmlspecialchars($item->dueDate) ?></td><td><?= htmlspecialchars($item->clientName) ?></td><td><?= htmlspecialchars($item->invoiceNumber) ?></td><td><?= $item->installmentNumber ?>/<?= $item->installmentCount ?></td><td class="amount"><?= euro($item->amount) ?></td><td><?= $item->lawyer ? 'Avvocato' : ($item->paid ? 'Pagata' : 'Da incassare') ?></td></tr><?php endforeach; ?></tbody></table><?php endforeach; ?></section>
</div>
</body>
</html>
