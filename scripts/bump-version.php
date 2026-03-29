<?php

declare(strict_types=1);

$versionFile = dirname(__DIR__) . '/VERSION';
if (!is_file($versionFile)) {
    fwrite(STDERR, "File VERSION mancante.\n");
    exit(1);
}

$current = trim((string) file_get_contents($versionFile));
if (!preg_match('/^(\d+)\.(\d+)\.(\d{1,2})$/', $current, $matches)) {
    fwrite(STDERR, "Formato VERSION non valido: {$current}\n");
    exit(1);
}

$major = (int) $matches[1];
$minor = (int) $matches[2];
$patch = (int) $matches[3];

if ($patch >= 99) {
    $minor++;
    $patch = 0;
} else {
    $patch++;
}

$next = sprintf('%d.%d.%02d', $major, $minor, $patch);
file_put_contents($versionFile, $next . PHP_EOL);

echo $next . PHP_EOL;
