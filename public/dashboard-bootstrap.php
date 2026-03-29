<?php

declare(strict_types=1);

use ScadenzeFatture\ContactsRepository;
use ScadenzeFatture\DashboardService;
use ScadenzeFatture\GoogleCalendarService;
use ScadenzeFatture\InvoiceParser;
use ScadenzeFatture\PaymentRegistryRepository;

require dirname(__DIR__) . '/src/bootstrap.php';

session_start();

$storageDirectory = dirname(__DIR__) . '/storage';
$defaultXmlDirectory = $storageDirectory . '/xml';

if (!is_dir($storageDirectory)) {
    mkdir($storageDirectory, 0775, true);
}

if (!is_dir($defaultXmlDirectory)) {
    mkdir($defaultXmlDirectory, 0775, true);
}

$paymentRegistryPath = $storageDirectory . '/payment-registry.json';
$versionFilePath = dirname(__DIR__) . '/VERSION';
$currentScript = basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php'));

$xmlDirectory = trim($_POST['xml_directory'] ?? $_GET['xml_directory'] ?? $defaultXmlDirectory);
$contactsPath = trim($_POST['contacts_path'] ?? $_GET['contacts_path'] ?? dirname(__DIR__) . '/storage/contatti-clienti.csv');
$calendarId = trim($_POST['calendar_id'] ?? $_GET['calendar_id'] ?? 'primary');
$chartGroupBy = trim($_POST['chart_group_by'] ?? $_GET['chart_group_by'] ?? 'cliente');
$clientSearch = trim($_POST['client_search'] ?? $_GET['client_search'] ?? '');
$amountMin = trim($_POST['amount_min'] ?? $_GET['amount_min'] ?? '');
$amountMax = trim($_POST['amount_max'] ?? $_GET['amount_max'] ?? '');
$message = null;
$error = null;
$focusRowId = null;
$appVersion = 'dev';
$versionContent = @file_get_contents($versionFilePath);
if ($versionContent !== false) {
    $parsedVersion = trim($versionContent);
    if ($parsedVersion !== '') {
        $appVersion = $parsedVersion;
    }
}
$dues = [];
$summary = ['total_dues' => 0, 'total_amount' => 0.0, 'collected_amount' => 0.0, 'outstanding_amount' => 0.0, 'legal_amount' => 0.0, 'by_payment_type' => [], 'customers' => [], 'charts' => ['overview' => [], 'by_customer' => []]];
$paymentRegistry = new PaymentRegistryRepository($paymentRegistryPath);

$contactsRepository = new ContactsRepository();
$parser = new InvoiceParser();
$dashboard = new DashboardService();
$googleService = new GoogleCalendarService(
    dirname(__DIR__) . '/config/google-calendar.local.json',
    $storageDirectory . '/google-token.json'
);

$redirectUri = sprintf(
    '%s://%s%s?action=oauth_callback',
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
    $_SERVER['HTTP_HOST'] ?? 'localhost',
    strtok($_SERVER['REQUEST_URI'] ?? '/index.php', '?')
);

