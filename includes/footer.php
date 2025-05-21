<?php
// Prevent direct access to this file
if (!defined('BASE_PATH')) {
    die('Direct access to this file is not allowed.');
}
?>
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">© <?= date('Y') ?> Library Management System. All rights reserved.</span>
            <div class="mt-2">
                <a href="<?= BASE_URL ?>/about.php" class="text-decoration-none text-muted mx-2">About</a>
                <a href="<?= BASE_URL ?>/contact.php" class="text-decoration-none text-muted mx-2">Contact</a>
                <a href="<?= BASE_URL ?>/privacy.php" class="text-decoration-none text-muted mx-2">Privacy Policy</a>
                <a href="<?= BASE_URL ?>/terms.php" class="text-decoration-none text-muted mx-2">Terms of Service</a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Add custom scripts below -->
    <?php if (isset($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?= BASE_URL ?>/assets/js/<?= $script ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>