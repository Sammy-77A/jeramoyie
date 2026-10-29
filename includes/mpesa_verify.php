<?php
/**
 * Helpers that stop forged M-PESA callbacks from crediting money.
 *
 *  - mpesa_callback_secret_ok(): checks ?k=<MPESA_CALLBACK_SECRET> on the callback URL.
 *  - mpesa_stk_confirmed():      asks Safaricom (STK Push Query) whether the payment really succeeded.
 */
require_once dirname(__DIR__) . '/.env.php';

/**
 * Returns true when the request carries the shared secret.
 * If MPESA_CALLBACK_SECRET is not configured yet, this allows the request
 * (so existing deployments keep working) and logs a warning; the Safaricom
 * confirmation in mpesa_stk_confirmed() still protects the money.
 */
function mpesa_callback_secret_ok(): bool
{
    $secret = getenv('MPESA_CALLBACK_SECRET');
    if ($secret === false || $secret === '') {
        error_log('MPESA_CALLBACK_SECRET is not set: callback URL is not secret-protected');
        return true;
    }
    return hash_equals($secret, (string)($_GET['k'] ?? ''));
}

/**
 * Confirms with Safaricom that a CheckoutRequestID was paid successfully.
 * Fails closed: any config, network or API problem returns false.
 */
function mpesa_stk_confirmed(string $checkoutId): bool
{
    $shortcode = getenv('MPESA_SHORTCODE');
    $passkey = getenv('MPESA_PASSKEY');
    $key = getenv('MPESA_CONSUMER_KEY');
    $secret = getenv('MPESA_CONSUMER_SECRET');
    if (!$checkoutId || !$shortcode || !$passkey || !$key || !$secret) {
        return false;
    }

    $base = (strtolower(getenv('MPESA_ENV') ?: 'sandbox') === 'live')
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke';

    $ch = curl_init($base . '/oauth/v1/generate?grant_type=client_credentials');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => "$key:$secret",
        CURLOPT_TIMEOUT => 15,
    ]);
    $tokenResp = curl_exec($ch);
    $tokenCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $accessToken = ($tokenCode === 200) ? (json_decode($tokenResp, true)['access_token'] ?? null) : null;
    if (!$accessToken) {
        return false;
    }

    $timestamp = date('YmdHis');
    $ch = curl_init($base . '/mpesa/stkpushquery/v1/query');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'BusinessShortCode' => $shortcode,
            'Password' => base64_encode($shortcode . $passkey . $timestamp),
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutId,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $accessToken],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        return false;
    }
    $data = json_decode($resp, true);
    return isset($data['ResultCode']) && (int)$data['ResultCode'] === 0;
}

/**
 * Builds a callback URL, appending the shared secret (?k=) when one is configured.
 * $params are extra query args such as ['token' => $token].
 */
function mpesa_callback_url(string $base, array $params = []): string
{
    $secret = getenv('MPESA_CALLBACK_SECRET');
    if ($secret !== false && $secret !== '') {
        $params['k'] = $secret;
    }
    if (!$params) {
        return $base;
    }
    return $base . (strpos($base, '?') === false ? '?' : '&') . http_build_query($params);
}

/** Daraja base URL: live only when MPESA_ENV=live, otherwise the sandbox. */
function mpesa_base_url(): string
{
    return (strtolower(getenv('MPESA_ENV') ?: 'sandbox') === 'live')
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke';
}
