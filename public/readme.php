<?php

declare(strict_types=1);

$readmePath = dirname(__DIR__) . '/README.md';
$readmeContent = is_file($readmePath) ? (string) file_get_contents($readmePath) : null;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>README progetto</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f3f4f6; color: #111827; }
        .container { max-width: 1000px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border-radius: 14px; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08); padding: 20px; }
        .actions { margin-bottom: 16px; display: flex; gap: 10px; flex-wrap: wrap; }
        .button { display: inline-block; text-decoration: none; border-radius: 10px; padding: 10px 14px; background: #2563eb; color: #fff; }
        .button.secondary { background: #059669; }
        pre { white-space: pre-wrap; word-break: break-word; margin: 0; line-height: 1.5; font-size: 14px; }
    </style>
</head>
<body>
<div class="container">
    <div class="actions">
        <a class="button" href="index.php">Torna alla Home</a>
        <a class="button secondary" href="controllo.php">Torna a Ricerca Cliente</a>
    </div>

    <div class="card">
        <h1>README aggiornato</h1>
        <?php if ($readmeContent === null): ?>
            <p>README non trovato.</p>
        <?php else: ?>
            <pre><?= htmlspecialchars($readmeContent) ?></pre>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
