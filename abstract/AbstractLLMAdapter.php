<?php

abstract class AbstractLLMAdapter implements LLMAdapter
{
    abstract protected function headers(): array;

    protected function request(string $url, array $payload): array
    {
        $json = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => $this->buildHeaders(),
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException("cURL Fehler: {$curlError}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = $decoded['error']['message'] ?? $response;
            throw new \RuntimeException("API Fehler {$httpCode}: {$errorMessage}");
        }

        return $decoded;
    }

    private function buildHeaders(): array
    {
        $formatted = ['Content-Type: application/json'];
        foreach ($this->headers() as $key => $value) {
            $formatted[] = "{$key}: {$value}";
        }
        return $formatted;
    }
}