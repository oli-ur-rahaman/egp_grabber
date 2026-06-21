<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/sources.php';

header('Content-Type: application/json; charset=UTF-8');

const HTTP_HEADERS = [
    'Accept: text/html, */*; q=0.01',
    'Accept-Language: en-US,en;q=0.9',
    'X-Requested-With: XMLHttpRequest',
    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
];

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $method === 'POST'
        ? strtolower(trim((string) ($_POST['action'] ?? 'status')))
        : strtolower(trim((string) ($_GET['action'] ?? 'status')));

    switch ($action) {
        case 'start':
            requirePost($method);
            startRun($pdo);
            break;

        case 'step':
            requirePost($method);
            processStep($pdo);
            break;

        case 'stop':
            requirePost($method);
            stopRun($pdo);
            break;

        case 'resume':
            requirePost($method);
            resumeRun($pdo);
            break;

        case 'status':
            sendJson(buildStatusPayload($pdo, isset($_GET['runId']) ? (int) $_GET['runId'] : null));
            break;

        default:
            sendJson(['success' => false, 'error' => 'Unsupported action.'], 400);
    }
} catch (Throwable $exception) {
    sendJson([
        'success' => false,
        'error' => $exception->getMessage(),
    ], 500);
}

function requirePost(string $method): void
{
    if ($method !== 'POST') {
        sendJson(['success' => false, 'error' => 'POST required.'], 405);
    }
}

function startRun(PDO $pdo): void
{
    $sources = getSourceDefinitions();
    $sourceKey = trim((string) ($_POST['sourceKey'] ?? ''));
    if (!isset($sources[$sourceKey])) {
        sendJson(['success' => false, 'error' => 'Invalid source.'], 422);
    }

    $criteria = normalizeCriteria($sourceKey, $_POST);
    $summary = summarizeCriteria($sourceKey, $criteria);
    $pageSize = max(1, min(100, (int) ($criteria['pageSize'] ?? $sources[$sourceKey]['default_page_size'])));

    $pdo->beginTransaction();
    $pdo->prepare(
        "UPDATE egp_scrape_runs
         SET status = 'stopped', finished_at = NOW(), updated_at = NOW()
         WHERE status = 'running'"
    )->execute();

    $statement = $pdo->prepare(
        "INSERT INTO egp_scrape_runs (
            source_key,
            source_label,
            criteria_json,
            criteria_summary,
            page_size,
            status,
            resume_mode,
            resume_scan_page,
            started_at
        ) VALUES (
            :source_key,
            :source_label,
            :criteria_json,
            :criteria_summary,
            :page_size,
            'running',
            'normal',
            1,
            NOW()
        )"
    );

    $statement->execute([
        ':source_key' => $sourceKey,
        ':source_label' => $sources[$sourceKey]['label'],
        ':criteria_json' => json_encode($criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':criteria_summary' => $summary,
        ':page_size' => $pageSize,
    ]);

    $runId = (int) $pdo->lastInsertId();
    $pdo->commit();

    sendJson([
        'success' => true,
        'runId' => $runId,
        'status' => buildStatusPayload($pdo, $runId),
    ]);
}

function resumeRun(PDO $pdo): void
{
    $runId = (int) ($_POST['runId'] ?? 0);
    $run = fetchRun($pdo, $runId);
    if (!$run) {
        sendJson(['success' => false, 'error' => 'Run not found.'], 404);
    }

    if ($run['status'] !== 'stopped') {
        sendJson(['success' => false, 'error' => 'Only stopped runs can be resumed.'], 409);
    }

    $pdo->beginTransaction();
    $pdo->prepare(
        "UPDATE egp_scrape_runs
         SET status = 'stopped', finished_at = NOW()
         WHERE status = 'running' AND id <> :id"
    )->execute([':id' => $runId]);

    $pdo->prepare(
        "UPDATE egp_scrape_runs
         SET status = 'running',
             resume_mode = 'reconcile',
             resume_scan_page = 1,
             finished_at = NULL,
             last_error = NULL
         WHERE id = :id"
    )->execute([':id' => $runId]);
    $pdo->commit();

    sendJson([
        'success' => true,
        'runId' => $runId,
        'status' => buildStatusPayload($pdo, $runId),
    ]);
}

function stopRun(PDO $pdo): void
{
    $runId = (int) ($_POST['runId'] ?? 0);
    if ($runId < 1) {
        sendJson(['success' => false, 'error' => 'Missing run id.'], 422);
    }

    $pdo->prepare(
        "UPDATE egp_scrape_runs
         SET status = 'stopped', finished_at = NOW()
         WHERE id = :id AND status IN ('running', 'pending')"
    )->execute([':id' => $runId]);

    sendJson([
        'success' => true,
        'status' => buildStatusPayload($pdo, $runId),
    ]);
}

