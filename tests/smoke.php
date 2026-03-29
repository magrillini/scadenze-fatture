<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ScadenzeFatture\ContactsRepository;
use ScadenzeFatture\DashboardService;
use ScadenzeFatture\InvoiceParser;

$root = dirname(__DIR__);
$contactsPath = $argv[1] ?? ($root . '/storage/contatti-clienti.csv');
$xmlDirectory = $argv[2] ?? ($root . '/storage/xml');

if (!is_file($contactsPath)) {
    fwrite(STDERR, "Contatti non trovati: {$contactsPath}\n");
    exit(1);
}

if (!is_dir($xmlDirectory)) {
    fwrite(STDERR, "Directory XML non trovata: {$xmlDirectory}\n");
    exit(1);
}

$contacts = (new ContactsRepository())->loadFromCsv($contactsPath);
$dues = (new InvoiceParser())->parseDirectory($xmlDirectory, $contacts);
$summary = (new DashboardService())->summarize($dues, []);

echo json_encode([
    'total_dues' => $summary['total_dues'],
    'total_amount' => $summary['total_amount'],
    'payment_types' => array_keys($summary['by_payment_type']),
    'first_due_client' => $summary['dues'][0]->clientName ?? null,
    'outstanding_amount' => $summary['outstanding_amount'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

