<?php
/**
 * Header Partial — shared <head> section.
 * Included by layouts, not used directly.
 */
$pageTitle = $title ?? 'Jera Moyie SHG';
$basePath = config('app.base_path', '');
?>
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