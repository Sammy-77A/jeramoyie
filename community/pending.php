<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['community_pending_token'])) {
    header('Location: register.php');
    exit;
}
$token = $_SESSION['community_pending_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Jera Moyie – Payment Pending</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--primary:#1a5928;--accent:#f59e0b}
body{font-family:'Inter',sans-serif;background:#f3f4f6;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
.pending-card{background:#fff;border-radius:24px;box-shadow:0 20px 60px rgba(0,0,0,.06);max-width:440px;width:100%;padding:2.5rem;text-align:center;position:relative;overflow:hidden}
.pending-card::before{content:'';position:absolute;top:0;left:0;width:100%;height:6px;background:linear-gradient(90deg,var(--primary),var(--accent))}
.pulse{animation:pulse 2s infinite}@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
.spinner{width:3.5rem;height:3.5rem;border-width:4px;color:var(--primary)}
@media(max-width:576px){.pending-card{padding:1.5rem}}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg fixed-top bg-white border-bottom shadow-sm py-2">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center text-success fw-bold" href="register.php"><i class="bi bi-chevron-left me-2"></i>Registration</a>
    </div>
</nav>
<div class="pending-card" style="margin-top: 4rem;">
    <div id="statusCard">
        <div class="mb-4"><div class="spinner spinner-border" role="status"></div></div>
        <h4 class="fw-bold" style="color:var(--primary)"><i class="bi bi-phone-vibrate me-2"></i>Check Your Phone</h4>
        <p class="text-muted mt-3">
            An M-PESA STK push has been sent to your phone.<br>
            <strong>Complete the payment of KSh 1</strong> to finish registration.
        </p>
        <div class="pulse mt-3"><small class="text-muted"><i class="bi bi-arrow-repeat me-1"></i>Waiting for confirmation...</small></div>
    </div>
</div>

<script>
const token = <?= json_encode($token) ?>;
let hasFailed = false;

const interval = setInterval(() => {
    fetch('check_payment.php?token=' + token)
        .then(r => r.json())
        .then(data => {
            if (data.paid === true) {
                clearInterval(interval);
                document.getElementById('statusCard').innerHTML = `
                    <div class="mb-3"><i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i></div>
                    <h4 class="fw-bold text-success">Registration Successful!</h4>
                    <p class="text-muted">Your account has been created. Redirecting...</p>
                `;
                setTimeout(() => { window.location.href = 'dashboard.php'; }, 2000);
            } else if (!data.pending_exists && !data.paid && !hasFailed) {
                hasFailed = true;
                clearInterval(interval);
                document.getElementById('statusCard').innerHTML = `
                    <div class="mb-3"><i class="bi bi-x-circle-fill text-danger" style="font-size:3.5rem"></i></div>
                    <h4 class="fw-bold text-danger">Payment Failed</h4>
                    <p class="text-muted">The transaction was cancelled or did not complete.<br>Your registration has been cancelled.</p>
                    <a href="register.php" class="btn btn-success rounded-pill px-4 mt-2"><i class="bi bi-arrow-clockwise me-2"></i>Try Again</a>
                `;
            }
        })
        .catch(() => {
            if (!hasFailed) {
                hasFailed = true;
                clearInterval(interval);
                document.getElementById('statusCard').innerHTML = `
                    <div class="mb-3"><i class="bi bi-wifi-off text-warning" style="font-size:3.5rem"></i></div>
                    <h4 class="fw-bold text-warning">Connection Error</h4>
                    <p class="text-muted">Please check your internet and try again.</p>
                    <a href="register.php" class="btn btn-success rounded-pill px-4 mt-2">Back to Registration</a>
                `;
            }
        });
}, 3000);
</script>
</body>
</html>