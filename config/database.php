<?php

declare(strict_types=1);

$host = '127.0.0.1';
$db = 'eprocure_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET NAMES {$charset}");

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS scrape_runs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            department_id VARCHAR(20) NOT NULL,
            department_label VARCHAR(255) NOT NULL,
            procurement_method_code VARCHAR(20) NOT NULL,
            procurement_method_label VARCHAR(100) NOT NULL,
            contract_date_from DATE NOT NULL,
            contract_date_to DATE NOT NULL,
            page_size INT UNSIGNED NOT NULL DEFAULT 10,
            total_pages INT UNSIGNED DEFAULT NULL,
            last_page_scraped INT UNSIGNED NOT NULL DEFAULT 0,
            total_records_seen INT UNSIGNED NOT NULL DEFAULT 0,
            total_records_inserted INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            last_error TEXT DEFAULT NULL,
            started_at DATETIME DEFAULT NULL,
            finished_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset}"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS contract_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source_run_id INT UNSIGNED NOT NULL,
            source_page_no INT UNSIGNED NOT NULL,
            source_row_no INT UNSIGNED NOT NULL,
            tender_id VARCHAR(50) NOT NULL,
            invitation_ref_no VARCHAR(255) DEFAULT NULL,
            tender_title TEXT DEFAULT NULL,
            ministry_division VARCHAR(255) NOT NULL,
            procuring_entity VARCHAR(255) NOT NULL,
            procurement_method VARCHAR(100) NOT NULL,
            district VARCHAR(150) DEFAULT NULL,
            notification_of_award_date DATE DEFAULT NULL,
            notification_of_award_raw VARCHAR(50) DEFAULT NULL,
            contract_award_to VARCHAR(255) DEFAULT NULL,
            contract_value_cr_bdt DECIMAL(18,3) DEFAULT NULL,
            contract_value_raw VARCHAR(100) DEFAULT NULL,
            advertisement_at DATETIME DEFAULT NULL,
            advertisement_raw VARCHAR(50) DEFAULT NULL,
            detail_url VARCHAR(255) NOT NULL,
            pkg_lot_id VARCHAR(50) DEFAULT NULL,
            record_hash CHAR(64) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_contract_record_hash (record_hash),
            KEY idx_contract_records_tender_id (tender_id),
            KEY idx_contract_records_page (source_page_no),
            KEY idx_contract_records_run (source_run_id),
            CONSTRAINT fk_contract_records_run
                FOREIGN KEY (source_run_id) REFERENCES scrape_runs(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset}"
    );
} catch (PDOException $exception) {
    http_response_code(500);
    exit('Database connection failed: ' . $exception->getMessage());
}
