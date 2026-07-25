<?php

abstract class AbstractLLMAdapter implements LLMAdapter
{
    abstract protected function headers(): array;

    protected function request(string $sUrl, array $aPayload): array
    {
        $sJson  = json_encode($aPayload);
        $oCurl  = curl_init($sUrl);

        curl_setopt_array($oCurl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $sJson,
            CURLOPT_HTTPHEADER     => $this->buildHeaders(),
            CURLOPT_TIMEOUT        => 30,
        ]);

        $sResponse  = curl_exec($oCurl);
        $iHttpCode  = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);
        $sCurlError = curl_error($oCurl);

        if ($sCurlError) {
            throw new \RuntimeException("cURL error: {$sCurlError}");
        }

        $aDecoded = json_decode($sResponse, true);

        if ($iHttpCode >= 400) {
            $sErrorMessage = $aDecoded['error']['message'] ?? $sResponse;
            throw new \RuntimeException("API error {$iHttpCode}: {$sErrorMessage}");
        }

        return $aDecoded;
    }

    private function buildHeaders(): array
    {
        $aFormatted = ['Content-Type: application/json'];
        foreach ($this->headers() as $sKey => $sValue) {
            $aFormatted[] = "{$sKey}: {$sValue}";
        }
        return $aFormatted;
    }
}
