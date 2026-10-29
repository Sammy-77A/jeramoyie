<?php
/**
 * Welfare View — uses main layout.
 * Variables: $fin, $contributions, $requests, $lifetime_contrib
 */
$title = 'Welfare Book • Jera Moyie';
$basePath = config('app.base_path', '');
?>

<div class="summary-box-welfare text-center mb-5 mt-4">
    <small class="text-uppercase opacity-75 fw-bold">My Personal Welfare Balance</small>
    <h1 class="display-4 fw-bold mb-0 mt-2">
        <?= format_currency($fin['welfare_balance'] ?? 0) ?>
    </h1>
    <p class="mb-0 mt-2 opacity-75">Available for emergency requests</p>
</div>

<div class="row g-4 mb-5">
    <div class="col-lg-5">
        <div class="glass-card mb-4" style="border-left: 5px solid #6f42c1;">
            <h6 class="fw-bold mb-3 text-uppercase text-muted small">Standard Benefits</h6>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between bg-transparent px-0 border-light">Death of
                    Member <strong class="text-dark">KSh 3,000</strong></li>
                <li class="list-group-item d-flex justify-content-between bg-transparent px-0 border-light">Death of
                    Parent <strong class="text-dark">KSh 2,000</strong></li>
                <li class="list-group-item d-flex justify-content-between bg-transparent px-0 border-light">Death of
                    Child <strong class="text-dark">KSh 2,000</strong></li>
            </ul>
        </div>

        <div class="glass-card h-100">
            <h5 class="fw-bold mb-4">Member Contributions</h5>
            <div style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm align-middle">
                    <thead class="text-muted small">
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contributions as $c): ?>
                            <tr>
                                <td class="small">
                                    <?= date('d M Y', strtotime($c['contribution_date'])) ?>
                                </td>
                                <td class="text-end fw-bold">
                                    <?= number_format($c['amount'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="glass-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">Claim History</h5>
                <a href="<?= $basePath ?>/request-welfare" class="btn btn-primary-custom btn-sm rounded-pill px-4">New
                    Claim</a>
            </div>

            <?php if (empty($requests)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-clipboard2-x fs-1 opacity-25"></i>
                    <p class="mt-3 small">No claims requested yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle border-0">
                        <thead class="bg-light small">
                            <tr>
                                <th class="border-0">Benefit Type</th>
                                <th class="border-0">Requested On</th>
                                <th class="border-0 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td>
                                        <?= e($req['benefit_type'] ?? '') ?>
                                    </td>
                                    <td class="small">
                                        <?= date('d M, Y', strtotime($req['created_at'])) ?>
                                    </td>
                                    <td class="text-center">
                                        <span
                                            class="badge rounded-pill bg-<?= ($req['status'] ?? '') === 'approved' ? 'success' : (($req['status'] ?? '') === 'rejected' ? 'danger' : 'warning text-dark') ?>">
                                            <?= ucfirst($req['status'] ?? 'pending') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>