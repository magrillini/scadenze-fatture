<?php

declare(strict_types=1);

$versionFile = dirname(__DIR__) . '/VERSION';
if (!is_file($versionFile)) {
    fwrite(STDERR, "File VERSION mancante.\n");
    exit(1);
}

$version = trim((string) file_get_contents($versionFile));
if ($version === '') {
    fwrite(STDERR, "VERSION vuota.\n");
    exit(1);
}

if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    fwrite(STDERR, "Formato VERSION non valido: {$version}\n");
    exit(1);
}

echo json_encode(['version' => $version], JSON_UNESCAPED_SLASHES) . PHP_EOL;

