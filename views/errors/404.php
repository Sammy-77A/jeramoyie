<?php
/**
 * 404 Error Page.
 */
$basePath = defined('BASE_PATH') ? config('app.base_path', '') : '/jeramy1';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found • Jera Moyie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .error-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.05);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
        }
    </style>
</head>

<body>
    <div class="error-card">
        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 4rem;"></i>
        <h2 class="fw-bold mt-3">Page Not Found</h2>
        <p class="text-muted">The page you're looking for doesn't exist or has been moved.</p>
        <div class="d-flex gap-2 justify-content-center mt-4">
            <a href="<?= $basePath ?>/" class="btn btn-success rounded-pill px-4">Home</a>
            <a href="<?= $basePath ?>/login" class="btn btn-outline-dark rounded-pill px-4">Login</a>
        </div>
    </div>
</body>

</html>