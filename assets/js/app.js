const state = {
    activeRunId: null,
    selectedRunId: null,
    isRunning: false,
    loopHandle: null,
};

const sourceForms = {
    eTender: [
        { type: 'select', name: 'viewType', label: 'Table Tab', options: ['Live', 'Archive', 'Cancelled', 'All'], value: 'Live' },
        { type: 'text', name: 'procuringEntity', label: 'Procuring Entity (contains)', value: '' },
        { type: 'select', name: 'procurementNature', label: 'Procurement Nature', options: ['', 'Goods', 'Works', 'Service', 'Physical Services'], value: '' },
        { type: 'select', name: 'procurementMethod', label: 'Procurement Method', options: ['', 'RFQ', 'OTM', 'LTM', 'TSTM', 'QCBS', 'LCS', 'SFB', 'DC', 'SBCQ', 'SSS', 'IC', 'CSO', 'DPM', 'OSTETM', 'RFQU', 'RFQL'], value: '' },
        { type: 'text', name: 'publishingDateFrom', label: 'Publishing Date From', value: '' },
        { type: 'text', name: 'publishingDateTo', label: 'Publishing Date To', value: '' },
        { type: 'text', name: 'closingDateFrom', label: 'Closing Date From', value: '' },
        { type: 'text', name: 'closingDateTo', label: 'Closing Date To', value: '' },
        { type: 'select', name: 'frameworkAgreement', label: 'Framework Agreement', options: ['', 'Yes', 'No'], value: '' },
    ],
    APP: [
        { type: 'text', name: 'procuringEntity', label: 'Procuring Entity (contains)', value: '' },
        { type: 'select', name: 'procurementNature', label: 'Procurement Nature', options: ['', 'Goods', 'Works', 'Service', 'Physical Services'], value: '' },
        { type: 'text', name: 'financialYear', label: 'Financial Year', value: '2025-2026' },
        { type: 'select', name: 'budgetType', label: 'Budget Type', options: ['', 'Development', 'Revenue', 'Own fund'], value: '' },
    ],
    eContract: [
        { type: 'text', name: 'procuringEntity', label: 'Procuring Entity (contains)', value: '' },
        { type: 'select', name: 'procurementMethod', label: 'Procurement Method', options: ['', 'RFQ', 'OTM', 'LTM', 'TSTM', 'QCBS', 'LCS', 'SFB', 'DC', 'SBCQ', 'SSS', 'IC', 'CSO', 'DPM', 'OSTETM', 'RFQU', 'RFQL'], value: '' },
        { type: 'text', name: 'district', label: 'District', value: '' },
        { type: 'text', name: 'contractAwardedTo', label: 'Contract Awarded To', value: '' },
        { type: 'select', name: 'frameworkAgreement', label: 'Framework Agreement', options: ['', 'Yes', 'No'], value: '' },
        { type: 'text', name: 'contractSignDateFrom', label: 'Contract Sign Date From', value: '03/07/2023' },
        { type: 'text', name: 'contractSignDateTo', label: 'Contract Sign Date To', value: '21/06/2026' },
    ],
    eExperience: [
        { type: 'text', name: 'procuringEntity', label: 'Procuring Entity (contains)', value: '' },
        { type: 'select', name: 'procurementNature', label: 'Procurement Nature', options: ['', 'Goods', 'Works', 'Service', 'Physical Services'], value: '' },
        { type: 'select', name: 'procurementMethod', label: 'Procurement Method', options: ['', 'RFQ', 'OTM', 'LTM', 'TSTM', 'QCBS', 'LCS', 'SFB', 'DC', 'SBCQ', 'SSS', 'IC', 'CSO', 'DPM', 'OSTETM', 'RFQU', 'RFQL'], value: '' },
        { type: 'text', name: 'contractStartDateFrom', label: 'Contract Start Date From', value: '' },
        { type: 'text', name: 'contractStartDateTo', label: 'Contract Start Date To', value: '' },
        { type: 'text', name: 'contractEndDateFrom', label: 'Contract End Date From', value: '' },
        { type: 'text', name: 'contractEndDateTo', label: 'Contract End Date To', value: '' },
        { type: 'select', name: 'workStatus', label: 'Work Status', options: ['All', 'Completed', 'Ongoing'], value: 'All' },
        { type: 'select', name: 'contractAwardedToMatch', label: 'Awarded To Match', options: ['Contains', 'Equals'], value: 'Contains' },
        { type: 'text', name: 'contractAwardedTo', label: 'Contract Awarded To', value: '' },
        { type: 'text', name: 'companyUniqueId', label: 'Company Unique ID', value: '' },
        { type: 'select', name: 'tenderType', label: 'Tender Type Tab', options: ['eTenders', 'eCMS', 'Manual', 'All'], value: 'eTenders' },
    ],
};

