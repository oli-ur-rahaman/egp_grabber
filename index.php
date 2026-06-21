<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eGP PWD LTM Grabber</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="min-h-screen bg-stone-100 text-stone-900">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-8 rounded-3xl bg-[linear-gradient(135deg,#0f172a,#1f2937_45%,#14532d)] p-6 text-white shadow-xl">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.35em] text-emerald-200">eProcure DB Pipeline</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight">PWD LTM Contract Table Grabber</h1>
                    <p class="mt-3 max-w-3xl text-sm text-slate-200">
                        Imports every paginated row from the public e-GP awarded contracts search into MySQL and exports the stored dataset on demand.
                    </p>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm backdrop-blur">
                    <div class="font-semibold">Fixed Source Filters</div>
                    <div class="mt-1 text-slate-200">Department: Public Works Department (ID 21)</div>
                    <div class="text-slate-200">Method: LTM</div>
                </div>
            </div>
        </header>

        <div class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <h2 class="text-lg font-bold">Run Controls</h2>
                <p class="mt-1 text-sm text-stone-500">Start a fresh scrape run for the selected contract signing date range.</p>

                <form id="scraperForm" class="mt-6 space-y-4">
                    <div>
                        <label for="contractDtFrom" class="mb-1 block text-sm font-medium text-stone-700">Contract sign date from</label>
                        <input id="contractDtFrom" name="contractDtFrom" type="text" value="03/07/2023" class="w-full rounded-2xl border border-stone-300 bg-stone-50 px-4 py-3 font-mono text-sm outline-none ring-0 transition focus:border-emerald-600" placeholder="dd/mm/yyyy">
                    </div>

                    <div>
                        <label for="contractDtTo" class="mb-1 block text-sm font-medium text-stone-700">Contract sign date to</label>
                        <input id="contractDtTo" name="contractDtTo" type="text" value="21/06/2026" class="w-full rounded-2xl border border-stone-300 bg-stone-50 px-4 py-3 font-mono text-sm outline-none ring-0 transition focus:border-emerald-600" placeholder="dd/mm/yyyy">
                    </div>

                    <div>
                        <label for="size" class="mb-1 block text-sm font-medium text-stone-700">Rows per source page</label>
                        <input id="size" name="size" type="number" min="1" max="100" value="10" class="w-full rounded-2xl border border-stone-300 bg-stone-50 px-4 py-3 font-mono text-sm outline-none ring-0 transition focus:border-emerald-600">
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <button id="startGrabBtn" type="submit" class="rounded-2xl bg-emerald-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800">
                            Start Import
                        </button>
                        <button id="stopGrabBtn" type="button" class="rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm font-semibold text-stone-700 transition hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-50" disabled>
                            Stop
                        </button>
                    </div>

                    <a href="api/export.php" class="block rounded-2xl border border-stone-300 bg-stone-900 px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-black">
                        Export Stored Data
                    </a>
                </form>

                <div class="mt-6 rounded-2xl bg-stone-100 p-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-stone-700">Progress</span>
                        <span id="progressText" class="font-mono text-stone-600">Idle</span>
                    </div>
                    <div class="mt-3 h-3 overflow-hidden rounded-full bg-stone-200">
                        <div id="progressBar" class="h-full w-0 rounded-full bg-[linear-gradient(90deg,#15803d,#65a30d)] transition-all duration-300"></div>
                    </div>
                    <p id="statusMessage" class="mt-3 text-sm text-stone-500">No active scrape run.</p>
                </div>
            </section>

            <section class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-3">
                    <article class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-stone-500">Stored Rows</p>
                        <p id="statTotalRecords" class="mt-3 text-3xl font-black text-stone-900">0</p>
                    </article>
                    <article class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-stone-500">Unique Tenders</p>
                        <p id="statTotalTenders" class="mt-3 text-3xl font-black text-stone-900">0</p>
                    </article>
                    <article class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-stone-500">Current Run</p>
                        <p id="statRunStatus" class="mt-3 text-xl font-bold text-stone-900">Idle</p>
                    </article>
                </div>

                <article class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-bold">Run Snapshot</h2>
                            <p class="text-sm text-stone-500">Latest scrape metadata and import counters.</p>
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl bg-stone-100 p-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-stone-500">Run ID</dt>
                            <dd id="runIdValue" class="mt-2 text-lg font-bold text-stone-900">-</dd>
                        </div>
                        <div class="rounded-2xl bg-stone-100 p-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-stone-500">Pages</dt>
                            <dd id="runPagesValue" class="mt-2 text-lg font-bold text-stone-900">0 / -</dd>
                        </div>
                        <div class="rounded-2xl bg-stone-100 p-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-stone-500">Rows Seen</dt>
                            <dd id="runSeenValue" class="mt-2 text-lg font-bold text-stone-900">0</dd>
                        </div>
                        <div class="rounded-2xl bg-stone-100 p-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-stone-500">Rows Inserted</dt>
                            <dd id="runInsertedValue" class="mt-2 text-lg font-bold text-stone-900">0</dd>
                        </div>
                    </dl>
                </article>

                <article class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-stone-200">
                    <div class="border-b border-stone-200 px-6 py-4">
                        <h2 class="text-lg font-bold">Latest Stored Records</h2>
                        <p class="text-sm text-stone-500">Recent rows imported into `eprocure_db.contract_records`.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-stone-100 text-stone-700">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">Tender ID</th>
                                    <th class="px-4 py-3 font-semibold">Reference</th>
                                    <th class="px-4 py-3 font-semibold">Title</th>
                                    <th class="px-4 py-3 font-semibold">Procuring Entity</th>
                                    <th class="px-4 py-3 font-semibold">District</th>
                                    <th class="px-4 py-3 font-semibold">NOA Date</th>
                                    <th class="px-4 py-3 font-semibold">Award To</th>
                                    <th class="px-4 py-3 font-semibold">Value</th>
                                </tr>
                            </thead>
                            <tbody id="recordsTableBody" class="divide-y divide-stone-200 bg-white">
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-stone-400">No records imported yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
