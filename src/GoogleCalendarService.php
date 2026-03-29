<?php

declare(strict_types=1);

namespace ScadenzeFatture;

use RuntimeException;

final class GoogleCalendarService
{
    public function __construct(
        private readonly string $configPath,
        private readonly string $tokenPath,
    ) {
    }

    public function isConfigured(): bool
    {
        return is_file($this->configPath);
    }

    public function getAuthUrl(string $redirectUri, ?string $state = null): string
    {
        $config = $this->loadConfig();
        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];
        if ($state !== null && $state !== '') {
            $params['state'] = $state;
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function fetchAndStoreAccessToken(string $code, string $redirectUri): void
    {
        $config = $this->loadConfig();
        $response = $this->postForm('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (isset($response['error'])) {
            $errorCode = is_array($response['error']) ? (string) ($response['error']['status'] ?? 'oauth_error') : (string) $response['error'];
            $errorDescription = (string) ($response['error_description'] ?? '');
            if (str_contains($errorCode, 'invalid_grant') || str_contains($errorDescription, 'invalid_grant')) {
                throw new RuntimeException('Token Google non valido o scaduto (invalid_grant). Ricollega Google Calendar.');
            }
            throw new RuntimeException('Errore OAuth Google: ' . ($errorDescription !== '' ? $errorDescription : $errorCode));
        }

        $response['created_at'] = time();
        file_put_contents($this->tokenPath, json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /** @param InvoiceDue[] $dues */
    public function pushEvents(array $dues, string $calendarId = '2861717ef5ab4f01829950ccbe6588e58314a7add509a4841a696e311fa45c8f@group.calendar.google.com'): int
    {
        $token = $this->getValidToken();
        $inserted = 0;

        foreach ($dues as $due) {
            $payload = [
                'summary' => $due->toCalendarSummary(),
                'description' => $due->toCalendarDescription(),
                'start' => ['date' => $due->dueDate],
                'end' => ['date' => $due->dueDate],
            ];

            $url = sprintf(
                'https://www.googleapis.com/calendar/v3/calendars/%s/events',
                rawurlencode($calendarId)
            );
            $this->postJson($url, $payload, $token['access_token']);
            $inserted++;
        }

        return $inserted;
    }

    private function getValidToken(): array
    {
        if (!is_file($this->tokenPath)) {
            throw new RuntimeException('Token Google assente. Completa prima l\'autorizzazione OAuth.');
        }

        $token = json_decode((string) file_get_contents($this->tokenPath), true, 512, JSON_THROW_ON_ERROR);
        if (empty($token['access_token'])) {
            throw new RuntimeException('Token Google non valido: access_token assente. Ricollega Google Calendar.');
        }
        $created = isset($token['created_at']) ? (int) $token['created_at'] : (filemtime($this->tokenPath) ?: time());
        $expiresIn = (int) ($token['expires_in'] ?? 0);
        $isExpired = $expiresIn > 0 && (time() >= ($created + $expiresIn - 60));

        if (!$isExpired) {
            return $token;
        }

        if (empty($token['refresh_token'])) {
            throw new RuntimeException('Refresh token mancante. Ripetere il collegamento con Google.');
        }

        $config = $this->loadConfig();
        $refreshed = $this->postForm('https://oauth2.googleapis.com/token', [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'refresh_token' => $token['refresh_token'],
            'grant_type' => 'refresh_token',
        ]);
        if (isset($refreshed['error'])) {
            $errorCode = is_array($refreshed['error']) ? (string) ($refreshed['error']['status'] ?? 'oauth_error') : (string) $refreshed['error'];
            $errorDescription = (string) ($refreshed['error_description'] ?? '');
            if (str_contains($errorCode, 'invalid_grant') || str_contains($errorDescription, 'invalid_grant')) {
                throw new RuntimeException('Token Google non valido o revocato (invalid_grant). Ricollega Google Calendar.');
            }
            throw new RuntimeException('Errore refresh token Google: ' . ($errorDescription !== '' ? $errorDescription : $errorCode));
        }

        $merged = array_merge($token, $refreshed);
        $merged['created_at'] = time();
        file_put_contents($this->tokenPath, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $merged;
    }

    private function loadConfig(): array
    {
        if (!is_file($this->configPath)) {
            throw new RuntimeException('Configurazione Google mancante o non valida: copia config/google-calendar.local.json.example e inserisci client_id/client_secret.');
        }

        $config = json_decode((string) file_get_contents($this->configPath), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($config) || empty($config['client_id']) || empty($config['client_secret'])) {
            throw new RuntimeException('Configurazione Google non valida: sono richiesti client_id e client_secret in config/google-calendar.local.json.');
        }

        return $config;
    }

    private function postForm(string $url, array $payload): array
    {
        return $this->request($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
    }

    private function postJson(string $url, array $payload, string $accessToken): array
    {
        return $this->request($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
        ]);
    }

    private function request(string $url, array $options): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, $options + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Errore CURL: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $decoded = json_decode($response, true);

        if ($status >= 400) {
            $message = $decoded['error']['message'] ?? $decoded['error'] ?? $response;
            if (is_string($message) && str_contains($message, 'invalid_grant')) {
                throw new RuntimeException('Token Google non valido o revocato (invalid_grant). Ricollega Google Calendar.');
            }
            throw new RuntimeException('Google API HTTP ' . $status . ': ' . $message);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
