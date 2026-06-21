<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=UTF-8');

const EPROCURE_URL = 'https://www.eprocure.gov.bd/AdvSearchNOAServlet';
const DEPARTMENT_ID = '21';
const DEPARTMENT_LABEL = 'Public Works Department (PWD)';
const PROCUREMENT_METHOD_CODE = '3';
const PROCUREMENT_METHOD_LABEL = 'LTM';

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $method === 'POST'
        ? strtolower(trim((string) ($_POST['action'] ?? 'status')))
        : strtolower(trim((string) ($_GET['action'] ?? 'status')));

    switch ($action) {
        case 'start':
            ensurePost($method);
            startRun($pdo);
            break;

        case 'step':
            ensurePost($method);
            processStep($pdo);
            break;

        case 'stop':
            ensurePost($method);
            stopRun($pdo);
            break;

        case 'status':
            sendJson(buildStatusPayload($pdo));
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

function ensurePost(string $method): void
{
    if ($method !== 'POST') {
        sendJson(['success' => false, 'error' => 'POST required.'], 405);
    }
}

function startRun(PDO $pdo): void
{
    $from = normalizeInputDate((string) ($_POST['contractDtFrom'] ?? '03/07/2023'));
    $to = normalizeInputDate((string) ($_POST['contractDtTo'] ?? '21/06/2026'));
    $pageSize = max(1, min(100, (int) ($_POST['size'] ?? 10)));

    if ($from > $to) {
        sendJson(['success' => false, 'error' => 'The start date must be before the end date.'], 422);
    }

    $pdo->beginTransaction();

    $pdo->prepare(
        "UPDATE scrape_runs
         SET status = 'stopped', finished_at = NOW(), updated_at = NOW()
         WHERE status = 'running'"
    )->execute();

    $statement = $pdo->prepare(
        "INSERT INTO scrape_runs (
            department_id,
            department_label,
            procurement_method_code,
            procurement_method_label,
            contract_date_from,
            contract_date_to,
            page_size,
            status,
            started_at
        ) VALUES (
            :department_id,
            :department_label,
            :procurement_method_code,
            :procurement_method_label,
            :contract_date_from,
            :contract_date_to,
            :page_size,
            'running',
            NOW()
        )"
    );

    $statement->execute([
        ':department_id' => DEPARTMENT_ID,
        ':department_label' => DEPARTMENT_LABEL,
        ':procurement_method_code' => PROCUREMENT_METHOD_CODE,
        ':procurement_method_label' => PROCUREMENT_METHOD_LABEL,
        ':contract_date_from' => $from->format('Y-m-d'),
        ':contract_date_to' => $to->format('Y-m-d'),
        ':page_size' => $pageSize,
    ]);

    $runId = (int) $pdo->lastInsertId();
    $pdo->commit();

    sendJson([
        'success' => true,
        'runId' => $runId,
        'message' => 'Scrape run created.',
        'status' => buildStatusPayload($pdo),
    ]);
}

function processStep(PDO $pdo): void
{
    $runId = (int) ($_POST['runId'] ?? 0);
    if ($runId < 1) {
        sendJson(['success' => false, 'error' => 'Missing run id.'], 422);
    }

    $run = fetchRun($pdo, $runId);
    if (!$run) {
        sendJson(['success' => false, 'error' => 'Scrape run not found.'], 404);
    }

    if ($run['status'] === 'completed') {
        sendJson([
            'success' => true,
            'completed' => true,
            'message' => 'This run is already complete.',
            'status' => buildStatusPayload($pdo),
        ]);
    }

    if ($run['status'] === 'stopped') {
        sendJson([
            'success' => false,
            'error' => 'This run has been stopped. Start a new run to continue.',
        ], 409);
    }

    $nextPage = ((int) $run['last_page_scraped']) + 1;
    $pageSize = (int) $run['page_size'];

    $html = fetchPageHtml($run, $nextPage, $pageSize);
    $parsed = parseResponseRows($html, $nextPage);

    $pdo->beginTransaction();

    $inserted = insertRecords($pdo, $runId, $parsed['records']);
    $seen = count($parsed['records']);
    $totalPages = $parsed['totalPages'];
    $status = $parsed['noRecordFound'] ? 'completed' : 'running';
    $finishedAtSql = $status === 'completed' ? ', finished_at = NOW()' : '';

    $update = $pdo->prepare(
        "UPDATE scrape_runs
         SET status = :status,
             total_pages = COALESCE(:total_pages, total_pages),
             last_page_scraped = :last_page_scraped,
             total_records_seen = total_records_seen + :total_records_seen,
             total_records_inserted = total_records_inserted + :total_records_inserted,
             last_error = NULL
             {$finishedAtSql}
         WHERE id = :id"
    );

    $update->execute([
        ':status' => $status,
        ':total_pages' => $totalPages,
        ':last_page_scraped' => $nextPage,
        ':total_records_seen' => $seen,
        ':total_records_inserted' => $inserted,
        ':id' => $runId,
    ]);

    $pdo->commit();

    if (!$parsed['noRecordFound'] && $totalPages !== null && $nextPage >= $totalPages) {
        $pdo->prepare(
            "UPDATE scrape_runs SET status = 'completed', finished_at = NOW() WHERE id = :id"
        )->execute([':id' => $runId]);
        $status = 'completed';
    }

    sendJson([
        'success' => true,
        'runId' => $runId,
        'pageProcessed' => $nextPage,
        'recordsSeen' => $seen,
        'recordsInserted' => $inserted,
        'totalPages' => $totalPages,
        'completed' => $status === 'completed',
        'status' => buildStatusPayload($pdo),
    ]);
}