function processStep(PDO $pdo): void
{
    $runId = (int) ($_POST['runId'] ?? 0);
    $run = fetchRun($pdo, $runId);
    if (!$run) {
        sendJson(['success' => false, 'error' => 'Run not found.'], 404);
    }

    if ($run['status'] === 'completed') {
        sendJson([
            'success' => true,
            'completed' => true,
            'status' => buildStatusPayload($pdo, $runId),
        ]);
    }

    if ($run['status'] !== 'running') {
        sendJson(['success' => false, 'error' => 'Run is not active.'], 409);
    }

    $criteria = decodeCriteria($run['criteria_json']);
    $sources = getSourceDefinitions();
    $source = $sources[$run['source_key']];

    $pageNo = $run['resume_mode'] === 'reconcile'
        ? max(1, (int) $run['resume_scan_page'])
        : ((int) $run['last_page_scraped'] + 1);

    $html = fetchSourcePage($source['endpoint'], buildPayload($run['source_key'], $criteria, $pageNo, (int) $run['page_size']));
    $parsed = parseResponse($run['source_key'], $html, $pageNo);
    $records = applyLocalFilters($run['source_key'], $criteria, $parsed['records']);

    $inserted = insertRecordsForRun($pdo, $run, $records);
    $seen = count($records);
    $totalPages = $parsed['totalPages'];

    $firstRecord = $records[0] ?? null;
    $lastRecord = $records !== [] ? $records[array_key_last($records)] : null;
    $foundEarliestAnchor = false;

    if ($run['resume_mode'] === 'reconcile' && $run['earliest_anchor_hash']) {
        foreach ($records as $record) {
            if ($record['record_hash'] === $run['earliest_anchor_hash']) {
                $foundEarliestAnchor = true;
                break;
            }
        }
    }

    $nextStatus = 'running';
    $nextResumeMode = $run['resume_mode'];
    $nextResumePage = $pageNo + 1;
    $nextLastPage = max((int) $run['last_page_scraped'], $pageNo);

    if ($parsed['noRecordFound']) {
        $nextStatus = 'completed';
    } elseif ($run['resume_mode'] === 'reconcile' && $foundEarliestAnchor) {
        $nextResumeMode = 'normal';
    } elseif ($run['resume_mode'] === 'reconcile' && $totalPages !== null && $pageNo >= $totalPages) {
        $nextResumeMode = 'normal';
        $nextStatus = 'completed';
    } elseif ($run['resume_mode'] === 'normal' && $totalPages !== null && $pageNo >= $totalPages) {
        $nextStatus = 'completed';
    }

    $pdo->prepare(
        "UPDATE egp_scrape_runs
         SET total_pages = COALESCE(:total_pages, total_pages),
             last_page_scraped = :last_page_scraped,
             total_records_seen = total_records_seen + :seen,
             total_records_inserted = total_records_inserted + :inserted,
             latest_anchor_hash = COALESCE(:latest_anchor_hash, latest_anchor_hash),
             latest_anchor_label = COALESCE(:latest_anchor_label, latest_anchor_label),
             earliest_anchor_hash = COALESCE(:earliest_anchor_hash, earliest_anchor_hash),
             earliest_anchor_label = COALESCE(:earliest_anchor_label, earliest_anchor_label),
             resume_mode = :resume_mode,
             resume_scan_page = :resume_scan_page,
             status = :status_value,
             last_error = NULL,
             finished_at = CASE WHEN :status_case = 'completed' THEN NOW() ELSE finished_at END
         WHERE id = :id"
    )->execute([
        ':total_pages' => $totalPages,
        ':last_page_scraped' => $nextLastPage,
        ':seen' => $seen,
        ':inserted' => $inserted,
        ':latest_anchor_hash' => $firstRecord['record_hash'] ?? null,
        ':latest_anchor_label' => $firstRecord['anchor_label'] ?? null,
        ':earliest_anchor_hash' => $lastRecord['record_hash'] ?? null,
        ':earliest_anchor_label' => $lastRecord['anchor_label'] ?? null,
        ':resume_mode' => $nextResumeMode,
        ':resume_scan_page' => $nextResumePage,
        ':status_value' => $nextStatus,
        ':status_case' => $nextStatus,
        ':id' => $runId,
    ]);

    sendJson([
        'success' => true,
        'runId' => $runId,
        'pageProcessed' => $pageNo,
        'recordsSeen' => $seen,
        'recordsInserted' => $inserted,
        'completed' => $nextStatus === 'completed',
        'status' => buildStatusPayload($pdo, $runId),
    ]);
}

function buildStatusPayload(PDO $pdo, ?int $selectedRunId = null): array
{
    $sources = getSourceDefinitions();

    $runs = $pdo->query(
        "SELECT *
         FROM egp_scrape_runs
         ORDER BY id DESC
         LIMIT 25"
    )->fetchAll();

    $activeRun = null;
    foreach ($runs as $run) {
        if ($run['status'] === 'running') {
            $activeRun = $run;
            break;
        }
    }

    $selectedRun = null;
    if ($selectedRunId) {
        $selectedRun = fetchRun($pdo, $selectedRunId);
    }
    if (!$selectedRun) {
        $selectedRun = $activeRun ?: ($runs[0] ?? null);
    }

    $counts = [];
    foreach ($sources as $sourceKey => $definition) {
        $counts[$sourceKey] = (int) $pdo->query("SELECT COUNT(*) FROM {$definition['table']}")->fetchColumn();
    }

    $preview = [
        'sourceKey' => null,
        'columns' => [],
        'records' => [],
    ];

    if ($selectedRun) {
        $preview = [
            'sourceKey' => $selectedRun['source_key'],
            'columns' => $sources[$selectedRun['source_key']]['preview_columns'],
            'records' => fetchPreviewRows($pdo, $selectedRun),
        ];
    }

    return [
        'success' => true,
        'counts' => $counts,
        'activeRunId' => $activeRun['id'] ?? null,
        'selectedRunId' => $selectedRun['id'] ?? null,
        'runs' => array_map(static function (array $run): array {
            $run['criteria'] = decodeCriteria($run['criteria_json']);
            return $run;
        }, $runs),
        'preview' => $preview,
    ];
}

