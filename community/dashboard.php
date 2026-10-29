<?php
require_once __DIR__ . '/includes/community-auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/models/CommunitySavings.php';
require_once __DIR__ . '/models/CommunityLoan.php';
require_once __DIR__ . '/models/CommunityCustomer.php';

set_security_headers();
verify_session_fingerprint();

$customer_id = $_SESSION['community_customer_id'];
$customer_name = $_SESSION['community_customer_name'];
$savingsModel = new CommunitySavings($pdo);
$loanModel = new CommunityLoan($pdo);
$customerModel = new CommunityCustomer($pdo);
$customer = $customerModel->findById($customer_id);
$total_savings = $savingsModel->getBalance($customer_id);
$loans = $loanModel->getByCustomer($customer_id);
$active_loans = array_filter($loans, fn($l) => $l['status'] === 'approved');
$total_outstanding = 0; $next_due_date = null;
foreach ($active_loans as $loan) {
    $total_outstanding += $loanModel->getOutstanding($loan);
    if ($loan['due_date'] && (!$next_due_date || $loan['due_date'] < $next_due_date)) $next_due_date = $loan['due_date'];
}
$recent_transactions = $savingsModel->getTransactions($customer_id, null, 5);
$names = explode(" ", $customer_name);
$initials = strtoupper(substr($names[0],0,1).(isset($names[1])?substr($names[1],0,1):''));
$active_page = 'dashboard';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard • Community Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--primary:#1a5928;--accent:#f59e0b;--bg:#f3f4f6}body{font-family:'Inter',sans-serif;background:var(--bg);padding-top:75px}
.glass-card{background:#fff;border-radius:20px;border:none;box-shadow:0 8px 30px rgba(0,0,0,.05);transition:transform .3s}
.stat-card{border-left:5px solid var(--primary)}.stat-card:hover{transform:translateY(-5px)}
.btn-main{background:var(--primary);color:#fff;border:none;border-radius:12px;font-weight:600}.btn-main:hover{background:#145020;color:#fff}
@media(max-width:768px){h2,h3{font-size:1.4rem!important}.glass-card{padding:1rem!important;border-radius:12px!important}.stat-card{padding:1rem!important}.stat-card h2{font-size:1.2rem!important}body{padding-top:65px!important}}
@media(max-width:576px){h2,h3{font-size:1.2rem!important}}
</style></head><body>
<?php include __DIR__.'/includes/navbar.php'; ?>
<div class="container mt-4"><div class="row g-4">
<div class="col-lg-4"><div class="glass-card p-4 text-center h-100">
<div class="mx-auto rounded-circle bg-success text-white d-flex align-items-center justify-content-center mb-3 shadow" style="width:80px;height:80px;font-size:2rem"><?=$initials?></div>
<h4 class="fw-bold mb-1"><?=htmlspecialchars($customer_name)?></h4>
<p class="text-muted small mb-1"><i class="bi bi-phone me-1"></i><?=htmlspecialchars($customer['phone_number'])?></p>
<p class="text-muted small mb-4"><i class="bi bi-card-text me-1"></i>ID: <?=htmlspecialchars($customer['national_id'])?></p>
<div class="d-grid gap-2"><a href="savings.php" class="btn btn-main py-2 shadow-sm"><i class="bi bi-piggy-bank me-2"></i>My Savings</a>
<a href="apply-loan.php" class="btn btn-outline-dark border-2 rounded-3 py-2"><i class="bi bi-cash-stack me-2"></i>Apply for Loan</a>
<a href="transactions.php" class="btn btn-outline-success rounded-3 py-2"><i class="bi bi-receipt me-2"></i>Transaction History</a></div></div></div>
<div class="col-lg-8"><h3 class="fw-bold mb-4">Financial Overview</h3>
<div class="row g-3">
<div class="col-md-6"><div class="glass-card p-4 stat-card h-100"><small class="text-muted text-uppercase fw-bold">Total Savings</small><h2 class="fw-bold text-success mt-2"><?=community_format_currency($total_savings)?></h2><a href="savings.php" class="small text-decoration-none">View Details →</a></div></div>
<div class="col-md-6"><div class="glass-card p-4 stat-card h-100" style="border-left-color:#dc3545"><small class="text-muted text-uppercase fw-bold">Outstanding Loans</small><h2 class="fw-bold text-danger mt-2"><?=community_format_currency($total_outstanding)?></h2><small class="text-muted"><?=count($active_loans)?> active loan(s)</small></div></div>
<div class="col-md-6"><div class="glass-card p-4 stat-card h-100" style="border-left-color:var(--accent)"><small class="text-muted text-uppercase fw-bold">Next Due Date</small><h2 class="fw-bold mt-2" style="color:var(--accent)"><?=$next_due_date ? date('d M Y',strtotime($next_due_date)) : '—'?></h2><small class="text-muted"><?=$next_due_date && $next_due_date < date('Y-m-d') ? '<span class="text-danger fw-bold">OVERDUE</span>' : 'On track'?></small></div></div>
<div class="col-md-6"><div class="glass-card p-4 stat-card h-100" style="border-left-color:#6f42c1"><small class="text-muted text-uppercase fw-bold">Total Loans</small><h2 class="fw-bold mt-2" style="color:#6f42c1"><?=count($loans)?></h2><small class="text-muted">lifetime applications</small></div></div>
</div>
<?php if(!empty($active_loans)): ?><h5 class="fw-bold mt-5 mb-3">Active Loans</h5>
<?php foreach($active_loans as $loan): $outstanding=$loanModel->getOutstanding($loan); ?>
<div class="glass-card p-3 mb-3"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><div><h6 class="fw-bold mb-1"><?=htmlspecialchars($loan['product_name'])?></h6><small class="text-muted">Due: <?=date('d M Y',strtotime($loan['due_date']))?></small></div><div class="text-end"><span class="fw-bold text-danger"><?=community_format_currency($outstanding)?></span><br><small class="text-muted">outstanding</small></div></div>
<div class="progress mt-2" style="height:6px"><?php $pct=$loan['total_repayable']>0?min(100,(($loan['total_repayable']-$outstanding)/$loan['total_repayable'])*100):0; ?><div class="progress-bar bg-success" style="width:<?=$pct?>%"></div></div><small class="text-muted"><?=round($pct)?>% repaid</small>
<a href="transactions.php" class="btn btn-sm btn-outline-success mt-2 rounded-pill"><i class="bi bi-phone me-1"></i>Repay via M-PESA</a></div>
<?php endforeach; endif; ?>
<?php if(!empty($recent_transactions)): ?><h5 class="fw-bold mt-5 mb-3">Recent Savings</h5>
<?php foreach($recent_transactions as $txn): ?>
<div class="glass-card p-3 mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2"><div><span class="badge rounded-pill <?=$txn['transaction_type']==='deposit'?'bg-success':'bg-warning text-dark'?> me-2"><?=ucfirst($txn['transaction_type'])?></span><small class="text-muted"><?=htmlspecialchars($txn['product_name'])?></small></div><div class="text-end"><span class="fw-bold <?=$txn['transaction_type']==='deposit'?'text-success':'text-danger'?>"><?=$txn['transaction_type']==='deposit'?'+':'-'?><?=community_format_currency($txn['amount'])?></span><br><small class="text-muted"><?=date('d M, H:i',strtotime($txn['created_at']))?></small></div></div>
<?php endforeach; ?>
<a href="transactions.php" class="btn btn-sm btn-outline-dark rounded-pill mt-2">View All</a><?php endif; ?>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>