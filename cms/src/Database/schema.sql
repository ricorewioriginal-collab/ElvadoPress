-- cms/src/Database/schema.sql
-- Kern-Schema des CMS (Version 001_core). Wird von Elvado\Database\DatabaseConnection::migrateCore() einmalig angewendet
-- und läuft unverändert auf MySQL, MariaDB und SQLite. Platzhalter (werden je Datenbank ersetzt):
--   {{PK}}          Auto-Increment-Primärschlüssel
--   {{LONGTEXT}}    langer Text (LONGTEXT bzw. TEXT)
--   {{DATETIME}}    Datum/Uhrzeit (DATETIME bzw. TEXT im Format YYYY-MM-DD HH:MM:SS)
--   {{TABLE_OPTS}}  Engine/Zeichensatz (nur MySQL/MariaDB)
-- Hinweis: Die Inhalte des CMS liegen weiterhin in Dateien (cms/data/*.json); die Tabellen users/posts sind eine Spiegelung bzw. Grundlage für
-- Dienste, die Datenbankzugriff brauchen. Passwort-Hashes der Dateikonten werden NICHT gespiegelt (Spalte password_hash bleibt leer).

CREATE TABLE IF NOT EXISTS users (
    id {{PK}},
    username VARCHAR(64) NOT NULL,
    email VARCHAR(190) NULL,
    display_name VARCHAR(120) NOT NULL DEFAULT '',
    role VARCHAR(32) NOT NULL DEFAULT 'author',
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    password_hash VARCHAR(255) NULL,
    created_at {{DATETIME}} NOT NULL,
    updated_at {{DATETIME}} NOT NULL,
    last_login_at {{DATETIME}} NULL,
    UNIQUE (username)
){{TABLE_OPTS}};

CREATE TABLE IF NOT EXISTS posts (
    id {{PK}},
    slug VARCHAR(190) NOT NULL,
    title VARCHAR(255) NOT NULL,
    excerpt {{LONGTEXT}} NULL,
    body_html {{LONGTEXT}} NULL,
    category VARCHAR(80) NOT NULL DEFAULT 'News',
    tags VARCHAR(800) NOT NULL DEFAULT '',
    image_url VARCHAR(1200) NOT NULL DEFAULT '',
    author VARCHAR(120) NOT NULL DEFAULT '',
    status VARCHAR(16) NOT NULL DEFAULT 'draft',
    featured SMALLINT NOT NULL DEFAULT 0,
    published_at {{DATETIME}} NULL,
    created_at {{DATETIME}} NOT NULL,
    updated_at {{DATETIME}} NOT NULL,
    deleted_at {{DATETIME}} NULL,
    meta_json {{LONGTEXT}} NULL
){{TABLE_OPTS}};
CREATE INDEX idx_posts_status_published ON posts (status, published_at);
CREATE INDEX idx_posts_category ON posts (category);
CREATE INDEX idx_posts_slug ON posts (slug);

CREATE TABLE IF NOT EXISTS ai_logs (
    id {{PK}},
    provider VARCHAR(40) NOT NULL,
    model VARCHAR(120) NOT NULL DEFAULT '',
    task VARCHAR(40) NOT NULL DEFAULT 'text',
    user_name VARCHAR(64) NOT NULL DEFAULT '',
    status VARCHAR(16) NOT NULL,
    http_code INT NOT NULL DEFAULT 0,
    error_message VARCHAR(300) NOT NULL DEFAULT '',
    prompt_chars INT NOT NULL DEFAULT 0,
    completion_chars INT NOT NULL DEFAULT 0,
    prompt_tokens INT NULL,
    completion_tokens INT NULL,
    latency_ms INT NOT NULL DEFAULT 0,
    created_at {{DATETIME}} NOT NULL
){{TABLE_OPTS}};
CREATE INDEX idx_ai_logs_created ON ai_logs (created_at);
CREATE INDEX idx_ai_logs_provider ON ai_logs (provider);

CREATE TABLE IF NOT EXISTS lovable_widgets (
    id {{PK}},
    project_id VARCHAR(120) NOT NULL,
    component_name VARCHAR(120) NOT NULL,
    label VARCHAR(160) NOT NULL DEFAULT '',
    config_json {{LONGTEXT}} NOT NULL,
    enabled SMALLINT NOT NULL DEFAULT 1,
    created_at {{DATETIME}} NOT NULL,
    updated_at {{DATETIME}} NOT NULL,
    UNIQUE (project_id, component_name)
){{TABLE_OPTS}};