function fetchPreviewRows(PDO $pdo, array $run): array
{
    $definitions = getSourceDefinitions();
    $table = $definitions[$run['source_key']]['table'];
    $statement = $pdo->prepare("SELECT * FROM {$table} WHERE source_run_id = :run_id ORDER BY id DESC LIMIT 20");
    $statement->execute([':run_id' => $run['id']]);
    return $statement->fetchAll();
}

function fetchRun(PDO $pdo, int $runId): ?array
{
    if ($runId < 1) {
        return null;
    }

    $statement = $pdo->prepare("SELECT * FROM egp_scrape_runs WHERE id = :id");
    $statement->execute([':id' => $runId]);
    $row = $statement->fetch();
    return $row ?: null;
}

function decodeCriteria(string $criteriaJson): array
{
    $criteria = json_decode($criteriaJson, true);
    return is_array($criteria) ? $criteria : [];
}

function normalizeCriteria(string $sourceKey, array $payload): array
{
    $pageSize = max(1, min(100, (int) ($payload['pageSize'] ?? 10)));

    return match ($sourceKey) {
        'eTender' => [
            'viewType' => normalizeEnum((string) ($payload['viewType'] ?? 'Live'), ['Live', 'Archive', 'Cancelled', 'All'], 'Live'),
            'procuringEntity' => trim((string) ($payload['procuringEntity'] ?? '')),
            'procurementNature' => normalizeEnum((string) ($payload['procurementNature'] ?? ''), ['Goods', 'Works', 'Service', 'Physical Services'], ''),
            'procurementMethod' => normalizeEnum((string) ($payload['procurementMethod'] ?? ''), procurementMethodLabels(), ''),
            'publishingDateFrom' => normalizeOptionalInputDate((string) ($payload['publishingDateFrom'] ?? '')),
            'publishingDateTo' => normalizeOptionalInputDate((string) ($payload['publishingDateTo'] ?? '')),
            'closingDateFrom' => normalizeOptionalInputDate((string) ($payload['closingDateFrom'] ?? '')),
            'closingDateTo' => normalizeOptionalInputDate((string) ($payload['closingDateTo'] ?? '')),
            'frameworkAgreement' => normalizeEnum((string) ($payload['frameworkAgreement'] ?? ''), ['Yes', 'No'], ''),
            'pageSize' => $pageSize,
        ],
        'APP' => [
            'procuringEntity' => trim((string) ($payload['procuringEntity'] ?? '')),
            'procurementNature' => normalizeEnum((string) ($payload['procurementNature'] ?? ''), ['Goods', 'Works', 'Service', 'Physical Services'], ''),
            'financialYear' => trim((string) ($payload['financialYear'] ?? '')),
            'budgetType' => normalizeEnum((string) ($payload['budgetType'] ?? ''), ['Development', 'Revenue', 'Own fund'], ''),
            'pageSize' => $pageSize,
        ],
        'eContract' => [
            'procuringEntity' => trim((string) ($payload['procuringEntity'] ?? '')),
            'procurementMethod' => normalizeEnum((string) ($payload['procurementMethod'] ?? ''), procurementMethodLabels(), ''),
            'district' => trim((string) ($payload['district'] ?? '')),
            'contractAwardedTo' => trim((string) ($payload['contractAwardedTo'] ?? '')),
            'frameworkAgreement' => normalizeEnum((string) ($payload['frameworkAgreement'] ?? ''), ['Yes', 'No'], ''),
            'contractSignDateFrom' => normalizeOptionalInputDate((string) ($payload['contractSignDateFrom'] ?? '')),
            'contractSignDateTo' => normalizeOptionalInputDate((string) ($payload['contractSignDateTo'] ?? '')),
            'pageSize' => $pageSize,
        ],
        'eExperience' => [
            'procuringEntity' => trim((string) ($payload['procuringEntity'] ?? '')),
            'procurementNature' => normalizeEnum((string) ($payload['procurementNature'] ?? ''), ['Goods', 'Works', 'Service', 'Physical Services'], ''),
            'procurementMethod' => normalizeEnum((string) ($payload['procurementMethod'] ?? ''), procurementMethodLabels(), ''),
            'contractStartDateFrom' => normalizeOptionalInputDate((string) ($payload['contractStartDateFrom'] ?? '')),
            'contractStartDateTo' => normalizeOptionalInputDate((string) ($payload['contractStartDateTo'] ?? '')),
            'contractEndDateFrom' => normalizeOptionalInputDate((string) ($payload['contractEndDateFrom'] ?? '')),
            'contractEndDateTo' => normalizeOptionalInputDate((string) ($payload['contractEndDateTo'] ?? '')),
            'workStatus' => normalizeEnum((string) ($payload['workStatus'] ?? 'All'), ['All', 'Completed', 'Ongoing'], 'All'),
            'contractAwardedToMatch' => normalizeEnum((string) ($payload['contractAwardedToMatch'] ?? 'Contains'), ['Contains', 'Equals'], 'Contains'),
            'contractAwardedTo' => trim((string) ($payload['contractAwardedTo'] ?? '')),
            'companyUniqueId' => trim((string) ($payload['companyUniqueId'] ?? '')),
            'tenderType' => normalizeEnum((string) ($payload['tenderType'] ?? 'eTenders'), ['eTenders', 'eCMS', 'Manual', 'All'], 'eTenders'),
            'pageSize' => $pageSize,
        ],
        default => throw new InvalidArgumentException('Invalid source.'),
    };
}

