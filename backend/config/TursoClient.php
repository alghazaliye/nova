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
        if (strpos($this->url, '.turso.io') !== false && strpos($this->url, 'https://') === 0) {
            // It's already an HTTPS URL for Turso
        } else {
             $this->url = str_replace('libsql://', 'https://', $url);
        }
        $this->url = rtrim($this->url, '/') ;
        $this->token = $token;
    }

    /**
     * Execute a single SQL statement.
     */
    public function execute(string $sql, array $args = []): array
    {
        if (empty($args)) {
            $payload = [
                'statements' => [
                    $sql
                ]
            ];
        } else {
            $payload = [
                'statements' => [
                    [
                        'q' => $sql,
                        'params' => $this->formatArgs($args)
                    ]
                ]
            ];
        }

        $res = $this->request($payload);
        return $res[0] ?? [];
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
            $val = ['type' => 'null', 'value' => null];
            if (is_int($value)) $val = ['type' => 'integer', 'value' => (string)$value];
            elseif (is_float($value)) $val = ['type' => 'float', 'value' => $value];
            elseif (is_string($value)) $val = ['type' => 'text', 'value' => $value];
            elseif (is_bool($value)) $val = ['type' => 'integer', 'value' => $value ? '1' : '0'];
            elseif (is_null($value)) $val = ['type' => 'null', 'value' => null];

            if (is_int($key)) {
                $formatted[] = $val;
            } else {
                $val['name'] = ltrim((string)$key, ':');
                $formatted[] = $val;
            }
        }
        return $formatted;
    }

    private function request(array $payload): array
    {
        // For Turso /v2/pipeline or /v1/execute, but here we use /v1/execute behavior
        // If it's a batch/statements request, Turso expects the path to be /v2/pipeline or similar
        // but current implementation seems to target the base URL.
        // Let's ensure we are hitting the right endpoint if needed.
        $targetUrl = $this->url;
        if (strpos($targetUrl, '/v2/pipeline') === false && strpos($targetUrl, '/v1/execute') === false) {
             // Default to v1 execute for compatibility with existing code
             // $targetUrl .= '/v1/execute'; 
        }

        $ch = curl_init($targetUrl);
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
            throw new Exception("Turso API Error ($httpCode): " . $msg . " URL: " . $this->url . " Response: " . $response);
        }

        return $data;
    }
}
