<?php
/**
 * SMM APEX PLUS — database layer (phase 2).
 *
 * cPanel shared-hosting friendly: plain MySQLi, prepared statements
 * only, no ORM, no Composer dependency.
 *
 * Credentials come ONLY from config.php 'db' placeholders. In this
 * scaffold copy they are all empty, so db_connect() returns null and
 * every page that needs the database shows a clear "database not
 * configured yet" state instead of failing or pretending to work.
 * On the live server the admin fills them in after creating the
 * database + user in cPanel and running schema.sql in phpMyAdmin.
 * Database errors are never shown to visitors (no credentials or
 * SQL details leak into page output).
 */

function db_config() {
    $config = require __DIR__ . '/../config.php';
    return isset($config['db']) && is_array($config['db']) ? $config['db'] : [];
}

function db_available() {
    $db = db_config();
    return trim((string) ($db['host'] ?? '')) !== ''
        && trim((string) ($db['username'] ?? '')) !== ''
        && trim((string) ($db['name'] ?? '')) !== '';
}

/**
 * Returns a mysqli connection, or null when the database is not
 * configured or cannot be reached. Callers must handle null by
 * showing the "database not configured" state.
 */
function db_connect() {
    if (!db_available()) {
        return null;
    }
    $db = db_config();
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli(
        (string) $db['host'],
        (string) $db['username'],
        (string) $db['password'],
        (string) $db['name'],
        (int) ($db['port'] ?? 3306)
    );
    if ($conn === false || $conn->connect_error) {
        return null;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
