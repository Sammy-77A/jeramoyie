<?php
require_once __DIR__ . "/../includes/admin-auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/validators.php";
require_once __DIR__ . "/../models/CommunityProduct.php";
$productModel = new CommunityProduct($pdo); $success = $error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    if ($action === "add" || $action === "edit") {
        $data = ["product_name" => sanitize_string($_POST["product_name"] ?? ""), "product_type" => $_POST["product_type"] ?? "custom",
            "description" => sanitize_string($_POST["description"] ?? ""), "minimum_amount" => (float)($_POST["minimum_amount"] ?? 0),
            "maximum_amount" => (float)($_POST["maximum_amount"] ?? 0), "interest_rate" => (float)($_POST["interest_rate"] ?? 0),
            "penalty_rate" => (float)($_POST["penalty_rate"] ?? 0), "duration_in_months" => (int)($_POST["duration_in_months"] ?? 1),
            "requires_approval" => isset($_POST["requires_approval"]) ? 1 : 0, "is_active" => isset($_POST["is_active"]) ? 1 : 0];
        if (!$data["product_name"]) $error = "Product name is required.";
        elseif ($data["minimum_amount"] < 0 || $data["maximum_amount"] < 0) $error = "Amounts must be positive.";
        else {
            if ($action === "add") { $productModel->create($data); log_community_audit($pdo, "admin", $admin_id, "Product Created", "Product: {$data['product_name']}");
                if (function_exists("log_admin_activity")) log_admin_activity($pdo, "Community Product Created", $data["product_name"]); $success = "Product created."; }
            else { $id = (int)$_POST["product_id"]; $productModel->update($id, $data); log_community_audit($pdo, "admin", $admin_id, "Product Updated", "#{$id}: {$data['product_name']}");
                if (function_exists("log_admin_activity")) log_admin_activity($pdo, "Community Product Updated", "#{$id}"); $success = "Product updated."; }
        }
    } elseif ($action === "delete") { $id = (int)$_POST["product_id"]; $productModel->delete($id);
        log_community_audit($pdo, "admin", $admin_id, "Product Deleted", "Product #{$id}");
        if (function_exists("log_admin_activity")) log_admin_activity($pdo, "Community Product Deleted", "#{$id}"); $success = "Product deleted."; }
}
$products = $productModel->getAll(); $edit_product = isset($_GET["edit"]) ? $productModel->findById((int)$_GET["edit"]) : null;
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
}</style><title>Community Products • Admin</title></head><body>
<div class="container mb-5">
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"><h2 class="fw-bold mb-0"><i class="bi bi-box-seam me-2"></i>Products</h2><div class="d-flex gap-2"><a href="community-dashboard.php" class="btn btn-outline-success rounded-pill px-4 btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a><a href="../../admin_dashboard.php" class="btn btn-outline-dark rounded-pill px-4 btn-sm"><i class="bi bi-chevron-left me-1"></i>Console</a><a href="../../profile.php" class="btn btn-outline-secondary rounded-pill px-4 btn-sm">Profile</a></div></div>
<div class="admin-nav mb-4 d-flex flex-wrap gap-2"><a href="community-dashboard.php">Dashboard</a><a href="community-customers.php">Customers</a><a href="community-products.php" class="active">Products</a><a href="community-loans.php">Loans</a><a href="community-reports.php">Reports</a></div>
<?php if($success): ?><div class="alert alert-success border-0 shadow-sm rounded-4 small py-2"><i class="bi bi-check-circle me-2"></i><?=htmlspecialchars($success)?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger border-0 shadow-sm rounded-4 small py-2"><i class="bi bi-exclamation-triangle me-2"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<div class="glass-card p-4 mb-4"><h5 class="fw-bold mb-3"><?=$edit_product?"Edit":"Add"?> Product</h5>
<form method="POST"><input type="hidden" name="action" value="<?=$edit_product?"edit":"add"?>">
<?php if($edit_product): ?><input type="hidden" name="product_id" value="<?=$edit_product["id"]?>"><?php endif; ?>
<div class="row g-3">
<div class="col-md-6"><label class="form-label small fw-bold">Name</label><input type="text" name="product_name" class="form-control" value="<?=htmlspecialchars($edit_product["product_name"]??"")?>" required></div>
<div class="col-md-3"><label class="form-label small fw-bold">Type</label><select name="product_type" class="form-select"><?php foreach(["saving_scheme","okoa_loan","custom"] as $t): ?><option value="<?=$t?>" <?=(($edit_product["product_type"]??"")===$t)?"selected":""?>><?=ucwords(str_replace("_"," ",$t))?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label small fw-bold">Duration (months)</label><input type="number" name="duration_in_months" class="form-control" min="0" value="<?=$edit_product["duration_in_months"]??1?>"></div>
<div class="col-md-3"><label class="form-label small fw-bold">Min Amount</label><input type="number" name="minimum_amount" class="form-control" step="0.01" min="0" value="<?=$edit_product["minimum_amount"]??0?>"></div>
<div class="col-md-3"><label class="form-label small fw-bold">Max Amount</label><input type="number" name="maximum_amount" class="form-control" step="0.01" min="0" value="<?=$edit_product["maximum_amount"]??0?>"></div>
<div class="col-md-3"><label class="form-label small fw-bold">Interest %</label><input type="number" name="interest_rate" class="form-control" step="0.01" min="0" value="<?=$edit_product["interest_rate"]??0?>"></div>
<div class="col-md-3"><label class="form-label small fw-bold">Penalty %</label><input type="number" name="penalty_rate" class="form-control" step="0.01" min="0" value="<?=$edit_product["penalty_rate"]??0?>"></div>
<div class="col-12"><label class="form-label small fw-bold">Description</label><textarea name="description" class="form-control" rows="2"><?=htmlspecialchars($edit_product["description"]??"")?></textarea></div>
<div class="col-md-3"><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="requires_approval" <?=($edit_product["requires_approval"]??1)?"checked":""?>><label class="form-check-label small">Requires Approval</label></div></div>
<div class="col-md-3"><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="is_active" <?=($edit_product["is_active"]??1)?"checked":""?>><label class="form-check-label small">Active</label></div></div>
<div class="col-12"><button type="submit" class="btn btn-main px-4"><i class="bi bi-save me-2"></i><?=$edit_product?"Update":"Add"?></button>
<?php if($edit_product): ?><a href="community-products.php" class="btn btn-outline-dark rounded-3 px-4 ms-2">Cancel</a><?php endif; ?></div></div></form></div>
<?php if(empty($products)): ?><p class="text-muted text-center">No products.</p>
<?php else: ?><div class="table-responsive"><table class="table table-hover bg-white rounded-4 overflow-hidden shadow-sm">
<thead class="table-light"><tr><th>Name</th><th>Type</th><th>Min</th><th>Max</th><th>Rate</th><th>Penalty</th><th>Months</th><th>Approval</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($products as $p): ?><tr><td class="fw-bold small"><?=htmlspecialchars($p["product_name"])?></td><td><span class="badge bg-dark rounded-pill"><?=ucwords(str_replace("_"," ",$p["product_type"]))?></span></td>
<td class="small"><?=community_format_currency($p["minimum_amount"])?></td><td class="small"><?=community_format_currency($p["maximum_amount"])?></td>
<td class="small"><?=$p["interest_rate"]?>%</td><td class="small"><?=$p["penalty_rate"]?>%</td><td class="small"><?=$p["duration_in_months"]?></td>
<td><span class="badge rounded-pill <?=$p["requires_approval"]?"bg-warning text-dark":"bg-info"?>"><?=$p["requires_approval"]?"Yes":"Auto"?></span></td>
<td><span class="badge rounded-pill <?=$p["is_active"]?"bg-success":"bg-secondary"?>"><?=$p["is_active"]?"Active":"Off"?></span></td>
<td class="d-flex gap-1"><a href="?edit=<?=$p["id"]?>" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-pencil"></i></a>
<form method="POST" style="display:inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?=$p["id"]?>"><button type="submit" class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-trash"></i></button></form></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>