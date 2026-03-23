<?php

declare(strict_types=1);

use ScadenzeFatture\ContactsRepository;
use ScadenzeFatture\DashboardService;
use ScadenzeFatture\GoogleCalendarService;
use ScadenzeFatture\HomeSettingsRepository;
use ScadenzeFatture\InvoiceParser;
use ScadenzeFatture\PaymentRegistryRepository;

require dirname(__DIR__) . '/src/bootstrap.php';

session_start();

$storageDirectory = dirname(__DIR__) . '/storage';
$homeConfigPath = $storageDirectory . '/home-settings.json';
$paymentRegistryPath = $storageDirectory . '/payment-registry.json';
$homeUploadDirectory = __DIR__ . '/uploads/home';
$adminPassword = getenv('HOME_SUPERADMIN_PASSWORD') ?: 'admin123';
$currentScript = basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php'));

$xmlDirectory = trim($_POST['xml_directory'] ?? $_GET['xml_directory'] ?? dirname(__DIR__) . '/samples/xml');
$contactsPath = trim($_POST['contacts_path'] ?? $_GET['contacts_path'] ?? dirname(__DIR__) . '/samples/contatti-clienti.csv');
$calendarId = trim($_POST['calendar_id'] ?? $_GET['calendar_id'] ?? 'primary');
$chartGroupBy = trim($_POST['chart_group_by'] ?? $_GET['chart_group_by'] ?? 'cliente');
$clientSearch = trim($_POST['client_search'] ?? $_GET['client_search'] ?? '');
$amountMin = trim($_POST['amount_min'] ?? $_GET['amount_min'] ?? '');
$amountMax = trim($_POST['amount_max'] ?? $_GET['amount_max'] ?? '');
$message = null;
$error = null;
$dues = [];
$summary = ['total_dues' => 0, 'total_amount' => 0.0, 'collected_amount' => 0.0, 'outstanding_amount' => 0.0, 'legal_amount' => 0.0, 'by_payment_type' => [], 'customers' => [], 'charts' => ['overview' => [], 'by_customer' => []]];
$showSuperadmin = isset($_GET['superadmin']) || isset($_POST['superadmin']);
$homeRepository = new HomeSettingsRepository($homeConfigPath, $homeUploadDirectory);
$paymentRegistry = new PaymentRegistryRepository($paymentRegistryPath);
$homeSettings = $homeRepository->load();
$selectedHomeVariant = $homeRepository->selectVariant($homeSettings);

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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'superadmin_login') {
        if (hash_equals($adminPassword, (string) ($_POST['superadmin_password'] ?? ''))) {
            $_SESSION['is_superadmin'] = true;
            $message = 'Accesso superadmin eseguito correttamente.';
        } else {
            $error = 'Password superadmin non valida.';
        }
    }

    if (isset($_GET['action']) && $_GET['action'] === 'superadmin_logout') {
        unset($_SESSION['is_superadmin']);
        $message = 'Sessione superadmin terminata.';
    }

    if (isset($_GET['action']) && $_GET['action'] === 'connect_google') {
        if (!$googleService->isConfigured()) {
            throw new RuntimeException('Config Google mancante: crea config/google-calendar.local.json partendo dal file example.');
        }

        header('Location: ' . $googleService->getAuthUrl($redirectUri));
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'oauth_callback' && isset($_GET['code'])) {
        $googleService->fetchAndStoreAccessToken((string) $_GET['code'], $redirectUri);
        $message = 'Google Calendar collegato correttamente.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_home_settings') {
        if (empty($_SESSION['is_superadmin'])) {
            throw new RuntimeException('Accesso superadmin richiesto per modificare la home.');
        }

        $homeSettings = $homeRepository->saveSettings([
            'headline' => trim((string) ($_POST['headline'] ?? 'Controllo Fatture XML')),
            'subheadline' => trim((string) ($_POST['subheadline'] ?? 'Panoramica generale in home e area CONTROLLO dedicata ai monitoraggi operativi.')),
            'max_photos' => (int) ($_POST['max_photos'] ?? 1),
            'enabled_layouts' => array_map('intval', $_POST['enabled_layouts'] ?? []),
            'image_title' => $_POST['image_title'] ?? [],
            'image_caption' => $_POST['image_caption'] ?? [],
            'keep_image' => $_POST['keep_image'] ?? [],
            'remove_image' => $_POST['remove_image'] ?? [],
        ], $_FILES['home_images'] ?? null);

        $selectedHomeVariant = $homeRepository->selectVariant($homeSettings);
        $message = 'Impostazioni home aggiornate con successo.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_due_status') {
        $paymentRegistry->updateDueStatus(
            (string) ($_POST['due_id'] ?? ''),
            isset($_POST['pagamenti']),
            isset($_POST['avvocato'])
        );
        $message = 'Stato pagamento aggiornato.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_installments') {
        $paymentRegistry->updateInvoiceInstallments(
            (string) ($_POST['invoice_id'] ?? ''),
            max(1, (int) ($_POST['numero_rate'] ?? 1))
        );
        $message = 'Numero rate aggiornato con successo.';
    }

    $contacts = $contactsRepository->loadFromCsv($contactsPath);
    $rawDues = $parser->parseDirectory($xmlDirectory, $contacts);
    $summary = $dashboard->summarize($rawDues, $paymentRegistry->load());
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