document.addEventListener('DOMContentLoaded', () => {
    const sourceKey = document.getElementById('sourceKey');
    const form = document.getElementById('scraperForm');
    const stopButton = document.getElementById('stopGrabBtn');

    sourceKey.addEventListener('change', () => renderDynamicFields(sourceKey.value));
    form.addEventListener('submit', handleStart);
    stopButton.addEventListener('click', handleStop);

    renderDynamicFields(sourceKey.value);
    refreshStatus();
});

function renderDynamicFields(sourceKey) {
    const fields = sourceForms[sourceKey] || [];
    const container = document.getElementById('dynamicFields');

    container.innerHTML = fields.map((field) => {
        if (field.type === 'select') {
            return `
                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="${field.name}">${field.label}</label>
                    <select id="${field.name}" name="${field.name}" class="w-full rounded-2xl border border-stone-300 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-600">
                        ${field.options.map((option) => `<option value="${escapeAttribute(option)}" ${option === field.value ? 'selected' : ''}>${escapeHtml(option || 'Any')}</option>`).join('')}
                    </select>
                </div>
            `;
        }

        return `
            <div>
                <label class="mb-1 block text-sm font-medium text-stone-700" for="${field.name}">${field.label}</label>
                <input id="${field.name}" name="${field.name}" type="text" value="${escapeAttribute(field.value)}" class="w-full rounded-2xl border border-stone-300 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-600" ${field.name.toLowerCase().includes('date') ? 'placeholder="dd/mm/yyyy"' : ''}>
            </div>
        `;
    }).join('');
}

async function handleStart(event) {
    event.preventDefault();
    if (state.isRunning) {
        return;
    }

    const formData = new FormData(document.getElementById('scraperForm'));
    formData.append('action', 'start');
    setRunningState(true);
    setStatusMessage('Creating a new run...');

    try {
        const payload = await postForm(formData);
        if (!payload.success) {
            throw new Error(payload.error || 'Unable to start run.');
        }

        state.activeRunId = Number(payload.runId);
        state.selectedRunId = Number(payload.runId);
        renderStatus(payload.status);
        setStatusMessage('Run started. Processing page 1...');
        await processLoop();
    } catch (error) {
        setRunningState(false);
        setStatusMessage(error.message || 'Unable to start run.');
    }
}

async function handleStop() {
    if (!state.activeRunId) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'stop');
    formData.append('runId', String(state.activeRunId));

    try {
        const payload = await postForm(formData);
        if (!payload.success) {
            throw new Error(payload.error || 'Unable to stop run.');
        }

        setRunningState(false);
        renderStatus(payload.status);
        setStatusMessage('Run stopped.');
    } catch (error) {
        setStatusMessage(error.message || 'Unable to stop run.');
    }
}

async function resumeRun(runId) {
    const formData = new FormData();
    formData.append('action', 'resume');
    formData.append('runId', String(runId));

    try {
        setRunningState(true);
        setStatusMessage(`Resuming run #${runId}...`);
        const payload = await postForm(formData);
        if (!payload.success) {
            throw new Error(payload.error || 'Unable to resume run.');
        }

        state.activeRunId = Number(runId);
        state.selectedRunId = Number(runId);
        renderStatus(payload.status);
        await processLoop();
    } catch (error) {
        setRunningState(false);
        setStatusMessage(error.message || 'Unable to resume run.');
    }
}

