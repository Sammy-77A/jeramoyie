<?php
require_once __DIR__ . "/../includes/admin-auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../models/CommunitySavings.php";
require_once __DIR__ . "/../models/CommunityLoan.php";
require_once __DIR__ . "/../models/CommunityCustomer.php";
$savingsModel = new CommunitySavings($pdo); $loanModel = new CommunityLoan($pdo); $customerModel = new CommunityCustomer($pdo);
$summary = $pdo->query("SELECT * FROM community_finance_summary")->fetch(PDO::FETCH_ASSOC);
$all_customers = $customerModel->getAll(); $all_loans = $loanModel->getAll(); $all_savings = $savingsModel->getAllTransactions(500);
if (isset($_GET["export"])) {
    require_once __DIR__ . '/../../dompdf/vendor/autoload.php';
    $type = $_GET["export"];
    $date = date('d M Y, H:i');
    $css = '<style>
        body{font-family:Arial,sans-serif;font-size:11px;color:#333;margin:20px}
        h1{color:#1a5928;font-size:20px;border-bottom:3px solid #1a5928;padding-bottom:8px;margin-bottom:5px}
        h2{color:#555;font-size:14px;margin:15px 0 8px}
        table{width:100%;border-collapse:collapse;margin:8px 0}
        th{background:#1a5928;color:#fff;padding:6px 8px;text-align:left;font-size:10px;text-transform:uppercase}
        td{padding:5px 8px;border-bottom:1px solid #eee;font-size:10px}
        tr:nth-child(even){background:#f8f8f8}
        .text-right{text-align:right}
        .summary{background:#f0fdf4;border:1px solid #bbf7d0;padding:12px;border-radius:6px;margin:10px 0 15px}
        .badge{padding:2px 8px;border-radius:10px;font-size:9px;font-weight:bold;color:#fff;display:inline-block}
        .badge-active{background:#22c55e}.badge-inactive{background:#ef4444}.badge-pending{background:#f59e0b;color:#333}
        .badge-approved{background:#22c55e}.badge-rejected{background:#ef4444}.badge-completed{background:#3b82f6}.badge-defaulted{background:#111}
        .footer{text-align:center;color:#999;font-size:9px;margin-top:25px;border-top:1px solid #ddd;padding-top:8px}
    </style>';

    $html = '<!DOCTYPE html><html><head>' . $css . '</head><body>';
    $html .= '<h1>Jera Moyie Community â€” ' . ucfirst($type) . ' Report</h1>';
    $html .= '<p style="color:#888;font-size:10px">Generated: ' . $date . ' &nbsp;|&nbsp; By: ' . htmlspecialchars($admin_name) . ' (' . htmlspecialchars($admin_role) . ')</p>';

    if ($type === "customers") {
        $active = 0; $inactive = 0;
        foreach ($all_customers as $c) { if ($c["status"] === "active") $active++; else $inactive++; }
        $html .= '<div class="summary"><strong>Total Customers:</strong> ' . count($all_customers) . ' &nbsp;|&nbsp; <strong>Active:</strong> ' . $active . ' &nbsp;|&nbsp; <strong>Inactive:</strong> ' . $inactive . '</div>';
        $html .= '<table><tr><th>#</th><th>Full Name</th><th>Phone</th><th>National ID</th><th>Email</th><th>Status</th><th>Joined</th></tr>';
        foreach ($all_customers as $i => $c) {
            $badge = $c["status"] === "active" ? "badge-active" : "badge-inactive";
            $html .= '<tr><td>' . ($i+1) . '</td><td><strong>' . htmlspecialchars($c["full_name"]) . '</strong></td><td>' . htmlspecialchars($c["phone_number"]) . '</td><td>' . htmlspecialchars($c["national_id"]) . '</td><td>' . htmlspecialchars($c["email"] ?? "â€”") . '</td><td><span class="badge ' . $badge . '">' . ucfirst($c["status"]) . '</span></td><td>' . date("d/m/Y", strtotime($c["created_at"])) . '</td></tr>';
        }
        $html .= '</table>';
    }
    elseif ($type === "loans") {
        $total_issued = 0; $total_repayable = 0;
        foreach ($all_loans as $l) { $total_issued += $l["amount"]; $total_repayable += $l["total_repayable"]; }
        $html .= '<div class="summary"><strong>Total Loans:</strong> ' . count($all_loans) . ' &nbsp;|&nbsp; <strong>Amount Issued:</strong> KSh ' . number_format($total_issued, 2) . ' &nbsp;|&nbsp; <strong>Total Repayable:</strong> KSh ' . number_format($total_repayable, 2) . '</div>';
        $html .= '<table><tr><th>#</th><th>Customer</th><th>Phone</th><th>Product</th><th class="text-right">Amount</th><th class="text-right">Interest</th><th class="text-right">Repayable</th><th>Status</th><th>Due Date</th><th>Applied</th></tr>';
        foreach ($all_loans as $i => $l) {
            $badge = "badge-" . $l["status"];
            $html .= '<tr><td>' . ($i+1) . '</td><td><strong>' . htmlspecialchars($l["customer_name"]) . '</strong></td><td>' . htmlspecialchars($l["phone_number"]) . '</td><td>' . htmlspecialchars($l["product_name"]) . '</td><td class="text-right">KSh ' . number_format($l["amount"], 2) . '</td><td class="text-right">KSh ' . number_format($l["interest_amount"], 2) . '</td><td class="text-right">KSh ' . number_format($l["total_repayable"], 2) . '</td><td><span class="badge ' . $badge . '">' . ucfirst($l["status"]) . '</span></td><td>' . ($l["due_date"] ? date("d/m/Y", strtotime($l["due_date"])) : "â€”") . '</td><td>' . date("d/m/Y", strtotime($l["created_at"])) . '</td></tr>';
        }
        $html .= '</table>';
    }
    elseif ($type === "savings") {
        $total_deposits = 0; $total_withdrawals = 0;
        foreach ($all_savings as $s) { if ($s["transaction_type"] === "deposit") $total_deposits += $s["amount"]; else $total_withdrawals += $s["amount"]; }
        $html .= '<div class="summary"><strong>Transactions:</strong> ' . count($all_savings) . ' &nbsp;|&nbsp; <strong>Total Deposits:</strong> KSh ' . number_format($total_deposits, 2) . ' &nbsp;|&nbsp; <strong>Total Withdrawals:</strong> KSh ' . number_format($total_withdrawals, 2) . ' &nbsp;|&nbsp; <strong>Net:</strong> KSh ' . number_format($total_deposits - $total_withdrawals, 2) . '</div>';
        $html .= '<table><tr><th>#</th><th>Customer</th><th>Product</th><th>Type</th><th class="text-right">Amount</th><th>Reference</th><th>Date</th></tr>';
        foreach ($all_savings as $i => $s) {
            $color = $s["transaction_type"] === "deposit" ? "color:#22c55e" : "color:#ef4444";
            $sign = $s["transaction_type"] === "deposit" ? "+" : "-";
            $html .= '<tr><td>' . ($i+1) . '</td><td><strong>' . htmlspecialchars($s["customer_name"]) . '</strong></td><td>' . htmlspecialchars($s["product_name"]) . '</td><td>' . ucfirst($s["transaction_type"]) . '</td><td class="text-right" style="' . $color . ';font-weight:bold">' . $sign . 'KSh ' . number_format($s["amount"], 2) . '</td><td>' . $s["reference_code"] . '</td><td>' . date("d/m/Y H:i", strtotime($s["created_at"])) . '</td></tr>';
        }
        $html .= '</table>';
    }

    $html .= '<div class="footer">Jera Moyie Community Portal â€” Confidential Report â€” ' . date('Y') . '</div></body></html>';

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream("community_{$type}_" . date("Ymd") . ".pdf", ["Attachment" => true]);
    exit;
}
$monthly_savings = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') as month, SUM(CASE WHEN transaction_type='deposit' THEN amount ELSE -amount END) as net FROM community_savings GROUP BY month ORDER BY month DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
$loans_by_product = $pdo->query("SELECT p.product_name, COUNT(*) as cnt, SUM(l.amount) as total FROM community_loans l JOIN community_products p ON l.product_id=p.id GROUP BY p.product_name ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
$status_breakdown = $pdo->query("SELECT status, COUNT(*) as cnt FROM community_loans GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
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
@media(max-width:768px){
    h2{font-size:1.5rem!important}h5{font-size:1.1rem!important}.container{padding-left:12px;padding-right:12px}
    .glass-card{padding:1rem!important;border-radius:12px!important}.stat-card{padding:1rem!important;border-radius:12px!important}
    .stat-card h3{font-size:1.3rem!important}.stat-card .icon{font-size:1.8rem!important}.table{font-size:.8rem}
    .table th,.table td{padding:.4rem .5rem!important;white-space:nowrap}.badge{font-size:.65rem!important;padding:3px 8px!important}
    .btn-main{padding:.6rem!important;font-size:.85rem}.d-flex.justify-content-between{flex-wrap:wrap;gap:.5rem}
    .admin-nav{gap:4px!important}.admin-nav a{padding:6px 10px!important;font-size:.75rem}
    .filter-pills a{padding:4px 10px!important;font-size:.7rem}body{padding-top:15px!important}
}
@media(max-width:576px){h2{font-size:1.3rem!important}.table-responsive{margin:0 -12px}.table-responsive table{margin:0}.stat-card h3{font-size:1.1rem!important}.stat-card small{font-size:.7rem}}
</style><title>Community Reports â€¢ Admin</title></head><body>
<div class="container mb-5">
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"><h2 class="fw-bold mb-0"><i class="bi bi-bar-chart me-2"></i>Reports</h2><div class="d-flex gap-2"><a href="community-dashboard.php" class="btn btn-outline-success rounded-pill px-4 btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a><a href="../../admin_dashboard.php" class="btn btn-outline-dark rounded-pill px-4 btn-sm"><i class="bi bi-chevron-left me-1"></i>Console</a><a href="../../profile.php" class="btn btn-outline-secondary rounded-pill px-4 btn-sm">Profile</a></div></div>
<div class="admin-nav mb-4 d-flex flex-wrap gap-2"><a href="community-dashboard.php">Dashboard</a><a href="community-customers.php">Customers</a><a href="community-products.php">Products</a><a href="community-loans.php">Loans</a><a href="community-reports.php" class="active">Reports</a></div>
<div class="glass-card p-4 mb-4"><h5 class="fw-bold mb-3"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export PDF Reports</h5>
<div class="d-flex flex-wrap gap-2"><a href="?export=customers" class="btn btn-outline-success rounded-pill px-4"><i class="bi bi-people me-1"></i>Customers Report</a>
<a href="?export=loans" class="btn btn-outline-danger rounded-pill px-4"><i class="bi bi-cash-stack me-1"></i>Loans Report</a>
<a href="?export=savings" class="btn btn-outline-primary rounded-pill px-4"><i class="bi bi-piggy-bank me-1"></i>Savings Report</a></div></div>
<div class="row g-4">
<div class="col-md-6"><div class="glass-card p-4 h-100"><h5 class="fw-bold mb-3"><i class="bi bi-wallet2 me-2 text-success"></i>Finance Summary</h5>
<table class="table table-borderless mb-0">
<tr><td class="text-muted">Total Savings</td><td class="fw-bold text-success text-end"><?=community_format_currency($summary["total_savings"])?></td></tr>
<tr><td class="text-muted">Total Loans Issued</td><td class="fw-bold text-danger text-end"><?=community_format_currency($summary["total_loans_issued"])?></td></tr>
<tr><td class="text-muted">Interest Earned</td><td class="fw-bold text-end" style="color:#f59e0b"><?=community_format_currency($summary["total_interest_earned"])?></td></tr>
<tr><td class="text-muted">Penalties</td><td class="fw-bold text-end" style="color:#6f42c1"><?=community_format_currency($summary["total_penalties_collected"])?></td></tr>
<tr><td class="text-muted">Active Loans</td><td class="fw-bold text-primary text-end"><?=$summary["active_loans_count"]?></td></tr>
<tr><td class="text-muted">Defaulted</td><td class="fw-bold text-dark text-end"><?=$summary["defaulted_loans_count"]?></td></tr></table></div></div>
<div class="col-md-6"><div class="glass-card p-4 h-100"><h5 class="fw-bold mb-3"><i class="bi bi-pie-chart me-2 text-primary"></i>Loan Status</h5>
<?php if(empty($status_breakdown)): ?><p class="text-muted small">No data.</p>
<?php else: $colors=["pending"=>"#f59e0b","approved"=>"#22c55e","rejected"=>"#ef4444","completed"=>"#3b82f6","defaulted"=>"#111827"]; $total_cnt=array_sum($status_breakdown); ?>
<?php foreach($status_breakdown as $st=>$cnt): ?><div class="mb-3"><div class="d-flex justify-content-between small mb-1"><span class="fw-bold"><?=ucfirst($st)?></span><span><?=$cnt?> (<?=$total_cnt>0?round($cnt/$total_cnt*100):0?>%)</span></div>
<div class="progress" style="height:8px;border-radius:4px"><div class="progress-bar" style="width:<?=$total_cnt>0?($cnt/$total_cnt*100):0?>%;background:<?=$colors[$st]??"#6b7280"?>"></div></div></div><?php endforeach; endif; ?></div></div>
<div class="col-md-6"><div class="glass-card p-4 h-100"><h5 class="fw-bold mb-3"><i class="bi bi-graph-up me-2 text-success"></i>Monthly Net Savings</h5>
<?php if(empty($monthly_savings)): ?><p class="text-muted small">No data.</p>
<?php else: ?><table class="table table-sm small"><thead class="table-light"><tr><th>Month</th><th class="text-end">Net</th></tr></thead><tbody>
<?php foreach($monthly_savings as $m): ?><tr><td><?=$m["month"]?></td><td class="text-end fw-bold <?=$m["net"]>=0?"text-success":"text-danger"?>"><?=community_format_currency($m["net"])?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div></div>
<div class="col-md-6"><div class="glass-card p-4 h-100"><h5 class="fw-bold mb-3"><i class="bi bi-box-seam me-2 text-danger"></i>Loans by Product</h5>
<?php if(empty($loans_by_product)): ?><p class="text-muted small">No data.</p>
<?php else: ?><table class="table table-sm small"><thead class="table-light"><tr><th>Product</th><th class="text-center">Count</th><th class="text-end">Total</th></tr></thead><tbody>
<?php foreach($loans_by_product as $lp): ?><tr><td class="fw-bold"><?=htmlspecialchars($lp["product_name"])?></td><td class="text-center"><?=$lp["cnt"]?></td><td class="text-end"><?=community_format_currency($lp["total"])?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div></div>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>