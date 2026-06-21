<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/sources.php';

$runId = isset($_GET['runId']) ? (int) $_GET['runId'] : 0;
if ($runId < 1) {
    http_response_code(422);
    exit('Missing runId.');
}

$runStatement = $pdo->prepare("SELECT * FROM egp_scrape_runs WHERE id = :id");
$runStatement->execute([':id' => $runId]);
$run = $runStatement->fetch();
if (!$run) {
    http_response_code(404);
    exit('Run not found.');
}

$sources = getSourceDefinitions();
$source = $sources[$run['source_key']] ?? null;
if (!$source) {
    http_response_code(500);
    exit('Invalid run source.');
}

$fileName = sprintf(
    '%s_run_%d_%s.csv',
    strtolower($run['source_key']),
    $runId,
    date('Y-m-d_H-i-s')
);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'wb');
if ($output === false) {
    http_response_code(500);
    exit('Unable to create export stream.');
}

fwrite($output, "\xEF\xBB\xBF");

match ($run['source_key']) {
    'eTender' => exportTenderRun($pdo, $output, $runId),
    'APP' => exportAppRun($pdo, $output, $runId),
    'eContract' => exportContractRun($pdo, $output, $runId),
    'eExperience' => exportExperienceRun($pdo, $output, $runId),
    default => null,
};

fclose($output);

function exportTenderRun(PDO $pdo, $output, int $runId): void
{
    fputcsv($output, [
        'Tender ID',
        'Reference No',
        'Status',
        'Procurement Nature',
        'Title',
        'Ministry/Division/Organization',
        'Procuring Entity',
        'Procurement Type',
        'Procurement Method',
        'Publishing Date',
        'Closing Date',
        'Detail URL',
        'Page No',
        'Imported At',
    ]);

    $statement = $pdo->prepare(
        "SELECT * FROM egp_tender_records WHERE source_run_id = :run_id ORDER BY id ASC"
    );
    $statement->execute([':run_id' => $runId]);

    while ($row = $statement->fetch()) {
        fputcsv($output, [
            $row['tender_id'],
            $row['reference_no'],
            $row['tender_status'],
            $row['procurement_nature'],
            $row['tender_title'],
            $row['ministry_division_organization'],
            $row['procuring_entity'],
            $row['procurement_type'],
            $row['procurement_method'],
            $row['publishing_raw'],
            $row['closing_raw'],
            $row['detail_url'],
            $row['source_page_no'],
            $row['created_at'],
        ]);
    }
}

function exportAppRun(PDO $pdo, $output, int $runId): void
{
    fputcsv($output, [
        'APP ID',
        'APP Code',
        'Ministry/Division/Organization',
        'Procuring Entity',
        'District',
        'Procurement Nature',
        'Project Name',
        'Package No',
        'Package Description',
        'Estimated Cost',
        'Procurement Method',
        'Detail URL',
        'Page No',
        'Imported At',
    ]);

    $statement = $pdo->prepare(
        "SELECT * FROM egp_app_records WHERE source_run_id = :run_id ORDER BY id ASC"
    );
    $statement->execute([':run_id' => $runId]);

    while ($row = $statement->fetch()) {
        fputcsv($output, [
            $row['app_id'],
            $row['app_code'],
            $row['ministry_division_organization'],
            $row['procuring_entity'],
            $row['district'],
            $row['procurement_nature'],
            $row['project_name'],
            $row['package_no'],
            $row['package_description'],
            $row['estimated_cost_raw'],
            $row['procurement_method'],
            $row['detail_url'],
            $row['source_page_no'],
            $row['created_at'],
        ]);
    }
}

function exportContractRun(PDO $pdo, $output, int $runId): void
{
    fputcsv($output, [
        'Tender ID',
        'Reference No',
        'Title',
        'Ministry/Division',
        'Procuring Entity',
        'Procurement Method',
        'District',
        'Date of Notification of Award',
        'Contract Awarded To',
        'Contract Value',
        'Advertisement Date',
        'Detail URL',
        'Page No',
        'Imported At',
    ]);

    $statement = $pdo->prepare(
        "SELECT * FROM egp_contract_records WHERE source_run_id = :run_id ORDER BY id ASC"
    );
    $statement->execute([':run_id' => $runId]);

    while ($row = $statement->fetch()) {
        fputcsv($output, [
            $row['tender_id'],
            $row['invitation_ref_no'],
            $row['tender_title'],
            $row['ministry_division'],
            $row['procuring_entity'],
            $row['procurement_method'],
            $row['district'],
            $row['notification_of_award_raw'],
            $row['contract_award_to'],
            $row['contract_value_raw'],
            $row['advertisement_raw'],
            $row['detail_url'],
            $row['source_page_no'],
            $row['created_at'],
        ]);
    }
}

function exportExperienceRun(PDO $pdo, $output, int $runId): void
{
    fputcsv($output, [
        'Ministry/Division/Organization',
        'Procuring Entity',
        'Procurement Nature',
        'Procurement Type',
        'Procurement Method',
        'Tender ID',
        'Reference No',
        'Title',
        'Publishing Date',
        'Contract Awarded To',
        'Company Unique ID',
        'Experience Certificate No',
        'Contract Amount',
        'Contract Start Date',
        'Contract End Date',
        'Work Status',
        'Detail URL',
        'Page No',
        'Imported At',
    ]);

    $statement = $pdo->prepare(
        "SELECT * FROM egp_experience_records WHERE source_run_id = :run_id ORDER BY id ASC"
    );
    $statement->execute([':run_id' => $runId]);

    while ($row = $statement->fetch()) {
        fputcsv($output, [
            $row['ministry_division_organization'],
            $row['procuring_entity'],
            $row['procurement_nature'],
            $row['procurement_type'],
            $row['procurement_method'],
            $row['tender_id'],
            $row['reference_no'],
            $row['tender_title'],
            $row['publishing_raw'],
            $row['contract_awarded_to'],
            $row['company_unique_id'],
            $row['experience_certificate_no'],
            $row['contract_amount_raw'],
            $row['contract_start_raw'],
            $row['contract_end_raw'],
            $row['work_status'],
            $row['detail_url'],
            $row['source_page_no'],
            $row['created_at'],
        ]);
    }
}
