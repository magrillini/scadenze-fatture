<?php

declare(strict_types=1);

$versionFile = dirname(__DIR__) . '/VERSION';
if (!is_file($versionFile)) {
    fwrite(STDERR, "File VERSION mancante.\n");
    exit(1);
}

$baseVersion = trim((string) file_get_contents($versionFile));
if (!preg_match('/^\d+\.\d+\.\d{2}$/', $baseVersion)) {
    fwrite(STDERR, "Formato VERSION non valido: {$baseVersion}\n");
    exit(1);
}

$prNumber = $argv[1] ?? getenv('PR_NUMBER') ?: '';
if (!preg_match('/^\d+$/', (string) $prNumber)) {
    fwrite(STDERR, "Numero PR non valido: {$prNumber}\n");
    exit(1);
}

$buildVersion = sprintf('%s-pr%s', $baseVersion, $prNumber);
echo $buildVersion . PHP_EOL;

$outputPath = $argv[2] ?? '';
if ($outputPath !== '') {
    file_put_contents($outputPath, $buildVersion . PHP_EOL);
}
