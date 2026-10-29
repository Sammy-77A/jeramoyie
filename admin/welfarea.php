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

// === APPROVE WELFARE ===
if (isset($_GET['approve'])) {
    $request_id = (int)$_GET['approve'];

    $req = $pdo->prepare("SELECT member_id FROM welfare_requests WHERE id = ? AND status = 'pending'");
    $req->execute([$request_id]);
    $reqRow = $req->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("UPDATE welfare_requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'");
    $stmt->execute([$admin_id, $request_id]);

    if ($reqRow && !empty($reqRow['member_id'])) {
        try {
            require_once __DIR__ . '/../includes/NotificationService.php';
            NotificationService::notify((int)$reqRow['member_id'], "Welfare Request Approved", "Your welfare request has been approved. You can now receive the payout as per group process.", 'approval');
        } catch (Exception $e) {
            error_log("welfarea approve notification: " . $e->getMessage());
        }
    }

    $_SESSION['success'] = "Welfare request approved! You can now record payouts.";
    log_admin_activity($pdo, "Welfare Approved", "Request ID: $request_id");
    header('Location: welfare.php');
    exit;
}

// === REJECT WELFARE ===
if (isset($_POST['reject'])) {
    $request_id = (int)$_POST['request_id'];
    $reason = trim($_POST['rejection_reason']);

    $req = $pdo->prepare("SELECT member_id FROM welfare_requests WHERE id = ? AND status = 'pending'");
    $req->execute([$request_id]);
    $reqRow = $req->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("UPDATE welfare_requests SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ? AND status = 'pending'");
    $stmt->execute([$admin_id, $reason, $request_id]);

    if ($reqRow && !empty($reqRow['member_id'])) {
        try {
            require_once __DIR__ . '/../includes/NotificationService.php';
            $msg = "Your welfare request was not approved.";
            if ($reason) $msg .= " Reason: " . $reason;
            NotificationService::notify((int)$reqRow['member_id'], "Welfare Request Not Approved", $msg, 'approval');
        } catch (Exception $e) {
            error_log("welfarea reject notification: " . $e->getMessage());
        }
    }

    $_SESSION['success'] = "Welfare request rejected.";
    log_admin_activity($pdo, "Welfare Rejected", "Request ID: $request_id, Reason: $reason");
    header('Location: welfare.php');
    exit;
}

// === RECORD PAYOUT ===
if (isset($_POST['record_payout'])) {
    $request_id = (int)$_POST['request_id'];
    $amount_paid = (float)$_POST['amount_paid'];
    $notes = trim($_POST['notes'] ?? '');

    // Fetch current state
    $welfare = $pdo->prepare("SELECT member_id, amount_requested, payout_amount FROM welfare_requests WHERE id = ?");
    $welfare->execute([$request_id]);
    $welfare_data = $welfare->fetch();

    if ($welfare_data) {
        $new_total = ($welfare_data['payout_amount'] ?? 0) + $amount_paid;

        // Update payout amount
        $stmt = $pdo->prepare("UPDATE welfare_requests SET payout_amount = ?, payout_date = NOW(), payout_notes = ? WHERE id = ?");
        $stmt->execute([$new_total, $notes, $request_id]);

        // Check if fully paid to mark as completed
        if ($new_total >= $welfare_data['amount_requested']) {
            $stmt = $pdo->prepare("UPDATE welfare_requests SET status = 'completed' WHERE id = ?");
            $stmt->execute([$request_id]);
        }

        $_SESSION['success'] = "Payout of KSh " . number_format($amount_paid) . " recorded successfully!";
        log_admin_activity($pdo, "Welfare Payout", "Request ID: $request_id, Amount: KSh " . number_format($amount_paid));
    }

    header('Location: welfare.php');
    exit;
}

// === DATA FETCHING ===

// 1. Financial Overview (FIXED: Pulling from welfare_contributions instead of savings_transactions)
// Total Contributions (Money In)
$total_contributions = $pdo->query("SELECT SUM(amount) FROM welfare_contributions")->fetchColumn() ?: 0;
// Total Payouts (Money Out)
$total_payouts = $pdo->query("SELECT SUM(payout_amount) FROM welfare_requests WHERE status IN ('approved', 'completed')")->fetchColumn() ?: 0;
// Current Fund Balance
$fund_balance = $total_contributions - $total_payouts;

