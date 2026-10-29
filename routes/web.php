<?php
/**
 * Route Definitions.
 * Maps URLs to Controller@method with optional middleware.
 *
 * $router is passed from App.php
 */

// =====================================================
// PUBLIC ROUTES (no auth required)
// =====================================================

// Landing page is served directly by .htaccess (index.html)

// Auth
$router->get('/login', 'AuthController@loginForm');
$router->post('/login', 'AuthController@authenticate');
$router->get('/signup', 'AuthController@signupForm');
$router->post('/register', 'AuthController@register');
$router->get('/logout', 'AuthController@logout');
$router->get('/forgot-password', 'AuthController@forgotPasswordForm');
$router->post('/forgot-password', 'AuthController@forgotPassword');
$router->get('/reset-password', 'AuthController@resetPasswordForm');
$router->post('/reset-password', 'AuthController@resetPassword');

// Static pages
$router->get('/constitution', 'PageController@constitution');
$router->get('/error', 'PageController@error');

// Pending pages (registration/payment flows)
$router->get('/pending', 'AuthController@pending');
$router->get('/check-status', 'AuthController@checkStatus');

// =====================================================
// AUTHENTICATED MEMBER ROUTES
// =====================================================
$router->group('', ['auth'], function ($router) {

    // Dashboard / Profile
    $router->get('/profile', 'DashboardController@profile');
    $router->post('/profile', 'DashboardController@updatePreference');
    $router->get('/edit-profile', 'DashboardController@editProfileForm');
    $router->post('/edit-profile', 'DashboardController@editProfile');

    // Savings
    $router->get('/savings', 'SavingsController@index');
    $router->get('/normal-book', 'SavingsController@normalBook');
    $router->get('/table-banking-book', 'SavingsController@tableBankingBook');

    // Loans
    $router->get('/loan-request', 'LoanController@requestForm');
    $router->post('/loan-request', 'LoanController@submitRequest');
    $router->get('/loan-statement', 'LoanController@statement');
    $router->get('/request-loan', 'LoanController@requestForm');

    // Welfare
    $router->get('/welfare', 'WelfareController@index');
    $router->get('/welfare-book', 'WelfareController@book');
    $router->post('/process-welfare', 'WelfareController@processRequest');

    // Payments
    $router->post('/process-payment', 'PaymentController@processPayment');
    $router->get('/payment-pending', 'PaymentController@pending');
    $router->get('/check-payment', 'PaymentController@checkPayment');
    $router->post('/process-deposit', 'PaymentController@processDeposit');

    // Voting
    $router->post('/cast-vote', 'DashboardController@castVote');

    // Events
    $router->get('/events', 'PageController@events');
});

// =====================================================
// ADMIN ROUTES
// =====================================================
$router->group('', ['auth', 'admin'], function ($router) {

    $router->get('/admin-dashboard', 'AdminController@dashboard');
    $router->get('/admin-members', 'AdminController@members');
    $router->get('/admin-member-profile/{id}', 'AdminController@memberProfile');
    $router->post('/admin-member-profile/{id}', 'AdminController@updateMemberProfile');
    $router->get('/admin-loans', 'AdminController@loans');
    $router->post('/admin-loans', 'AdminController@processLoan');
    $router->get('/admin-finance', 'AdminController@finance');
    $router->post('/admin-finance', 'AdminController@processFinance');
    $router->get('/admin-welfare', 'AdminController@welfare');
    $router->post('/admin-welfare', 'AdminController@processWelfare');
    $router->get('/admin-attendance', 'AdminController@attendance');
    $router->post('/admin-attendance', 'AdminController@processAttendance');
    $router->get('/admin-announcements', 'AdminController@announcements');
    $router->post('/admin-announcements', 'AdminController@createAnnouncement');
    $router->get('/admin-sharing', 'AdminController@sharing');
    $router->post('/admin-sharing', 'AdminController@processSharing');
    $router->get('/admin-audit-log', 'AdminController@auditLog');
});

// =====================================================
// API / CALLBACK ROUTES (no auth — external callers)
// =====================================================
$router->post('/api/callback', 'PaymentController@callback');
$router->post('/api/callback-handler', 'PaymentController@callbackHandler');
$router->get('/api/check-payment', 'PaymentController@apiCheckPayment');
$router->get('/api/check-status', 'PaymentController@apiCheckStatus');
$router->get('/api/check-reset-status', 'AuthController@checkResetStatus');

// =====================================================
// COMMUNITY ROUTES
// =====================================================
$router->get('/community/login', 'CommunityController@loginForm');
$router->post('/community/login', 'CommunityController@authenticate');
$router->get('/community/register', 'CommunityController@registerForm');
$router->post('/community/register', 'CommunityController@register');
$router->get('/community/logout', 'CommunityController@logout');
$router->get('/community/dashboard', 'CommunityController@dashboard');
$router->get('/community/savings', 'CommunityController@savings');
$router->get('/community/apply-loan', 'CommunityController@applyLoanForm');
$router->post('/community/apply-loan', 'CommunityController@applyLoan');
$router->get('/community/transactions', 'CommunityController@transactions');
$router->get('/community/download-statement', 'CommunityController@downloadStatement');
$router->get('/community/forgot-password', 'CommunityController@forgotPassword');
$router->post('/community/forgot-password', 'CommunityController@processForgotPassword');

// Community Admin
$router->get('/community/admin/community-dashboard', 'CommunityAdminController@dashboard');
$router->get('/community/admin/community-customers', 'CommunityAdminController@customers');
$router->get('/community/admin/community-loans', 'CommunityAdminController@loans');
$router->post('/community/admin/community-loans', 'CommunityAdminController@processLoan');
$router->get('/community/admin/community-products', 'CommunityAdminController@products');
$router->post('/community/admin/community-products', 'CommunityAdminController@processProduct');
$router->get('/community/admin/community-reports', 'CommunityAdminController@reports');
