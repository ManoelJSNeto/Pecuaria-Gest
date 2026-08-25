<?php
// Configurações Globais da Aplicação PecuáriaGest

define('APP_NAME', getenv('APP_NAME') ?: 'PecuáriaGest');
define('APP_VERSION', getenv('APP_VERSION') ?: '1.0.0');
define('APP_ENV', getenv('APP_ENV') ?: 'production');

// Diretório de armazenamento persistente (Storage)
$storageDir = dirname(__DIR__) . '/storage';
define('STORAGE_PATH', $storageDir);
define('DATA_PATH', $storageDir . '/data');
define('UPLOADS_PATH', $storageDir . '/uploads');

// Banco de Dados (Suporte Dual: SQLite e PostgreSQL / AWS RDS)
define('DB_DRIVER', getenv('DB_DRIVER') ?: (getenv('DB_CONNECTION') ?: 'sqlite')); // 'sqlite' ou 'pgsql'
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_DATABASE', getenv('DB_DATABASE') ?: getenv('DB_NAME') ?: 'pecuaria');
define('DB_USERNAME', getenv('DB_USERNAME') ?: getenv('DB_USER') ?: 'postgres');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: '');
define('DB_PATH', getenv('DB_PATH') ?: (DATA_PATH . '/pecuaria.db'));

// Chaves de Segurança e Sessão
define('SESSION_SECRET', getenv('SESSION_SECRET') ?: 'pecuaria_secret_key_change_in_prod');
define('API_KEY', getenv('API_KEY') ?: 'pecuaria-mobile-key');

// Credenciais do Administrador Padrão (utilizado na inicialização do banco)
define('DEFAULT_ADMIN_EMAIL', getenv('DEFAULT_ADMIN_EMAIL') ?: 'admin@fazenda.com');
define('DEFAULT_ADMIN_PASS', getenv('DEFAULT_ADMIN_PASS') ?: 'admin123');

// Garante a existência dos diretórios de armazenamento
if (!is_dir(DATA_PATH)) {
    @mkdir(DATA_PATH, 0775, true);
}
if (!is_dir(UPLOADS_PATH)) {
    @mkdir(UPLOADS_PATH, 0775, true);
}
