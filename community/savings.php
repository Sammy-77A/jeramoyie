<?php
require_once __DIR__ . '/includes/community-auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/validators.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/models/CommunitySavings.php';
require_once __DIR__ . '/models/CommunityProduct.php';

set_security_headers();
verify_session_fingerprint();

$customer_id = $_SESSION['community_customer_id'];
$savingsModel = new CommunitySavings($pdo);
$productModel = new CommunityProduct($pdo);
$saving_products = $productModel->getSavingProducts();
if (empty($saving_products))
    $saving_products = $productModel->getActive();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $product_id = (int) ($_POST['product_id'] ?? 0);
    $amount = validate_amount($_POST['amount'] ?? 0, 1);
    $rawPhone = trim($_POST['phone'] ?? ($_SESSION['community_customer_phone'] ?? ''));

    if (!$amount)
        $error = 'Please enter a valid amount (minimum KSh 1).';
    elseif (!$product_id)
        $error = 'Please select a product.';
    else {
        $product = $productModel->findById($product_id);
        if (!$product || !$product['is_active'])
            $error = 'Invalid product.';
        elseif ($action === 'deposit') {
            // Security checks
            $velocity = check_transaction_velocity($pdo, $customer_id, 'deposit', 5);
            if ($velocity) {
                $error = $velocity;
            } elseif (has_pending_transaction($pdo, 'community_pending_deposits', $customer_id)) {
                $error = 'You already have a pending deposit. Please wait for it to complete.';
            } else {
                // Insert pending deposit
                $token = bin2hex(random_bytes(16));
                $pdo->prepare("INSERT INTO community_pending_deposits (token, customer_id, product_id, amount) VALUES (?,?,?,?)")
                    ->execute([$token, $customer_id, $product_id, $amount]);

                // Get phone from customer record
                $custStmt = $pdo->prepare("SELECT phone_number FROM community_customers WHERE id = ?");
                $custStmt->execute([$customer_id]);
                $phone = $custStmt->fetchColumn();

                // Perform STK push
                $stk = perform_stk_push($phone, $amount, $token, 'Savings Deposit');

                if ($stk['success']) {
                    // Save checkout ID for polling fallback
                    if (!empty($stk['checkout_request_id'])) {
                        $pdo->prepare("UPDATE community_pending_deposits SET checkout_request_id = ? WHERE token = ?")
                            ->execute([$stk['checkout_request_id'], $token]);
                    }
                    $_SESSION['community_deposit_token'] = $token;
                    $_SESSION['community_deposit_amount'] = $amount;
                    log_security_event($pdo, 'stk_deposit_initiated', "Amount: $amount, Token: $token", $customer_id);
                    header('Location: deposit_pending.php');
                    exit;
                } else {
                    $pdo->prepare("DELETE FROM community_pending_deposits WHERE token = ?")->execute([$token]);
                    $error = $stk['error'];
                }
            }
        } elseif ($action === 'withdraw') {
            try {
                $ref = generate_reference_code('WDR');
                $savingsModel->withdraw($customer_id, $product_id, $amount, $ref);
                $success = "Withdrawal request of " . community_format_currency($amount) . " submitted! Ref: $ref";
                log_community_audit($pdo, 'customer', $customer_id, 'Withdrawal', "Amount: $amount, Ref: $ref");
                log_security_event($pdo, 'withdrawal', "Amount: $amount, Ref: $ref", $customer_id);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
    regenerate_csrf_token();
}

$total_balance = $savingsModel->getBalance($customer_id);
$product_balances = [];
foreach ($saving_products as $sp) {
    $product_balances[$sp['id']] = ['name' => $sp['product_name'], 'balance' => $savingsModel->getBalance($customer_id, $sp['id'])];
}
$transactions = $savingsModel->getTransactions($customer_id, null, 50);
$active_page = 'savings';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings • Community Portal</title>
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
                <h2 class="fw-bold mb-0"><i class="bi bi-piggy-bank me-2"></i>My Savings</h2>
                <p class="text-muted small">Manage deposits and withdrawals</p>
            </div>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 small py-2"><i
                    class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 small py-2"><i
                    class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="glass-card p-4 mb-4 text-center" style="border-left:5px solid var(--primary)"><small
                class="text-muted text-uppercase fw-bold">Total Savings Balance</small>
            <h1 class="fw-bold text-success mt-2"><?= community_format_currency($total_balance) ?></h1>
        </div>
        <?php if (count($product_balances) > 1): ?>
            <div class="row g-3 mb-4"><?php foreach ($product_balances as $pid => $pb): ?>
                    <div class="col-md-4">
                        <div class="glass-card p-3 text-center"><small
                                class="text-muted"><?= htmlspecialchars($pb['name']) ?></small>
                            <h5 class="fw-bold text-dark mt-1"><?= community_format_currency($pb['balance']) ?></h5>
                        </div>
                    </div><?php endforeach; ?>
            </div><?php endif; ?>
        <div class="glass-card p-4 mb-4">
            <ul class="nav nav-pills mb-4" role="tablist">
                <li class="nav-item"><a class="nav-link active rounded-pill" data-bs-toggle="tab" href="#deposit"><i
                            class="bi bi-plus-circle me-1"></i>Deposit via M-PESA</a></li>
                <li class="nav-item ms-2"><a class="nav-link rounded-pill" data-bs-toggle="tab" href="#withdraw"><i
                            class="bi bi-dash-circle me-1"></i>Withdraw</a></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="deposit">
                    <div class="mb-3"><span class="mpesa-badge"><i class="bi bi-phone-fill"></i>Payment via M-PESA STK
                            Push</span></div>
                    <form method="POST"><?= csrf_input() ?><input type="hidden" name="action" value="deposit">
                        <div class="mb-3"><label class="form-label small fw-bold text-muted">Product</label><select
                                name="product_id" class="form-select"
                                required><?php foreach ($saving_products as $sp): ?>
                                    <option value="<?= $sp['id'] ?>"><?= htmlspecialchars($sp['product_name']) ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="mb-3"><label class="form-label small fw-bold text-muted">Amount (KSh)</label><input
                                type="number" name="amount" class="form-control" min="1" step="1" required></div>
                        <button type="submit" class="btn btn-main px-4"><i class="bi bi-phone me-2"></i>Deposit via
                            M-PESA</button>
                    </form>
                </div>
                <div class="tab-pane fade" id="withdraw">
                    <form method="POST"><?= csrf_input() ?><input type="hidden" name="action" value="withdraw">
                        <div class="mb-3"><label class="form-label small fw-bold text-muted">Product</label><select
                                name="product_id" class="form-select"
                                required><?php foreach ($saving_products as $sp): ?>
                                    <option value="<?= $sp['id'] ?>"><?= htmlspecialchars($sp['product_name']) ?> (Bal:
                                        <?= community_format_currency($savingsModel->getBalance($customer_id, $sp['id'])) ?>)
                                    </option><?php endforeach; ?>
                            </select></div>
                        <div class="mb-3"><label class="form-label small fw-bold text-muted">Amount (KSh)</label><input
                                type="number" name="amount" class="form-control" min="1" step="0.01" required></div>
                        <button type="submit" class="btn btn-outline-danger rounded-3 px-4"><i
                                class="bi bi-dash-circle me-2"></i>Request Withdrawal</button>
                    </form>
                </div>
            </div>
        </div>
        <h5 class="fw-bold mb-3">Transaction History</h5>
        <?php if (empty($transactions)): ?>
            <div class="glass-card p-4 text-center text-muted"><i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>No
                transactions.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover bg-white rounded-4 overflow-hidden shadow-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td class="small"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></td>
                                <td class="small"><?= htmlspecialchars($t['product_name']) ?></td>
                                <td><span
                                        class="badge rounded-pill <?= $t['transaction_type'] === 'deposit' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= ucfirst($t['transaction_type']) ?></span>
                                </td>
                                <td class="fw-bold <?= $t['transaction_type'] === 'deposit' ? 'text-success' : 'text-danger' ?>">
                                    <?= ($t['transaction_type'] === 'deposit' ? '+' : '-') . community_format_currency($t['amount']) ?>
                                </td>
                                <td class="small text-muted"><?= $t['reference_code'] ?></td>
                            </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div><?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>