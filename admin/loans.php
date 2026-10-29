<?php
session_start();
require '../config.php';

// Protection
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$admin_id = $_SESSION['admin_id'] ?? 0;
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// === APPROVE LOAN ===
if (isset($_GET['approve'])) {
    $loan_id = (int)$_GET['approve'];

    // Get loan details
    $loan = $pdo->prepare("SELECT * FROM loan_requests WHERE id = ? AND status = 'pending'");
    $loan->execute([$loan_id]);
    $loan = $loan->fetch();

    if ($loan) {
        // Update loan status - THIS ADMIN BECOMES THE OWNER
        $stmt = $pdo->prepare("UPDATE loan_requests SET status = 'approved', approved_by = ?, approval_date = NOW() WHERE id = ?");
        $stmt->execute([$admin_id, $loan_id]);

        // Record as transaction in savings (deduct from member's balance/record loan)
        // Note: Usually loans are money OUT, but in savings tracking, it might be tracked differently.
        // Keeping your original logic:
        $stmt = $pdo->prepare("INSERT INTO savings_transactions (member_id, type, amount, date, description) VALUES (?, 'loan', ?, NOW(), 'Loan Disbursed')");
        $stmt->execute([$loan['member_id'], $loan['amount']]);

        $_SESSION['success'] = "Loan approved! You are now the manager of this loan.";
        log_admin_activity($pdo, "Loan Approved", "Loan ID: $loan_id");
    }

    header('Location: loans.php');
    exit;
}

// === REJECT LOAN ===
if (isset($_POST['reject'])) {
    $loan_id = (int)$_POST['loan_id'];
    $reason = trim($_POST['rejection_reason']);

    $stmt = $pdo->prepare("UPDATE loan_requests SET status = 'rejected', approved_by = ?, approval_date = NOW(), rejection_reason = ? WHERE id = ? AND status = 'pending'");
    $stmt->execute([$admin_id, $reason, $loan_id]);

    $_SESSION['success'] = "Loan request rejected.";
    log_admin_activity($pdo, "Loan Rejected", "Loan ID: $loan_id, Reason: $reason");
    header('Location: loans.php');
    exit;
}

