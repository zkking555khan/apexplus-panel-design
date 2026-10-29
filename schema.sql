-- SMM APEX PLUS — Full Panel database schema
--
-- Fresh install (on the live server only, in cPanel):
--   1. cPanel > MySQL Databases: create a database and a database user,
--      then add the user to the database with all privileges.
--   2. cPanel > phpMyAdmin: open that database, paste this whole file
--      into the SQL tab, and run it.
--   3. Put the database host, username, password and name into
--      config.php 'db' (they are empty placeholders in the package).
--   4. Do NOT paste any real password into chat, code comments or
--      this file. There is no sample data and no default account here.
--
-- Upgrade from the simple (phase 3) package: if that schema is already
-- installed, run ONLY the ALTER lines at the bottom of this file, then
-- the two CREATE TABLE lines for tickets / ticket_messages. Do NOT
-- re-run the whole file over an existing database unless you want to
-- keep the existing tables (CREATE TABLE IF NOT EXISTS will not wipe
-- them, but it will not add the new columns either).
--
-- Passwords are stored ONLY as password_hash() values (VARCHAR 255
-- holds the full hash). Never store a plain-text password.
-- The admin account is separate: it lives in data/admin.json as a
-- hash, created once through admin-setup.php — it is not in this SQL.

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    balance       DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    status        ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Orders placed by customers. charge = quantity/1000 x retail price
-- (provider rate + admin markup %). charge_deducted = 1 means the
-- charge was already taken from the customer's balance; a refund
-- credits it back exactly once and sets charge_deducted back to 0.
-- status starts as 'Pending'. When the provider accepts the order it
-- becomes 'Sent' with the provider's real order ID. Provider IDs and
-- statuses are only ever stored when the provider returns them.
CREATE TABLE IF NOT EXISTS orders (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED NOT NULL,
    service_name      VARCHAR(255) NOT NULL,
    service_id        VARCHAR(100) NULL DEFAULT NULL,
    link              VARCHAR(1000) NOT NULL,
    quantity          INT UNSIGNED NOT NULL,
    status            VARCHAR(30) NOT NULL DEFAULT 'Pending',
    provider_order_id VARCHAR(100) NULL DEFAULT NULL,
    notes             VARCHAR(500) NULL DEFAULT NULL,
    charge            DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    charge_deducted   TINYINT(1) NOT NULL DEFAULT 0,
    fulfilment        ENUM('live','manual') NOT NULL DEFAULT 'live',
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_orders_user (user_id),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fund requests submitted by customers (payment method, amount and
-- transaction ID they report). Admin approval is in admin-funds.php:
-- approving a Pending request adds its amount to that customer's
-- balance exactly once (the UPDATE only matches status='Pending',
-- so a second approval cannot credit twice); rejecting changes no
-- balance. Until an admin acts, every request stays 'Pending'.
-- This is the owner's panel style: method numbers are set by the
-- admin, the customer reports the TRX, the admin credits only after
-- actually receiving the payment. It is NOT an instant auto-credit
-- from the owner's phone — the customer's payments go to the
-- customer's own numbers, so only the customer's admin can approve.
CREATE TABLE IF NOT EXISTS fund_requests (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    method        ENUM('easypaisa', 'jazzcash', 'upaisa') NOT NULL,
    amount        DECIMAL(10, 2) NOT NULL,
    trx_id        VARCHAR(100) NOT NULL,
    sender_number VARCHAR(50) NULL DEFAULT NULL,
    status        VARCHAR(30) NOT NULL DEFAULT 'Pending',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_funds_user (user_id),
    CONSTRAINT fk_funds_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Support tickets: one row per ticket, messages in ticket_messages.
-- author_type 'user' = customer message, 'admin' = admin reply.
CREATE TABLE IF NOT EXISTS tickets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    subject    VARCHAR(200) NOT NULL,
    status     ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tickets_user (user_id),
    CONSTRAINT fk_tickets_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ticket_messages (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id  INT UNSIGNED NOT NULL,
    author_type ENUM('user', 'admin') NOT NULL,
    message    VARCHAR(2000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket_messages_ticket (ticket_id),
    CONSTRAINT fk_ticket_messages_ticket FOREIGN KEY (ticket_id)
        REFERENCES tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrade lines for an existing phase-3 database (run these only):
-- ALTER TABLE users ADD COLUMN status ENUM('active', 'suspended') NOT NULL DEFAULT 'active';
-- ALTER TABLE orders ADD COLUMN charge DECIMAL(10, 2) NOT NULL DEFAULT 0.00;
-- ALTER TABLE orders ADD COLUMN charge_deducted TINYINT(1) NOT NULL DEFAULT 0;

-- v2: Service Manager overrides (hidden live services + manual services).
CREATE TABLE IF NOT EXISTS service_overrides (
    service_id      VARCHAR(100)  NOT NULL PRIMARY KEY,
    is_hidden       TINYINT(1)    NOT NULL DEFAULT 0,
    is_manual       TINYINT(1)    NOT NULL DEFAULT 0,
    display_name    VARCHAR(255)  NULL,
    manual_rate     DECIMAL(10,4) NULL,
    manual_min      INT UNSIGNED  NULL,
    manual_max      INT UNSIGNED  NULL,
    manual_category VARCHAR(100)  NULL,
    sort_order      INT           NOT NULL DEFAULT 0,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_overrides_manual (is_manual),
    INDEX idx_overrides_hidden (is_hidden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- v2: balance audit log — one row per money event (fund approval,
-- order charge, order refund, manual admin adjustment), with reason.
CREATE TABLE IF NOT EXISTS balance_audit_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    delta       DECIMAL(10, 2) NOT NULL,
    kind        VARCHAR(30) NOT NULL,
    ref_type    VARCHAR(30) NOT NULL,
    ref_id      INT UNSIGNED NULL DEFAULT NULL,
    reason      VARCHAR(200) NOT NULL DEFAULT '',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_kind (kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
