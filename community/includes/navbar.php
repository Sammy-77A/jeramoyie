<?php
/**
 * Community Portal — Shared Navigation Bar
 * Usage: set $active_page before including. e.g. $active_page = 'dashboard';
 */
if (!isset($active_page))
    $active_page = '';
$_nav_name = $_SESSION['community_customer_name'] ?? 'User';
$_nav_first = explode(' ', $_nav_name)[0];
?>
<style>
    .community-nav {
        background: rgba(255, 255, 255, .97);
        backdrop-filter: blur(10px);
        z-index: 1050
    }

    .community-nav .navbar-collapse {
        z-index: 1051
    }

    @media(max-width:991px) {
        .community-nav .navbar-collapse {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, .98);
            backdrop-filter: blur(20px);
            padding: 80px 1.5rem 2rem;
            z-index: 1051;
            overflow-y: auto;
            display: flex !important;
            flex-direction: column;
            transform: translateX(-100%);
            transition: transform .3s ease
        }

        .community-nav .navbar-collapse.show {
            transform: translateX(0);
            overflow-y: auto !important;
        }

        .community-nav .navbar-collapse.collapsing {
            transform: translateX(-100%);
            transition: transform .3s ease
        }

        .community-nav .navbar-nav {
            flex-direction: column;
            gap: .5rem !important;
            width: 100%
        }

        .community-nav .navbar-nav .nav-link {
            font-size: 1.1rem;
            padding: .75rem 1rem !important;
            border-radius: 12px !important;
            margin-bottom: 5px;
        }

        .community-nav .navbar-nav .nav-link.active {
            background: #1a5928 !important
        }

        .community-nav .dropdown-menu {
            position: relative !important;
            transform: none !important;
            width: 100%;
            box-shadow: none !important;
            border: 1px solid #eee !important;
            border-radius: 12px !important;
            margin-top: .5rem !important;
            margin-bottom: 2rem !important;
            /* Extra space at bottom of scroll */
        }

    }
</style>
<nav class="navbar navbar-expand-lg border-bottom fixed-top shadow-sm community-nav">
    <div class="container">
        <!-- Brand -->
        <a class="navbar-brand fw-bold text-success d-flex align-items-center gap-2" href="dashboard.php">
            <div class="d-flex align-items-center justify-content-center rounded-circle bg-success text-white"
                style="width:32px;height:32px;font-size:.8rem"><i class="bi bi-people-fill"></i></div>
            <span class="d-none d-sm-inline">COMMUNITY</span>
        </a>

        <!-- Right Side Nav (Always Visible) -->
        <div class="ms-auto d-flex align-items-center gap-2">
            <!-- Desktop Home Button -->
            <a href="../index.html"
                class="btn btn-sm btn-outline-secondary rounded-pill px-3 d-none d-lg-inline-flex align-items-center"
                title="Main Website">
                <i class="bi bi-house me-1"></i>Home
            </a>

            <!-- Profile Dropdown -->
            <div class="dropdown me-lg-2">
                <button class="btn btn-outline-dark btn-sm rounded-pill px-3 dropdown-toggle shadow-sm" type="button"
                    data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_nav_first) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-2 pt-0" style="min-width:200px;">
                    <li class="px-3 py-2 border-bottom bg-light mb-2">
                        <small class="text-muted">Signed in as</small><br>
                        <strong class="small"><?= htmlspecialchars($_nav_name) ?></strong>
                    </li>
                    <li><a class="dropdown-item py-2" href="dashboard.php"><i
                                class="bi bi-speedometer2 me-2 text-success"></i>Dashboard</a></li>
                    <li><a class="dropdown-item py-2" href="transactions.php"><i
                                class="bi bi-receipt me-2 text-primary"></i>Transactions</a></li>
                    <li><a class="dropdown-item py-2" href="download-statement.php"><i
                                class="bi bi-file-pdf me-2 text-danger"></i>Statement</a></li>
                    <li class="d-lg-none"><a class="dropdown-item py-2" href="../index.html"><i
                                class="bi bi-house me-2 text-secondary"></i>Main Website</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i
                                class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>

            <!-- Mobile Menu Toggle -->
            <button class="navbar-toggler border-0 shadow-none p-1" type="button" data-bs-toggle="collapse"
                data-bs-target="#communityNav" aria-expanded="false" id="communityNavToggler">
                <i class="bi bi-list fs-3 text-dark" id="navIcon"></i>
            </button>
        </div>

        <!-- Collapsible Navigation Links -->
        <div class="collapse navbar-collapse mt-3 mt-lg-0" id="communityNav">
            <ul class="navbar-nav mx-auto gap-1">
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 <?= $active_page === 'dashboard' ? 'active fw-bold text-white bg-success' : '' ?>"
                        href="dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 <?= $active_page === 'savings' ? 'active fw-bold text-white bg-success' : '' ?>"
                        href="savings.php">
                        <i class="bi bi-piggy-bank me-1"></i>Savings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 <?= $active_page === 'loans' ? 'active fw-bold text-white bg-success' : '' ?>"
                        href="apply-loan.php">
                        <i class="bi bi-cash-stack me-1"></i>Loans
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 <?= $active_page === 'transactions' ? 'active fw-bold text-white bg-success' : '' ?>"
                        href="transactions.php">
                        <i class="bi bi-receipt me-1"></i>Transactions
                    </a>
                </li>
                <li class="nav-item d-lg-none">
                    <a class="nav-link rounded-pill px-3 <?= $active_page === 'statement' ? 'active fw-bold text-white bg-success' : '' ?>"
                        href="download-statement.php">
                        <i class="bi bi-file-pdf me-1"></i>Statement
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const nav = document.getElementById('communityNav');
        const icon = document.getElementById('navIcon');
        if (nav && icon) {
            nav.addEventListener('show.bs.collapse', function () { icon.classList.replace('bi-list', 'bi-x-lg') });
            nav.addEventListener('hide.bs.collapse', function () { icon.classList.replace('bi-x-lg', 'bi-list') });
            // Close menu when a link is clicked on mobile, EXCEPT the dropdown toggle
            nav.querySelectorAll('.nav-link:not(.dropdown-toggle), .dropdown-item').forEach(function (link) {
                link.addEventListener('click', function () { if (window.innerWidth < 992) { var bsCollapse = bootstrap.Collapse.getInstance(nav); if (bsCollapse) bsCollapse.hide(); } });
            });
        }
    });
</script>