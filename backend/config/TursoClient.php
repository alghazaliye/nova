<?php
/**
 * Standalone Turso libSQL HTTP Client for PHP.
 * Works without native extensions using standard curl.
 */

declare(strict_types=1);

class TursoClient
{
    private string $url;
    private string $token;

    public function __construct(string $url, string $token)
    {
        // Turso URLs might be libsql://, convert to https:// for HTTP client
        $this->url = str_replace('libsql://', 'https://', $url);
        $this->token = $token;
    }

    /**
     * Execute a single SQL statement.
     */
    public function execute(string $sql, array $args = []): array
    {
        $payload = [
            'statements' => [
                [
                    'q' => $sql,
                    'params' => $this->formatArgs($args)
                ]
            ]
        ];

        return $this->request($payload)[0] ?? [];
    }

    /**
     * Execute multiple SQL statements in a batch.
     */
    public function batch(array $statements): array
    {
        $payload = ['statements' => []];
        foreach ($statements as $stmt) {
            if (is_string($stmt)) {
                $payload['statements'][] = ['q' => $stmt];
            } else {
                $payload['statements'][] = [
                    'q' => $stmt['q'],
                    'params' => $this->formatArgs($stmt['params'] ?? [])
                ];
            }
        }

        return $this->request($payload);
    }

    private function formatArgs(array $args): array
    {
        $formatted = [];
        foreach ($args as $key => $value) {
            // Turso expects named parameters to have the prefix if used in query,
            // but the HTTP API expects them as keys in the params object.
            // If it's a list (positional), just return values.
            if (is_int($key)) {
                $formatted[] = $value;
            } else {
                $formatted[ltrim((string)$key, ':')] = $value;
            }
        }
        return $formatted;
    }

    private function request(array $payload): array
    {
        $ch = curl_init($this->url);
        $jsonData = json_encode($payload);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Turso Request Failed: " . $error);
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = $data['error'] ?? $data['message'] ?? 'Unknown Error';
            throw new Exception("Turso API Error ($httpCode): " . $msg);
        }

        return $data;
    }
}
