-- Shared by before/ and after/: the refactoring does not change the database.
CREATE TABLE IF NOT EXISTS clientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    email TEXT NOT NULL DEFAULT '',
    cidade TEXT NOT NULL DEFAULT '',
    criado_em TEXT NOT NULL
);

-- Back-office users of the old system (passwords as unsalted MD5, as found in many legacy apps).
-- Not used by this page, but reachable through the SQL injection in before/clientes.php.
CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    login TEXT NOT NULL UNIQUE,
    senha TEXT NOT NULL
);
