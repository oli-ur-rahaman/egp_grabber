# eGP Multi-Source Grabber Plan

## Status
- Phase 1: Discovery complete
- Phase 2: Design complete
- Phase 3: Implementation complete
- Phase 4: Verification complete

## Scope
Build a web app that can:
- let the user choose one of four e-GP sources: `eTender`, `APP`, `eContract`, `eExperience`
- render the relevant search inputs for the selected source
- scrape paginated table rows from the corresponding public e-GP page
- store each source into a separate MySQL table
- track every scrape run with its own criteria, progress, and status
- show `Previous Runs` with resume/export actions
- export stored rows from any run on demand

## Live Source Audit

### 1. eTender
- URL: `https://www.eprocure.gov.bd/resources/common/AllTenders.jsp?h=t`
- Filters confirmed:
  - Ministry/Division/Organization
  - Procuring Entity
  - Procurement Nature
  - Procurement Type
  - Procurement Method
  - Tender/Proposal ID
  - Reference No
  - From Publishing Date
  - To Publishing Date
  - From Closing Date
  - To Closing Date
  - Category
  - Framework Agreement
- Tabs confirmed:
  - `Live`
  - `Archive`
  - `Cancelled`
  - `All`
- Table columns confirmed:
  - `S.No.`
  - `Tender/Proposal ID, Reference No`
  - `Procurement Nature, Title`
  - `Ministry, Division, Organization, PE`
  - `Type, Method`
  - `Publishing Date and Time, Closing Date and Time`

### 2. APP
- URL: `https://www.eprocure.gov.bd/resources/common/AdvAPPSearch.jsp`
- Filters confirmed on page:
  - Ministry/Division/Organization
  - Procuring Entity
  - Financial Year
  - Budget Type
  - Project Name
  - Procurement Nature
  - Procurement Type
  - APP ID
  - APP Code
  - Package No.
  - Package Estimated Cost operation/value/value2
  - Category
- User-requested minimum set:
  - Procuring Entity
  - Procurement Nature
  - Financial Year
  - Budget Type
- Table columns confirmed:
  - `S. No.`
  - `APP ID, APP Code`
  - `Ministry, Division, Organization, PE`
  - `District`
  - `Procurement Nature,Project Name`
  - `Package No, Description`
  - `Estimated Cost (in BDT),Procurement Method`

### 3. eContract
- URL: `https://www.eprocure.gov.bd/resources/common/AdvSearchNOA.jsp`
- Filters confirmed:
  - Ministry/Division/Agency
  - Procuring Entity
  - District
  - Contract No
  - Tender/Proposal ID
  - Tender/Proposal Ref. No
  - Value (In BDT)
  - Keyword
  - Advertisement Date
  - Date of Notification of Award
  - Procurement Method
  - Contract Award to
  - Framework Agreement
  - Contract Sign Date From
  - Contract Sign Date To
- User-requested minimum set:
  - Procuring Entity
  - Procurement Method
  - District
  - Contract Awarded To
  - Contract Sign Date From/To
  - Framework Agreement
- Table columns confirmed:
  - `S. No.`
  - `Ministry & Division`
  - `Tender/Proposal ID,Ref No., Title & Advertisement Date`
  - `Procuring Entity / Procurement Method`
  - `District`
  - `Date of Notification of Award`
  - `Contract Award to`
  - `Value (Cr. BDT)/(Other Currency)`

### 4. eExperience
- URL: `https://www.eprocure.gov.bd/resources/common/SearcheCMS.jsp?v=advSearch`
- Filters confirmed on page:
  - Ministry/Division/Organization
  - Procuring Entity
  - Procurement Nature
  - Tender/Proposal ID
  - Procurement Method
  - Contract Start Date From/To
  - Contract End Date From/To
  - Work Status
  - Contract Awarded To with `Contains/Equals`
  - Company Unique ID
  - Experience Certificate No with `Contains/Equals`
  - Procurement Type
  - Tender Type
- User-requested minimum set:
  - Procuring Entity
  - Procurement Nature
  - Procurement Method
  - Contract End Date From/To
  - Work Status
  - Contract Awarded To (contains/equal)
  - Company Unique ID
  - Tender Type