function summarizeCriteria(string $sourceKey, array $criteria): string
{
    $parts = [];
    foreach ($criteria as $key => $value) {
        if ($key === 'pageSize' || $value === '' || $value === null) {
            continue;
        }
        $parts[] = humanizeKey($key) . ': ' . $value;
    }

    $prefix = match ($sourceKey) {
        'eTender' => 'eTender',
        'APP' => 'APP',
        'eContract' => 'eContract',
        'eExperience' => 'eExperience',
        default => $sourceKey,
    };

    return $prefix . ($parts ? ' | ' . implode(' | ', $parts) : '');
}

function humanizeKey(string $key): string
{
    $text = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
    return ucwords((string) $text);
}

function procurementMethodLabels(): array
{
    return ['RFQ', 'OTM', 'LTM', 'TSTM', 'QCBS', 'LCS', 'SFB', 'DC', 'SBCQ', 'SSS', 'IC', 'CSO', 'DPM', 'OSTETM', 'RFQU', 'RFQL'];
}

function normalizeEnum(string $value, array $allowed, string $default): string
{
    $value = trim($value);
    return in_array($value, $allowed, true) ? $value : $default;
}

function normalizeOptionalInputDate(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('d/m/Y', $value);
    if (!$date) {
        throw new InvalidArgumentException('Dates must use dd/mm/yyyy.');
    }

    return $date->format('d/m/Y');
}

function buildPayload(string $sourceKey, array $criteria, int $pageNo, int $pageSize): array
{
    return match ($sourceKey) {
        'eTender' => [
            'funName' => 'AllTenders',
            'departmentId' => '',
            'viewType' => $criteria['viewType'] ?: 'Live',
            'office' => '',
            'procNature' => $criteria['procurementNature'],
            'procType' => '',
            'procMethod' => mapProcurementMethodValue($criteria['procurementMethod']),
            'tenderId' => '',
            'refNo' => '',
            'pubDtFrm' => $criteria['publishingDateFrom'],
            'pubDtTo' => $criteria['publishingDateTo'],
            'closeDtFrm' => $criteria['closingDateFrom'],
            'closeDtTo' => $criteria['closingDateTo'],
            'cpvCategory' => '',
            'isFrame' => mapYesNoSelect($criteria['frameworkAgreement'], '- Select -'),
            'pageNo' => (string) $pageNo,
            'size' => (string) $pageSize,
            'h' => 't',
        ],
        'APP' => [
            'action' => 'Search',
            'departmentId' => '',
            'office' => '',
            'project' => ' ',
            'financialYear' => $criteria['financialYear'],
            'budgetType' => $criteria['budgetType'],
            'procNature' => $criteria['procurementNature'],
            'procType' => '',
            'appId' => '',
            'appCode' => '',
            'pkgNo' => '',
            'operation' => '',
            'value' => '',
            'value2' => '',
            'pageNo' => (string) $pageNo,
            'size' => (string) $pageSize,
            'cpvCat' => '',
        ],
        'eContract' => [
            'keyword' => '',
            'officeId' => '0',
            'contractAwardTo' => $criteria['contractAwardedTo'],
            'noaDt' => '',
            'stateName' => $criteria['district'] !== '' ? $criteria['district'] : ' ',
            'departmentId' => '',
            'tenderId' => '',
            'contractNo' => '',
            'contractDtFrom' => $criteria['contractSignDateFrom'],
            'contractDtTo' => $criteria['contractSignDateTo'],
            'tenderRefNo' => '',
            'contractAmount' => '',
            'cpvCode' => '',
            'advDt' => '',
            'procurementMethod' => mapProcurementMethodValue($criteria['procurementMethod']),
            'isFrame' => mapYesNoSelect($criteria['frameworkAgreement'], '- Please Select -'),
            'pageNo' => (string) $pageNo,
            'size' => (string) $pageSize,
        ],
        'eExperience' => [
            'action' => 'geteCMSList',
            'keyword' => '',
            'expCertNo' => '',
            'officeId' => '0',
            'contractAwardTo' => $criteria['contractAwardedTo'],
            'contractStartDtFrom' => formatToPortalIso($criteria['contractStartDateFrom']),
            'contractStartDtTo' => formatToPortalIso($criteria['contractStartDateTo']),
            'contractEndDtFrom' => formatToPortalIso($criteria['contractEndDateFrom']),
            'contractEndDtTo' => formatToPortalIso($criteria['contractEndDateTo']),
            'departmentId' => '',
            'tenderId' => '',
            'contractAmount' => '',
            'procurementMethod' => mapProcurementMethodValue($criteria['procurementMethod']),
            'procurementNature' => $criteria['procurementNature'],
            'contAwrdSearchOpt' => $criteria['contractAwardedToMatch'],
            'exCertSearchOpt' => 'Contains',
            'exCertificateNo' => '',
            'tendererId' => $criteria['companyUniqueId'],
            'procType' => '',
            'statusTab' => $criteria['tenderType'],
            'pageNo' => (string) $pageNo,
            'size' => (string) $pageSize,
            'workStatus' => $criteria['workStatus'],
        ],
        default => throw new InvalidArgumentException('Invalid source.'),
    };
}

