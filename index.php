<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eGP Multi-Source Grabber</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="min-h-screen bg-stone-100 text-stone-900">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="rounded-3xl bg-[linear-gradient(135deg,#0f172a,#1f2937_45%,#14532d)] p-6 text-white shadow-xl">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.35em] text-emerald-200">eProcure DB Pipeline</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight">eGP Multi-Source Table Grabber</h1>
                    <p class="mt-3 max-w-3xl text-sm text-slate-200">
                        Scrape `eTender`, `APP`, `eContract`, and `eExperience` public search tables into MySQL, resume stopped runs, and export each run separately.
                    </p>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm backdrop-blur">
                    <div class="font-semibold">Run Model</div>
                    <div class="mt-1 text-slate-200">One export per run</div>
                    <div class="text-slate-200">Stopped runs resume on the same record</div>
                </div>
            </div>
        </header>

        <section class="mt-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">Previous Runs</h2>
                    <p class="text-sm text-stone-500">Compact run history with quick actions and structured criteria.</p>
                </div>
                <div class="text-sm text-stone-500">Latest 25 runs</div>
            </div>
            <div id="previousRuns" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-2xl border border-dashed border-stone-300 p-5 text-sm text-stone-400">No runs yet.</div>
            </div>
        </section>

        <div class="mt-6 grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <h2 class="text-lg font-bold">New Run</h2>
                <p class="mt-1 text-sm text-stone-500">Choose a source, fill the relevant filters, and start scraping.</p>

                <form id="scraperForm" class="mt-6 space-y-4">
                    <div>
                        <label for="sourceKey" class="mb-1 block text-sm font-medium text-stone-700">Source</label>
                        <select id="sourceKey" name="sourceKey" class="w-full rounded-2xl border border-stone-300 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-600">
                            <option value="eTender">eTender</option>
                            <option value="APP">APP</option>
                            <option value="eContract" selected>eContract</option>
                            <option value="eExperience">eExperience</option>
                        </select>
                    </div>

                    <div>
                        <label for="ministryId" class="mb-1 block text-sm font-medium text-stone-700">Ministry / Division / Organization</label>
                        <select id="ministryId" name="ministryId" class="w-full rounded-2xl border border-stone-300 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-600">
                            <option value="">Any</option>
                        </select>
                        <p id="ministryMeta" class="mt-2 text-xs text-stone-500">No selection</p>
                        <input type="hidden" id="ministryLabel" name="ministryLabel" value="">
                    </div>

                    <div>
                        <label for="departmentId" class="mb-1 block text-sm font-medium text-stone-700">Department / Organization</label>
                        <select id="departmentId" name="departmentId" class="w-full rounded-2xl border border-stone-300 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-600">
                            <option value="">Any</option>
                        </select>
                        <p id="departmentMeta" class="mt-2 text-xs text-stone-500">No selection</p>
                        <input type="hidden" id="departmentLabel" name="departmentLabel" value="">
                    </div>

                    <div>
                        <label for="officeId" class="mb-1 block text-sm font-medium text-stone-700">Procuring Entity</label>
                        <select id="officeId" name="officeId" class="w-full rounded-2xl border border-stone-300 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-600">
                            <option value="">Any</option>
                        </select>
                        <p id="officeMeta" class="mt-2 text-xs text-stone-500">No selection</p>
                        <input type="hidden" id="officeLabel" name="officeLabel" value="">
                    </div>

                    <div id="dynamicFields" class="space-y-4"></div>

                    <div>
                        <label for="pageSize" class="mb-1 block text-sm font-medium text-stone-700">Rows per source page</label>
                        <input id="pageSize" name="pageSize" type="number" min="1" max="100" value="10" class="w-full rounded-2xl border border-stone-300 bg-stone-50 px-4 py-3 font-mono text-sm outline-none transition focus:border-emerald-600">
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <button id="startGrabBtn" type="submit" class="rounded-2xl bg-emerald-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800">
                            Start Run
                        </button>
                        <button id="stopGrabBtn" type="button" class="rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm font-semibold text-stone-700 transition hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-50" disabled>
                            Stop
                        </button>
                    </div>
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
                <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    <article class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-stone-500">eTender Rows</p>
                        <p id="count-eTender" class="mt-3 text-2xl font-black text-stone-900">0</p>
                    </article>
                    <article class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-stone-500">APP Rows</p>
                        <p id="count-APP" class="mt-3 text-2xl font-black text-stone-900">0</p>
                    </article>
                    <article class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-stone-500">eContract Rows</p>
                        <p id="count-eContract" class="mt-3 text-2xl font-black text-stone-900">0</p>
                    </article>
                    <article class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-stone-500">eExperience Rows</p>
                        <p id="count-eExperience" class="mt-3 text-2xl font-black text-stone-900">0</p>
                    </article>
                    <article class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-stone-500">Active Run</p>
                        <p id="activeRunStatus" class="mt-3 text-lg font-bold text-stone-900">Idle</p>
                    </article>
                </div>

                <article class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <h2 class="text-lg font-bold">Run Snapshot</h2>
                    <p class="mt-1 text-sm text-stone-500">Details for the active or selected run.</p>

                    <dl class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-2xl bg-stone-100 p-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-stone-500">Run ID</dt>
                            <dd id="runIdValue" class="mt-2 text-lg font-bold text-stone-900">-</dd>
                        </div>
                        <div class="rounded-2xl bg-stone-100 p-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-stone-500">Source</dt>
                            <dd id="runSourceValue" class="mt-2 text-lg font-bold text-stone-900">-</dd>
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
                        <h2 class="text-lg font-bold">Run Preview</h2>
                        <p class="text-sm text-stone-500">Latest stored rows for the active or selected run.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead id="previewHead" class="bg-stone-100 text-stone-700"></thead>
                            <tbody id="previewBody" class="divide-y divide-stone-200 bg-white">
                                <tr>
                                    <td class="px-4 py-8 text-center text-stone-400">No records imported yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </div>
    </div>

    <div id="criteriaModal" class="fixed inset-0 z-50 hidden bg-slate-950/45 px-4 py-6">
        <div class="mx-auto flex min-h-full max-w-3xl items-center justify-center">
            <div class="w-full overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-stone-200">
                <div class="flex items-center justify-between border-b border-stone-200 px-6 py-4">
                    <div>
                        <h2 id="criteriaModalTitle" class="text-lg font-bold text-stone-900">Search Criteria</h2>
                        <p id="criteriaModalSubtitle" class="text-sm text-stone-500">Run details</p>
                    </div>
                    <button id="closeCriteriaModalBtn" type="button" class="rounded-xl border border-stone-300 bg-white px-3 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-100">
                        Close
                    </button>
                </div>
                <div class="px-6 py-5">
                    <div class="overflow-hidden rounded-2xl border border-stone-200">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-stone-100 text-stone-700">
                                <tr>
                                    <th class="w-1/3 px-4 py-3 font-semibold">Field</th>
                                    <th class="px-4 py-3 font-semibold">Value</th>
                                </tr>
                            </thead>
                            <tbody id="criteriaModalBody" class="divide-y divide-stone-200 bg-white">
                                <tr>
                                    <td colspan="2" class="px-4 py-8 text-center text-stone-400">No criteria loaded.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
