-- Adds the replay-protection table used by sso_consume.php (Main LGU SSO).
-- Safe to import on a database that already has it — sso_consume.php also
-- creates this table on first use if it's missing, so this file just lets
-- you provision it up front instead (e.g. via phpMyAdmin's Import tab).

CREATE TABLE IF NOT EXISTS sso_used_tokens (
    nonce VARCHAR(64) PRIMARY KEY,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
