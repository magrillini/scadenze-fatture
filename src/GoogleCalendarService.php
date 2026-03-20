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

    public function getAuthUrl(string $redirectUri): string
    {
        $config = $this->loadConfig();
        $params = http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
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
            throw new RuntimeException('Errore OAuth Google: ' . (is_array($response['error']) ? json_encode($response['error']) : $response['error']));
        }

        file_put_contents($this->tokenPath, json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /** @param InvoiceDue[] $dues */
    public function pushEvents(array $dues, string $calendarId = 'primary'): int
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
        $created = filemtime($this->tokenPath) ?: time();
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

        $merged = array_merge($token, $refreshed);
        file_put_contents($this->tokenPath, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $merged;
    }

    private function loadConfig(): array
    {
        if (!is_file($this->configPath)) {
            throw new RuntimeException('Config Google mancante: copia config/google-calendar.local.json.example');
        }

        return json_decode((string) file_get_contents($this->configPath), true, 512, JSON_THROW_ON_ERROR);
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
            throw new RuntimeException('Google API HTTP ' . $status . ': ' . $message);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
