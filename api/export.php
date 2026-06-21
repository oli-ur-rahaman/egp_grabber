<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

$fileName = 'eprocure_pwd_ltm_' . date('Y-m-d_H-i-s') . '.csv';

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

fputcsv($output, [
    'Tender ID',
    'Invitation Reference No',
    'Tender Title',
    'Ministry/Division',
    'Procuring Entity',
    'Procurement Method',
    'District',
    'Date of Notification of Award',
    'Awarded To',
    'Contract Value (Cr. BDT)',
    'Advertisement Date',
    'Detail URL',
    'Imported Run ID',
    'Imported Page No',
    'Imported At',
]);

$statement = $pdo->query(
    "SELECT
        tender_id,
        invitation_ref_no,
        tender_title,
        ministry_division,
        procuring_entity,
        procurement_method,
        district,
        notification_of_award_raw,
        contract_award_to,
        contract_value_raw,
        advertisement_raw,
        detail_url,
        source_run_id,
        source_page_no,
        created_at
     FROM contract_records
     ORDER BY id ASC"
);

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
        $row['source_run_id'],
        $row['source_page_no'],
        $row['created_at'],
    ]);
}

fclose($output);
