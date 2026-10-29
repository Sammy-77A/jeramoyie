<?php
/**
 * Pending Registration View — Standalone.
 * Variables: $token, $phone
 */
$basePath = config('app.base_path', '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Pending • Jera Moyie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .pending-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 420px;
            padding: 2.5rem;
            text-align: center;
        }

        .spinner-big {
            width: 60px;
            height: 60px;
            border-width: 4px;
            color: #2c7a4b;
        }
    </style>
</head>

<body>
    <div class="pending-card">
        <div class="spinner-border spinner-big mb-4" role="status"><span class="visually-hidden">Loading...</span></div>
        <h4 class="fw-bold mb-3">Waiting for M-PESA Payment</h4>
        <p class="text-muted small mb-4">Please check your phone and enter your M-PESA PIN to complete the registration
            payment of <strong>KSh 1</strong>.</p>
        <p class="text-muted small" id="statusMsg">Checking payment status...</p>
        <a href="<?= $basePath ?>/signup" class="btn btn-outline-secondary btn-sm rounded-pill mt-3">Cancel</a>
    </div>
    <script>
        const checkUrl = '<?= $basePath ?>/check-status';
        let attempts = 0;
        const maxAttempts = 60;
        function checkStatus() {
            if (attempts >= maxAttempts) {
                document.getElementById('statusMsg').innerHTML = '<span class="text-danger">Payment timeout. Please try again.</span>';
                return;
            }
            attempts++;
            fetch(checkUrl).then(r => r.json()).then(data => {
                if (data.status === 'success') {
                    document.getElementById('statusMsg').innerHTML = '<span class="text-success fw-bold">Payment confirmed! Redirecting...</span>';
                    setTimeout(() => window.location.href = '<?= $basePath ?>/login?msg=' + encodeURIComponent('Account created! Please login.'), 1500);
                } else if (data.status === 'failed') {
                    document.getElementById('statusMsg').innerHTML = '<span class="text-danger">Payment failed. Please try again.</span>';
                } else {
                    setTimeout(checkStatus, 3000);
                }
            }).catch(() => setTimeout(checkStatus, 5000));
        }
        setTimeout(checkStatus, 3000);
    </script>
</body>

</html>