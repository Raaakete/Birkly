<?php
/**
 * Minimal HTTP client for live import adapters (curl, injectable for tests).
 */

class ImportHttpClient
{
    /** @var callable|null */
    private $handler;

    /** @param callable|null $handler function(string $method, string $url, array $options): array */
    public function __construct(?callable $handler = null)
    {
        $this->handler = $handler;
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): array
    {
        if ($this->handler !== null) {
            return ($this->handler)($method, $url, $options);
        }

        return $this->curlRequest($method, $url, $options);
    }

    /** @param array<string, mixed> $options */
    private function curlRequest(string $method, string $url, array $options = []): array
    {
        $headers = $options['headers'] ?? [];
        $body = $options['body'] ?? null;
        $auth = $options['auth'] ?? null;
        $timeout = (int) ($options['timeout'] ?? 60);

        if ($auth !== null) {
            if (is_array($auth)) {
                $headers[] = 'Authorization: Basic ' . base64_encode(($auth['user'] ?? '') . ':' . ($auth['pass'] ?? ''));
            } else {
                $headers[] = 'Authorization: Bearer ' . $auth;
            }
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => true,
        ]);

        if ($body !== null) {
            if (is_array($body)) {
                $encoded = json_encode($body);
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return [
                'ok' => false,
                'status' => 0,
                'error' => $error ?: 'HTTP request failed',
                'data' => null,
                'headers' => [],
            ];
        }

        $headerText = substr($raw, 0, $headerSize);
        $responseBody = substr($raw, $headerSize);
        $parsedHeaders = $this->parseHeaders($headerText);
        $decoded = json_decode($responseBody, true);
        if ($decoded === null && $responseBody !== '' && $responseBody !== 'null') {
            $decoded = $responseBody;
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'error' => ($status >= 200 && $status < 300) ? null : $this->extractErrorMessage($decoded, $status),
            'data' => $decoded,
            'headers' => $parsedHeaders,
        ];
    }

    /** @return array<string, string> */
    private function parseHeaders(string $headerText): array
    {
        $headers = [];
        foreach (explode("\r\n", $headerText) as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
    }

    /** @param mixed $decoded */
    private function extractErrorMessage($decoded, int $status): string
    {
        if (is_array($decoded)) {
            return (string) ($decoded['message'] ?? $decoded['error'] ?? $decoded['errors'] ?? 'HTTP ' . $status);
        }

        return 'HTTP ' . $status;
    }
}
