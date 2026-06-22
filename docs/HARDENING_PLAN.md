# eGP Grabber Hardening Plan

Date: 2026-06-22
Scope: reduce future scraper failures, make runs easier to recover, and make debugging faster across `eTender`, `APP`, `eContract`, and `eExperience`.

## Objectives

- prevent avoidable run crashes from source-data shape changes
- isolate bad rows instead of failing whole runs
- make parser behavior more predictable across all four modules
- improve visibility into what each run is doing and why it fails
- reduce confusion in criteria selection, saved runs, and resume behavior

## Phase 1: Data Safety Baseline

Goal: stop common insert failures before they break a running job.

### Tasks

- add a central pre-insert sanitizer for every module
- inspect DB column metadata once per request and enforce max safe lengths before insert
- trim overlong `VARCHAR` values safely and predictably
- keep `TEXT` for all raw source-capture fields
- normalize whitespace for all inserted strings
- convert empty strings to `NULL` where appropriate for date, datetime, and numeric fields
- add a shared helper for safe decimal parsing
- add a shared helper for safe date and datetime parsing

### Deliverables

- one reusable row-sanitization pipeline used by all four modules
- no silent SQL truncation failures for normal scraper input

## Phase 2: Parser Hardening

Goal: make parsing less fragile when the e-GP table layout shifts slightly.

### Tasks

- define explicit parser expectations per module and per table column
- add validation helpers for known patterns:
  - portal date: `dd-MMM-yyyy`
  - portal datetime: `dd-MMM-yyyy HH:mm`
  - numeric amount fields
  - procurement method fields
- update `eContract` parser to prefer pattern matching over positional assumptions
- review `eTender`, `APP`, and `eExperience` parsers for similar “last line wins” or fixed-position risks
- add parser fallback behavior when one field cannot be parsed:
  - keep raw text
  - store parsed field as `NULL`
  - log the parser warning
- reject obviously malformed rows from insertion only if row identity cannot be established

### Deliverables

- module-specific parser rules
- fewer false parses when table cells contain extra lines or changed formatting

## Phase 3: Fault Tolerance During Runs

Goal: keep long runs alive even when some rows are bad.

### Tasks

- add row-level exception handling inside insert loops
- create a `egp_scrape_run_errors` table
- store failed row payload, run ID, page number, row number, module, column context, and error message
- continue the run after a row-level failure when safe
- add thresholds:
  - continue on isolated row errors
  - stop run if too many consecutive row errors occur
- separate fatal errors from recoverable row errors
- record the exact failing page and row for recovery

### Deliverables

- bad rows isolated without killing whole runs
- structured error history for debugging and retry analysis

## Phase 4: Resume and Recovery Improvements

Goal: make stopped and resumed runs reliable over long time gaps.

### Tasks

- review current resume logic for all four modules, not only `eContract`
- persist stronger row anchors:
  - latest record hash
  - earliest record hash
  - latest source identifier
  - earliest source identifier
- add a resume audit step before continuing:
  - confirm criteria still valid
  - confirm source total page count still reachable
  - confirm anchor rows are still meaningful
- add recovery states:
  - `running`
  - `stopped`
  - `completed`
  - `completed_with_errors`
  - `failed`
- distinguish manual stop from automatic halt due to repeated errors
- add a “retry failed page” capability for targeted recovery

### Deliverables

- more reliable continuation after interruptions
- clearer run lifecycle states

## Phase 5: Observability and Diagnostics

Goal: make problems visible without needing code inspection every time.

### Tasks

- add structured run event logging
- create a lightweight run timeline:
  - run started
  - page fetched
  - rows parsed
  - rows inserted
  - row errors
  - run stopped/completed/failed
- show latest error and warning counts in the UI
- add “view errors” action per run
- show exact criteria with IDs in all run displays
- display module-specific counters:
  - pages fetched
  - rows parsed
  - rows inserted
  - rows skipped
  - rows errored
- add a debug export for one run’s error rows as CSV

### Deliverables

- faster diagnosis of field mismatches and parser issues
- less guesswork during production scraping

## Phase 6: Input and Criteria Reliability

Goal: reduce invalid or misleading searches before they start.

### Tasks

- validate required field combinations per module before starting a run
- show selected ministry, department, and entity as `Label (ID)` everywhere
- optionally cache live dropdown options with refresh timestamps
- add stale-option detection if a saved run uses an entity no longer present in current dropdown data
- validate date ranges before run creation
- validate module-specific enum compatibility with the live site payload format
- keep a normalized copy of search criteria for exact replay

### Deliverables

- fewer bad runs caused by incorrect or ambiguous criteria
- more reproducible reruns

## Phase 7: Performance and Stability

Goal: make large runs smoother and less likely to stall.

### Tasks

- add configurable request retry with backoff for e-GP fetches
- separate fetch timeout, parse timeout, and insert timeout concerns
- batch writes where safe
- reduce repeated schema introspection by caching column metadata in-process
- add periodic checkpoint writes for very long runs
- monitor memory growth during long HTML parse loops
- consider storing raw page HTML only for failed pages, not all pages

### Deliverables

- smoother long-running jobs
- better behavior under network instability

## Phase 8: Test Coverage

Goal: prevent regressions when modifying parsers or schema behavior.

### Tasks

- capture representative HTML fixtures for all four modules
- add parser tests against saved fixtures
- add tests for:
  - normal rows
  - empty result rows
  - long raw values
  - malformed date/datetime values
  - duplicate row upserts
- add integration tests for run creation, one-step processing, stop, and resume
- add regression fixtures for known production issues

### Deliverables

- safer refactors
- lower risk when the e-GP site changes

## Recommended Execution Order

1. Phase 1: Data Safety Baseline
2. Phase 3: Fault Tolerance During Runs
3. Phase 2: Parser Hardening
4. Phase 5: Observability and Diagnostics
5. Phase 4: Resume and Recovery Improvements
6. Phase 6: Input and Criteria Reliability
7. Phase 7: Performance and Stability
8. Phase 8: Test Coverage

## Priority Tasks

These should be done first because they give the largest reliability gain quickly.

- implement central pre-insert sanitization
- add `egp_scrape_run_errors`
- continue run on row-level insert failures
- harden all parser date/datetime extraction
- expose per-run error visibility in the UI

## Success Criteria

- long runs do not stop because one row exceeds a column size
- raw-field variability does not break inserts
- row-level failures are logged and recoverable
- a stopped run can be resumed with clear status and error context
- operators can understand failures from the UI without reading source code