- Tabs confirmed:
  - `eTenders`
  - `eCMS`
  - `Manual`
  - `All`
- Table columns confirmed:
  - `S. No.`
  - `Ministry, Division, Organization, PE`
  - `Procurement Nature, Type & Method`
  - `Tender/Proposal ID, Ref No., Title & Publishing Date`
  - `Contract Awarded To`
  - `Company Unique ID`
  - `Experience Certificate No`
  - `Contract Amount(In BDT/Equivalent in BDT)`
  - `Contract Start & End Date`
  - `Work Status`

## Target Architecture

### Shared tables
- `scrape_runs`
  - source key
  - source label
  - serialized criteria JSON
  - status
  - page size
  - total pages
  - last page scraped
  - seen/inserted counters
  - resume markers
  - error text
  - timestamps

### Source-specific data tables
- `tender_records`
- `app_records`
- `contract_records`
- `experience_records`

Each source table should store:
- normalized columns for the visible table fields
- source page number
- source row number
- detail URL when present
- stable record hash for dedupe
- source run id
- created/updated timestamps

## Resume Strategy

### Goal
Avoid re-importing old rows when a stopped run resumes after new rows have appeared at the front of the source table.

### Proposed strategy
Use anchor-based resume instead of only `last_page_scraped`.

For each run store:
- `earliest_anchor_id`
  - the oldest row captured so far in that run
- `latest_anchor_id`
  - the newest row captured so far in that run
- optional anchor hashes if tender ids are not enough for a source

Resume algorithm:
1. Re-run page 1 for the same criteria and detect current `total_pages`.
2. Scan forward until the stored `latest_anchor_id` is found.
3. Skip all rows before that anchor because they are new rows not previously seen.
4. Continue importing from the row immediately after that anchor.
5. Keep page-by-page import going until:
   - the old `earliest_anchor_id` is reached, then
   - continue from the stored `last_page_scraped + drift` if needed to capture old tail pages that were not completed.

This will likely be simplified into:
- a front-gap recovery phase
- then a normal tail continuation phase

Implementation detail:
- use row hash as the true dedupe guard
- use tender id only as a fast anchor lookup

## UI Changes
- Add a `Previous Runs` card below the top hero/header card.
- Show:
  - source
  - run id
  - search criteria summary
  - status
  - pages scraped / total pages
  - inserted rows
  - created/updated timestamps
- Actions per run:
  - `Resume` for stopped runs
  - `Export Excel/CSV`
  - optional `View Records`

## Implementation Phases

### Phase 1. Data model refactor
- extend `scrape_runs` for multi-source support
- add source-specific tables
- add criteria JSON and anchor fields
- add per-run export support

### Phase 2. Shared scraping engine
- create source registry/config
- create shared HTTP fetcher
- create shared runner/status engine
- create per-source parser modules

### Phase 3. UI refactor
- source selector
- dynamic criteria form
- previous runs card
- run actions
- records preview based on selected source/run

### Phase 4. Resume support
- stopped-run resume endpoint
- anchor lookup logic
- front-gap recovery logic
- tail continuation logic

### Phase 5. Verification
- test fresh run for each source
- test stopped-run resume for at least eContract
- verify DB inserts and exports

## Decisions Captured
1. Export is per run only.
2. `eTender` defaults to `Live`, but the user can change the tab.
3. `eExperience` keeps `Contract Start Date` available; `Experience Certificate No` is not exposed in the UI.
4. CSV export is sufficient.
5. Resume stays on the same run record.

## Verification Notes
- Exact ministry/department/office lookup is implemented against live e-GP endpoints.
- Verified `eContract` with:
  - ministry `20` = `Ministry of Housing and Public Works`
  - department `21` = `Public Works Department (PWD)`
  - office `1637` = `Narail PWD Division`
  - procurement method `LTM`
  - contract sign dates `03/07/2023` to `21/06/2026`
- Department-level test returned `2618` pages.
- Office-level test returned `16` pages and completed on the same run record.
- Resume test on the same office-level run reconciled page 1, skipped duplicates, and continued from page 2.
- Per-run CSV export was verified on the completed office-level run.
