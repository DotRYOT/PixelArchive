<?php

declare(strict_types=1);

/** 404 page (rendered inside the base layout). */
$emptyTitle = 'Page not found';
$emptyBody  = 'The page you were looking for does not exist in this hoard.';
?>
<div class="max-w-2xl mx-auto">
    <?php require dirname(__DIR__) . '/partials/empty-state.php'; ?>
</div>
