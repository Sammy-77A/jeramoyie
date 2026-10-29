<?php
require_once __DIR__ . '/includes/community-auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/validators.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/models/CommunityLoan.php';
require_once __DIR__ . '/models/CommunityProduct.php';

set_security_headers();
verify_session_fingerprint();

$customer_id = $_SESSION['community_customer_id'];
$loanModel = new CommunityLoan($pdo);
$productModel = new CommunityProduct($pdo);
$loan_products = array_filter($productModel->getActive(), fn($p) => in_array($p['product_type'], ['okoa_loan','custom']));
$error = $success = ''; $preview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $amount = validate_amount($_POST['amount'] ?? 0, 1);
    $product = $productModel->findById($product_id);

    if (!$amount) $error = 'Please enter a valid loan amount.';
    elseif (!$product || !$product['is_active']) $error = 'Invalid loan product.';
    elseif ($amount < $product['minimum_amount'] || $amount > $product['maximum_amount'])
        $error = 'Amount must be between ' . community_format_currency($product['minimum_amount']) . ' and ' . community_format_currency($product['maximum_amount']) . '.';
    elseif (isset($_POST['confirm']) && $_POST['confirm'] === '1') {
        if ($loanModel->hasActiveLoan($customer_id)) $error = 'You already have an active or pending loan.';
        else {
            $interest = ($amount * $product['interest_rate'] * $product['duration_in_months']) / 100;
            $total = $amount + $interest;
            $due = date('Y-m-d', strtotime("+{$product['duration_in_months']} months"));
            $status = $product['requires_approval'] ? 'pending' : 'approved';
            $loanModel->apply($customer_id, $product_id, $amount, $interest, $total, $status, $due);
            log_community_audit($pdo, 'customer', $customer_id, 'Loan Application', "Product: {$product['product_name']}, Amount: $amount");
            log_security_event($pdo, 'loan_application', "Amount: $amount, Product: {$product['product_name']}", $customer_id);
            $success = $status === 'pending' ? 'Loan submitted for admin approval.' : 'Loan auto-approved! Due: ' . date('d M Y', strtotime($due));
        }
    } else {
        $interest = ($amount * $product['interest_rate'] * $product['duration_in_months']) / 100;
        $preview = ['product' => $product, 'amount' => $amount, 'interest' => $interest, 'total' => $amount + $interest, 'due_date' => date('d M Y', strtotime("+{$product['duration_in_months']} months")), 'product_id' => $product_id];
    }
    regenerate_csrf_token();
}