function mapProcurementMethodValue(string $label): string
{
    $map = [
        'RFQ' => '1',
        'OTM' => '2',
        'LTM' => '3',
        'TSTM' => '5',
        'QCBS' => '6',
        'LCS' => '7',
        'SFB' => '8',
        'DC' => '9',
        'SBCQ' => '10',
        'SSS' => '11',
        'IC' => '12',
        'CSO' => '13',
        'DPM' => '14',
        'OSTETM' => '15',
        'RFQU' => '16',
        'RFQL' => '17',
    ];

    return $map[$label] ?? '';
}

function mapYesNoSelect(string $label, string $emptyLabel): string
{
    if ($label === 'Yes') {
        return '1';
    }
    if ($label === 'No') {
        return '2';
    }
    return $emptyLabel === '- Select -' ? '0' : '0';
}

function formatToPortalIso(string $value): string
{
    if ($value === '') {
        return '';
    }
    $date = DateTimeImmutable::createFromFormat('d/m/Y', $value);
    return $date ? $date->format('Y-m-d') : '';
}

function fetchSourcePage(string $url, array $payload): string
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => HTTP_HEADERS,
    ]);

    $response = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $error !== '') {
        throw new RuntimeException('Failed to fetch source page: ' . $error);
    }

    if ($statusCode !== 200) {
        throw new RuntimeException('Source endpoint returned HTTP ' . $statusCode . '.');
    }

    return $response;
}

function parseResponse(string $sourceKey, string $html, int $pageNo): array
{
    if (stripos($html, 'id="noRecordFound"') !== false) {
        return ['records' => [], 'totalPages' => 1, 'noRecordFound' => true];
    }

    $wrapped = '<table><tbody>' . $html . '</tbody></table>';
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped);
    $xpath = new DOMXPath($dom);
    $rows = $xpath->query('//tr');

    $records = [];
    $rowNo = 0;
    foreach ($rows as $row) {
        $cells = $xpath->query('./td', $row);
        if (!$cells instanceof DOMNodeList) {
            continue;
        }

        $expected = match ($sourceKey) {
            'eTender' => 6,
            'APP' => 7,
            'eContract' => 8,
            'eExperience' => 10,
            default => 999,
        };

        if ($cells->length < $expected) {
            continue;
        }

        $rowNo++;
        $records[] = match ($sourceKey) {
            'eTender' => parseTenderRow($cells, $pageNo, $rowNo),
            'APP' => parseAppRow($cells, $pageNo, $rowNo),
            'eContract' => parseContractRow($cells, $pageNo, $rowNo),
            'eExperience' => parseExperienceRow($cells, $pageNo, $rowNo),
        };
    }

    $totalPages = null;
    if (preg_match('/id="totalPages"\s+value="(\d+)"/i', $html, $matches) === 1) {
        $totalPages = (int) $matches[1];
    }

    return [
        'records' => $records,
        'totalPages' => $totalPages,
        'noRecordFound' => false,
    ];
}

function applyLocalFilters(string $sourceKey, array $criteria, array $records): array
{
    return array_values(array_filter($records, static function (array $record) use ($sourceKey, $criteria): bool {
        $entityNeedle = trim((string) ($criteria['procuringEntity'] ?? ''));
        if ($entityNeedle !== '') {
            $haystack = match ($sourceKey) {
                'eTender' => ($record['ministry_division_organization'] ?? '') . ' ' . ($record['procuring_entity'] ?? ''),
                'APP' => ($record['ministry_division_organization'] ?? '') . ' ' . ($record['procuring_entity'] ?? ''),
                'eContract' => ($record['procuring_entity'] ?? ''),
                'eExperience' => ($record['ministry_division_organization'] ?? '') . ' ' . ($record['procuring_entity'] ?? ''),
                default => '',
            };

            if (stripos($haystack, $entityNeedle) === false) {
                return false;
            }
        }

        return true;
    }));
}

