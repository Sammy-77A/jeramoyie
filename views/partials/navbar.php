<?php
/**
 * Navigation Bar Partial.
 * Adapts based on auth state, admin status.
 */
$basePath = config('app.base_path', '');
$isLoggedIn = isset($_SESSION['member_id']);
$isAdmin = $isLoggedIn && ($_SESSION['is_admin'] ?? 0) == 1;
$memberName = $_SESSION['name'] ?? '';
$names = $isLoggedIn ? explode(" ", $memberName) : [];
?>
<nav class="navbar navbar-expand-lg border-bottom fixed-top shadow-sm" style="background: white;">
    <div class="container">
        <a class="navbar-brand fw-bold text-success" href="<?= $basePath ?>/profile">
            <i class="bi bi-house-heart me-1"></i>JERA MOYIE
        </a>
        <?php if ($isLoggedIn): ?>
            <div class="ms-auto d-flex align-items-center">
                <div class="dropdown">
                    <button class="btn btn-outline-dark btn-sm rounded-pill px-3 dropdown-toggle shadow-sm" type="button"
                        data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= e($names[0] ?? '') ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                        <li><a class="dropdown-item" href="<?= $basePath ?>/profile"><i
                                    class="bi bi-grid me-2"></i>Dashboard</a></li>
                        <li><a class="dropdown-item" href="<?= $basePath ?>/savings"><i
                                    class="bi bi-piggy-bank me-2"></i>Savings</a></li>
                        <li><a class="dropdown-item" href="<?= $basePath ?>/welfare"><i
                                    class="bi bi-heart me-2"></i>Welfare</a></li>
                        <li><a class="dropdown-item" href="<?= $basePath ?>/loan-request"><i
                                    class="bi bi-cash-stack me-2"></i>Loans</a></li>
                        <?php if ($isAdmin): ?>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="<?= $basePath ?>/admin-dashboard"><i
                                        class="bi bi-speedometer2 me-2"></i>Admin Console</a></li>
                        <?php endif; ?>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="<?= $basePath ?>/logout"><i
                                    class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <div class="ms-auto d-flex align-items-center gap-2">
                <a href="<?= $basePath ?>/login" class="btn btn-outline-success btn-sm rounded-pill px-3">Login</a>
                <a href="<?= $basePath ?>/signup" class="btn btn-success btn-sm rounded-pill px-3">Sign Up</a>
            </div>
        <?php endif; ?>
    </div>
</nav>