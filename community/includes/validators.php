<?php
function validate_phone($phone) {
    $phone = trim($phone);
    if (preg_match('/^07[0-9]{8}$/', $phone)) return preg_replace('/^0/', '254', $phone);
    if (preg_match('/^2547[0-9]{8}$/', $phone)) return $phone;
    return false;
}
function validate_national_id($id) {
    $id = trim($id);
    return preg_match('/^[0-9]{7,10}$/', $id) ? $id : false;
}
function validate_amount($amount, $min = 0, $max = PHP_FLOAT_MAX) {
    $amount = (float)$amount;
    return ($amount <= 0 || $amount < $min || $amount > $max) ? false : round($amount, 2);
}
function sanitize_string($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}
function validate_email($email) {
    $email = trim($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
}