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
        "CREATE TABLE IF NOT EXISTS egp_scrape_runs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source_key VARCHAR(30) NOT NULL,
            source_label VARCHAR(100) NOT NULL,
            criteria_json LONGTEXT NOT NULL,
            criteria_summary TEXT NOT NULL,
            page_size INT UNSIGNED NOT NULL DEFAULT 10,
            total_pages INT UNSIGNED DEFAULT NULL,
            last_page_scraped INT UNSIGNED NOT NULL DEFAULT 0,
            total_records_seen INT UNSIGNED NOT NULL DEFAULT 0,
            total_records_inserted INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            resume_mode VARCHAR(20) NOT NULL DEFAULT 'normal',
            resume_scan_page INT UNSIGNED DEFAULT 1,
            latest_anchor_hash CHAR(64) DEFAULT NULL,
            earliest_anchor_hash CHAR(64) DEFAULT NULL,
            latest_anchor_label VARCHAR(255) DEFAULT NULL,
            earliest_anchor_label VARCHAR(255) DEFAULT NULL,
            last_error TEXT DEFAULT NULL,
            started_at DATETIME DEFAULT NULL,
            finished_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_runs_source (source_key),
            KEY idx_runs_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset}"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS egp_contract_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source_run_id INT UNSIGNED NOT NULL,
            source_page_no INT UNSIGNED NOT NULL,
            source_row_no INT UNSIGNED NOT NULL,
            tender_id VARCHAR(80) NOT NULL,
            invitation_ref_no VARCHAR(255) DEFAULT NULL,
            tender_title TEXT DEFAULT NULL,
            ministry_division VARCHAR(255) NOT NULL,
            procuring_entity VARCHAR(255) DEFAULT NULL,
            procurement_method VARCHAR(100) DEFAULT NULL,
            district VARCHAR(150) DEFAULT NULL,
            notification_of_award_date DATE DEFAULT NULL,
            notification_of_award_raw VARCHAR(50) DEFAULT NULL,
            contract_award_to VARCHAR(255) DEFAULT NULL,
            contract_value_cr_bdt DECIMAL(18,3) DEFAULT NULL,
            contract_value_raw VARCHAR(100) DEFAULT NULL,
            advertisement_at DATETIME DEFAULT NULL,
            advertisement_raw VARCHAR(50) DEFAULT NULL,
            detail_url VARCHAR(255) DEFAULT NULL,
            record_hash CHAR(64) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_contract_run_hash (source_run_id, record_hash),
            KEY idx_contract_run (source_run_id),
            CONSTRAINT fk_contract_run FOREIGN KEY (source_run_id) REFERENCES egp_scrape_runs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset}"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS egp_tender_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source_run_id INT UNSIGNED NOT NULL,
            source_page_no INT UNSIGNED NOT NULL,
            source_row_no INT UNSIGNED NOT NULL,
            tender_id VARCHAR(80) NOT NULL,
            reference_no VARCHAR(255) DEFAULT NULL,
            tender_status VARCHAR(40) DEFAULT NULL,
            procurement_nature VARCHAR(80) DEFAULT NULL,
            tender_title TEXT DEFAULT NULL,
            ministry_division_organization VARCHAR(255) DEFAULT NULL,
            procuring_entity VARCHAR(255) DEFAULT NULL,
            procurement_type VARCHAR(40) DEFAULT NULL,
            procurement_method VARCHAR(100) DEFAULT NULL,
            publishing_at DATETIME DEFAULT NULL,
            publishing_raw VARCHAR(50) DEFAULT NULL,
            closing_at DATETIME DEFAULT NULL,
            closing_raw VARCHAR(50) DEFAULT NULL,
            detail_url VARCHAR(255) DEFAULT NULL,
            record_hash CHAR(64) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tender_run_hash (source_run_id, record_hash),
            KEY idx_tender_run (source_run_id),
            CONSTRAINT fk_tender_run FOREIGN KEY (source_run_id) REFERENCES egp_scrape_runs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset}"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS egp_app_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source_run_id INT UNSIGNED NOT NULL,
            source_page_no INT UNSIGNED NOT NULL,
            source_row_no INT UNSIGNED NOT NULL,
            app_id VARCHAR(80) NOT NULL,
            app_code VARCHAR(255) DEFAULT NULL,
            ministry_division_organization VARCHAR(255) DEFAULT NULL,
            procuring_entity VARCHAR(255) DEFAULT NULL,
            district VARCHAR(150) DEFAULT NULL,
            procurement_nature VARCHAR(80) DEFAULT NULL,
            project_name TEXT DEFAULT NULL,
            package_no VARCHAR(255) DEFAULT NULL,
            package_description TEXT DEFAULT NULL,
            estimated_cost_value DECIMAL(18,2) DEFAULT NULL,
            estimated_cost_raw VARCHAR(100) DEFAULT NULL,
            procurement_method VARCHAR(100) DEFAULT NULL,
            detail_url VARCHAR(255) DEFAULT NULL,
            record_hash CHAR(64) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_app_run_hash (source_run_id, record_hash),
            KEY idx_app_run (source_run_id),
            CONSTRAINT fk_app_run FOREIGN KEY (source_run_id) REFERENCES egp_scrape_runs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset}"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS egp_experience_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source_run_id INT UNSIGNED NOT NULL,
            source_page_no INT UNSIGNED NOT NULL,
            source_row_no INT UNSIGNED NOT NULL,
            ministry_division_organization VARCHAR(255) DEFAULT NULL,
            procuring_entity VARCHAR(255) DEFAULT NULL,
            procurement_nature VARCHAR(80) DEFAULT NULL,
            procurement_type VARCHAR(40) DEFAULT NULL,
            procurement_method VARCHAR(100) DEFAULT NULL,
            tender_id VARCHAR(80) DEFAULT NULL,
            reference_no VARCHAR(255) DEFAULT NULL,
            tender_title TEXT DEFAULT NULL,
            publishing_date DATE DEFAULT NULL,
            publishing_raw VARCHAR(50) DEFAULT NULL,
            contract_awarded_to VARCHAR(255) DEFAULT NULL,
            company_unique_id VARCHAR(80) DEFAULT NULL,
            experience_certificate_no VARCHAR(255) DEFAULT NULL,
            contract_amount_value DECIMAL(18,3) DEFAULT NULL,
            contract_amount_raw VARCHAR(100) DEFAULT NULL,
            contract_start_date DATE DEFAULT NULL,
            contract_start_raw VARCHAR(50) DEFAULT NULL,
            contract_end_date DATE DEFAULT NULL,
            contract_end_raw VARCHAR(50) DEFAULT NULL,
            work_status VARCHAR(40) DEFAULT NULL,
            detail_url VARCHAR(255) DEFAULT NULL,
            record_hash CHAR(64) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_exp_run_hash (source_run_id, record_hash),
            KEY idx_exp_run (source_run_id),
            CONSTRAINT fk_exp_run FOREIGN KEY (source_run_id) REFERENCES egp_scrape_runs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset}"
    );
} catch (PDOException $exception) {
    http_response_code(500);
    exit('Database connection failed: ' . $exception->getMessage());
}
