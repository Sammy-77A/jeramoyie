<?php
/**
 * Check Reset Verification — AJAX polling endpoint for reset_pending.php
 */
session_start();
require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/json');

$token = $_GET['token'] ?? null;
$verified = false;
$pending_exists = false;

if ($token && $token === ($_SESSION['community_reset_token'] ?? '')) {
    $stmt = $pdo->prepare("SELECT id, verified FROM community_password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $pending_exists = true;
        if ((int)$row['verified'] === 1) {
            $verified = true;
        // Keep token in session — needed for reset-password.php
        }
    }
// If row doesn't exist and not verified, it was deleted (failed payment)
}

echo json_encode(['verified' => $verified, 'pending_exists' => $pending_exists]);