// 2. Pending Requests
$pending_requests = $pdo->query("SELECT wr.*, m.name, m.phone 
    FROM welfare_requests wr 
    JOIN members m ON wr.member_id = m.id 
    WHERE wr.status = 'pending' 
    ORDER BY wr.date ASC")->fetchAll();

// 3. Active Approved Requests (Not yet completed)
$active_requests = $pdo->prepare("SELECT 
    wr.id, wr.reason, wr.amount_requested, wr.description, wr.date, wr.approved_at,
    wr.payout_amount, wr.payout_date, wr.payout_notes,
    (wr.amount_requested - COALESCE(wr.payout_amount, 0)) as balance_remaining,
    m.name, m.phone,
    admin.name as approved_by_name
    FROM welfare_requests wr
    JOIN members m ON wr.member_id = m.id
    LEFT JOIN members admin ON wr.approved_by = admin.id
    WHERE wr.status = 'approved'
    ORDER BY wr.approved_at DESC");
$active_requests->execute();
$active_requests = $active_requests->fetchAll();

// 4. History (Completed/Rejected)
$welfare_history = $pdo->prepare("SELECT 
    wr.*, m.name, m.phone, admin.name as approved_by_name
    FROM welfare_requests wr
    JOIN members m ON wr.member_id = m.id
    LEFT JOIN members admin ON wr.approved_by = admin.id
    WHERE wr.status IN ('completed', 'rejected')
    ORDER BY wr.approved_at DESC
    LIMIT 20");
$welfare_history->execute();
$welfare_history = $welfare_history->fetchAll();

// 5. Welfare Contributions Log (FIXED: Pulling from welfare_contributions table)
$contribution_records = $pdo->query("SELECT 
    wc.*, m.name, m.phone 
    FROM welfare_contributions wc 
    JOIN members m ON wc.member_id = m.id 
    ORDER BY wc.date DESC, wc.id DESC 
    LIMIT 50")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welfare Management • Admin</title>
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
            <h2 class="fw-bold mb-1">Welfare Management</h2>
            <p class="text-muted">Track fund balance, review requests, and record payouts.</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm border-0 mb-4">
                <i class="bi bi-check-circle-fill me-2"></i><?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="row g-3 mb-5">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon bg-icon-blue">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0">KSh <?= number_format($fund_balance) ?></h3>
                        <span class="text-muted small">Available Fund Balance</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon bg-icon-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0"><?= count($pending_requests) ?></h3>
                        <span class="text-muted small">Pending Approvals</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon bg-icon-success">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0"><?= count($active_requests) ?></h3>
                        <span class="text-muted small">Active Payout Cases</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="main-card">
            <div class="card-body p-0">
                <div class="p-3 pb-0">
                    <ul class="nav nav-tabs border-bottom-0" id="welfareTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pending">Requests</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#active">Active Payouts</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#contributions">Contributions</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#history">History</button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pending">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Member</th>
                                        <th>Reason</th>
                                        <th>Amount</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $req): ?>
                                        <tr>
                                            <td class="text-muted small"><?= date('M j, Y', strtotime($req['date'])) ?></td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($req['name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($req['phone']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark mb-1"><?= htmlspecialchars($req['reason']) ?></span>
                                                <div class="small text-muted text-truncate" style="max-width: 200px;">
                                                    <?= htmlspecialchars($req['description']) ?>
                                                </div>
                                            </td>
                                            <td class="price-tag">KSh <?= number_format($req['amount_requested']) ?></td>
                                            <td class="text-end">
                                                <div class="btn-group">
                                                    <a href="?approve=<?= $req['id'] ?>" class="btn btn-sm btn-success text-white"
                                                        onclick="return confirm('Approve this request?')">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $req['id'] ?>">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>

                                                <div class="modal fade text-start" id="rejectModal<?= $req['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <div class="modal-header border-0">
                                                                <h5 class="modal-title text-danger">Reject Request</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                                    <p class="mb-2">Why are you rejecting <strong><?= htmlspecialchars($req['name']) ?></strong>?</p>
                                                                    <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
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
                                    <?php if (empty($pending_requests)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">No pending requests.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="active">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Approved Date</th>
                                        <th>Total</th>
                                        <th>Paid Out</th>
                                        <th>Balance</th>
                                        <th class="text-end">Manage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_requests as $req): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($req['name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($req['reason']) ?></small>
                                            </td>
                                            <td class="small text-muted"><?= date('M j, Y', strtotime($req['approved_at'])) ?></td>
                                            <td class="price-tag">KSh <?= number_format($req['amount_requested']) ?></td>
                                            <td class="price-tag text-success">KSh <?= number_format($req['payout_amount'] ?? 0) ?></td>
                                            <td class="price-tag text-danger">KSh <?= number_format($req['balance_remaining']) ?></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-primary-custom" data-bs-toggle="modal" data-bs-target="#payoutModal<?= $req['id'] ?>">
                                                    Record Payout
                                                </button>

                                                <div class="modal fade text-start" id="payoutModal<?= $req['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <div class="modal-header border-0 bg-light">
                                                                <h5 class="modal-title">Record Payout</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                                    <div class="text-center mb-3">
                                                                        <small class="text-muted">Remaining Balance</small>
                                                                        <h3 class="text-danger fw-bold">KSh <?= number_format($req['balance_remaining']) ?></h3>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Amount Paid Now</label>
                                                                        <input type="number" name="amount_paid" class="form-control" max="<?= $req['balance_remaining'] ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Notes (M-PESA Code)</label>
                                                                        <input type="text" name="notes" class="form-control">
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer border-0">
                                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" name="record_payout" class="btn btn-success">Save Payout</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($active_requests)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">No active cases.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="contributions">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Member</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contribution_records as $contrib): ?>
                                        <tr>
                                            <td class="text-muted small"><?= date('M j, Y', strtotime($contrib['date'])) ?></td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($contrib['name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($contrib['phone']) ?></small>
                                            </td>
                                            <td class="price-tag text-success text-end">+ KSh <?= number_format($contrib['amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($contribution_records)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-5 text-muted">No contribution records found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="history">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Reason</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date Processed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($welfare_history as $hist): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($hist['name']) ?></td>
                                            <td><?= htmlspecialchars($hist['reason']) ?></td>
                                            <td>KSh <?= number_format($hist['amount_requested']) ?></td>
                                            <td>
                                                <?php if ($hist['status'] == 'completed'): ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted small"><?= date('M j, Y', strtotime($hist['approved_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
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