<?php
require_once __DIR__ . "/../includes/admin-auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../models/CommunityCustomer.php";
$customerModel = new CommunityCustomer($pdo); $success = $error = ""; $search = trim($_GET["search"] ?? "");
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cust_id = (int)$_POST["customer_id"]; $new_status = $_POST["new_status"] === "suspended" ? "suspended" : "active";
    $customerModel->updateStatus($cust_id, $new_status);
    log_community_audit($pdo, "admin", $admin_id, "Customer Status Changed", "Customer #{$cust_id} → {$new_status}");
    if (function_exists("log_admin_activity")) log_admin_activity($pdo, "Community Customer Status", "#{$cust_id} → {$new_status}");
    $success = "Customer status updated to {$new_status}.";
}
$customers = $search ? $customerModel->search($search) : $customerModel->getAll();
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
}</style><title>Community Customers • Admin</title></head><body>
<div class="container mb-5">
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"><h2 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>Community Customers</h2><div class="d-flex gap-2"><a href="community-dashboard.php" class="btn btn-outline-success rounded-pill px-4 btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a><a href="../../admin_dashboard.php" class="btn btn-outline-dark rounded-pill px-4 btn-sm"><i class="bi bi-chevron-left me-1"></i>Console</a><a href="../../profile.php" class="btn btn-outline-secondary rounded-pill px-4 btn-sm">Profile</a></div></div>
<div class="admin-nav mb-4 d-flex flex-wrap gap-2">
<a href="community-dashboard.php" class=""><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
<a href="community-customers.php" class="active"><i class="bi bi-people me-1"></i>Customers</a>
<a href="community-products.php" class=""><i class="bi bi-box-seam me-1"></i>Products</a>
<a href="community-loans.php" class=""><i class="bi bi-cash-stack me-1"></i>Loans</a>
<a href="community-reports.php" class=""><i class="bi bi-bar-chart me-1"></i>Reports</a></div>
<?php if($success): ?><div class="alert alert-success border-0 shadow-sm rounded-4 small py-2"><i class="bi bi-check-circle me-2"></i><?=htmlspecialchars($success)?></div><?php endif; ?>
<div class="glass-card p-4 mb-4"><form method="GET" class="d-flex gap-2"><input type="text" name="search" class="form-control rounded-pill" placeholder="Search name, phone, ID..." value="<?=htmlspecialchars($search)?>">
<button type="submit" class="btn btn-main px-4 rounded-pill"><i class="bi bi-search"></i></button><?php if($search): ?><a href="community-customers.php" class="btn btn-outline-dark rounded-pill px-3">Clear</a><?php endif; ?></form></div>
<p class="text-muted small mb-3"><?=count($customers)?> customer(s) <?=$search ? "matching " . htmlspecialchars($search) : ""?></p>
<?php if(empty($customers)): ?><div class="glass-card p-4 text-center text-muted"><i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>No customers found.</div>
<?php else: ?><div class="table-responsive"><table class="table table-hover bg-white rounded-4 overflow-hidden shadow-sm">
<thead class="table-light"><tr><th>#</th><th>Name</th><th>Phone</th><th>National ID</th><th>Email</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead><tbody>
<?php foreach($customers as $c): ?>
<tr><td class="small"><?=$c["id"]?></td><td class="fw-bold small"><?=htmlspecialchars($c["full_name"])?></td><td class="small"><?=htmlspecialchars($c["phone_number"])?></td>
<td class="small"><?=htmlspecialchars($c["national_id"])?></td><td class="small text-muted"><?=htmlspecialchars($c["email"] ?? "—")?></td>
<td><span class="badge rounded-pill <?=$c["status"]==="active"?"bg-success":"bg-danger"?>"><?=ucfirst($c["status"])?></span></td>
<td class="small text-muted"><?=date("d M Y",strtotime($c["created_at"]))?></td>
<td><form method="POST" style="display:inline"><input type="hidden" name="customer_id" value="<?=$c["id"]?>"><input type="hidden" name="new_status" value="<?=$c["status"]==="active"?"suspended":"active"?>">
<button type="submit" class="btn btn-sm rounded-pill <?=$c["status"]==="active"?"btn-outline-danger":"btn-outline-success"?>" onclick="return confirm('<?=$c["status"]==="active"?"Suspend":"Activate"?>?')"><i class="bi bi-<?=$c["status"]==="active"?"pause-circle":"play-circle"?>"></i> <?=$c["status"]==="active"?"Suspend":"Activate"?></button></form></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>