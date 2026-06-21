# Module Verification Findings

Date: 2026-06-22
Project: `egp_grabber`
Scope: live verification of all four grabber modules against the e-GP website with full search criteria, plus fixes for any confirmed payload or parsing issues.

## Summary

All four modules were checked against the live site:

- `eTender`: issue found and fixed
- `APP`: issue found and fixed
- `eContract`: validated with working results
- `eExperience`: issue found and fixed

The main problem pattern was payload mismatch between the app and the live e-GP form. Some fields visually look like labels in the UI, but the backend expects either a numeric option value or the raw label text depending on the module.

## Fixed Findings

### 1. `eTender` sent the wrong `procNature` value

Problem:
- the app sent the procurement nature label instead of the value expected by the live endpoint

Impact:
- valid searches could drift into bad pagination and produce no usable rows

Fix:
- mapped `procNature` to the numeric values expected by the live endpoint
- added stronger no-record detection so the scraper stops when the response contains `No records found`

Status:
- fixed in [api/grabber.php](/D:/Laragon-root/www/egp_grabber/api/grabber.php)

### 2. `APP` sent the wrong `budgetType` value

Problem:
- the app sent `Development` instead of the numeric value required by the endpoint

Impact:
- searches returned zero rows even when the site had matching data

Fix:
- mapped `budgetType` to the numeric values expected by the live endpoint

Status:
- fixed in [api/grabber.php](/D:/Laragon-root/www/egp_grabber/api/grabber.php)

### 3. `eExperience` sent the wrong `procurementMethod` value

Problem:
- the app sent the numeric method code, but this endpoint expects the visible method text such as `RFQL`

Impact:
- searches returned zero rows even when the live site returned data

Fix:
- changed the request payload to send the method label for `eExperience`
- also aligned `packageType` to the endpoint's numeric values

Status:
- fixed in [api/grabber.php](/D:/Laragon-root/www/egp_grabber/api/grabber.php)

## Per-Module Verification

### `eTender`

Criteria used:

| Field | Value |
| --- | --- |
| Ministry | Ministry of Housing and Public Works |
| Department | Public Works Department (PWD) |
| Office | Sunamganj PWD Division |
| View Type | Live |
| Procurement Nature | Works |
| Procurement Method | OTM |
| Publishing Date From | 2026-06-21 |
| Publishing Date To | 2026-06-21 |

Result:

- verification run: `#13`
- status: `completed`
- total pages: `1`
- rows seen: `5`
- rows inserted: `5`

Notes:

- the previous bad historical run `#7` is consistent with the old payload bug
- after the fix, the same search pattern returns the expected single-page result

### `APP`

Criteria used:

| Field | Value |
| --- | --- |
| Ministry | Ministry of Housing and Public Works |
| Department | Public Works Department (PWD) |
| Office | Narail PWD Division |
| Procurement Nature | Works |
| Financial Year | 2025-2026 |
| Budget Type | Development |

Result:

- broken verification run before fix: `#9`
- working verification run after fix: `#10`
- status: `completed`
- total pages: `1`
- rows seen: `2`
- rows inserted: `2`

Notes:

- live endpoint requires numeric `budgetType`
- using the label text caused the false zero-result behavior

### `eContract`

Criteria used:

| Field | Value |
| --- | --- |
| Ministry | Ministry of Housing and Public Works |
| Department | Public Works Department (PWD) |
| Office | Narail PWD Division |
| Procurement Method | LTM |
| Contract Sign Date From | 2023-07-03 |
| Contract Sign Date To | 2026-06-21 |

Result:

- verification run: `#14`
- status during validation: `stopped`
- total pages detected: `16`
- page 1 rows seen: `10`
- page 1 rows inserted: `10`

Notes:

- this module produced valid live data without requiring a payload fix in this pass
- the run was intentionally stopped after first-page validation instead of scraping all 16 pages

### `eExperience`

Criteria used:

| Field | Value |
| --- | --- |
| Ministry | Ministry of Post, Telecommunications & Information Technology |
| Department | Bangladesh Telecommunications Company Ltd. (BTCL) |
| Office | DGM Phones-2, Secretariat, Ramna. |
| Procurement Nature | Works |
| Procurement Method | RFQL |
| Contract Start Date From | 2026-05-20 |
| Contract Start Date To | 2026-05-20 |
| Contract End Date From | 2026-05-24 |
| Contract End Date To | 2026-05-24 |
| Work Status | Completed |
| Awarded To Match | Equals |
| Contract Awarded To | Creative Carvetech Co. |
| Company Unique ID | 1210308 |
| Tender Type | eTenders |

Result:

- broken verification run before fix: `#11`
- working verification run after fix: `#12`
- status: `completed`
- total pages: `1`
- rows seen: `1`
- rows inserted: `1`

Notes:

- live endpoint requires text `procurementMethod` for this module
- the numeric value produced a false no-data result

## Current Verification Data in Local DB

Rows currently present from this verification pass:

| Module | Rows |
| --- | ---: |
| `eTender` | 5 |
| `APP` | 2 |
| `eContract` | 10 |
| `eExperience` | 1 |

These are test rows created during live verification. The historical runs `#7`, `#9`, and `#11` remain in the database as pre-fix evidence of the issues above.

## Residual Risks

- the e-GP site can change parameter names or option values without notice
- resume logic was not fully re-stress-tested across large multi-page runs for all four modules in this verification pass
- dropdown dependencies still rely on the live site structure staying compatible

## Recommendation

Before running long production imports, use the now-fixed module flow to create one fresh run per module with a narrow criteria set, confirm the row preview in the UI, and then start the large-range runs.
