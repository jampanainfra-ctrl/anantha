<?php
require_once __DIR__ . '/../includes/admin_auth.php';
session_destroy();
redirect('login.php');
