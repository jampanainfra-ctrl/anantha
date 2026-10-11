<?php
require_once __DIR__ . '/../includes/admin_auth.php';
redirect(admin_user() ? 'dashboard.php' : 'login.php');