async function processLoop() {
    if (!state.activeRunId || !state.isRunning) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'step');
        formData.append('runId', String(state.activeRunId));

        const payload = await postForm(formData);
        if (!payload.success) {
            throw new Error(payload.error || 'Run step failed.');
        }

        renderStatus(payload.status);

        if (payload.completed) {
            setRunningState(false);
            setStatusMessage('Run completed.');
            return;
        }

        setStatusMessage(`Processed page ${payload.pageProcessed}. Inserted ${payload.recordsInserted} new row(s).`);
        state.loopHandle = window.setTimeout(() => processLoop(), 350);
    } catch (error) {
        setRunningState(false);
        setStatusMessage(error.message || 'Run halted due to an error.');
    }
}

async function refreshStatus(runId = null) {
    try {
        const url = runId ? `api/grabber.php?action=status&runId=${encodeURIComponent(runId)}` : 'api/grabber.php?action=status';
        const response = await fetch(url, { method: 'GET' });
        const payload = await response.json();
        if (!payload.success) {
            throw new Error(payload.error || 'Unable to load status.');
        }

        renderStatus(payload);

        if (payload.activeRunId) {
            state.activeRunId = Number(payload.activeRunId);
            const active = (payload.runs || []).find((run) => Number(run.id) === state.activeRunId);
            if (active && active.status === 'running') {
                state.selectedRunId = Number(payload.selectedRunId || state.activeRunId);
                setRunningState(true);
                setStatusMessage('Resuming active run...');
                await processLoop();
                return;
            }
        }

        setRunningState(false);
    } catch (error) {
        setStatusMessage(error.message || 'Unable to load status.');
    }
}

function renderStatus(payload) {
    state.selectedRunId = Number(payload.selectedRunId || state.selectedRunId || 0) || null;

    renderCounts(payload.counts || {});
    renderRuns(payload.runs || []);
    renderPreview(payload.preview || { columns: [], records: [] }, payload.runs || []);
}

function renderCounts(counts) {
    document.getElementById('count-eTender').textContent = formatNumber(counts.eTender || 0);
    document.getElementById('count-APP').textContent = formatNumber(counts.APP || 0);
    document.getElementById('count-eContract').textContent = formatNumber(counts.eContract || 0);
    document.getElementById('count-eExperience').textContent = formatNumber(counts.eExperience || 0);
}

function renderRuns(runs) {
    const container = document.getElementById('previousRuns');

    if (!runs.length) {
        container.innerHTML = '<div class="rounded-2xl border border-dashed border-stone-300 p-5 text-sm text-stone-400">No runs yet.</div>';
        document.getElementById('activeRunStatus').textContent = 'Idle';
        populateRunSnapshot(null);
        return;
    }

    const activeRun = runs.find((run) => run.status === 'running') || runs[0];
    document.getElementById('activeRunStatus').textContent = humanize(activeRun.status);
    const selectedRun = runs.find((run) => Number(run.id) === Number(state.selectedRunId)) || activeRun;
    populateRunSnapshot(selectedRun);

    container.innerHTML = runs.map((run) => {
        const isSelected = Number(run.id) === Number(state.selectedRunId);
        const canResume = run.status === 'stopped';

        return `
            <article class="rounded-2xl border ${isSelected ? 'border-emerald-500 bg-emerald-50/50' : 'border-stone-200 bg-stone-50'} p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-bold text-stone-900">#${escapeHtml(run.id)} · ${escapeHtml(run.source_label)}</div>
                        <div class="mt-1 text-xs uppercase tracking-wide text-stone-500">${escapeHtml(humanize(run.status))}</div>
                    </div>
                    <button type="button" data-view-run="${escapeAttribute(run.id)}" class="rounded-xl border border-stone-300 bg-white px-3 py-1 text-xs font-semibold text-stone-700 hover:bg-stone-100">
                        View
                    </button>
                </div>
                <p class="mt-3 text-sm leading-6 text-stone-600">${escapeHtml(run.criteria_summary)}</p>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-xs text-stone-600">
                    <div>
                        <dt class="font-semibold text-stone-500">Pages</dt>
                        <dd>${formatNumber(run.last_page_scraped || 0)} / ${run.total_pages ? formatNumber(run.total_pages) : '-'}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-stone-500">Inserted</dt>
                        <dd>${formatNumber(run.total_records_inserted || 0)}</dd>
                    </div>
                </dl>
                <div class="mt-4 flex flex-wrap gap-2">
                    ${canResume ? `<button type="button" data-resume-run="${escapeAttribute(run.id)}" class="rounded-xl bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">Resume</button>` : ''}
                    <a href="api/export.php?runId=${encodeURIComponent(run.id)}" class="rounded-xl border border-stone-300 bg-white px-3 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-100">Export CSV</a>
                </div>
            </article>
        `;
    }).join('');

    container.querySelectorAll('[data-resume-run]').forEach((button) => {
        button.addEventListener('click', () => resumeRun(Number(button.dataset.resumeRun)));
    });

    container.querySelectorAll('[data-view-run]').forEach((button) => {
        button.addEventListener('click', () => {
            state.selectedRunId = Number(button.dataset.viewRun);
            refreshStatus(state.selectedRunId);
        });
    });
}