$my_loans = $loanModel->getByCustomer($customer_id);
$active_page = 'loans';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Apply for Loan • Community Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--primary:#1a5928;--bg:#f3f4f6}body{font-family:'Inter',sans-serif;background:var(--bg);padding-top:75px}
.glass-card{background:#fff;border-radius:15px;border:none;box-shadow:0 4px 15px rgba(0,0,0,.05)}
.btn-main{background:var(--primary);color:#fff;border:none;border-radius:10px;font-weight:600}.btn-main:hover{background:#145020;color:#fff}
.preview-box{background:#f0fdf4;border:2px solid #bbf7d0;border-radius:15px}
@media(max-width:768px){h2{font-size:1.5rem!important}.glass-card{padding:1rem!important;border-radius:12px!important}.table{font-size:.8rem}.preview-box{padding:1rem!important}body{padding-top:65px!important}}
@media(max-width:576px){h2{font-size:1.3rem!important}}
</style></head><body>
<?php include __DIR__.'/includes/navbar.php'; ?>
<div class="container mb-5">
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"><div><h2 class="fw-bold mb-0"><i class="bi bi-cash-stack me-2"></i>Apply for a Loan</h2><p class="text-muted small">Select a product and amount</p></div></div>
<?php if($success): ?><div class="alert alert-success border-0 shadow-sm rounded-4 small py-2"><i class="bi bi-check-circle me-2"></i><?=htmlspecialchars($success)?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger border-0 shadow-sm rounded-4 small py-2"><i class="bi bi-exclamation-triangle me-2"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if($preview): ?>
<div class="preview-box p-4 mb-4"><h5 class="fw-bold text-success mb-3"><i class="bi bi-calculator me-2"></i>Loan Summary</h5>
<div class="row"><div class="col-md-6"><table class="table table-borderless small mb-0">
<tr><td class="text-muted">Product</td><td class="fw-bold"><?=htmlspecialchars($preview['product']['product_name'])?></td></tr>
<tr><td class="text-muted">Loan Amount</td><td class="fw-bold"><?=community_format_currency($preview['amount'])?></td></tr>
<tr><td class="text-muted">Interest Rate</td><td class="fw-bold"><?=$preview['product']['interest_rate']?>%</td></tr>
<tr><td class="text-muted">Duration</td><td class="fw-bold"><?=$preview['product']['duration_in_months']?> month(s)</td></tr>
</table></div><div class="col-md-6"><table class="table table-borderless small mb-0">
<tr><td class="text-muted">Interest</td><td class="fw-bold text-warning"><?=community_format_currency($preview['interest'])?></td></tr>
<tr><td class="text-muted">Total Repayable</td><td class="fw-bold text-danger"><?=community_format_currency($preview['total'])?></td></tr>
<tr><td class="text-muted">Due Date</td><td class="fw-bold"><?=$preview['due_date']?></td></tr>
<tr><td class="text-muted">Approval</td><td class="fw-bold"><?=$preview['product']['requires_approval']?'Requires admin':'Auto-approved'?></td></tr>
</table></div></div>
<form method="POST" class="mt-3"><?=csrf_input()?><input type="hidden" name="product_id" value="<?=$preview['product_id']?>"><input type="hidden" name="amount" value="<?=$preview['amount']?>"><input type="hidden" name="confirm" value="1">
<div class="d-flex gap-2"><button type="submit" class="btn btn-main px-4"><i class="bi bi-check-circle me-2"></i>Confirm Application</button><a href="apply-loan.php" class="btn btn-outline-dark rounded-3 px-4">Cancel</a></div></form></div>
<?php endif; ?>
<?php if(!$preview && !$success): ?><div class="glass-card p-4 mb-4"><h5 class="fw-bold mb-3">Loan Application</h5>
<?php if(empty($loan_products)): ?><p class="text-muted">No loan products available.</p>
<?php else: ?><form method="POST"><?=csrf_input()?>
<div class="mb-3"><label class="form-label small fw-bold text-muted">Select Loan Product</label>
<select name="product_id" class="form-select" id="ps" required><option value="">Choose</option>
<?php foreach($loan_products as $lp): ?><option value="<?=$lp['id']?>" data-min="<?=$lp['minimum_amount']?>" data-max="<?=$lp['maximum_amount']?>" data-rate="<?=$lp['interest_rate']?>" data-months="<?=$lp['duration_in_months']?>"><?=htmlspecialchars($lp['product_name'])?> (<?=$lp['interest_rate']?>% / <?=$lp['duration_in_months']?>mo)</option><?php endforeach; ?>
</select></div><div id="productInfo" class="alert alert-info small border-0 rounded-3 py-2 d-none mb-3"></div>
<div class="mb-3"><label class="form-label small fw-bold text-muted">Loan Amount (KSh)</label><input type="number" name="amount" class="form-control" id="ai" min="1" step="0.01" required></div>
<button type="submit" class="btn btn-main px-4"><i class="bi bi-arrow-right me-2"></i>Preview Loan</button></form><?php endif; ?></div><?php endif; ?>
<h5 class="fw-bold mb-3">My Loan History</h5>
<?php if(empty($my_loans)): ?><div class="glass-card p-4 text-center text-muted"><i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>No loans yet.</div>
<?php else: ?><div class="table-responsive"><table class="table table-hover bg-white rounded-4 overflow-hidden shadow-sm">
<thead class="table-light"><tr><th>Product</th><th>Amount</th><th>Repayable</th><th>Status</th><th>Due</th><th>Applied</th></tr></thead><tbody>
<?php foreach($my_loans as $l): $badge=['pending'=>'bg-warning text-dark','approved'=>'bg-success','rejected'=>'bg-danger','completed'=>'bg-primary','defaulted'=>'bg-dark']; ?>
<tr><td class="small"><?=htmlspecialchars($l['product_name'])?></td><td class="fw-bold small"><?=community_format_currency($l['amount'])?></td><td class="small"><?=community_format_currency($l['total_repayable'])?></td>
<td><span class="badge rounded-pill <?=$badge[$l['status']]??'bg-secondary'?>"><?=ucfirst($l['status'])?></span></td>
<td class="small"><?=$l['due_date']?date('d M Y',strtotime($l['due_date'])):'—'?></td><td class="small text-muted"><?=date('d M Y',strtotime($l['created_at']))?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>document.getElementById('ps')?.addEventListener('change',function(){const o=this.options[this.selectedIndex],i=document.getElementById('productInfo'),a=document.getElementById('ai');if(this.value){i.innerHTML='Range: KSh '+Number(o.dataset.min).toLocaleString()+' — KSh '+Number(o.dataset.max).toLocaleString()+' | '+o.dataset.rate+'% over '+o.dataset.months+' month(s)';i.classList.remove('d-none');a.min=o.dataset.min;a.max=o.dataset.max;}else i.classList.add('d-none');});</script></body></html>