<?php

if (!defined('ABSPATH')) {
    exit;
}

class GNCE_API_Client
{
    private string $gnce_token_url = 'https://api.1nce.com/oauth/token';
    private string $gnce_sims_url = 'https://api.1nce.com/management-api/v1/sims';
    private string $gnce_client_id;
    private string $gnce_client_secret;
    private string $gnce_access_token = "";

    public function __construct(string $client_id, string $client_secret)
    {
        $this->gnce_client_id = $client_id;
        $this->gnce_client_secret = $client_secret;
    }

    private function gnce_get_access_token(): string
    {
        if ($this->gnce_access_token !== "") {
            return $this->gnce_access_token;
        }

        $response = wp_remote_post($this->gnce_token_url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->gnce_client_id . ':' . $this->gnce_client_secret)
            ],
            'body' => [
                'grant_type' => 'client_credentials'
            ],
            'timeout' => 30,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            throw new Exception(__('Token request error: ', 'gnce-1nce-products') . $response->get_error_message());
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($http_code !== 200) {
            throw new Exception(sprintf(__('Token request failed (%d): %s', 'gnce-1nce-products'), $http_code, $body));
        }

        $json = json_decode($body, true);

        if (!isset($json['access_token'])) {
            throw new Exception(__('No access_token returned', 'gnce-1nce-products'));
        }

        $this->gnce_access_token = $json['access_token'];
        return $this->gnce_access_token;
    }

    public function gnce_get_sims(): array
    {
        $access_token = $this->gnce_get_access_token();
        $all_sims = [];
        $page = 1;
        $page_size = 100;

        do {
            $url = $this->gnce_sims_url . "?page=" . $page . "&pageSize=" . $page_size;
            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept' => 'application/json'
                ],
                'timeout' => 30,
                'sslverify' => false
            ]);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($http_code !== 200) {
                throw new Exception(sprintf(__('SIM request failed (Status %d)', 'gnce-1nce-products'), $http_code));
            }

            $data = json_decode($body, true);
            if (!is_array($data)) {
                throw new Exception(__('Invalid SIM data received from API', 'gnce-1nce-products'));
            }

            $all_sims = array_merge($all_sims, $data);

            if (count($data) < $page_size) {
                break;
            }

            $page++;
        } while (true);

        return $all_sims;
    }

    public function gnce_get_sim_data_quota(string $iccid): array
    {
        return $this->gnce_get_quota($iccid, 'data');
    }

    public function gnce_get_sim_sms_quota(string $iccid): array
    {
        return $this->gnce_get_quota($iccid, 'sms');
    }

    private function gnce_get_quota(string $iccid, string $type): array
    {
        $access_token = $this->gnce_get_access_token();
        $url = "https://api.1nce.com/management-api/v1/sims/{$iccid}/quota/{$type}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json'
            ],
            'timeout' => 30,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($http_code !== 200) {
            throw new Exception(sprintf(__('SIM quota request failed (Status %d)', 'gnce-1nce-products'), $http_code));
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new Exception(__('Invalid quota data received from API', 'gnce-1nce-products'));
        }

        return $data;
    }

    public function gnce_top_up_sim_quota(string $iccid, string $payment_method = 'creditcard'): string
    {
        $access_token = $this->gnce_get_access_token();
        $url = $this->gnce_sims_url . "/{$iccid}/topup?payment_method=" . urlencode($payment_method);
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code === 201 || $http_code === 200) {
            return sprintf(__('Data quota renewal successful for ICCID: %s', 'gnce-1nce-products'), $iccid);
        }

        throw new Exception(sprintf(__('Data quota renewal failed for ICCID %s (Status %d)', 'gnce-1nce-products'), $iccid, $http_code));
    }
}
