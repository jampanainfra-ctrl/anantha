<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_require_db(): void
{
    if (!db_ready()) {
        json_response(['ok' => false, 'error' => 'Database not ready. Import database.sql.'], 503);
    }
}

function api_require_admin(): void
{
    require_once __DIR__ . '/../includes/admin_auth.php';
    if (!admin_user()) {
        json_response(['ok' => false, 'error' => 'Admin login required.'], 401);
    }
}
