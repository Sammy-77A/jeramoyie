<?php
require_once __DIR__ . '/includes/community-auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/validators.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/models/CommunitySavings.php';
require_once __DIR__ . '/models/CommunityLoan.php';

set_security_headers();
verify_session_fingerprint();

$customer_id = $_SESSION['community_customer_id'];
$savingsModel = new CommunitySavings($pdo);
$loanModel = new CommunityLoan($pdo);
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $loan_id = (int) ($_POST['loan_id'] ?? 0);
    $amount = validate_amount($_POST['repay_amount'] ?? 0, 1);

    if (!$amount)
        $error = 'Valid amount required.';
    elseif (!$loan_id)
        $error = 'Invalid loan.';
    else {
        $loan = $loanModel->findById($loan_id);
        if (!$loan || $loan['customer_id'] != $customer_id || $loan['status'] !== 'approved') {
            $error = 'Invalid loan.';
        } else {
            $outstanding = $loanModel->getOutstanding($loan);
            if ($amount > $outstanding) {
                $error = 'Amount exceeds outstanding balance of ' . community_format_currency($outstanding);
            } else {
                // Security checks
                $velocity = check_transaction_velocity($pdo, $customer_id, 'repayment', 3);
                if ($velocity) {
                    $error = $velocity;
                } elseif (has_pending_transaction($pdo, 'community_pending_repayments', $customer_id)) {
                    $error = 'You already have a pending repayment. Please wait.';
                } else {
                    // Insert pending repayment
                    $token = bin2hex(random_bytes(16));
                    $pdo->prepare("INSERT INTO community_pending_repayments (token, customer_id, loan_id, amount) VALUES (?,?,?,?)")
                        ->execute([$token, $customer_id, $loan_id, $amount]);

                    // Get phone
                    $custStmt = $pdo->prepare("SELECT phone_number FROM community_customers WHERE id = ?");
                    $custStmt->execute([$customer_id]);
                    $phone = $custStmt->fetchColumn();

                    // STK push
                    $stk = perform_stk_push($phone, $amount, $token, 'Loan Repayment');

                    if ($stk['success']) {
                        // Save the checkout request ID strictly needed for fallback querying
                        if (!empty($stk['checkout_request_id'])) {
                            $pdo->prepare("UPDATE community_pending_repayments SET checkout_request_id = ? WHERE token = ?")
                                ->execute([$stk['checkout_request_id'], $token]);
                        }
                        $_SESSION['community_repay_token'] = $token;
                        $_SESSION['community_repay_amount'] = $amount;
                        log_security_event($pdo, 'stk_repay_initiated', "Loan: $loan_id, Amount: $amount", $customer_id);
                        header('Location: repay_pending.php');
                        exit;
                    } else {
                        $pdo->prepare("DELETE FROM community_pending_repayments WHERE token = ?")->execute([$token]);
                        $error = $stk['error'];
                    }
                }
            }
        }
    }
    regenerate_csrf_token();
}