function stopRun(PDO $pdo): void
{
    $runId = (int) ($_POST['runId'] ?? 0);
    if ($runId < 1) {
        sendJson(['success' => false, 'error' => 'Missing run id.'], 422);
    }

    $statement = $pdo->prepare(
        "UPDATE scrape_runs
         SET status = 'stopped', finished_at = NOW()
         WHERE id = :id AND status IN ('pending', 'running')"
    );
    $statement->execute([':id' => $runId]);

    sendJson([
        'success' => true,
        'message' => 'Scrape stopped.',
        'status' => buildStatusPayload($pdo),
    ]);
}

function buildStatusPayload(PDO $pdo): array
{
    $summary = $pdo->query(
        "SELECT
            COUNT(*) AS total_records,
            COUNT(DISTINCT tender_id) AS total_tenders,
            MAX(created_at) AS last_imported_at
         FROM contract_records"
    )->fetch() ?: [
        'total_records' => 0,
        'total_tenders' => 0,
        'last_imported_at' => null,
    ];

    $activeRun = $pdo->query(
        "SELECT *
         FROM scrape_runs
         ORDER BY id DESC
         LIMIT 1"
    )->fetch() ?: null;

    $recentRecords = $pdo->query(
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
            detail_url
         FROM contract_records
         ORDER BY id DESC
         LIMIT 20"
    )->fetchAll();

    return [
        'success' => true,
        'summary' => $summary,
        'run' => $activeRun,
        'records' => $recentRecords,
    ];
}

function fetchRun(PDO $pdo, int $runId): ?array
{
    $statement = $pdo->prepare("SELECT * FROM scrape_runs WHERE id = :id");
    $statement->execute([':id' => $runId]);
    $run = $statement->fetch();

    return $run ?: null;
}