function populateRunSnapshot(run) {
    document.getElementById('runIdValue').textContent = run ? run.id : '-';
    document.getElementById('runSourceValue').textContent = run ? run.source_label : '-';
    document.getElementById('runPagesValue').textContent = run
        ? `${formatNumber(run.last_page_scraped || 0)} / ${run.total_pages ? formatNumber(run.total_pages) : '-'}`
        : '0 / -';
    document.getElementById('runSeenValue').textContent = run ? formatNumber(run.total_records_seen || 0) : '0';
    document.getElementById('runInsertedValue').textContent = run ? formatNumber(run.total_records_inserted || 0) : '0';

    const totalPages = Number(run?.total_pages || 0);
    const currentPage = Number(run?.last_page_scraped || 0);
    const percentage = totalPages > 0 ? Math.min(100, Math.round((currentPage / totalPages) * 100)) : 0;
    document.getElementById('progressBar').style.width = `${percentage}%`;
    document.getElementById('progressText').textContent = run ? (totalPages > 0 ? `${percentage}%` : humanize(run.status)) : 'Idle';
}

function renderPreview(preview, runs) {
    const head = document.getElementById('previewHead');
    const body = document.getElementById('previewBody');
    const selectedRun = (runs || []).find((run) => Number(run.id) === Number(state.selectedRunId));

    if (!preview.columns?.length) {
        head.innerHTML = '';
        body.innerHTML = '<tr><td class="px-4 py-8 text-center text-stone-400">No records imported yet.</td></tr>';
        return;
    }

    head.innerHTML = `
        <tr>
            ${preview.columns.map((column) => `<th class="px-4 py-3 font-semibold">${escapeHtml(column.label)}</th>`).join('')}
        </tr>
    `;

    if (!preview.records?.length) {
        body.innerHTML = `<tr><td colspan="${preview.columns.length}" class="px-4 py-8 text-center text-stone-400">No records stored for ${escapeHtml(selectedRun?.source_label || 'this run')} yet.</td></tr>`;
        return;
    }

    body.innerHTML = preview.records.map((record) => `
        <tr class="align-top">
            ${preview.columns.map((column) => `<td class="px-4 py-3 text-xs text-stone-700">${formatCell(record[column.key] ?? '')}</td>`).join('')}
        </tr>
    `).join('');
}

async function postForm(formData) {
    const response = await fetch('api/grabber.php', {
        method: 'POST',
        body: formData,
    });
    return response.json();
}

function setRunningState(isRunning) {
    state.isRunning = isRunning;

    if (!isRunning && state.loopHandle) {
        window.clearTimeout(state.loopHandle);
        state.loopHandle = null;
    }

    document.getElementById('startGrabBtn').disabled = isRunning;
    document.getElementById('startGrabBtn').classList.toggle('opacity-50', isRunning);
    document.getElementById('startGrabBtn').classList.toggle('cursor-not-allowed', isRunning);
    document.getElementById('stopGrabBtn').disabled = !isRunning;
}

function setStatusMessage(message) {
    document.getElementById('statusMessage').textContent = message;
}

function formatCell(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    return escapeHtml(String(value));
}

function formatNumber(value) {
    return new Intl.NumberFormat().format(Number(value || 0));
}

function humanize(value) {
    if (!value) {
        return 'Idle';
    }

    return String(value).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeAttribute(value) {
    return escapeHtml(value);
}
