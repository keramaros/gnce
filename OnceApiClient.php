<?php

class OnceApiClient
{
    private string $tokenUrl = 'https://api.1nce.com/oauth/token';
    private string $simsUrl;
    private string $clientId;
    private string $clientSecret;
    private ?string $accessToken = null;

    public function __construct(string $apiUrl, string $clientId, string $clientSecret)
    {
        $this->simsUrl = $apiUrl;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->tokenUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret)
            ],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'client_credentials'
            ]),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('Token cURL error: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Token request failed ({$httpCode}): {$response}");
        }

        $json = json_decode($response, true);

        if (!isset($json['access_token'])) {
            throw new Exception('No access_token returned');
        }

        $this->accessToken = $json['access_token'];
        return $this->accessToken;
    }

    public function getSims(): array
    {
        $accessToken = $this->getAccessToken();
        $allSims = [];
        $page = 1;
        $pageSize = 100;

        do {
            $ch = curl_init();
            $url = $this->simsUrl . "?page=" . $page . "&pageSize=" . $pageSize;

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception('SIM cURL error: ' . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception("SIM request failed ({$httpCode}): {$response}");
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                break;
            }

            $allSims = array_merge($allSims, $data);

            if (count($data) < $pageSize) {
                break;
            }

            $page++;
        } while (true);

        return $allSims;
    }

    public function getSimDataQuota(string $iccid): ?array
    {
        $accessToken = $this->getAccessToken();
        $url = "https://api.1nce.com/management-api/v1/sims/{$iccid}/quota/data";
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }

        return null;
    }
}
