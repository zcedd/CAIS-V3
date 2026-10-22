import * as SQLite from 'expo-sqlite';

const MIGRATION_SQL = `
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version INTEGER PRIMARY KEY NOT NULL,
    applied_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY NOT NULL,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS batches (
    id INTEGER PRIMARY KEY NOT NULL,
    user_id INTEGER NOT NULL,
    batch_code TEXT NOT NULL,
    title TEXT NOT NULL,
    status TEXT NOT NULL,
    status_label TEXT NOT NULL,
    program TEXT,
    venue TEXT,
    venue_details TEXT,
    scheduled_at TEXT,
    privacy_notice TEXT NOT NULL DEFAULT '',
    retention_notice TEXT NOT NULL DEFAULT '',
    can_execute INTEGER NOT NULL DEFAULT 0,
    can_waive INTEGER NOT NULL DEFAULT 0,
    can_issue_receipt INTEGER NOT NULL DEFAULT 0,
    server_time TEXT,
    downloaded_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS batch_items (
    id INTEGER PRIMARY KEY NOT NULL,
    batch_id INTEGER NOT NULL,
    assistance_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    cais_number TEXT,
    approval_status TEXT NOT NULL DEFAULT '',
    release_status TEXT NOT NULL,
    lines_json TEXT NOT NULL,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS officers (
    batch_id INTEGER NOT NULL,
    id INTEGER NOT NULL,
    name TEXT NOT NULL,
    PRIMARY KEY (batch_id, id),
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS operations (
    client_operation_id TEXT PRIMARY KEY NOT NULL,
    idempotency_key TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    batch_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    live_photo_path TEXT,
    signature_path TEXT,
    captured_at TEXT NOT NULL,
    status TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error TEXT,
    result_json TEXT,
    created_at TEXT NOT NULL,
    next_attempt_at TEXT
);

CREATE INDEX IF NOT EXISTS idx_operations_queue
    ON operations (user_id, status, created_at);
CREATE INDEX IF NOT EXISTS idx_operations_item
    ON operations (batch_id, item_id);
CREATE INDEX IF NOT EXISTS idx_batch_items_batch
    ON batch_items (batch_id);
`;

let databasePromise: Promise<SQLite.SQLiteDatabase> | null = null;

async function migrate(db: SQLite.SQLiteDatabase): Promise<void> {
    await db.execAsync(`
        PRAGMA foreign_keys = ON;
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version INTEGER PRIMARY KEY NOT NULL,
            applied_at TEXT NOT NULL
        );
    `);
    const existing = await db.getFirstAsync<{ version: number }>(
        'SELECT version FROM schema_migrations WHERE version = 1',
    );

    if (existing) {
        return;
    }

    await db.execAsync(MIGRATION_SQL);
    await db.runAsync(
        'INSERT INTO schema_migrations (version, applied_at) VALUES (1, ?)',
        new Date().toISOString(),
    );
}

export function getDatabase(): Promise<SQLite.SQLiteDatabase> {
    if (!databasePromise) {
        databasePromise = (async () => {
            const db = await SQLite.openDatabaseAsync('cais-field.db');
            await migrate(db);

            return db;
        })();
    }

    return databasePromise;
}
