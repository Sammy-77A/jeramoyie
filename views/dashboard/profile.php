<?php
/**
 * Profile/Dashboard View — uses main layout.
 * Variables: $member, $financials, $pending_votes, $announcements, $needs_email,
 *           $notif_count, $names, $initials, $success_msg, $error_msg
 */
$title = 'Dashboard • Jera Moyie';
$basePath = config('app.base_path', '');
?>

<?php if ($error_msg): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?= e($error_msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?= e($success_msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4 mt-2">
    <div class="col-lg-4">
        <div class="glass-card p-4 text-center h-100">
            <div class="mx-auto rounded-circle bg-success text-white d-flex align-items-center justify-content-center mb-3 shadow"
                style="width: 80px; height: 80px; font-size: 2rem;">
                <?= $initials ?>
            </div>
            <h4 class="fw-bold mb-1">
                <?= e($member['name']) ?>
            </h4>
            <p class="text-muted small mb-4">Member ID: #
                <?= str_pad($member['id'], 4, '0', STR_PAD_LEFT) ?>
            </p>

            <div class="d-grid gap-2">
                <button class="btn btn-main py-2 shadow-sm rounded-pill" data-bs-toggle="modal"
                    data-bs-target="#paymentModal">
                    <i class="bi bi-plus-circle me-2"></i>Make a Payment
                </button>
                <a href="<?= $basePath ?>/loan-request" class="btn btn-outline-dark border-2 rounded-pill py-2">Request
                    Loan</a>

                <?php if ($member['is_admin'] == 1): ?>
                    <a href="<?= $basePath ?>/admin-dashboard" class="btn btn-dark w-100 py-2 rounded-pill mt-1">
                        <i class="bi bi-speedometer2 me-2"></i>Official Console
                    </a>
                <?php endif; ?>
                <a href="<?= $basePath ?>/edit-profile" class="btn btn-outline-secondary w-100 py-2 rounded-pill mt-1">
                    <i class="bi bi-pencil-square me-2"></i>Edit Profile
                </a>
            </div>

            <div class="mt-4 pt-4 border-top text-start">
                <h6 class="fw-bold small mb-3 text-muted text-uppercase opactiy-75">Share-Out Preference</h6>
                <form method="POST" action="<?= $basePath ?>/profile">
                    <select name="payout_preference" class="form-select form-select-sm border-2 mb-2 rounded-3">
                        <option value="savings" <?= ($member['payout_preference'] == 'savings') ? 'selected' : '' ?>>
                            Re-invest (Savings)</option>
                        <option value="mpesa" <?= ($member['payout_preference'] == 'mpesa') ? 'selected' : '' ?>>Direct
                            M-PESA</option>
                        <option value="cash" <?= ($member['payout_preference'] == 'cash') ? 'selected' : '' ?>>Physical
                            Cash</option>
                    </select>
                    <button type="submit" name="update_preference"
                        class="btn btn-sm btn-outline-dark w-100 rounded-pill mt-1">Update Preference</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <h3 class="fw-bold mb-4 section-title">Financial Overview</h3>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="glass-card p-4 stat-card h-100">
                    <small class="text-muted text-uppercase fw-bold opacity-75">Combined Savings Base</small>
                    <h2 class="fw-bold text-success mt-2">
                        <?= format_currency($financials['total_dividend_weight']) ?>
                    </h2>
                    <div class="d-flex justify-content-between mt-3 small text-muted">
                        <span>Normal:
                            <?= format_currency($financials['normal_savings_balance']) ?>
                        </span>
                        <span>Weekly:
                            <?= format_currency($financials['total_weekly_paid']) ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="glass-card p-4 stat-card h-100 border-start border-danger border-4">
                    <small class="text-muted text-uppercase fw-bold opacity-75">Active Loan Balance</small>
                    <h2 class="fw-bold text-danger mt-2">
                        <?= format_currency($financials['normal_loans_balance'] + $financials['uwezo_loans_balance'] + $financials['table_banking_loans_balance']) ?>
                    </h2>
                    <a href="<?= $basePath ?>/loan-statement"
                        class="small text-decoration-none text-danger fw-bold">View Full Statement <i
                            class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>

        <div class="mt-5">
            <h5 class="fw-bold mb-3 section-title h6 text-uppercase opacity-75">Group Ledgers</h5>
            <div class="row g-3 text-center">
                <div class="col-4">
                    <a href="<?= $basePath ?>/normal-book"
                        class="glass-card p-3 d-block text-decoration-none text-dark shadow-sm">
                        <div class="icon-box mx-auto mb-2 bg-primary bg-opacity-10 text-primary"><i
                                class="bi bi-journal-text fs-4"></i></div>
                        <small class="fw-bold">Normal</small>
                    </a>
                </div>
                <div class="col-4">
                    <a href="<?= $basePath ?>/table-banking-book"
                        class="glass-card p-3 d-block text-decoration-none text-dark shadow-sm">
                        <div class="icon-box mx-auto mb-2 bg-warning bg-opacity-10 text-warning"><i
                                class="bi bi-bank fs-4"></i></div>
                        <small class="fw-bold">Table</small>
                    </a>
                </div>
                <div class="col-4">
                    <a href="<?= $basePath ?>/welfare-book"
                        class="glass-card p-3 d-block text-decoration-none text-dark shadow-sm">
                        <div class="icon-box mx-auto mb-2 bg-danger bg-opacity-10 text-danger"><i
                                class="bi bi-heart-fill fs-4"></i></div>
                        <small class="fw-bold">Welfare</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Overlay (blur + animation) -->
<div id="paymentOverlay"
    style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(255,255,255,.85);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);justify-content:center;align-items:center;flex-direction:column">
    <style>
        @keyframes phonePulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 1
            }

            50% {
                transform: scale(1.15);
                opacity: .8
            }
        }

        @keyframes progressGrow {
            0% {
                width: 0
            }

            50% {
                width: 60%
            }

            100% {
                width: 95%
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .stk-phone {
            animation: phonePulse 1.5s ease-in-out infinite
        }

        .stk-progress-bar {
            animation: progressGrow 4s ease-in-out forwards
        }

        .stk-overlay-content {
            animation: fadeInUp .5s ease-out
        }
    </style>
    <div class="stk-overlay-content text-center">
        <div class="stk-phone mb-4">
            <div
                style="width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,#1a5928,#2c7a4b);display:flex;align-items:center;justify-content:center;margin:0 auto;box-shadow:0 10px 40px rgba(26,89,40,.3)">
                <i class="bi bi-phone-vibrate text-white" style="font-size:2.5rem"></i>
            </div>
        </div>
        <h4 class="fw-bold mb-2" style="color:#1a5928">Initiating M-Pesa</h4>
        <p class="text-muted small mb-4">Please wait while we send the STK push to your phone...</p>
        <div style="width:220px;height:6px;background:#e9ecef;border-radius:6px;overflow:hidden;margin:0 auto">
            <div class="stk-progress-bar"
                style="height:100%;background:linear-gradient(90deg,#1a5928,#2c7a4b,#f59e0b);border-radius:6px"></div>
        </div>
        <p class="text-muted mt-3" style="font-size:.75rem"><i
                class="bi bi-shield-lock-fill text-success me-1"></i>Secured by Safaricom M-PESA</p>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg p-3">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold section-title">Payment Portal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= $basePath ?>/process-payment" method="POST" id="paymentForm">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Category</label>
                        <select name="type" class="form-select py-2 border-2 rounded-3" required>
                            <option value="weekly">Weekly Contribution (KSh 3,000)</option>
                            <option value="normal_savings">Normal Savings Deposit</option>
                            <option value="table_banking">Table Banking Shares</option>
                            <option value="welfare">Welfare Contribution</option>
                            <option value="loan_repayment">Loan Repayment</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Amount (KSh)</label>
                        <input type="number" name="amount" class="form-control py-2 border-2 rounded-3"
                            placeholder="3000" min="1" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase opacity-75">M-PESA Phone Number</label>
                        <input type="text" name="phone" class="form-control py-2 border-2 rounded-3"
                            value="<?= e($member['phone']) ?>" required>
                    </div>
                    <div class="d-grid pt-2">
                        <button type="submit" id="payBtn" class="btn btn-main py-3 rounded-pill shadow">
                            <i class="bi bi-phone me-2"></i>Initiate M-Pesa Payment
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('paymentForm').addEventListener('submit', function (e) {
        var form = this;
        if (!form.checkValidity()) return;

        // Close modal
        var modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
        if (modal) modal.hide();

        // Show blur overlay with animation
        var overlay = document.getElementById('paymentOverlay');
        overlay.style.display = 'flex';

        // Disable button
        document.getElementById('payBtn').disabled = true;
        document.getElementById('payBtn').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    });
</script>