function fetchPageHtml(array $run, int $pageNo, int $pageSize): string
{
    $payload = [
        'keyword' => '',
        'officeId' => '0',
        'contractAwardTo' => '',
        'noaDt' => '',
        'stateName' => ' ',
        'departmentId' => DEPARTMENT_ID,
        'tenderId' => '',
        'contractNo' => '',
        'contractDtFrom' => formatPortalDate($run['contract_date_from']),
        'contractDtTo' => formatPortalDate($run['contract_date_to']),
        'tenderRefNo' => '',
        'contractAmount' => '',
        'cpvCode' => '',
        'advDt' => '',
        'procurementMethod' => PROCUREMENT_METHOD_CODE,
        'isFrame' => '0',
        'pageNo' => (string) $pageNo,
        'size' => (string) $pageSize,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => EPROCURE_URL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html, */*; q=0.01',
            'Accept-Language: en-US,en;q=0.9',
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        throw new RuntimeException('Failed to fetch page from e-GP: ' . $curlError);
    }

    if ($httpCode !== 200) {
        throw new RuntimeException('e-GP returned HTTP ' . $httpCode . '.');
    }

    return $response;
}

function parseResponseRows(string $html, int $pageNo): array
{
    if (stripos($html, 'id="noRecordFound"') !== false) {
        return [
            'records' => [],
            'totalPages' => 1,
            'noRecordFound' => true,
        ];
    }

    $wrappedHtml = '<table><tbody>' . $html . '</tbody></table>';
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrappedHtml);
    $xpath = new DOMXPath($dom);
    $rows = $xpath->query('//tr');

    $records = [];
    $rowIndex = 0;

    foreach ($rows as $row) {
        $cells = $xpath->query('./td', $row);
        if ($cells->length < 8) {
            continue;
        }

        $rowIndex++;
        $records[] = parseRowCells($cells, $pageNo, $rowIndex);
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

function parseRowCells(DOMNodeList $cells, int $pageNo, int $rowIndex): array
{
    $ministry = cleanNodeText($cells->item(1));

    $thirdCell = $cells->item(2);
    $linkNode = firstChildElementByTag($thirdCell, 'a');
    $detailUrl = normalizeDetailUrl($linkNode?->getAttribute('href') ?? '');
    $linkText = '';
    $titleText = '';
    $advertisementRaw = '';

    if ($linkNode instanceof DOMElement) {
        $paragraph = firstChildElementByTag($linkNode, 'p');
        $linkText = cleanNodeText($paragraph ?? $linkNode);
    }

    $spanNode = firstDescendantByTagAndClass($thirdCell, 'span', 'more');
    if ($spanNode instanceof DOMElement) {
        $titleText = cleanNodeText($spanNode);
    }

    $allCellText = preg_replace('/\s+/', ' ', trim($thirdCell->textContent));
    if ($linkText !== '' && preg_match('/' . preg_quote($linkText, '/') . '\s*(.*?)\s*([0-9]{2}-[A-Za-z]{3}-[0-9]{4}\s+[0-9]{2}:[0-9]{2})$/u', $allCellText, $matches) === 1) {
        if ($titleText === '') {
            $titleText = trim($matches[1]);
        }
        $advertisementRaw = trim($matches[2]);
    } else {
        $titleText = $titleText !== '' ? $titleText : $allCellText;
    }

    [$tenderId, $invitationRefNo] = splitLinkText($linkText);

    [$procuringEntity, $procurementMethod] = parseEntityMethodCell($cells->item(3));

    $district = cleanNodeText($cells->item(4));
    $notificationRaw = cleanNodeText($cells->item(5));
    $awardTo = cleanNodeText($cells->item(6));
    $contractValueRaw = cleanNodeText($cells->item(7));

    preg_match('/pkgLotId=([^&]+)/', $detailUrl, $pkgLotMatches);
    $pkgLotId = $pkgLotMatches[1] ?? null;

    $record = [
        'source_page_no' => $pageNo,
        'source_row_no' => $rowIndex,
        'tender_id' => $tenderId,
        'invitation_ref_no' => $invitationRefNo,
        'tender_title' => $titleText,
        'ministry_division' => $ministry,
        'procuring_entity' => $procuringEntity,
        'procurement_method' => $procurementMethod,
        'district' => $district,
        'notification_of_award_date' => parsePortalDate($notificationRaw),
        'notification_of_award_raw' => $notificationRaw,
        'contract_award_to' => $awardTo,
        'contract_value_cr_bdt' => parseDecimal($contractValueRaw),
        'contract_value_raw' => $contractValueRaw,
        'advertisement_at' => parsePortalDateTime($advertisementRaw),
        'advertisement_raw' => $advertisementRaw,
        'detail_url' => $detailUrl,
        'pkg_lot_id' => $pkgLotId,
    ];

    $record['record_hash'] = hash('sha256', implode('|', [
        $record['tender_id'],
        $record['detail_url'],
        $record['contract_award_to'],
        $record['notification_of_award_raw'],
        $record['contract_value_raw'],
    ]));

    return $record;
}

function insertRecords(PDO $pdo, int $runId, array $records): int
{
    if ($records === []) {
        return 0;
    }

    $statement = $pdo->prepare(
        "INSERT INTO contract_records (
            source_run_id,
            source_page_no,
            source_row_no,
            tender_id,
            invitation_ref_no,
            tender_title,
            ministry_division,
            procuring_entity,
            procurement_method,
            district,
            notification_of_award_date,
            notification_of_award_raw,
            contract_award_to,
            contract_value_cr_bdt,
            contract_value_raw,
            advertisement_at,
            advertisement_raw,
            detail_url,
            pkg_lot_id,
            record_hash
        ) VALUES (
            :source_run_id,
            :source_page_no,
            :source_row_no,
            :tender_id,
            :invitation_ref_no,
            :tender_title,
            :ministry_division,
            :procuring_entity,
            :procurement_method,
            :district,
            :notification_of_award_date,
            :notification_of_award_raw,
            :contract_award_to,
            :contract_value_cr_bdt,
            :contract_value_raw,
            :advertisement_at,
            :advertisement_raw,
            :detail_url,
            :pkg_lot_id,
            :record_hash
        )
        ON DUPLICATE KEY UPDATE
            source_run_id = VALUES(source_run_id),
            source_page_no = VALUES(source_page_no),
            source_row_no = VALUES(source_row_no),
            invitation_ref_no = VALUES(invitation_ref_no),
            tender_title = VALUES(tender_title),
            ministry_division = VALUES(ministry_division),
            procuring_entity = VALUES(procuring_entity),
            procurement_method = VALUES(procurement_method),
            district = VALUES(district),
            notification_of_award_date = VALUES(notification_of_award_date),
            notification_of_award_raw = VALUES(notification_of_award_raw),
            contract_award_to = VALUES(contract_award_to),
            contract_value_cr_bdt = VALUES(contract_value_cr_bdt),
            contract_value_raw = VALUES(contract_value_raw),
            advertisement_at = VALUES(advertisement_at),
            advertisement_raw = VALUES(advertisement_raw),
            detail_url = VALUES(detail_url),
            pkg_lot_id = VALUES(pkg_lot_id),
            updated_at = CURRENT_TIMESTAMP"
    );

    $inserted = 0;

    foreach ($records as $record) {
        $params = $record;
        $params['source_run_id'] = $runId;

        $statement->execute([
            ':source_run_id' => $params['source_run_id'],
            ':source_page_no' => $params['source_page_no'],
            ':source_row_no' => $params['source_row_no'],
            ':tender_id' => $params['tender_id'],
            ':invitation_ref_no' => $params['invitation_ref_no'],
            ':tender_title' => $params['tender_title'],
            ':ministry_division' => $params['ministry_division'],
            ':procuring_entity' => $params['procuring_entity'],
            ':procurement_method' => $params['procurement_method'],
            ':district' => $params['district'],
            ':notification_of_award_date' => $params['notification_of_award_date'],
            ':notification_of_award_raw' => $params['notification_of_award_raw'],
            ':contract_award_to' => $params['contract_award_to'],
            ':contract_value_cr_bdt' => $params['contract_value_cr_bdt'],
            ':contract_value_raw' => $params['contract_value_raw'],
            ':advertisement_at' => $params['advertisement_at'],
            ':advertisement_raw' => $params['advertisement_raw'],
            ':detail_url' => $params['detail_url'],
            ':pkg_lot_id' => $params['pkg_lot_id'],
            ':record_hash' => $params['record_hash'],
        ]);

        if ($statement->rowCount() === 1) {
            $inserted++;
        }
    }

    return $inserted;
}

function normalizeInputDate(string $value): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('d/m/Y', trim($value));
    if (!$date) {
        throw new InvalidArgumentException('Dates must use dd/mm/yyyy.');
    }

    return $date;
}

function formatPortalDate(string $value): string
{
    $date = new DateTimeImmutable($value);
    return $date->format('d/m/Y');
}

function parsePortalDate(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('d-M-Y', $value);
    return $date ? $date->format('Y-m-d') : null;
}

function parsePortalDateTime(string $value): ?string
{
    $value = trim($value);
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
    return $normalized === '' ? null : $normalized;
}

function splitLinkText(string $value): array
{
    $parts = explode(',', $value, 2);
    $tenderId = trim($parts[0] ?? '');
    $invitationRefNo = trim($parts[1] ?? '');

    return [$tenderId, $invitationRefNo];
}

function splitLines(string $value): array
{
    $lines = preg_split('/\R+/', html_entity_decode($value, ENT_QUOTES | ENT_HTML5));
    $lines = array_map(static fn (string $line): string => trim(preg_replace('/\s+/', ' ', $line)), $lines ?: []);

    return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
}

function parseEntityMethodCell(?DOMNode $node): array
{
    if (!$node || !$node->ownerDocument) {
        return ['', ''];
    }

    $html = $node->ownerDocument->saveHTML($node);
    if ($html === false) {
        return [cleanNodeText($node), ''];
    }

    $html = preg_replace('/^<td[^>]*>|<\/td>$/i', '', $html);
    $html = preg_replace('/<br\s*\/?>/i', "\n", (string) $html);
    $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5);
    $lines = splitLines($text);

    return [
        $lines[0] ?? '',
        $lines[1] ?? '',
    ];
}

function cleanNodeText(?DOMNode $node): string
{
    if (!$node) {
        return '';
    }

    return trim(preg_replace('/\s+/', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5)));
}

function firstChildElementByTag(?DOMNode $node, string $tag): ?DOMElement
{
    if (!$node) {
        return null;
    }

    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMElement && strcasecmp($child->tagName, $tag) === 0) {
            return $child;
        }
    }

    return null;
}

function firstDescendantByTagAndClass(?DOMNode $node, string $tag, string $class): ?DOMElement
{
    if (!$node) {
        return null;
    }

    $dom = $node->ownerDocument;
    if (!$dom) {
        return null;
    }

    $xpath = new DOMXPath($dom);
    $query = './/' . $tag . '[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]';
    $nodes = $xpath->query($query, $node);

    return $nodes && $nodes->length > 0 && $nodes->item(0) instanceof DOMElement
        ? $nodes->item(0)
        : null;
}

function normalizeDetailUrl(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
        return $url;
    }

    return 'https://www.eprocure.gov.bd' . $url;
}

function sendJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
