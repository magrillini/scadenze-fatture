<?php

declare(strict_types=1);

require __DIR__ . '/dashboard-bootstrap.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ricerca Cliente</title>
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
        .customer-search-panel { display: grid; gap: 14px; }
        .customer-search-toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .search-input-wrap { position: relative; flex: 1 1 380px; min-width: 250px; }
        .search-input-wrap input { padding-left: 38px; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #6b7280; font-size: 18px; pointer-events: none; }
        .button.all-customers { background: #d40000; color: #000000; text-transform: uppercase; font-variant: small-caps; letter-spacing: .04em; font-weight: 800; }
        .customer-results { border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .customer-results button { width: 100%; text-align: left; background: #fff; color: var(--text); border: 0; border-bottom: 1px solid #f3f4f6; border-radius: 0; padding: 12px 14px; }
        .customer-results button:hover { background: #f9fafb; }
        .customer-results button:last-child { border-bottom: 0; }
        .selected-customer { border: 1px solid #bfdbfe; border-radius: 10px; background: #eff6ff; padding: 12px 14px; }
        @media (max-width: 768px) { .bar-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="page-actions">
        <div>
            <a class="button" href="index.php">Home</a>
            <a class="button secondary" href="controllo.php">Ricerca Cliente</a>
            <a class="button ghost" href="scadenzario.php?xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>&amp;chart_group_by=<?= urlencode($chartGroupBy) ?>&amp;client_search=<?= urlencode($clientSearch) ?>&amp;amount_min=<?= urlencode($amountMin) ?>&amp;amount_max=<?= urlencode($amountMax) ?>">Scadenzario generale</a>
        </div>
        <span class="pill">Monitoraggi dettagliati e filtri operativi</span>
    </div>

    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="card">
        <h1>Ricerca Cliente</h1>
        <p class="muted">Digita almeno 3 caratteri per cercare e selezionare un cliente. Usa “VEDI TUTTI” solo quando vuoi l’elenco completo.</p>
    </section>

    <section class="card customer-search-panel">
        <h2>Ricerca cliente</h2>
        <div class="customer-search-toolbar">
            <div class="search-input-wrap">
                <span class="search-icon" aria-hidden="true">🔍</span>
                <input id="customer_search" type="search" autocomplete="off" placeholder="Scrivi almeno 3 caratteri (nome o CF/P.IVA)">
            </div>
            <button type="button" class="button all-customers" id="show_all_customers">VEDI TUTTI</button>
        </div>
        <p class="muted" id="customer_search_hint">Nessun cliente mostrato all’apertura per migliorare la lettura della pagina.</p>
        <div id="customer_results" class="customer-results" hidden></div>
        <div id="selected_customer" class="selected-customer" hidden></div>
    </section>

    <section class="card"><form method="post"><div class="grid"><div><label for="xml_directory">Directory fatture XML</label><input id="xml_directory" name="xml_directory" value="<?= htmlspecialchars($xmlDirectory) ?>"></div><div><label for="contacts_path">File contatti CSV</label><input id="contacts_path" name="contacts_path" value="<?= htmlspecialchars($contactsPath) ?>"></div><div><label for="calendar_id">Google Calendar ID</label><input id="calendar_id" name="calendar_id" value="<?= htmlspecialchars($calendarId) ?>"></div><div><label for="chart_group_by">Grafici raggruppati per</label><select id="chart_group_by" name="chart_group_by"><option value="cliente" <?= $chartGroupBy === 'cliente' ? 'selected' : '' ?>>Cliente</option><option value="cf" <?= $chartGroupBy === 'cf' ? 'selected' : '' ?>>CF / P.IVA</option></select></div></div><p style="margin-top: 16px; display: flex; gap: 12px; flex-wrap: wrap;"><button type="submit">Aggiorna scadenzario</button><a class="button secondary" href="?action=connect_google&amp;xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>">Collega Google Calendar</a><button class="secondary" type="submit" name="action" value="push_google">Invia scadenze a Google</button></p></form></section>

    <section class="card"><h2>Grafico clienti / CF</h2><p class="muted">Ordinato per grandezza di cifra e raggruppato per <?= $chartGroupBy === 'cf' ? 'codice fiscale / partita IVA' : 'cliente' ?>.</p><div class="chart"><?php $customersMax = 0.0; foreach ($summary['customers'] as $customer) { $customersMax = max($customersMax, (float) $customer['turnover']); } foreach ($summary['customers'] as $customer): $label = $chartGroupBy === 'cf' ? ($customer['cf'] ?: '-') : $customer['client']; ?><div class="bar-row"><div><strong><?= htmlspecialchars($label) ?></strong><br><span class="muted"><?= htmlspecialchars($customer['client']) ?></span> — <span class="stars"><?= htmlspecialchars(renderStars((int) $customer['stars'])) ?></span></div><div class="bar-track"><div class="bar-fill" style="width: <?= number_format(percentOf((float) $customer['turnover'], $customersMax), 2, '.', '') ?>%;"></div></div><span class="amount"><?= euro((float) $customer['turnover']) ?></span></div><?php endforeach; ?></div></section>

    <section class="card"><h2>Rating clienti</h2><table><thead><tr><th>Cliente</th><th>CF / P.IVA</th><th>Stelle</th><th>Fatturato</th><th>Riscosso</th><th>Da riscuotere</th><th>Legale</th></tr></thead><tbody><?php foreach ($summary['customers'] as $customer): ?><tr><td><?= htmlspecialchars($customer['client']) ?></td><td><?= htmlspecialchars($customer['cf']) ?></td><td class="stars"><?= htmlspecialchars(renderStars((int) $customer['stars'])) ?></td><td class="amount"><?= euro((float) $customer['turnover']) ?></td><td class="amount"><?= euro((float) $customer['collected']) ?></td><td class="amount"><?= euro((float) $customer['outstanding']) ?></td><td class="amount"><?= euro((float) $customer['legal']) ?></td></tr><?php endforeach; ?></tbody></table></section>

    <section class="card">
        <div class="table-tools">
            <div>
                <h2>Scadenzario generale su pagina dedicata</h2>
                <p class="muted">Per una lettura più ampia e comoda, lo scadenzario completo è stato spostato in una pagina separata con i relativi filtri.</p>
            </div>
            <a class="button secondary" href="scadenzario.php?xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>&amp;chart_group_by=<?= urlencode($chartGroupBy) ?>&amp;client_search=<?= urlencode($clientSearch) ?>&amp;amount_min=<?= urlencode($amountMin) ?>&amp;amount_max=<?= urlencode($amountMax) ?>">Apri scadenzario generale</a>
        </div>
    </section>

    <section class="card"><h2>Esploso per tipo di pagamento</h2><?php foreach ($summary['by_payment_type'] as $code => $group): ?><h3><?= htmlspecialchars($group['label']) ?> (<?= htmlspecialchars($code) ?>)</h3><p>Scadenze: <strong><?= (int) $group['count'] ?></strong> — Totale: <strong><?= euro((float) $group['amount']) ?></strong></p><table><thead><tr><th>Scadenza</th><th>Cliente</th><th>Fattura</th><th>Rata</th><th>Importo</th><th>Stato</th></tr></thead><tbody><?php foreach ($group['items'] as $item): $dueDateClass = isDueBeforeInvoiceDate($item->dueDate, $item->invoiceDate) ? 'due-date-anomaly' : ''; ?><tr><td class="<?= $dueDateClass ?>"><?= htmlspecialchars($item->dueDate) ?></td><td><?= htmlspecialchars($item->clientName) ?></td><td><?= htmlspecialchars($item->invoiceNumber) ?></td><td><?= $item->installmentNumber ?>/<?= $item->installmentCount ?></td><td class="amount"><?= euro($item->amount) ?></td><td><?= $item->lawyer ? 'Avvocato' : ($item->paid ? 'Pagata' : 'Da incassare') ?></td></tr><?php endforeach; ?></tbody></table><?php endforeach; ?></section>
</div>
<?php
$customerChoices = array_map(
    static fn (array $customer): array => [
        'client' => (string) ($customer['client'] ?? ''),
        'cf' => (string) ($customer['cf'] ?? ''),
        'turnover' => euro((float) ($customer['turnover'] ?? 0)),
        'outstanding' => euro((float) ($customer['outstanding'] ?? 0)),
        'stars' => renderStars((int) ($customer['stars'] ?? 0)),
    ],
    $summary['customers']
);
?>
<script>
const customers = <?= json_encode($customerChoices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const input = document.getElementById('customer_search');
const showAllButton = document.getElementById('show_all_customers');
const results = document.getElementById('customer_results');
const selected = document.getElementById('selected_customer');
const hint = document.getElementById('customer_search_hint');

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
}

function renderResults(list) {
    if (!list.length) {
        results.hidden = true;
        results.innerHTML = '';
        hint.textContent = 'Nessun cliente trovato.';
        return;
    }

    results.hidden = false;
    hint.textContent = `${list.length} cliente/i trovato/i. Selezionane uno.`;
    results.innerHTML = list.map((customer, index) => `
        <button type="button" data-index="${index}">
            <strong>${escapeHtml(customer.client || '-')}</strong><br>
            <span class="muted">${escapeHtml(customer.cf || 'CF/P.IVA non disponibile')}</span>
        </button>
    `).join('');

    Array.from(results.querySelectorAll('button')).forEach((button) => {
        button.addEventListener('click', () => {
            const customer = list[Number(button.dataset.index)];
            selected.hidden = false;
            selected.innerHTML = `<strong>Cliente selezionato:</strong> ${escapeHtml(customer.client)}<br>
                <span class="muted">CF/P.IVA: ${escapeHtml(customer.cf || '-')} — Rating: ${escapeHtml(customer.stars)} — Fatturato: ${escapeHtml(customer.turnover)} — Da riscuotere: ${escapeHtml(customer.outstanding)}</span>`;
        });
    });
}

input.addEventListener('input', () => {
    const term = input.value.trim().toLowerCase();
    selected.hidden = true;
    if (term.length < 3) {
        results.hidden = true;
        results.innerHTML = '';
        hint.textContent = 'Digita almeno 3 caratteri per vedere i clienti.';
        return;
    }

    const filtered = customers.filter((customer) => (`${customer.client} ${customer.cf}`).toLowerCase().includes(term));
    renderResults(filtered);
});

showAllButton.addEventListener('click', () => {
    selected.hidden = true;
    renderResults(customers);
});
</script>
</body>
</html>
