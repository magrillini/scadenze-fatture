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
        .home-gallery { display: grid; gap: 14px; }
        .home-gallery.layout-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        .home-gallery.layout-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .home-gallery.layout-3 { grid-template-columns: 1.2fr .8fr .8fr; }
        .home-gallery.layout-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .home-gallery.layout-5 { grid-template-columns: repeat(12, minmax(0, 1fr)); }
        .home-gallery.layout-5 .home-photo:first-child { grid-column: span 6; }
        .home-gallery.layout-5 .home-photo:nth-child(2), .home-gallery.layout-5 .home-photo:nth-child(3) { grid-column: span 3; }
        .home-gallery.layout-5 .home-photo:nth-child(n+4) { grid-column: span 4; }
        .home-photo { position: relative; min-height: 220px; border-radius: 18px; overflow: hidden; background: rgba(255,255,255,.1); }
        .home-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .home-photo figcaption { position: absolute; inset: auto 0 0 0; padding: 16px; background: linear-gradient(180deg, transparent, rgba(17,24,39,.86)); }
        .home-photo strong, .home-photo span { display: block; }
        .home-photo span { margin-top: 4px; font-size: 14px; color: rgba(255,255,255,.82); }
        .home-empty, .image-editor { border-radius: 16px; }
        .home-empty { padding: 20px; border: 1px dashed rgba(255,255,255,.4); background: rgba(255,255,255,.1); }
        .image-editor { border: 1px solid #e5e7eb; padding: 16px; background: #f9fafb; }
        .image-preview { width: 100%; max-height: 180px; object-fit: cover; border-radius: 12px; margin-bottom: 12px; background: #e5e7eb; }
        @media (max-width: 768px) { .bar-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="page-actions">
        <a class="button" href="index.php">Home</a>
        <a class="button secondary" href="controllo.php">Apri Ricerca Cliente</a>
        <a class="button ghost" href="scadenzario.php">Registrazione pagamenti</a>
        <a class="button ghost" href="?superadmin=1">Area superadmin home</a>
        <?php if (!empty($_SESSION['is_superadmin'])): ?><a class="button ghost" href="?action=superadmin_logout">Logout superadmin</a><?php endif; ?>
    </div>

    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="card hero">
        <div class="hero-content">
            <div>
                <h1>RICERCA CLIENTE FATTURE XML — IO RECUPERO</h1>
                <p>Questa home mostra il riepilogo generale. Tutti i monitoraggi operativi, le ricerche e i controlli dettagliati sono disponibili nella pagina dedicata <strong>Ricerca Cliente</strong>.</p>
            </div>
            <div class="hero-badges">
                <span class="hero-badge">Scadenze totali: <?= (int) $summary['total_dues'] ?></span>
                <span class="hero-badge">Da riscuotere: <?= euro((float) $summary['outstanding_amount']) ?></span>
                <span class="hero-badge">Clienti monitorati: <?= count($summary['customers']) ?></span>
            </div>
            <div>
                <a class="button secondary" href="controllo.php?xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>&amp;chart_group_by=<?= urlencode($chartGroupBy) ?>">Vai a Ricerca Cliente</a>
                <a class="button ghost" href="scadenzario.php?xml_directory=<?= urlencode($xmlDirectory) ?>&amp;contacts_path=<?= urlencode($contactsPath) ?>&amp;calendar_id=<?= urlencode($calendarId) ?>&amp;chart_group_by=<?= urlencode($chartGroupBy) ?>">Vai a Registrazione pagamenti</a>
            </div>
            <?php if ($selectedHomeVariant['images'] !== []): ?><div class="home-gallery layout-<?= (int) $selectedHomeVariant['layout'] ?>"><?php foreach ($selectedHomeVariant['images'] as $image): ?><figure class="home-photo"><img src="<?= htmlspecialchars($image['path']) ?>?v=<?= urlencode((string) ($image['updated_at'] ?? '1')) ?>" alt="<?= htmlspecialchars($image['title']) ?>"><figcaption><strong><?= htmlspecialchars($image['title']) ?></strong><span><?= htmlspecialchars($image['caption']) ?></span></figcaption></figure><?php endforeach; ?></div><?php else: ?><div class="home-empty"><strong>Nessuna foto configurata.</strong><p>Apri l'area superadmin per caricare da 1 a 5 immagini e attivare i layout casuali della home.</p></div><?php endif; ?>
        </div>
    </section>

    <?php if ($showSuperadmin): ?>
    <section class="card"><h2>Area superadmin: gestione immagini home</h2><p class="muted">Puoi configurare da 1 a 5 foto attive. Ad ogni refresh la home sceglie in modo casuale il layout e l'insieme di immagini da mostrare.</p><?php if (empty($_SESSION['is_superadmin'])): ?><form method="post"><input type="hidden" name="superadmin" value="1"><input type="hidden" name="action" value="superadmin_login"><div class="grid"><div><label for="superadmin_password">Password superadmin</label><input id="superadmin_password" type="password" name="superadmin_password" placeholder="Inserisci la password superadmin"><p class="muted">Password predefinita: <code>admin123</code>.</p></div></div><p><button class="button" type="submit">Accedi</button></p></form><?php else: ?><form method="post" enctype="multipart/form-data"><input type="hidden" name="superadmin" value="1"><input type="hidden" name="action" value="save_home_settings"><div class="grid"><div><label for="headline">Titolo hero</label><input id="headline" name="headline" value="<?= htmlspecialchars($homeSettings['headline']) ?>"></div><div><label for="max_photos">Numero massimo foto da mostrare</label><select id="max_photos" name="max_photos"><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>" <?= $i === (int) $homeSettings['max_photos'] ? 'selected' : '' ?>><?= $i ?> foto</option><?php endfor; ?></select></div></div><div style="margin-top:16px;"><label for="subheadline">Sottotitolo hero</label><textarea id="subheadline" name="subheadline"><?= htmlspecialchars($homeSettings['subheadline']) ?></textarea></div><div style="margin-top:16px;"><label>Abilita uno o più layout casuali</label><div class="grid"><?php for ($layout = 1; $layout <= 5; $layout++): ?><label><input type="checkbox" name="enabled_layouts[]" value="<?= $layout ?>" <?= in_array($layout, $homeSettings['enabled_layouts'], true) ? 'checked' : '' ?> style="width:auto;"> Layout <?= $layout ?></label><?php endfor; ?></div></div><div class="grid" style="margin-top:20px;"><?php for ($slot = 0; $slot < 5; $slot++): $image = $homeSettings['images'][$slot] ?? null; ?><div class="image-editor"><h3>Foto <?= $slot + 1 ?></h3><?php if ($image !== null): ?><img class="image-preview" src="<?= htmlspecialchars($image['path']) ?>?v=<?= urlencode((string) ($image['updated_at'] ?? '1')) ?>" alt="Anteprima foto <?= $slot + 1 ?>"><input type="hidden" name="keep_image[<?= $slot ?>]" value="<?= htmlspecialchars($image['filename']) ?>"><?php else: ?><div class="image-preview" style="display:flex;align-items:center;justify-content:center;color:#6b7280;">Nessuna immagine caricata</div><?php endif; ?><label>Carica / sostituisci immagine</label><input type="file" name="home_images[<?= $slot ?>]" accept="image/png,image/jpeg,image/webp,image/gif"><div style="margin-top:12px;"><label>Titolo foto</label><input name="image_title[<?= $slot ?>]" value="<?= htmlspecialchars($image['title'] ?? ('Foto home ' . ($slot + 1))) ?>"></div><div style="margin-top:12px;"><label>Descrizione foto</label><textarea name="image_caption[<?= $slot ?>]"><?= htmlspecialchars($image['caption'] ?? 'Immagine hero gestita da superadmin.') ?></textarea></div><?php if ($image !== null): ?><label style="margin-top:12px; display:flex; align-items:center; gap:8px; font-weight:600;"><input type="checkbox" name="remove_image[<?= $slot ?>]" value="1" style="width:auto;">Rimuovi questa foto</label><?php endif; ?></div><?php endfor; ?></div><p style="margin-top:20px;"><button class="button" type="submit">Salva configurazione home</button></p></form><?php endif; ?></section>
    <?php endif; ?>

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