$savings_txns = $savingsModel->getTransactions($customer_id, null, 100);
$my_loans = $loanModel->getByCustomer($customer_id);
$all_repayments = [];
foreach ($my_loans as $loan) {
    foreach ($loanModel->getRepayments($loan['id']) as $r) {
        $r['loan_product'] = $loan['product_name'];
        $all_repayments[] = $r;
    }
}
usort($all_repayments, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
$active_loans = array_filter($my_loans, fn($l) => $l['status'] === 'approved');
$active_page = 'transactions';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions • Community Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a5928;
            --bg: #f3f4f6
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            padding-top: 75px
        }

        .glass-card {
            background: #fff;
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .05)
        }

        .btn-main {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600
        }

        .btn-main:hover {
            background: #145020;
            color: #fff
        }

        .nav-pills .nav-link.active {
            background: var(--primary)
        }

        .nav-pills .nav-link {
            color: var(--primary);
            font-weight: 500
        }

        .mpesa-badge {
            background: #fff8e1;
            color: #b45309;
            border: 1px solid #fcd34d;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: .75rem;
            display: inline-flex;
            align-items: center;
            gap: 6px
        }

        @media(max-width:768px) {
            h2 {
                font-size: 1.5rem !important
            }

            .glass-card {
                padding: 1rem !important;
                border-radius: 12px !important
            }

            .table {
                font-size: .8rem
            }

            .table th,
            .table td {
                padding: .4rem .5rem !important;
                white-space: nowrap
            }

            body {
                padding-top: 65px !important
            }
        }

        @media(max-width:576px) {
            h2 {
                font-size: 1.3rem !important
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <div class="container mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold mb-0"><i class="bi bi-receipt me-2"></i>Transactions</h2>
                <p class="text-muted small">All savings and loan activity</p>
            </div>
            <a href="download-statement.php" class="btn btn-outline-success rounded-pill px-3 btn-sm"><i
                    class="bi bi-file-pdf me-1"></i>PDF Statement</a>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 small py-2"><i
                    class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 small py-2"><i
                    class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if (!empty($active_loans)): ?>
            <div class="glass-card p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-cash-coin me-2"></i>Repay Loan via M-PESA</h5>
                <div class="mb-3"><span class="mpesa-badge"><i class="bi bi-phone-fill"></i>Payment via M-PESA STK
                        Push</span></div>
                <form method="POST">
                    <div class="row g-3"><?= csrf_input() ?>
                        <div class="col-md-5"><label class="form-label small fw-bold text-muted">Loan</label><select
                                name="loan_id" class="form-select" required><?php foreach ($active_loans as $al): ?>
                                    <option value="<?= $al['id'] ?>"><?= htmlspecialchars($al['product_name']) ?> —
                                        <?= community_format_currency($loanModel->getOutstanding($al)) ?> outstanding</option>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="col-md-4"><label class="form-label small fw-bold text-muted">Amount (KSh)</label><input
                                type="number" name="repay_amount" class="form-control" min="1" step="1" required></div>
                        <div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-main w-100"><i
                                    class="bi bi-phone me-1"></i>Pay via M-PESA</button></div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        <ul class="nav nav-pills mb-4" role="tablist">
            <li class="nav-item"><a class="nav-link active rounded-pill" data-bs-toggle="tab" href="#savings">Savings
                    (<?= count($savings_txns) ?>)</a></li>
            <li class="nav-item ms-2"><a class="nav-link rounded-pill" data-bs-toggle="tab"
                    href="#repayments">Repayments (<?= count($all_repayments) ?>)</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="savings">
                <?php if (empty($savings_txns)): ?>
                    <div class="glass-card p-4 text-center text-muted"><i
                            class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>No savings.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover bg-white rounded-4 overflow-hidden shadow-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Ref</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($savings_txns as $t): ?>
                                    <tr>
                                        <td class="small"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></td>
                                        <td class="small"><?= htmlspecialchars($t['product_name']) ?></td>
                                        <td><span
                                                class="badge rounded-pill <?= $t['transaction_type'] === 'deposit' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= ucfirst($t['transaction_type']) ?></span>
                                        </td>
                                        <td
                                            class="fw-bold <?= $t['transaction_type'] === 'deposit' ? 'text-success' : 'text-danger' ?>">
                                            <?= ($t['transaction_type'] === 'deposit' ? '+' : '-') . community_format_currency($t['amount']) ?>
                                        </td>
                                        <td class="small text-muted"><?= $t['reference_code'] ?></td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div><?php endif; ?>
            </div>
            <div class="tab-pane fade" id="repayments">
                <?php if (empty($all_repayments)): ?>
                    <div class="glass-card p-4 text-center text-muted"><i
                            class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>No repayments.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover bg-white rounded-4 overflow-hidden shadow-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Loan</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Ref</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_repayments as $r): ?>
                                    <tr>
                                        <td class="small"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></td>
                                        <td class="small"><?= htmlspecialchars($r['loan_product']) ?></td>
                                        <td class="fw-bold text-success">+<?= community_format_currency($r['amount']) ?></td>
                                        <td class="small"><?= ucfirst($r['payment_method']) ?></td>
                                        <td class="small text-muted"><?= $r['reference_code'] ?></td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div><?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>