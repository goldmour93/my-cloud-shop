<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_post_csrf();
logout_customer();
message('You have signed out.');
redirect_to('login.php');