try {
    if (isset($_GET['action']) && $_GET['action'] === 'connect_google') {
        if (!$googleService->isConfigured()) {
            throw new RuntimeException('Configurazione Google mancante o non valida: crea/controlla config/google-calendar.local.json con client_id e client_secret.');
        }

        $oauthStatePayload = [
            'xml_directory' => $xmlDirectory,
            'contacts_path' => $contactsPath,
            'calendar_id' => $calendarId,
            'chart_group_by' => $chartGroupBy,
            'client_search' => $clientSearch,
            'amount_min' => $amountMin,
            'amount_max' => $amountMax,
        ];
        $oauthState = rtrim(strtr(base64_encode(json_encode($oauthStatePayload, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        header('Location: ' . $googleService->getAuthUrl($redirectUri, $oauthState));
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'oauth_callback' && isset($_GET['code'])) {
        $googleService->fetchAndStoreAccessToken((string) $_GET['code'], $redirectUri);
        $_SESSION['flash_success'] = 'Google Calendar collegato correttamente.';

        $redirectParams = ['calendar_id' => $calendarId];
        if (isset($_GET['state'])) {
            $rawState = (string) $_GET['state'];
            $normalizedState = strtr($rawState, '-_', '+/');
            $padding = strlen($normalizedState) % 4;
            if ($padding > 0) {
                $normalizedState .= str_repeat('=', 4 - $padding);
            }
            $decodedState = base64_decode($normalizedState, true);
            if ($decodedState !== false) {
                $statePayload = json_decode($decodedState, true);
                if (is_array($statePayload)) {
                    $redirectParams = array_merge($redirectParams, array_filter([
                        'xml_directory' => isset($statePayload['xml_directory']) ? (string) $statePayload['xml_directory'] : null,
                        'contacts_path' => isset($statePayload['contacts_path']) ? (string) $statePayload['contacts_path'] : null,
                        'calendar_id' => isset($statePayload['calendar_id']) ? (string) $statePayload['calendar_id'] : null,
                        'chart_group_by' => isset($statePayload['chart_group_by']) ? (string) $statePayload['chart_group_by'] : null,
                        'client_search' => isset($statePayload['client_search']) ? (string) $statePayload['client_search'] : null,
                        'amount_min' => isset($statePayload['amount_min']) ? (string) $statePayload['amount_min'] : null,
                        'amount_max' => isset($statePayload['amount_max']) ? (string) $statePayload['amount_max'] : null,
                    ], static fn ($value): bool => $value !== null));
                }
            }
        }

        header('Location: ' . $currentScript . '?' . http_build_query($redirectParams));
        exit;
    }

    if (isset($_SESSION['flash_success'])) {
        $message = (string) $_SESSION['flash_success'];
        unset($_SESSION['flash_success']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_due_status') {
        $paymentRegistry->updateDueStatus(
            (string) ($_POST['due_id'] ?? ''),
            isset($_POST['pagamenti']),
            isset($_POST['avvocato'])
        );
        $message = 'Stato pagamento aggiornato.';
        $focusRowId = trim((string) ($_POST['focus_row_id'] ?? ''));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_installments') {
        $paymentRegistry->updateInvoiceInstallments(
            (string) ($_POST['invoice_id'] ?? ''),
            max(1, (int) ($_POST['numero_rate'] ?? 1))
        );
        $message = 'Numero rate aggiornato con successo.';
        $focusRowId = trim((string) ($_POST['focus_row_id'] ?? ''));
    }

    $contacts = $contactsRepository->loadFromCsv($contactsPath);
    $rawDues = $parser->parseDirectory($xmlDirectory, $contacts);
    $summary = $dashboard->summarize($rawDues, $paymentRegistry->load(), $chartGroupBy);
    $dues = $summary['dues'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'push_google') {
        $inserted = $googleService->pushEvents($dues, $calendarId);
        $message = sprintf('Inseriti %d eventi in Google Calendar (%s).', $inserted, $calendarId);
    }
} catch (Throwable $throwable) {
    $error = $throwable->getMessage();
}

$filteredDues = array_values(array_filter($dues, static function ($due) use ($clientSearch, $amountMin, $amountMax): bool {
    if ($clientSearch !== '') {
        $haystack = mb_strtolower($due->clientName . ' ' . ($due->clientVat ?? '') . ' ' . $due->invoiceNumber);
        if (!str_contains($haystack, mb_strtolower($clientSearch))) {
            return false;
        }
    }

    $min = $amountMin !== '' ? (float) str_replace(',', '.', $amountMin) : null;
    $max = $amountMax !== '' ? (float) str_replace(',', '.', $amountMax) : null;

    if ($min !== null && $due->amount < $min) {
        return false;
    }

    if ($max !== null && $due->amount > $max) {
        return false;
    }

    return true;
}));

$pieSegments = array_map(
    static fn (array $bucket): array => ['label' => $bucket['label'], 'color' => $bucket['color'], 'value' => (float) $bucket['amount'], 'count' => (int) $bucket['count']],
    array_values($summary['aging_buckets'] ?? [])
);
$pieByCount = array_map(static fn (array $segment): array => ['label' => $segment['label'], 'color' => $segment['color'], 'value' => (float) $segment['count']], $pieSegments);
$pieByAmount = array_map(static fn (array $segment): array => ['label' => $segment['label'], 'color' => $segment['color'], 'value' => (float) $segment['value']], $pieSegments);

function euro(float $amount): string
{
    return number_format($amount, 2, ',', '.') . ' €';
}

function isDueBeforeInvoiceDate(string $dueDate, string $invoiceDate): bool
{
    $dueTimestamp = strtotime($dueDate);
    $invoiceTimestamp = strtotime($invoiceDate);

    if ($dueTimestamp === false || $invoiceTimestamp === false) {
        return false;
    }

    return $dueTimestamp < $invoiceTimestamp;
}

function renderStars(int $stars): string
{
    return str_repeat('★', $stars) . str_repeat('☆', max(0, 5 - $stars));
}

function percentOf(float $value, float $max): float
{
    if ($max <= 0) {
        return 0.0;
    }

    return min(100, max(0, ($value / $max) * 100));
}

function buildPieGradient(array $segments): string
{
    $total = array_reduce($segments, static fn (float $carry, array $segment): float => $carry + (float) ($segment['value'] ?? 0), 0.0);
    if ($total <= 0) {
        return 'conic-gradient(#d1d5db 0 100%)';
    }

    $start = 0.0;
    $parts = [];
    foreach ($segments as $segment) {
        $value = (float) ($segment['value'] ?? 0);
        if ($value <= 0) {
            continue;
        }

        $end = $start + (($value / $total) * 100);
        $parts[] = sprintf('%s %.2f%% %.2f%%', $segment['color'], $start, $end);
        $start = $end;
    }

    return 'conic-gradient(' . implode(', ', $parts) . ')';
}
