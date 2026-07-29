<?php
define('APP_NAME', 'PecuáriaGest');
define('APP_VERSION', '1.0');
define('DB_PATH', __DIR__ . '/../data/pecuaria.db');
define('SESSION_SECRET', getenv('SESSION_SECRET') ?: 'pecuaria_secret_2026');

// Ensure data directory exists
if (!is_dir(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0755, true);
}

// Default admin credentials (change in production)
define('DEFAULT_ADMIN_EMAIL', 'admin@fazenda.com');
define('DEFAULT_ADMIN_PASS', 'admin123');
