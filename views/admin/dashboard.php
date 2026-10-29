<?php
/**
 * Admin Dashboard View — uses main layout.
 * Variables: $admin_name, $admin_role, $permissions
 */
$title = 'Official Console - Jera Moyie';
$basePath = config('app.base_path', '');

function can_access_view($module, $current_role, $perms)
{
    if (!isset($perms[$current_role]))
        return false;
    return in_array($module, $perms[$current_role]);
}
?>

<style>
    :root {
        --primary: #1a5928;
    }

    .admin-card {
        background: white;
        border-radius: 20px;
        border: none;
        transition: all 0.3s ease;
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1.5rem;
        text-align: center;
        height: 100%;
    }

    .admin-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    }

    .icon-box {
        width: 55px;
        height: 55px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        margin-bottom: 1rem;
    }

    .role-badge {
        background: var(--primary);
        color: white;
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 0.75rem;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-5">
    <div>
        <h1 class="fw-bold mb-0 text-dark">Official Console</h1>
        <p class="text-muted small">Bearer: <strong>
                <?= e($admin_name) ?>
            </strong> <span class="role-badge ms-2">
                <?= e($admin_role) ?>
            </span></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $basePath ?>/" class="btn btn-outline-secondary rounded-pill px-3 btn-sm"><i
                class="bi bi-house me-1"></i>Home</a>
        <a href="<?= $basePath ?>/profile" class="btn btn-outline-dark rounded-pill px-3 btn-sm"><i
                class="bi bi-person me-1"></i>Profile</a>
        <a href="<?= $basePath ?>/community/dashboard" class="btn btn-outline-success rounded-pill px-3 btn-sm"><i
                class="bi bi-people me-1"></i>Community</a>
    </div>
</div>

<div class="row g-4">
    <?php if (can_access_view('finance', $admin_role, $permissions)): ?>
        <div class="col-md-4 col-lg-3">
            <a href="<?= $basePath ?>/admin-finance" class="admin-card shadow-sm">
                <div class="icon-box bg-info bg-opacity-10 text-info"><i class="bi bi-currency-exchange"></i></div>
                <h6 class="fw-bold">Financial Books</h6>
                <p class="text-muted small">Savings & Welfare Pools</p>
            </a>
        </div>
    <?php endif; ?>

    <?php if (can_access_view('sharing', $admin_role, $permissions)): ?>
        <div class="col-md-4 col-lg-3">
            <a href="<?= $basePath ?>/admin-sharing" class="admin-card shadow-sm">
                <div class="icon-box bg-success bg-opacity-10 text-success"><i class="bi bi-pie-chart-fill"></i></div>
                <h6 class="fw-bold">Sharing Out</h6>
                <p class="text-muted small">Quarterly & Yearly Dividends</p>
            </a>
        </div>
    <?php endif; ?>

    <?php if (can_access_view('loans', $admin_role, $permissions)): ?>
        <div class="col-md-4 col-lg-3">
            <a href="<?= $basePath ?>/admin-loans" class="admin-card shadow-sm">
                <div class="icon-box bg-primary bg-opacity-10 text-primary"><i class="bi bi-safe2-fill"></i></div>
                <h6 class="fw-bold">Loan Approvals</h6>
                <p class="text-muted small">Process Principal Requests</p>
            </a>
        </div>
    <?php endif; ?>

    <?php if (can_access_view('welfare', $admin_role, $permissions)): ?>
        <div class="col-md-4 col-lg-3">
            <a href="<?= $basePath ?>/admin-welfare" class="admin-card shadow-sm">
                <div class="icon-box bg-danger bg-opacity-10 text-danger"><i class="bi bi-heart-pulse-fill"></i></div>
                <h6 class="fw-bold">Welfare Vetting</h6>
                <p class="text-muted small">Uwezo Loan Vetting</p>
            </a>
        </div>
    <?php endif; ?>

    <?php if (in_array($admin_role, ['Chairperson', 'Assistant Chairperson', 'Treasury', 'dev'])): ?>
        <div class="col-md-4 col-lg-3">
            <a href="<?= $basePath ?>/admin-audit-log" class="admin-card shadow-sm">
                <div class="icon-box bg-dark bg-opacity-10 text-dark"><i class="bi bi-shield-check"></i></div>
                <h6 class="fw-bold">Audit Trail</h6>
                <p class="text-muted small">Admin Activity Logs</p>
            </a>
        </div>
    <?php endif; ?>

    <?php if (can_access_view('attendance', $admin_role, $permissions)): ?>
        <div class="col-md-4 col-lg-3">
            <a href="<?= $basePath ?>/admin-announcements" class="admin-card shadow-sm">
                <div class="icon-box bg-info bg-opacity-10 text-info"><i class="bi bi-megaphone"></i></div>
                <h6 class="fw-bold">Announcements</h6>
                <p class="text-muted small">Broadcast to Members</p>
            </a>
        </div>
        <div class="col-md-4 col-lg-3">
            <a href="<?= $basePath ?>/admin-attendance" class="admin-card shadow-sm">
                <div class="icon-box bg-warning bg-opacity-10 text-warning"><i class="bi bi-calendar-check"></i></div>
                <h6 class="fw-bold">Meeting Logs</h6>
                <p class="text-muted small">Roll Call & Minutes</p>
            </a>
        </div>
    <?php endif; ?>

    <?php if (can_access_view('members', $admin_role, $permissions)): ?>
        <div class="col-md-4 col-lg-3">
            <a href="<?= $basePath ?>/admin-members" class="admin-card shadow-sm">
                <div class="icon-box bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-people-fill"></i></div>
                <h6 class="fw-bold">Member Registry</h6>
                <p class="text-muted small">Manage Roles & Profiles</p>
            </a>
        </div>
    <?php endif; ?>

    <?php if (can_access_view('community', $admin_role, $permissions)): ?>
        <div class="col-md-4 col-lg-3">
            <a href="<?= $basePath ?>/community/admin/community-dashboard" class="admin-card shadow-sm">
                <div class="icon-box bg-success bg-opacity-10 text-success"><i class="bi bi-globe2"></i></div>
                <h6 class="fw-bold">Community Portal</h6>
                <p class="text-muted small">Manage Okoa Customers</p>
            </a>
        </div>
    <?php endif; ?>
</div>