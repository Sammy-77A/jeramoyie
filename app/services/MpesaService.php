<?php
/**
 * MpesaService — consolidates all M-Pesa STK Push logic.
 */

namespace App\Services;

class MpesaService
{
    private string $consumerKey;
    private string $consumerSecret;
    private string $shortcode;
    private string $passkey;
    private string $baseUrl;
    private string $callbackUrl;

    public function __construct()
    {
        // Load .env if not already loaded
        $envPhp = BASE_PATH . '/.env.php';
        if (file_exists($envPhp)) {
            require_once $envPhp;
        }

        $this->consumerKey = getenv('MPESA_CONSUMER_KEY') ?: '';
        $this->consumerSecret = getenv('MPESA_CONSUMER_SECRET') ?: '';
        $this->shortcode = getenv('MPESA_SHORTCODE') ?: '';
        $this->passkey = getenv('MPESA_PASSKEY') ?: '';
        $this->callbackUrl = getenv('MPESA_CALLBACK_URL') ?: '';

        $env = strtolower(getenv('MPESA_ENV') ?: 'sandbox');
        $this->baseUrl = ($env === 'live')
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Check if M-Pesa is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->consumerKey)
            && !empty($this->consumerSecret)
            && !empty($this->shortcode)
            && !empty($this->passkey);
    }

    /**
     * Get OAuth access token from Safaricom.
     */
    public function getAccessToken(): ?string
    {
        $url = $this->baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_USERPWD => "{$this->consumerKey}:{$this->consumerSecret}",
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("M-Pesa Token Failed: HTTP {$httpCode}, Response: {$response}");
            return null;
        }

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    /**
     * Initiate an STK Push.
     */
    public function initiateSTKPush(string $phone, float $amount, string $accountRef, string $description, ?string $callbackUrl = null): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'error' => 'M-Pesa Authentication Failed'];
        }

        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);
        $callbackUrl = $callbackUrl ?: $this->callbackUrl;

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountRef,
            'TransactionDesc' => $description,
        ];

        $url = $this->baseUrl . '/mpesa/stkpush/v1/processrequest';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200) {
            error_log("STK Push HTTP Error: code={$httpCode} error={$curlErr} response={$response}");
            return ['success' => false, 'error' => 'Failed to contact M-PESA'];
        }

        $data = json_decode($response, true) ?? [];

        if (isset($data['ResponseCode']) && $data['ResponseCode'] == '0') {
            return [
                'success' => true,
                'CheckoutRequestID' => $data['CheckoutRequestID'] ?? '',
                'CustomerMessage' => $data['CustomerMessage'] ?? '',
                'raw' => $data,
            ];
        }

        return [
            'success' => false,
            'error' => $data['CustomerMessage'] ?? $data['errorMessage'] ?? 'STK Push Failed',
            'raw' => $data,
        ];
    }

    /**
     * Normalize a phone number to 254 format.
     */
    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-]/', '', $phone);
        $phone = ltrim($phone, '+');
        if (preg_match('/^0[71][0-9]{8}$/', $phone)) {
            $phone = '254' . substr($phone, 1);
        }
        return $phone;
    }

    /**
     * Extract the M-Pesa receipt number from callback metadata.
     */
    public function extractReceipt(array $callbackData): ?string
    {
        $items = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'] ?? [];
        foreach ($items as $item) {
            if ($item['Name'] === 'MpesaReceiptNumber') {
                return $item['Value'];
            }
        }
        return null;
    }
}
