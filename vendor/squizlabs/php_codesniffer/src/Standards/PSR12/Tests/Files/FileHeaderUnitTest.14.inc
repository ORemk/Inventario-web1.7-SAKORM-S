<?php if (array_key_exists('user', $_SESSION)) : ?>
    <li><a href="#">My page</a></li>
    <li><a href="#">Write</a></li>
    <li><a href="#">Sign out</a></li>
<?php else : ?>
    <li><a href="/auth/login.php">Sign in</a></li>
<?php endif; ?>
