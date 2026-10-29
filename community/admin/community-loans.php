<?php
require_once __DIR__ . "/../includes/admin-auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../models/CommunityLoan.php";
$loanModel = new CommunityLoan($pdo); $success = $error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $loan_id = (int)($_POST["loan_id"] ?? 0); $comment = trim($_POST["comment"] ?? ""); $action = $_POST["loan_action"] ?? "";
    if ($loan_id && in_array($action, ["approve", "reject"])) {
        if ($action === "approve") { $r = $loanModel->approve($loan_id, $admin_id, $comment); if ($r) { log_community_audit($pdo, "admin", $admin_id, "Loan Approved", "Loan #{$loan_id}"); $success = "Loan #{$loan_id} approved."; } else $error = "Failed."; }
        else { $r = $loanModel->reject($loan_id, $admin_id, $comment); if ($r) { log_community_audit($pdo, "admin", $admin_id, "Loan Rejected", "Loan #{$loan_id}: $comment"); $success = "Loan #{$loan_id} rejected."; } else $error = "Failed."; }
    }
}
$status_filter = $_GET["status"] ?? ""; $loans = $loanModel->getAll($status_filter ?: null);
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
}</style><title>Community Loans • Admin</title></head><body>
<div class="container mb-5">
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"><h2 class="fw-bold mb-0"><i class="bi bi-cash-stack me-2"></i>Loans</h2><div class="d-flex gap-2"><a href="community-dashboard.php" class="btn btn-outline-success rounded-pill px-4 btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a><a href="../../admin_dashboard.php" class="btn btn-outline-dark rounded-pill px-4 btn-sm"><i class="bi bi-chevron-left me-1"></i>Console</a><a href="../../profile.php" class="btn btn-outline-secondary rounded-pill px-4 btn-sm">Profile</a></div></div>
<div class="admin-nav mb-4 d-flex flex-wrap gap-2"><a href="community-dashboard.php">Dashboard</a><a href="community-customers.php">Customers</a><a href="community-products.php">Products</a><a href="community-loans.php" class="active">Loans</a><a href="community-reports.php">Reports</a></div>
<?php if($success): ?><div class="alert alert-success border-0 shadow-sm rounded-4 small py-2"><i class="bi bi-check-circle me-2"></i><?=htmlspecialchars($success)?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger border-0 shadow-sm rounded-4 small py-2"><i class="bi bi-exclamation-triangle me-2"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<div class="filter-pills d-flex flex-wrap gap-2 mb-4"><a href="?status=" class="<?=!$status_filter?"active":""?>">All</a>
<?php foreach(["pending","approved","rejected","completed","defaulted"] as $s): ?><a href="?status=<?=$s?>" class="<?=$status_filter===$s?"active":""?>"><?=ucfirst($s)?></a><?php endforeach; ?></div>
<?php if(empty($loans)): ?><div class="glass-card p-4 text-center text-muted">No loans found.</div>
<?php else: ?><div class="table-responsive"><table class="table table-hover bg-white rounded-4 overflow-hidden shadow-sm">
<thead class="table-light"><tr><th>#</th><th>Customer</th><th>Product</th><th>Amount</th><th>Interest</th><th>Total</th><th>Penalty</th><th>Status</th><th>Due</th><th>Actions</th></tr></thead><tbody>
<?php foreach($loans as $l): $badge=["pending"=>"bg-warning text-dark","approved"=>"bg-success","rejected"=>"bg-danger","completed"=>"bg-primary","defaulted"=>"bg-dark"]; ?>
<tr><td class="small"><?=$l["id"]?></td><td class="small"><span class="fw-bold"><?=htmlspecialchars($l["customer_name"])?></span><br><small class="text-muted"><?=htmlspecialchars($l["phone_number"])?></small></td>
<td class="small"><?=htmlspecialchars($l["product_name"])?></td><td class="fw-bold small"><?=community_format_currency($l["amount"])?></td>
<td class="small text-muted"><?=community_format_currency($l["interest_amount"])?></td><td class="small"><?=community_format_currency($l["total_repayable"])?></td>
<td class="small <?=$l["penalty_accrued"]>0?"text-danger fw-bold":"text-muted"?>"><?=community_format_currency($l["penalty_accrued"])?></td>
<td><span class="badge rounded-pill <?=$badge[$l["status"]]??"bg-secondary"?>"><?=ucfirst($l["status"])?></span></td>
<td class="small"><?=$l["due_date"]?date("d M Y",strtotime($l["due_date"])):"—"?>
<?php if($l["due_date"]&&$l["status"]==="approved"&&$l["due_date"]<date("Y-m-d")): ?><br><span class="badge bg-danger rounded-pill" style="font-size:.6rem">OVERDUE</span><?php endif; ?></td>
<td><?php if($l["status"]==="pending"): ?>
<div class="d-flex gap-1"><form method="POST" style="display:inline"><input type="hidden" name="loan_id" value="<?=$l["id"]?>"><input type="hidden" name="loan_action" value="approve"><input type="hidden" name="comment" value="">
<button type="submit" class="btn btn-sm btn-success rounded-pill" onclick="return confirm('Approve?')"><i class="bi bi-check-lg"></i></button></form>
<button class="btn btn-sm btn-outline-danger rounded-pill" data-bs-toggle="modal" data-bs-target="#rm<?=$l["id"]?>"><i class="bi bi-x-lg"></i></button></div>
<div class="modal fade" id="rm<?=$l["id"]?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content rounded-4 border-0 shadow">
<div class="modal-header border-0"><h6 class="fw-bold">Reject Loan #<?=$l["id"]?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="POST"><div class="modal-body"><input type="hidden" name="loan_id" value="<?=$l["id"]?>"><input type="hidden" name="loan_action" value="reject"><label class="form-label small fw-bold">Reason</label><textarea name="comment" class="form-control" rows="3"></textarea></div>
<div class="modal-footer border-0"><button type="submit" class="btn btn-danger rounded-pill px-4">Reject</button></div></form></div></div></div>
<?php else: ?>—<?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>