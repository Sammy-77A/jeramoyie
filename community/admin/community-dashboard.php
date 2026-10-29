<?php
require_once __DIR__ . "/../includes/admin-auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../models/CommunitySavings.php";
require_once __DIR__ . "/../models/CommunityLoan.php";
require_once __DIR__ . "/../models/CommunityCustomer.php";
$savingsModel = new CommunitySavings($pdo); $loanModel = new CommunityLoan($pdo); $customerModel = new CommunityCustomer($pdo);
$total_savings = $savingsModel->getTotalSavings(); $all_customers = $customerModel->getAll();
$pending_loans = $loanModel->getAll("pending"); $approved_loans = $loanModel->getAll("approved");
$recent_loans = $loanModel->getAll(); $recent_savings = $savingsModel->getAllTransactions(10);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>:root{--primary:#1a5928;--bg:#f3f4f6}body{font-family:"Inter",sans-serif;background:var(--bg);padding-top:30px}
.glass-card{background:#fff;border-radius:15px;border:none;box-shadow:0 4px 15px rgba(0,0,0,.05)}
.admin-nav a{color:var(--primary);text-decoration:none;font-weight:600;padding:8px 16px;border-radius:10px;transition:all .2s;display:inline-block}
.admin-nav a:hover,.admin-nav a.active{background:var(--primary);color:#fff}
.btn-main{background:var(--primary);color:#fff;border:none;border-radius:10px;font-weight:600}.btn-main:hover{background:#145020;color:#fff}
.stat-card{position:relative;overflow:hidden;border-radius:15px;padding:1.5rem;color:#fff;transition:transform .3s}.stat-card:hover{transform:translateY(-5px)}.stat-card .icon{position:absolute;top:15px;right:15px;font-size:2.5rem;opacity:.2}
.filter-pills a{text-decoration:none;padding:6px 14px;border-radius:20px;font-size:.8rem;font-weight:500;border:1px solid #ddd;color:#333;transition:all .2s}.filter-pills a.active,.filter-pills a:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
/* ─── Mobile Responsive ─── */
@media(max-width:768px){
    h2{font-size:1.5rem!important}
    h5{font-size:1.1rem!important}
    .container{padding-left:12px;padding-right:12px}
    .glass-card{padding:1rem!important;border-radius:12px!important}
    .stat-card{padding:1rem!important;border-radius:12px!important}
    .stat-card h3{font-size:1.3rem!important}
    .stat-card .icon{font-size:1.8rem!important}
    .table{font-size:.8rem}
    .table th,.table td{padding:.4rem .5rem!important;white-space:nowrap}
    .badge{font-size:.65rem!important;padding:3px 8px!important}
    .btn-main{padding:.6rem!important;font-size:.85rem}
    .d-flex.justify-content-between{flex-wrap:wrap;gap:.5rem}
    .nav-pills .nav-link{padding:6px 12px!important;font-size:.8rem}
    .admin-nav{gap:4px!important}.admin-nav a{padding:6px 10px!important;font-size:.75rem}
    .preview-box{padding:1rem!important}.preview-box .table td{font-size:.8rem}
    .filter-pills a{padding:4px 10px!important;font-size:.7rem}
    .form-floating>.form-control{font-size:.9rem}
    .col-md-3,.col-md-4,.col-md-6{margin-bottom:.5rem}
    body{padding-top:15px!important}
}
@media(max-width:576px){
    h2{font-size:1.3rem!important}
    .table-responsive{margin:0 -12px}.table-responsive table{margin:0}
    .stat-card h3{font-size:1.1rem!important}
    .stat-card small{font-size:.7rem}
}</style><title>Community Admin Dashboard</title></head><body>
<div class="container mb-5">
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"><h2 class="fw-bold mb-0"><i class="bi bi-globe me-2"></i>Community Admin</h2><div class="d-flex gap-2"><a href="../../admin_dashboard.php" class="btn btn-outline-dark rounded-pill px-4 btn-sm"><i class="bi bi-chevron-left me-1"></i>Console</a><a href="../../profile.php" class="btn btn-outline-secondary rounded-pill px-4 btn-sm">Profile</a></div></div>
<div class="admin-nav mb-4 d-flex flex-wrap gap-2">
<a href="community-dashboard.php" class="active"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
<a href="community-customers.php" class=""><i class="bi bi-people me-1"></i>Customers</a>
<a href="community-products.php" class=""><i class="bi bi-box-seam me-1"></i>Products</a>
<a href="community-loans.php" class=""><i class="bi bi-cash-stack me-1"></i>Loans</a>
<a href="community-reports.php" class=""><i class="bi bi-bar-chart me-1"></i>Reports</a></div>
<div class="row g-4 mb-4">
<div class="col-md-3"><div class="stat-card shadow" style="background:linear-gradient(135deg,#22c55e,#15803d)"><div class="icon"><i class="bi bi-piggy-bank"></i></div><small class="opacity-75">Total Savings</small><h3 class="fw-bold mt-1"><?=community_format_currency($total_savings)?></h3></div></div>
<div class="col-md-3"><div class="stat-card shadow" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)"><div class="icon"><i class="bi bi-people-fill"></i></div><small class="opacity-75">Customers</small><h3 class="fw-bold mt-1"><?=count($all_customers)?></h3></div></div>
<div class="col-md-3"><div class="stat-card shadow" style="background:linear-gradient(135deg,#f59e0b,#d97706)"><div class="icon"><i class="bi bi-hourglass-split"></i></div><small class="opacity-75">Pending Loans</small><h3 class="fw-bold mt-1"><?=count($pending_loans)?></h3></div></div>
<div class="col-md-3"><div class="stat-card shadow" style="background:linear-gradient(135deg,#ef4444,#b91c1c)"><div class="icon"><i class="bi bi-cash-stack"></i></div><small class="opacity-75">Active Loans</small><h3 class="fw-bold mt-1"><?=count($approved_loans)?></h3></div></div></div>
<div class="row g-4"><div class="col-md-6"><div class="glass-card p-4 h-100"><h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-warning"></i>Recent Loan Applications</h5>
<?php if(empty($recent_loans)): ?><p class="text-muted small">None yet.</p><?php else: foreach(array_slice($recent_loans,0,8) as $l): ?>
<div class="d-flex justify-content-between align-items-center border-bottom py-2"><div><span class="fw-bold small"><?=htmlspecialchars($l["customer_name"])?></span><br><small class="text-muted"><?=htmlspecialchars($l["product_name"])?> • <?=community_format_currency($l["amount"])?></small></div>
<?php $c=["pending"=>"bg-warning text-dark","approved"=>"bg-success","rejected"=>"bg-danger","completed"=>"bg-primary","defaulted"=>"bg-dark"]; ?>
<span class="badge rounded-pill <?=$c[$l["status"]]??"bg-secondary"?>"><?=ucfirst($l["status"])?></span></div>
<?php endforeach; endif; ?>
<?php if(count($pending_loans)>0): ?><a href="community-loans.php?status=pending" class="btn btn-sm btn-outline-warning rounded-pill mt-3"><i class="bi bi-exclamation-circle me-1"></i>Review <?=count($pending_loans)?> Pending</a><?php endif; ?></div></div>
<div class="col-md-6"><div class="glass-card p-4 h-100"><h5 class="fw-bold mb-3"><i class="bi bi-piggy-bank me-2 text-success"></i>Recent Savings</h5>
<?php if(empty($recent_savings)): ?><p class="text-muted small">None yet.</p><?php else: foreach($recent_savings as $s): ?>
<div class="d-flex justify-content-between align-items-center border-bottom py-2"><div><span class="fw-bold small"><?=htmlspecialchars($s["customer_name"])?></span><br><small class="text-muted"><?=htmlspecialchars($s["product_name"])?></small></div>
<div class="text-end"><span class="fw-bold small <?=$s["transaction_type"]==="deposit"?"text-success":"text-danger"?>"><?=$s["transaction_type"]==="deposit"?"+":"-"?><?=community_format_currency($s["amount"])?></span><br><small class="text-muted"><?=date("d M, H:i",strtotime($s["created_at"]))?></small></div></div>
<?php endforeach; endif; ?></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>