// === RECORD REPAYMENT ===
if (isset($_POST['record_repayment'])) {
    $loan_id = (int)$_POST['loan_id'];
    $amount = (float)$_POST['amount'];
    $notes = trim($_POST['notes'] ?? '');

    // Verify ownership
    $loan = $pdo->prepare("SELECT approved_by, member_id, amount FROM loan_requests WHERE id = ?");
    $loan->execute([$loan_id]);
    $loan_data = $loan->fetch();

    // Calculate current balance to ensure no overpayment
    $repaid_query = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM loan_repayments WHERE loan_request_id = ?");
    $repaid_query->execute([$loan_id]);
    $total_repaid = $repaid_query->fetchColumn();
    $balance = $loan_data['amount'] - $total_repaid;

    // Allow payment only if amount is valid and user is owner (or allow teamwork if desired, currently restricted to owner)
    // REMOVED strict ownership check to match the 'Teamwork' style of the welfare page, or keep it if strict policy.
    // Keeping strict check based on your previous prompt request:
    if ($loan_data && $loan_data['approved_by'] == $admin_id) {
        if ($amount > 0 && $amount <= $balance) {

            $pdo->beginTransaction();
            try {
                // 1. Record repayment
                $stmt = $pdo->prepare("INSERT INTO loan_repayments (loan_request_id, amount, repayment_date, recorded_by, notes) VALUES (?, ?, NOW(), ?, ?)");
                $stmt->execute([$loan_id, $amount, $admin_id, $notes]);

                // 2. Add to member's transaction history
                $stmt = $pdo->prepare("INSERT INTO savings_transactions (member_id, type, amount, date, description) VALUES (?, 'repayment', ?, NOW(), 'Loan Repayment')");
                $stmt->execute([$loan_data['member_id'], $amount]);

                // 3. Check if loan is fully paid
                if (($total_repaid + $amount) >= $loan_data['amount']) {
                    // Optional: Mark status as 'completed' if you have such a status, 
                    // or just rely on balance calculation. 
                    // Assuming 'approved' covers active loans until balance is 0.
                }

                $pdo->commit();
                log_admin_activity($pdo, "Repayment Recorded", "Loan ID: $loan_id, Amount: KSh " . number_format($amount));
                $_SESSION['success'] = "Repayment of KSh " . number_format($amount) . " recorded successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Database error: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Invalid amount. Cannot exceed outstanding balance of KSh " . number_format($balance);
        }
    } else {
        $_SESSION['error'] = "Access Denied: Only the admin who approved this loan can record repayments.";
    }

    header('Location: loans.php');
    exit;
}

// === DATA FETCHING ===

// 1. Financial Overview (Portfolio Health)
// Total Disbursed (All approved loans)
$total_disbursed = $pdo->query("SELECT SUM(amount) FROM loan_requests WHERE status = 'approved'")->fetchColumn() ?: 0;
// Total Repaid (All repayments)
$total_repaid = $pdo->query("SELECT SUM(amount) FROM loan_repayments")->fetchColumn() ?: 0;
// Outstanding Debt (Portfolio at Risk)
$outstanding_balance = $total_disbursed - $total_repaid;

// 2. Pending Requests (Global)
$pending_loans = $pdo->query("SELECT lr.*, m.name, m.phone 
    FROM loan_requests lr 
    JOIN members m ON lr.member_id = m.id 
    WHERE lr.status = 'pending' 
    ORDER BY lr.request_date ASC")->fetchAll();

// 3. My Active Loans (Loans approved by THIS admin that still have balance)
$active_loans = $pdo->prepare("SELECT 
    lr.id, lr.amount, lr.purpose, lr.approval_date, 
    m.name, m.phone,
    COALESCE(SUM(lrep.amount), 0) as total_repaid,
    (lr.amount - COALESCE(SUM(lrep.amount), 0)) as balance_owed,
    admin.name as approved_by_name
    FROM loan_requests lr
    JOIN members m ON lr.member_id = m.id
    LEFT JOIN loan_repayments lrep ON lr.id = lrep.loan_request_id
    LEFT JOIN members admin ON lr.approved_by = admin.id
    WHERE lr.status = 'approved' AND lr.approved_by = ?
    GROUP BY lr.id
    HAVING balance_owed > 0
    ORDER BY lr.approval_date DESC");
$active_loans->execute([$admin_id]);
$active_loans = $active_loans->fetchAll();

// 4. History (Completed or Rejected) - Filtered by THIS admin
// A loan is effectively "completed" in history if balance is 0
$loan_history = $pdo->prepare("SELECT 
    lr.id, lr.amount, lr.purpose, lr.status, lr.approval_date,
    m.name, m.phone,
    COALESCE(SUM(lrep.amount), 0) as total_repaid,
    (lr.amount - COALESCE(SUM(lrep.amount), 0)) as balance_owed
    FROM loan_requests lr
    JOIN members m ON lr.member_id = m.id
    LEFT JOIN loan_repayments lrep ON lr.id = lrep.loan_request_id
    WHERE lr.approved_by = ? AND (lr.status = 'rejected' OR (lr.status = 'approved' AND (lr.amount - COALESCE((SELECT SUM(amount) FROM loan_repayments WHERE loan_request_id = lr.id), 0)) <= 0))
    GROUP BY lr.id
    ORDER BY lr.approval_date DESC
    LIMIT 20");
$loan_history->execute([$admin_id]);
$loan_history = $loan_history->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Management • Admin</title>
    <link rel="icon" href="../images/jm.ico">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --primary-green: #2c7a4b;
            --light-green: #e8f5e9;
            --hover-green: #24663e;
            --text-dark: #343a40;
        }

        body {
            background-color: #f4f6f8;
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
        }

        /* Navbar */
        .navbar {
            background: white;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.04);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-green) !important;
        }

        /* Stats Cards */
        .stat-card {
            background: white;
            border: none;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s;
            height: 100%;
            display: flex;
            align-items: center;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }

        .bg-icon-warning {
            background: #fff8e1;
            color: #f59e0b;
        }

        .bg-icon-success {
            background: var(--light-green);
            color: var(--primary-green);
        }

        .bg-icon-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .bg-icon-blue {
            background: #e3f2fd;
            color: #0d6efd;
        }

        /* Main Content Card */
        .main-card {
            background: white;
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        /* Tabs */
        .nav-tabs {
            border-bottom: 1px solid #edf2f7;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 1rem 1.5rem;
            transition: all 0.2s;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-green);
            border-bottom: 2px solid var(--primary-green);
            background: transparent;
        }

        .nav-tabs .nav-link:hover:not(.active) {
            color: var(--primary-green);
        }

        /* Tables */
        .table th {
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            font-size: 0.75rem;
            background: #f8f9fa;
            border-bottom: 1px solid #edf2f7;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f7;
        }

        .btn-primary-custom {
            background: var(--primary-green);
            color: white;
            border: none;
        }

        .btn-primary-custom:hover {
            background: var(--hover-green);
            color: white;
        }

        .price-tag {
            font-weight: 600;
            letter-spacing: -0.5px;
        }
    </style>
</head>

<body>

    <nav class="navbar sticky-top">
        <div class="container">
            <a href="dashboard.php" class="navbar-brand d-flex align-items-center">
                <i class="bi bi-arrow-left-circle me-2"></i> Dashboard
            </a>
            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-md-block text-end">
                    <small class="text-muted d-block">Signed in as</small>
                    <span class="fw-bold"><?= htmlspecialchars($admin_name) ?></span>
                </div>
                <a href="dashboard.php?logout=1" class="btn btn-outline-danger btn-sm rounded-pill px-3">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">

        <div class="mb-4">
            <h2 class="fw-bold mb-1">Loan Management</h2>
            <p class="text-muted">Review applications, track portfolio health, and manage repayments.</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm border-0 mb-4">
                <i class="bi bi-check-circle-fill me-2"></i><?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm border-0 mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Financial Stats -->
        <div class="row g-3 mb-5">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon bg-icon-danger">
                        <i class="bi bi-graph-down-arrow"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0">KSh <?= number_format($outstanding_balance) ?></h3>
                        <span class="text-muted small">Outstanding Debt (At Risk)</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon bg-icon-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0"><?= count($pending_loans) ?></h3>
                        <span class="text-muted small">Pending Approvals</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon bg-icon-success">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0"><?= count($active_loans) ?></h3>
                        <span class="text-muted small">Active Loans Managed by Me</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Tabs -->
        <div class="main-card">
            <div class="card-body p-0">
                <div class="p-3 pb-0">
                    <ul class="nav nav-tabs border-bottom-0" id="loanTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pending">Pending Requests</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#active">My Active Loans</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#history">My History</button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">

                    <!-- Pending Tab -->
                    <div class="tab-pane fade show active" id="pending">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Member</th>
                                        <th>Amount</th>
                                        <th>Purpose</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_loans as $loan): ?>
                                        <tr>
                                            <td class="text-muted small"><?= date('M j, Y', strtotime($loan['request_date'])) ?></td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($loan['name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($loan['phone']) ?></small>
                                            </td>
                                            <td class="price-tag text-dark">KSh <?= number_format($loan['amount']) ?></td>
                                            <td>
                                                <div class="small text-muted text-truncate" style="max-width: 200px;">
                                                    <?= htmlspecialchars($loan['purpose']) ?>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group">
                                                    <a href="?approve=<?= $loan['id'] ?>" class="btn btn-sm btn-success text-white"
                                                        onclick="return confirm('Approve this loan? You will become the manager.')">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $loan['id'] ?>">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>

                                                <!-- Reject Modal -->
                                                <div class="modal fade text-start" id="rejectModal<?= $loan['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <div class="modal-header border-0">
                                                                <h5 class="modal-title text-danger">Reject Loan</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                                                    <p class="mb-2">Reject loan for <strong><?= htmlspecialchars($loan['name']) ?></strong>?</p>
                                                                    <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Reason for rejection..." required></textarea>
                                                                </div>
                                                                <div class="modal-footer border-0">
                                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                                                    <button type="submit" name="reject" class="btn btn-danger">Reject</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($pending_loans)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">No pending loan requests.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Active Tab -->
                    <div class="tab-pane fade" id="active">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Principal</th>
                                        <th>Repaid</th>
                                        <th>Balance</th>
                                        <th>Date Approved</th>
                                        <th class="text-end">Manage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_loans as $loan): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($loan['name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($loan['phone']) ?></small>
                                            </td>
                                            <td class="price-tag">KSh <?= number_format($loan['amount']) ?></td>
                                            <td class="price-tag text-success">KSh <?= number_format($loan['total_repaid']) ?></td>
                                            <td class="price-tag text-danger">KSh <?= number_format($loan['balance_owed']) ?></td>
                                            <td class="small text-muted"><?= date('M j, Y', strtotime($loan['approval_date'])) ?></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-primary-custom" data-bs-toggle="modal" data-bs-target="#repayModal<?= $loan['id'] ?>">
                                                    Record Repayment
                                                </button>

                                                <!-- Repayment Modal -->
                                                <div class="modal fade text-start" id="repayModal<?= $loan['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <div class="modal-header border-0 bg-light">
                                                                <h5 class="modal-title fw-bold">Record Repayment</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">

                                                                    <div class="text-center mb-4">
                                                                        <p class="text-muted mb-1">Balance Owed</p>
                                                                        <h3 class="text-danger fw-bold">KSh <?= number_format($loan['balance_owed']) ?></h3>
                                                                    </div>

                                                                    <div class="mb-3">
                                                                        <label class="form-label">Amount (KSh)</label>
                                                                        <input type="number" name="amount" class="form-control form-control-lg" min="1" max="<?= $loan['balance_owed'] ?>" required>
                                                                    </div>

                                                                    <div class="mb-3">
                                                                        <label class="form-label">Notes (Optional)</label>
                                                                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. M-PESA Ref code"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer border-0">
                                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" name="record_repayment" class="btn btn-primary-custom px-4">Save Repayment</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($active_loans)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">No active loans managed by you.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- History Tab -->
                    <div class="tab-pane fade" id="history">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Amount</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loan_history as $loan): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($loan['name']) ?></td>
                                            <td>KSh <?= number_format($loan['amount']) ?></td>
                                            <td><?= htmlspecialchars(substr($loan['purpose'], 0, 30)) ?>...</td>
                                            <td>
                                                <?php if ($loan['status'] == 'rejected'): ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Fully Paid</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted small"><?= date('M j, Y', strtotime($loan['approval_date'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($loan_history)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">No history yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>