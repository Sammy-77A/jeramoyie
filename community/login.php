<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/validators.php';
require_once __DIR__ . '/models/CommunityCustomer.php';
if (isset($_SESSION['community_customer_id'])) { header('Location: dashboard.php'); exit; }
$error = ''; $msg = htmlspecialchars($_GET['msg'] ?? ''); $customerModel = new CommunityCustomer($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = 'Session expired.'; }
    else {
        $phone = validate_phone(trim($_POST['phone'] ?? '')); $password = $_POST['password'] ?? '';
        if (!$phone) { $error = 'Invalid phone number format.'; }
        else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM community_login_attempts WHERE phone_number=? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $stmt->execute([$phone]);
            if ((int)$stmt->fetchColumn() >= 5) { $error = 'Too many login attempts. Wait 15 minutes.'; }
            else {
                $pdo->prepare("INSERT INTO community_login_attempts (phone_number) VALUES (?)")->execute([$phone]);
                $customer = $customerModel->findByPhone($phone);
                if ($customer && password_verify($password, $customer['password'])) {
                    if ($customer['status'] !== 'active') { $error = 'Account suspended. Contact admin.'; }
                    else {
                        session_regenerate_id(true);
                        $_SESSION['community_customer_id'] = $customer['id'];
                        $_SESSION['community_customer_name'] = $customer['full_name'];
                        $pdo->prepare("DELETE FROM community_login_attempts WHERE phone_number=?")->execute([$phone]);
                        log_community_audit($pdo, 'customer', $customer['id'], 'Login', 'Successful login');
                        header('Location: dashboard.php'); exit;
                    }
                } else { $error = 'Invalid phone number or password.'; }
            }
        }
    }
}
$csrf = regenerate_csrf_token();
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Community Login • Jera Moyie</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--primary:#1a5928;--primary-hover:#145020;--accent:#f59e0b;--bg:#f3f4f6}
body{font-family:'Inter',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;background-image:radial-gradient(at 0% 0%,rgba(26,89,40,.05) 0,transparent 50%),radial-gradient(at 100% 100%,rgba(245,158,11,.05) 0,transparent 50%)}
.auth-card{background:#fff;border-radius:24px;box-shadow:0 20px 60px rgba(0,0,0,.06);width:100%;max-width:420px;padding:2.5rem;position:relative;overflow:hidden}
.auth-card::before{content:'';position:absolute;top:0;left:0;width:100%;height:6px;background:linear-gradient(90deg,var(--primary),var(--accent))}
.form-floating>.form-control{border:1px solid #e0e0e0;border-radius:12px}.form-floating>.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(26,89,40,.1)}
.btn-main{background:var(--primary);border:none;color:#fff;font-weight:600;padding:.9rem;border-radius:12px;width:100%;transition:all .3s}.btn-main:hover{background:var(--primary-hover);transform:translateY(-2px);color:#fff}
.password-toggle{position:absolute;top:50%;right:15px;transform:translateY(-50%);cursor:pointer;color:#6c757d;z-index:5}
</style></head><body>
<nav class="navbar navbar-expand-lg fixed-top bg-white border-bottom shadow-sm py-2">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center text-success fw-bold" href="../index.html"><i class="bi bi-chevron-left me-2"></i>Home</a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="register.php" class="btn btn-sm btn-outline-success rounded-pill px-3">Create Account</a>
        </div>
    </div>
</nav>
<div class="auth-card" style="margin-top: 4rem;">
<div class="text-center mb-4"><div class="mx-auto rounded-circle bg-success text-white d-flex align-items-center justify-content-center mb-3 shadow" style="width:70px;height:70px;font-size:1.5rem"><i class="bi bi-shield-lock"></i></div><h3 class="fw-bold">Community Login</h3><p class="text-muted small">Access your savings & loans portal</p></div>
<?php if($msg): ?><div class="alert alert-info small rounded-3 border-0 py-2 mb-3"><i class="bi bi-info-circle me-2"></i><?=$msg?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger small rounded-3 border-0 py-2 mb-3"><i class="bi bi-exclamation-circle me-2"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<form method="POST"><?=csrf_input()?>
<div class="form-floating mb-3"><input type="tel" class="form-control" name="phone" id="phone" placeholder="07..." required><label for="phone">Phone Number</label></div>
<div class="form-floating mb-4 position-relative"><input type="password" class="form-control" name="password" id="password" placeholder="Password" required><label for="password">Password</label><i class="bi bi-eye-slash password-toggle" id="togglePassword"></i></div>
<button type="submit" class="btn btn-main"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</button></form>
<div class="text-center mt-4"><p class="small text-muted">Don't have an account? <a href="register.php" class="text-decoration-none fw-bold" style="color:var(--primary)">Create Account</a></p></div>
</div>
<script>document.getElementById('togglePassword').addEventListener('click',function(){const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';this.classList.toggle('bi-eye');this.classList.toggle('bi-eye-slash');});</script>
</body></html>