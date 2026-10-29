<?php
session_start();
require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/json');
$token = $_GET['token'] ?? null;
$paid = false; $pending_exists = false;
if ($token && $token === ($_SESSION['community_repay_token'] ?? '')) {
    $stmt = $pdo->prepare("SELECT id FROM community_pending_repayments WHERE token = ?");
    $stmt->execute([$token]);
    if ($stmt->fetch()) { $pending_exists = true; }
    else {
        $paid = true;
        unset($_SESSION['community_repay_token']);
        unset($_SESSION['community_repay_amount']);
    }
}
echo json_encode(['paid' => $paid, 'pending_exists' => $pending_exists]);