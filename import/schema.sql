-- MailCleaner database schema
-- Engine: InnoDB, charset utf8mb4 (for emne-linjer med emoji/internasjonale tegn)

CREATE TABLE accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,           -- f.eks. "kim@systemweb.no", "Gmail privat"
    driver_type ENUM('gmail', 'imap') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NULL,          -- NULL = gjelder alle kontoer
    source ENUM('gmail_import', 'manual') NOT NULL DEFAULT 'manual',
    from_contains VARCHAR(255) NULL,
    subject_contains VARCHAR(255) NULL,
    action ENUM('delete', 'trash', 'label', 'move') NOT NULL,
    action_value VARCHAR(255) NULL,        -- label-navn / mappenavn, hvis relevant
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE processed_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    message_id VARCHAR(255) NOT NULL,      -- driver-spesifikk ID
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_account_message (account_id, message_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE processing_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    message_id VARCHAR(255) NOT NULL,
    rule_id INT UNSIGNED NULL,             -- NULL hvis manuell handling
    action_taken VARCHAR(50) NOT NULL,
    from_address VARCHAR(255) NULL,
    subject VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (rule_id) REFERENCES rules(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;