function insertRecordsForRun(PDO $pdo, array $run, array $records): int
{
    if ($records === []) {
        return 0;
    }

    return match ($run['source_key']) {
        'eTender' => insertTenderRecords($pdo, (int) $run['id'], $records),
        'APP' => insertAppRecords($pdo, (int) $run['id'], $records),
        'eContract' => insertContractRecords($pdo, (int) $run['id'], $records),
        'eExperience' => insertExperienceRecords($pdo, (int) $run['id'], $records),
        default => 0,
    };
}

function insertTenderRecords(PDO $pdo, int $runId, array $records): int
{
    $statement = $pdo->prepare(
        "INSERT INTO egp_tender_records (
            source_run_id, source_page_no, source_row_no, tender_id, reference_no, tender_status,
            procurement_nature, tender_title, ministry_division_organization, procuring_entity,
            procurement_type, procurement_method, publishing_at, publishing_raw, closing_at, closing_raw,
            detail_url, record_hash
        ) VALUES (
            :run_id, :source_page_no, :source_row_no, :tender_id, :reference_no, :tender_status,
            :procurement_nature, :tender_title, :ministry_division_organization, :procuring_entity,
            :procurement_type, :procurement_method, :publishing_at, :publishing_raw, :closing_at, :closing_raw,
            :detail_url, :record_hash
        ) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP"
    );

    return executeInsertLoop($statement, $runId, $records);
}

function insertAppRecords(PDO $pdo, int $runId, array $records): int
{
    $statement = $pdo->prepare(
        "INSERT INTO egp_app_records (
            source_run_id, source_page_no, source_row_no, app_id, app_code, ministry_division_organization,
            procuring_entity, district, procurement_nature, project_name, package_no, package_description,
            estimated_cost_value, estimated_cost_raw, procurement_method, detail_url, record_hash
        ) VALUES (
            :run_id, :source_page_no, :source_row_no, :app_id, :app_code, :ministry_division_organization,
            :procuring_entity, :district, :procurement_nature, :project_name, :package_no, :package_description,
            :estimated_cost_value, :estimated_cost_raw, :procurement_method, :detail_url, :record_hash
        ) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP"
    );

    return executeInsertLoop($statement, $runId, $records);
}

function insertContractRecords(PDO $pdo, int $runId, array $records): int
{
    $statement = $pdo->prepare(
        "INSERT INTO egp_contract_records (
            source_run_id, source_page_no, source_row_no, tender_id, invitation_ref_no, tender_title,
            ministry_division, procuring_entity, procurement_method, district, notification_of_award_date,
            notification_of_award_raw, contract_award_to, contract_value_cr_bdt, contract_value_raw,
            advertisement_at, advertisement_raw, detail_url, record_hash
        ) VALUES (
            :run_id, :source_page_no, :source_row_no, :tender_id, :invitation_ref_no, :tender_title,
            :ministry_division, :procuring_entity, :procurement_method, :district, :notification_of_award_date,
            :notification_of_award_raw, :contract_award_to, :contract_value_cr_bdt, :contract_value_raw,
            :advertisement_at, :advertisement_raw, :detail_url, :record_hash
        ) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP"
    );

    return executeInsertLoop($statement, $runId, $records);
}

function insertExperienceRecords(PDO $pdo, int $runId, array $records): int
{
    $statement = $pdo->prepare(
        "INSERT INTO egp_experience_records (
            source_run_id, source_page_no, source_row_no, ministry_division_organization, procuring_entity,
            procurement_nature, procurement_type, procurement_method, tender_id, reference_no, tender_title,
            publishing_date, publishing_raw, contract_awarded_to, company_unique_id, experience_certificate_no,
            contract_amount_value, contract_amount_raw, contract_start_date, contract_start_raw,
            contract_end_date, contract_end_raw, work_status, detail_url, record_hash
        ) VALUES (
            :run_id, :source_page_no, :source_row_no, :ministry_division_organization, :procuring_entity,
            :procurement_nature, :procurement_type, :procurement_method, :tender_id, :reference_no, :tender_title,
            :publishing_date, :publishing_raw, :contract_awarded_to, :company_unique_id, :experience_certificate_no,
            :contract_amount_value, :contract_amount_raw, :contract_start_date, :contract_start_raw,
            :contract_end_date, :contract_end_raw, :work_status, :detail_url, :record_hash
        ) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP"
    );

    return executeInsertLoop($statement, $runId, $records);
}

function executeInsertLoop(PDOStatement $statement, int $runId, array $records): int
{
    $inserted = 0;

    foreach ($records as $record) {
        $params = [':run_id' => $runId];
        foreach ($record as $key => $value) {
            if (in_array($key, ['anchor_label'], true)) {
                continue;
            }
            $params[':' . $key] = $value;
        }

        $statement->execute($params);
        if ($statement->rowCount() === 1) {
            $inserted++;
        }
    }

    return $inserted;
}

