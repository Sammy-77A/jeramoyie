<?php
session_start();
require_once __DIR__ . '/includes/db.php';
if (!isset($_SESSION['community_repay_token'])) {
    header('Location: transactions.php');
    exit;
}
$token = $_SESSION['community_repay_token'];
$amount = $_SESSION['community_repay_amount'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repayment Pending • Jera Moyie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a5928;
            --accent: #f59e0b
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem
        }

        .pending-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .06);
            max-width: 440px;
            width: 100%;
            padding: 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden
        }

        .pending-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--accent))
        }

        .pulse {
            animation: pulse 2s infinite
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: .5
            }
        }

        .spinner {
            width: 3.5rem;
            height: 3.5rem;
            border-width: 4px;
            color: var(--primary)
        }

        @media(max-width:576px) {
            .pending-card {
                padding: 1.5rem
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg fixed-top bg-white border-bottom shadow-sm py-2">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center text-success fw-bold" href="transactions.php"><i
                    class="bi bi-chevron-left me-2"></i>Transactions</a>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary rounded-pill ms-auto">Dashboard</a>
        </div>
    </nav>
    <div class="pending-card" style="margin-top: 4rem;">
        <div id="statusCard">
            <div class="mb-4">
                <div class="spinner spinner-border" role="status"></div>
            </div>
            <h4 class="fw-bold" style="color:var(--primary)"><i class="bi bi-phone-vibrate me-2"></i>Check Your Phone
            </h4>
            <p class="text-muted mt-3">An M-PESA STK push for <strong>KSh <?= number_format($amount, 2) ?></strong> has
                been sent.<br>Complete the payment to repay your loan.</p>
            <div class="pulse mt-3"><small class="text-muted"><i class="bi bi-arrow-repeat me-1"></i>Waiting for
                    confirmation...</small></div>
        </div>
    </div>
    <script>
        const token = <?= json_encode($token) ?>;
        let hasFailed = false;
        let attempts = 0;

        const interval = setInterval(() => {
            attempts++;

            // Check local database first
            fetch('check_repay.php?token=' + token).then(r => r.json()).then(data => {
                if (data.paid) {
                    clearInterval(interval);
                    document.getElementById('statusCard').innerHTML = '<div class="mb-3"><i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i></div><h4 class="fw-bold text-success">Repayment Confirmed!</h4><p class="text-muted">KSh <?= number_format($amount, 2) ?> has been applied to your loan.</p>';
                    setTimeout(() => { window.location.href = 'transactions.php'; }, 2500);
                }
                else if (!data.pending_exists && !data.paid && !hasFailed) {
                    hasFailed = true;
                    clearInterval(interval);
                    document.getElementById('statusCard').innerHTML = '<div class="mb-3"><i class="bi bi-x-circle-fill text-danger" style="font-size:3.5rem"></i></div><h4 class="fw-bold text-danger">Payment Failed</h4><p class="text-muted">The transaction was not completed.</p><a href="transactions.php" class="btn btn-success rounded-pill px-4 mt-2"><i class="bi bi-arrow-clockwise me-2"></i>Try Again</a>';
                }
                else if (data.pending_exists && attempts >= 10) {
                    // After ~30 seconds, manually trigger STK query fallback
                    fetch('check_stk_query.php?type=repay&token=' + token).then(r => r.json()).then(stk => {
                        if (stk.success && stk.repay_processed) {
                            clearInterval(interval);
                            document.getElementById('statusCard').innerHTML = '<div class="mb-3"><i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i></div><h4 class="fw-bold text-success">Repayment Confirmed!</h4><p class="text-muted">KSh <?= number_format($amount, 2) ?> has been applied to your loan.</p>';
                            setTimeout(() => { window.location.href = 'transactions.php'; }, 2500);
                        } else if (stk.queried && !stk.success && stk.result_code !== '1032') {
                            // STK query failed permanently (e.g. cancelled)
                            hasFailed = true;
                            clearInterval(interval);
                            document.getElementById('statusCard').innerHTML = '<div class="mb-3"><i class="bi bi-x-circle-fill text-danger" style="font-size:3.5rem"></i></div><h4 class="fw-bold text-danger">Payment Failed</h4><p class="text-muted">The transaction failed or was rejected.</p><a href="transactions.php" class="btn btn-success rounded-pill px-4 mt-2"><i class="bi bi-arrow-clockwise me-2"></i>Try Again</a>';
                        }
                    });
                }
            }).catch(() => {
                if (!hasFailed) {
                    hasFailed = true;
                    clearInterval(interval);
                    document.getElementById('statusCard').innerHTML = '<div class="mb-3"><i class="bi bi-wifi-off text-warning" style="font-size:3.5rem"></i></div><h4 class="fw-bold text-warning">Connection Error</h4><p class="text-muted">Check your internet.</p><a href="transactions.php" class="btn btn-success rounded-pill px-4 mt-2">Back</a>';
                }
            });
        }, 3000);
    </script>
</body>

</html>