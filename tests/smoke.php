<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ScadenzeFatture\ContactsRepository;
use ScadenzeFatture\DashboardService;
use ScadenzeFatture\InvoiceParser;

$contacts = (new ContactsRepository())->loadFromCsv(dirname(__DIR__) . '/samples/contatti-clienti.csv');
$dues = (new InvoiceParser())->parseDirectory(dirname(__DIR__) . '/samples/xml', $contacts);
$summary = (new DashboardService())->summarize($dues);

echo json_encode([
    'total_dues' => $summary['total_dues'],
    'total_amount' => $summary['total_amount'],
    'payment_types' => array_keys($summary['by_payment_type']),
    'first_due_client' => $dues[0]->clientName ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