function parseTenderRow(DOMNodeList $cells, int $pageNo, int $rowNo): array
{
    $idBits = extractCellLines($cells->item(1));
    $titleCell = $cells->item(2);
    $natureLines = extractCellLines($titleCell);
    $orgLines = extractCellLines($cells->item(3));
    $typeMethodLines = extractCellLines($cells->item(4));
    $dateLines = extractCellLines($cells->item(5));

    $form = firstDescendantByTag($titleCell, 'form');
    $detailUrl = '';
    if ($form instanceof DOMElement) {
        $tenderId = findHiddenInputValue($form, 'id');
        $h = findHiddenInputValue($form, 'h');
        if ($tenderId !== '') {
            $detailUrl = 'https://www.eprocure.gov.bd/resources/common/ViewTender.jsp?id=' . rawurlencode($tenderId) . '&h=' . rawurlencode($h);
        }
    }

    $record = [
        'source_page_no' => $pageNo,
        'source_row_no' => $rowNo,
        'tender_id' => trim(str_replace(',', '', $idBits[0] ?? '')),
        'reference_no' => rtrim($idBits[1] ?? '', ','),
        'tender_status' => $idBits[2] ?? '',
        'procurement_nature' => rtrim($natureLines[0] ?? '', ','),
        'tender_title' => trim(implode(' ', array_slice($natureLines, 1))),
        'ministry_division_organization' => $orgLines[0] ?? '',
        'procuring_entity' => $orgLines[count($orgLines) - 1] ?? '',
        'procurement_type' => rtrim($typeMethodLines[0] ?? '', ','),
        'procurement_method' => $typeMethodLines[1] ?? '',
        'publishing_at' => parsePortalDateTime($dateLines[0] ?? ''),
        'publishing_raw' => $dateLines[0] ?? '',
        'closing_at' => parsePortalDateTime($dateLines[1] ?? ''),
        'closing_raw' => $dateLines[1] ?? '',
        'detail_url' => $detailUrl,
    ];

    $record['anchor_label'] = $record['tender_id'];
    $record['record_hash'] = hash('sha256', implode('|', [
        $record['tender_id'],
        $record['reference_no'],
        $record['tender_status'],
        $record['closing_raw'],
    ]));

    return $record;
}

function parseAppRow(DOMNodeList $cells, int $pageNo, int $rowNo): array
{
    $idLines = extractCellLines($cells->item(1));
    $orgLines = extractCellLines($cells->item(2));
    $natureLines = extractCellLines($cells->item(4));
    $packageLines = extractCellLines($cells->item(5));
    $costLines = extractCellLines($cells->item(6));
    $detailUrl = normalizeUrl(firstDescendantByTag($cells->item(5), 'a')?->getAttribute('href') ?? '');

    $record = [
        'source_page_no' => $pageNo,
        'source_row_no' => $rowNo,
        'app_id' => trim(str_replace(',', '', $idLines[0] ?? '')),
        'app_code' => $idLines[1] ?? '',
        'ministry_division_organization' => $orgLines[0] ?? '',
        'procuring_entity' => $orgLines[count($orgLines) - 1] ?? '',
        'district' => cleanNodeText($cells->item(3)),
        'procurement_nature' => rtrim($natureLines[0] ?? '', ','),
        'project_name' => trim(implode(' ', array_slice($natureLines, 1))),
        'package_no' => rtrim($packageLines[0] ?? '', ','),
        'package_description' => trim(implode(' ', array_slice($packageLines, 1))),
        'estimated_cost_value' => parseDecimal($costLines[0] ?? ''),
        'estimated_cost_raw' => rtrim($costLines[0] ?? '', ','),
        'procurement_method' => $costLines[1] ?? '',
        'detail_url' => $detailUrl,
    ];

    $record['anchor_label'] = $record['app_id'];
    $record['record_hash'] = hash('sha256', implode('|', [
        $record['app_id'],
        $record['package_no'],
        $record['estimated_cost_raw'],
        $record['detail_url'],
    ]));

    return $record;
}

function parseContractRow(DOMNodeList $cells, int $pageNo, int $rowNo): array
{
    $ministry = cleanNodeText($cells->item(1));
    $detailCellLines = extractCellLines($cells->item(2));
    $entityMethodLines = extractCellLines($cells->item(3));
    $detailUrl = normalizeUrl(firstDescendantByTag($cells->item(2), 'a')?->getAttribute('href') ?? '');

    $record = [
        'source_page_no' => $pageNo,
        'source_row_no' => $rowNo,
        'tender_id' => trim(explode(',', $detailCellLines[0] ?? '')[0]),
        'invitation_ref_no' => trim(substr($detailCellLines[0] ?? '', strpos($detailCellLines[0] ?? '', ',') + 1)),
        'tender_title' => $detailCellLines[1] ?? '',
        'ministry_division' => $ministry,
        'procuring_entity' => $entityMethodLines[0] ?? '',
        'procurement_method' => $entityMethodLines[1] ?? '',
        'district' => cleanNodeText($cells->item(4)),
        'notification_of_award_date' => parsePortalDate(cleanNodeText($cells->item(5))),
        'notification_of_award_raw' => cleanNodeText($cells->item(5)),
        'contract_award_to' => cleanNodeText($cells->item(6)),
        'contract_value_cr_bdt' => parseDecimal(cleanNodeText($cells->item(7))),
        'contract_value_raw' => cleanNodeText($cells->item(7)),
        'advertisement_at' => parsePortalDateTime($detailCellLines[2] ?? ''),
        'advertisement_raw' => $detailCellLines[2] ?? '',
        'detail_url' => $detailUrl,
    ];

    $record['anchor_label'] = $record['tender_id'];
    $record['record_hash'] = hash('sha256', implode('|', [
        $record['tender_id'],
        $record['detail_url'],
        $record['contract_award_to'],
        $record['notification_of_award_raw'],
        $record['contract_value_raw'],
    ]));

    return $record;
}

