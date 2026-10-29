<?php
/**
 * Admin Auth Guard for Community Admin Pages
 * Reuses existing Jera Moyie admin session (member_id + is_admin).
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['member_id']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../../admin_dashboard.php');
    exit;
}

$admin_id = $_SESSION['member_id'];
$admin_name = $_SESSION['name'];
$admin_role = $_SESSION['role'];
