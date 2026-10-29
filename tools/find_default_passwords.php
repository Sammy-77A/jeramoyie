<?php
/**
 * CLI only. Finds members still using a known default password.
 *
 *   php tools/find_default_passwords.php            # list them
 *   php tools/find_default_passwords.php --lock     # also replace their hash with a random one,
 *                                                   # so they must use "Forgot password" to get in
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config.php';

$defaults = ['Jera2026', 'JeraMyE2025'];
$lock = in_array('--lock', $argv, true);

$found = 0;
foreach ($pdo->query("SELECT id, name, phone, role, password_hash FROM members") as $m) {
    foreach ($defaults as $d) {
        if (password_verify($d, $m['password_hash'])) {
            $found++;
            echo "#{$m['id']} {$m['name']} ({$m['role']}, {$m['phone']}) uses a default password\n";
            if ($lock) {
                $random = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE members SET password_hash = ? WHERE id = ?")->execute([$random, $m['id']]);
                echo "   -> locked; member must use Forgot Password\n";
            }
            break;
        }
    }
}
echo $found ? "\n$found member(s) found.\n" : "No members on default passwords.\n";