function parseExperienceRow(DOMNodeList $cells, int $pageNo, int $rowNo): array
{
    $orgLines = extractCellLines($cells->item(1));
    $natureLines = extractCellLines($cells->item(2));
    $tenderLines = extractCellLines($cells->item(3));
    $dateLines = extractCellLines($cells->item(8));
    $detailUrl = normalizeUrl(firstDescendantByTag($cells->item(3), 'a')?->getAttribute('href') ?? '');
    [$tenderId, $referenceNo] = splitFirstLineAtComma($tenderLines[0] ?? '');

    $record = [
        'source_page_no' => $pageNo,
        'source_row_no' => $rowNo,
        'ministry_division_organization' => $orgLines[0] ?? '',
        'procuring_entity' => $orgLines[count($orgLines) - 1] ?? '',
        'procurement_nature' => rtrim($natureLines[0] ?? '', ','),
        'procurement_type' => rtrim($natureLines[1] ?? '', ','),
        'procurement_method' => $natureLines[2] ?? '',
        'tender_id' => $tenderId,
        'reference_no' => $referenceNo,
        'tender_title' => $tenderLines[1] ?? '',
        'publishing_date' => parsePortalDate($tenderLines[2] ?? ''),
        'publishing_raw' => $tenderLines[2] ?? '',
        'contract_awarded_to' => cleanNodeText($cells->item(4)),
        'company_unique_id' => cleanNodeText($cells->item(5)),
        'experience_certificate_no' => cleanNodeText($cells->item(6)),
        'contract_amount_value' => parseDecimal(cleanNodeText($cells->item(7))),
        'contract_amount_raw' => cleanNodeText($cells->item(7)),
        'contract_start_date' => parsePortalDate($dateLines[0] ?? ''),
        'contract_start_raw' => $dateLines[0] ?? '',
        'contract_end_date' => parsePortalDate($dateLines[1] ?? ''),
        'contract_end_raw' => $dateLines[1] ?? '',
        'work_status' => cleanNodeText($cells->item(9)),
        'detail_url' => $detailUrl,
    ];

    $record['anchor_label'] = $record['tender_id'] !== '' ? $record['tender_id'] : $record['experience_certificate_no'];
    $record['record_hash'] = hash('sha256', implode('|', [
        $record['tender_id'],
        $record['experience_certificate_no'],
        $record['contract_awarded_to'],
        $record['contract_end_raw'],
    ]));

    return $record;
}

function extractCellLines(?DOMNode $node): array
{
    if (!$node || !$node->ownerDocument) {
        return [];
    }

    $html = $node->ownerDocument->saveHTML($node);
    if ($html === false) {
        return [];
    }

    $html = preg_replace('/^<td[^>]*>|<\/td>$/i', '', $html);
    $html = preg_replace('/<br\s*\/?>/i', "\n", (string) $html);
    $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5);
    $lines = preg_split('/\R+/', $text) ?: [];
    $lines = array_map(static fn (string $line): string => trim(preg_replace('/\s+/', ' ', $line)), $lines);
    return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
}

function splitFirstLineAtComma(string $value): array
{
    $parts = explode(',', $value, 2);
    return [trim($parts[0] ?? ''), trim($parts[1] ?? '')];
}

function cleanNodeText(?DOMNode $node): string
{
    if (!$node) {
        return '';
    }

    return trim((string) preg_replace('/\s+/', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5)));
}

function firstDescendantByTag(?DOMNode $node, string $tag): ?DOMElement
{
    if (!$node || !$node->ownerDocument) {
        return null;
    }

    $xpath = new DOMXPath($node->ownerDocument);
    $result = $xpath->query('.//' . $tag, $node);
    return $result && $result->length > 0 && $result->item(0) instanceof DOMElement ? $result->item(0) : null;
}

function findHiddenInputValue(DOMElement $scope, string $name): string
{
    $xpath = new DOMXPath($scope->ownerDocument);
    $result = $xpath->query('.//input[@type="hidden" and @name="' . $name . '"]', $scope);
    return $result && $result->length > 0 && $result->item(0) instanceof DOMElement
        ? (string) $result->item(0)->getAttribute('value')
        : '';
}

function normalizeUrl(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
        return $url;
    }
    return 'https://www.eprocure.gov.bd/' . ltrim($url, '/');
}

function parsePortalDate(string $value): ?string
{
    $value = trim($value, " \t\n\r\0\x0B,");
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('d-M-Y', $value);
    return $date ? $date->format('Y-m-d') : null;
}

function parsePortalDateTime(string $value): ?string
{
    $value = trim($value, " \t\n\r\0\x0B,");
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('d-M-Y H:i', $value);
    return $date ? $date->format('Y-m-d H:i:s') : null;
}

function parseDecimal(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $normalized = preg_replace('/[^0-9.\-]/', '', $value);
    return $normalized !== '' ? $normalized : null;
}

function sendJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
