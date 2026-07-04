<?php
/**
 * Base payment provider with shared HTTP helper (curl, no composer).
 */

abstract class AbstractPaymentProvider implements PaymentProviderInterface
{
    protected function httpRequest(
        string $method,
        string $url,
        array $options = []
    ): array {
        $headers = $options['headers'] ?? [];
        $body = $options['body'] ?? null;
        $auth = $options['auth'] ?? null;
        $timeout = (int) ($options['timeout'] ?? 30);

        if ($auth !== null) {
            if (is_array($auth)) {
                $user = $auth['user'] ?? '';
                $pass = $auth['pass'] ?? '';
                $headers[] = 'Authorization: Basic ' . base64_encode($user . ':' . $pass);
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

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'ok' => false,
                'status' => 0,
                'error' => $error ?: 'HTTP request failed',
                'data' => null,
            ];
        }

        $decoded = json_decode($response, true);
        if ($decoded === null && $response !== 'null' && $response !== '') {
            $decoded = $response;
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'error' => ($status >= 200 && $status < 300) ? null : (is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? 'HTTP ' . $status) : 'HTTP ' . $status),
            'data' => $decoded,
        ];
    }

    protected function formRequest(string $method, string $url, array $fields, array $options = []): array
    {
        $headers = $options['headers'] ?? [];
        $auth = $options['auth'] ?? null;

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
            CURLOPT_HTTPHEADER => array_merge($headers, ['Content-Type: application/x-www-form-urlencoded']),
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_TIMEOUT => (int) ($options['timeout'] ?? 30),
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'status' => 0, 'error' => $error ?: 'HTTP request failed', 'data' => null];
        }

        $decoded = json_decode($response, true);
        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'error' => ($status >= 200 && $status < 300) ? null : 'HTTP ' . $status,
            'data' => $decoded ?? $response,
        ];
    }

    protected function isMockMode(array $credentials): bool
    {
        return !empty($credentials['mock']) || !empty($credentials['test_mode']);
    }

    protected function mockPaymentResponse(array $params, string $providerId): array
    {
        $id = 'mock_' . $providerId . '_' . bin2hex(random_bytes(8));
        return [
            'ok' => true,
            'payment_id' => $id,
            'status' => 'pending',
            'client_secret' => 'mock_secret_' . $id,
            'redirect_url' => $params['return_url'] ?? null,
            'raw' => ['id' => $id, 'status' => 'pending', 'mock' => true],
        ];
    }

    protected function normalizeHeaderKey(string $key): string
    {
        return strtolower(str_replace('_', '-', $key));
    }

    protected function getHeader(array $headers, string $name): ?string
    {
        $target = strtolower($name);
        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                if (stripos($value, $name . ':') === 0) {
                    return trim(substr($value, strlen($name) + 1));
                }
                continue;
            }
            if (strtolower($key) === $target) {
                return is_array($value) ? ($value[0] ?? null) : $value;
            }
        }
        return null;
    }
}
