<?php
/**
 * Main Layout — wraps page content with shared header/footer.
 * Variables available: $content (rendered view), $title, $basePath
 */
$pageTitle = $title ?? 'Jera Moyie SHG';
$basePath = config('app.base_path', '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= e($pageTitle) ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/style.css">
    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
</head>

<body>

    <?php include BASE_PATH . '/views/partials/navbar.php'; ?>

    <div class="container mt-4 pt-5">
        <?= $content ?>
    </div>

    <?php include BASE_PATH . '/views/partials/footer.php'; ?>

</body>

</html>