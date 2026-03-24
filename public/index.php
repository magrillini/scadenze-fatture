<?php

declare(strict_types=1);

require __DIR__ . '/dashboard-bootstrap.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controllo Fatture XML</title>
    <style>
        :root { color-scheme: light; --bg: #f3f4f6; --text: #111827; --muted: #6b7280; --card: #ffffff; --primary: #2563eb; --secondary: #059669; --accent: #7c3aed; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; background: var(--bg); color: var(--text); }
        .container { max-width: 1380px; margin: 0 auto; padding: 24px; }
        .card { background: var(--card); border-radius: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); padding: 20px; margin-bottom: 20px; }
        .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        .hero { position: relative; overflow: hidden; background: linear-gradient(135deg, #111827 0%, #1d4ed8 48%, #7c3aed 100%); color: white; }
        .hero-content { position: relative; z-index: 1; display: grid; gap: 18px; }
        .hero h1 { margin: 0; font-size: clamp(2rem, 4vw, 3.6rem); }
        .hero p { margin: 0; max-width: 760px; color: rgba(255,255,255,.88); }
        .hero-badges, .page-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .hero-badge, .pill { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.18); border-radius: 999px; padding: 8px 12px; font-size: 13px; }
        .pill { background: #dbeafe; border-color: transparent; color: #1d4ed8; }
        label { display: block; font-weight: 700; margin-bottom: 6px; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; box-sizing: border-box; font: inherit; }
        textarea { min-height: 88px; resize: vertical; }
        .button { display: inline-block; border: 0; border-radius: 10px; padding: 10px 14px; background: var(--primary); color: white; text-decoration: none; cursor: pointer; }
        .button.secondary { background: var(--secondary); }
        .button.ghost { background: #e5e7eb; color: var(--text); }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .amount { font-weight: 700; }
        .chart { display: grid; gap: 12px; }
        .bar-row { display: grid; grid-template-columns: minmax(140px, 220px) 1fr auto; gap: 12px; align-items: center; }
        .bar-track { background: #e5e7eb; border-radius: 999px; overflow: hidden; min-height: 14px; }
        .bar-fill { min-height: 14px; border-radius: 999px; background: linear-gradient(90deg, var(--primary), var(--accent)); }
        .metric-card .amount { font-size: 1.6rem; display: inline-block; margin-top: 8px; }
        .muted { color: var(--muted); }
        .pie-grid { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .pie-card { border: 1px solid #e5e7eb; border-radius: 16px; padding: 18px; background: #f9fafb; }
        .pie-wrapper { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
        .pie-chart { width: 160px; height: 160px; border-radius: 50%; position: relative; flex: 0 0 auto; }
        .pie-chart::after { content: ''; position: absolute; inset: 24px; border-radius: 50%; background: #fff; box-shadow: inset 0 0 0 1px #e5e7eb; }
        .legend { display: grid; gap: 10px; flex: 1; min-width: 180px; }
        .legend-item { display: grid; grid-template-columns: auto 1fr auto; gap: 10px; align-items: center; }
        .legend-color { width: 14px; height: 14px; border-radius: 999px; }
        @media (max-width: 768px) { .bar-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="page-actions">
        <a class="button" href="index.php">Home</a>
        <a class="button secondary" href="controllo.php">Apri CONTROLLO</a>
    </div>

    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="card hero">
        <div class="hero-content">
            <div>
                <h1>CONTROLLO FATTURE XML — IO RECUPERO</h1>
                <p>Questa home mostra il riepilogo generale. Tutti i monitoraggi operativi, le ricerche e i controlli dettagliati sono disponibili nella pagina dedicata <strong>CONTROLLO</strong>.</p>
            </div>
            <div class="hero-badges">
                <span class="hero-badge">Scadenze totali: <?= (int) $summary['total_dues'] ?></span>
                <span class="hero-badge">Da riscuotere: <?= euro((float) $summary['outstanding_amount']) ?></span>
                <span class="hero-badge">Clienti monitorati: <?= count($summary['customers']) ?></span>
            </div>
            <div>
                <a class="button secondary" href="controllo.php?xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>&amp;chart_group_by=<?= urlencode($chartGroupBy) ?>">Vai alla pagina CONTROLLO</a>
            </div>
        </div>
    </section>

    <section class="grid">
        <div class="card metric-card"><strong>Scadenze totali</strong><br><span class="amount"><?= (int) $summary['total_dues'] ?></span></div>
        <div class="card metric-card"><strong>Fatturato</strong><br><span class="amount"><?= euro((float) $summary['total_amount']) ?></span></div>
        <div class="card metric-card"><strong>Riscosso</strong><br><span class="amount"><?= euro((float) $summary['collected_amount']) ?></span></div>
        <div class="card metric-card"><strong>Da riscuotere</strong><br><span class="amount"><?= euro((float) $summary['outstanding_amount']) ?></span></div>
        <div class="card metric-card"><strong>Inviato al legale</strong><br><span class="amount"><?= euro((float) $summary['legal_amount']) ?></span></div>
    </section>

    <section class="card">
        <h2>Grafici generali</h2>
        <p class="muted">La home mantiene solo i riepiloghi generali richiesti.</p>
        <div class="chart"><?php $overviewMax = max($summary['charts']['overview'] ?: [0]); foreach ($summary['charts']['overview'] as $label => $value): ?><div class="bar-row"><strong><?= htmlspecialchars($label) ?></strong><div class="bar-track"><div class="bar-fill" style="width: <?= number_format(percentOf((float) $value, (float) $overviewMax), 2, '.', '') ?>%;"></div></div><span class="amount"><?= euro((float) $value) ?></span></div><?php endforeach; ?></div>
    </section>

    <section class="card">
        <h2>Grafici a torta scadenze</h2>
        <p class="muted">Rosso per fatture scadute non pagate, arancione per fatture in scadenza entro 19 giorni, verde per fatture oltre 20 giorni.</p>
        <div class="pie-grid">
            <div class="pie-card">
                <h3>Distribuzione per importo totale IVA</h3>
                <div class="pie-wrapper">
                    <div class="pie-chart" style="background: <?= htmlspecialchars(buildPieGradient($pieByAmount)) ?>;"></div>
                    <div class="legend"><?php foreach ($pieSegments as $segment): ?><div class="legend-item"><span class="legend-color" style="background: <?= htmlspecialchars($segment['color']) ?>;"></span><span><?= htmlspecialchars($segment['label']) ?></span><strong><?= euro((float) $segment['value']) ?></strong></div><?php endforeach; ?></div>
                </div>
            </div>
            <div class="pie-card">
                <h3>Distribuzione per numero fatture</h3>
                <div class="pie-wrapper">
                    <div class="pie-chart" style="background: <?= htmlspecialchars(buildPieGradient($pieByCount)) ?>;"></div>
                    <div class="legend"><?php foreach ($pieSegments as $segment): ?><div class="legend-item"><span class="legend-color" style="background: <?= htmlspecialchars($segment['color']) ?>;"></span><span><?= htmlspecialchars($segment['label']) ?></span><strong><?= (int) $segment['count'] ?></strong></div><?php endforeach; ?></div>
                </div>
            </div>
        </div>
    </section>
</div>
</body>
</html>
