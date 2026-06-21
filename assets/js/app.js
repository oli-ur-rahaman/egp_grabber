const state = {
    activeRunId: null,
    selectedRunId: null,
    isRunning: false,
    loopHandle: null,
    topLevelLoaded: false,
};

const sourceForms = {
    eTender: [
        { type: 'select', name: 'viewType', label: 'Table Tab', options: ['Live', 'Archive', 'Cancelled', 'All'], value: 'Live' },
        { type: 'select', name: 'procurementNature', label: 'Procurement Nature', options: ['', 'Goods', 'Works', 'Service', 'Physical Services'], value: '' },
        { type: 'select', name: 'procurementMethod', label: 'Procurement Method', options: ['', 'RFQ', 'OTM', 'LTM', 'TSTM', 'QCBS', 'LCS', 'SFB', 'DC', 'SBCQ', 'SSS', 'IC', 'CSO', 'DPM', 'OSTETM', 'RFQU', 'RFQL'], value: '' },
        { type: 'text', name: 'publishingDateFrom', label: 'Publishing Date From', value: '' },
        { type: 'text', name: 'publishingDateTo', label: 'Publishing Date To', value: '' },
        { type: 'text', name: 'closingDateFrom', label: 'Closing Date From', value: '' },
        { type: 'text', name: 'closingDateTo', label: 'Closing Date To', value: '' },
        { type: 'select', name: 'frameworkAgreement', label: 'Framework Agreement', options: ['', 'Yes', 'No'], value: '' },
    ],
    APP: [
        { type: 'select', name: 'procurementNature', label: 'Procurement Nature', options: ['', 'Goods', 'Works', 'Service', 'Physical Services'], value: '' },
        { type: 'text', name: 'financialYear', label: 'Financial Year', value: '2025-2026' },
        { type: 'select', name: 'budgetType', label: 'Budget Type', options: ['', 'Development', 'Revenue', 'Own fund'], value: '' },
    ],
    eContract: [
        { type: 'select', name: 'procurementMethod', label: 'Procurement Method', options: ['', 'RFQ', 'OTM', 'LTM', 'TSTM', 'QCBS', 'LCS', 'SFB', 'DC', 'SBCQ', 'SSS', 'IC', 'CSO', 'DPM', 'OSTETM', 'RFQU', 'RFQL'], value: '' },
        { type: 'text', name: 'district', label: 'District', value: '' },
        { type: 'text', name: 'contractAwardedTo', label: 'Contract Awarded To', value: '' },
        { type: 'select', name: 'frameworkAgreement', label: 'Framework Agreement', options: ['', 'Yes', 'No'], value: '' },
        { type: 'text', name: 'contractSignDateFrom', label: 'Contract Sign Date From', value: '03/07/2023' },
        { type: 'text', name: 'contractSignDateTo', label: 'Contract Sign Date To', value: '21/06/2026' },
    ],
    eExperience: [
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
    const ministry = document.getElementById('ministryId');
    const department = document.getElementById('departmentId');
    const office = document.getElementById('officeId');
    const criteriaModal = document.getElementById('criteriaModal');
    const closeCriteriaModalBtn = document.getElementById('closeCriteriaModalBtn');

    sourceKey.addEventListener('change', () => renderDynamicFields(sourceKey.value));
    form.addEventListener('submit', handleStart);
    stopButton.addEventListener('click', handleStop);
    ministry.addEventListener('change', handleMinistryChange);
    department.addEventListener('change', handleDepartmentChange);
    office.addEventListener('change', syncOptionLabels);
    closeCriteriaModalBtn.addEventListener('click', closeCriteriaModal);
    criteriaModal.addEventListener('click', (event) => {
        if (event.target === criteriaModal) {
            closeCriteriaModal();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeCriteriaModal();
        }
    });

    renderDynamicFields(sourceKey.value);
    loadTopLevelDepartments();
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
                <input
                    id="${field.name}"
                    name="${field.name}"
                    type="${isDateField(field.name) ? 'date' : 'text'}"
                    value="${escapeAttribute(isDateField(field.name) ? convertDisplayDateToInput(field.value) : field.value)}"
                    class="w-full rounded-2xl border border-stone-300 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-600"
                >
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
    normalizeDateFields(formData);
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

function normalizeDateFields(formData) {
    for (const [key, value] of Array.from(formData.entries())) {
        if (!isDateField(key) || typeof value !== 'string') {
            continue;
        }

        formData.set(key, convertInputDateToDisplay(value));
    }
}

function isDateField(fieldName) {
    return fieldName.toLowerCase().includes('date');
}

function convertDisplayDateToInput(value) {
    const match = String(value || '').trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (!match) {
        return '';
    }

    const [, day, month, year] = match;
    return `${year}-${month}-${day}`;
}

function convertInputDateToDisplay(value) {
    const match = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) {
        return String(value || '').trim();
    }

    const [, year, month, day] = match;
    return `${day}/${month}/${year}`;
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

async function loadTopLevelDepartments() {
    if (state.topLevelLoaded) {
        return;
    }

    const payload = await fetchJson('api/grabber.php?action=lookup&type=top-level');
    const ministrySelect = document.getElementById('ministryId');
    renderSelectOptions(ministrySelect, payload.options || [], 'Any');
    state.topLevelLoaded = true;
    syncOptionLabels();
}

async function handleMinistryChange() {
    const ministryId = document.getElementById('ministryId').value;
    const departmentSelect = document.getElementById('departmentId');
    const officeSelect = document.getElementById('officeId');

    renderSelectOptions(departmentSelect, [], 'Any');
    renderSelectOptions(officeSelect, [], 'Any');
    syncOptionLabels();

    if (!ministryId) {
        return;
    }

    const payload = await fetchJson(`api/grabber.php?action=lookup&type=children&parentId=${encodeURIComponent(ministryId)}`);
    renderSelectOptions(departmentSelect, payload.options || [], 'Any');
    syncOptionLabels();
}

async function handleDepartmentChange() {
    const departmentId = document.getElementById('departmentId').value;
    const officeSelect = document.getElementById('officeId');
    renderSelectOptions(officeSelect, [], 'Any');
    syncOptionLabels();

    if (!departmentId) {
        return;
    }

    const payload = await fetchJson(`api/grabber.php?action=lookup&type=offices&departmentId=${encodeURIComponent(departmentId)}`);
    renderSelectOptions(officeSelect, payload.options || [], 'Any');
    syncOptionLabels();
}

function renderSelectOptions(selectElement, options, emptyLabel) {
    selectElement.innerHTML = `<option value="">${escapeHtml(emptyLabel)}</option>` + options.map((option) => {
        const label = option.label || '';
        return `<option value="${escapeAttribute(option.id)}">${escapeHtml(label)}</option>`;
    }).join('');
}

function syncOptionLabels() {
    syncHiddenLabel('ministryId', 'ministryLabel');
    syncHiddenLabel('departmentId', 'departmentLabel');
    syncHiddenLabel('officeId', 'officeLabel');
    renderSelectionMeta('ministryId', 'ministryMeta', 'Ministry');
    renderSelectionMeta('departmentId', 'departmentMeta', 'Department');
    renderSelectionMeta('officeId', 'officeMeta', 'Entity');
}

function syncHiddenLabel(selectId, hiddenId) {
    const select = document.getElementById(selectId);
    const hidden = document.getElementById(hiddenId);
    const option = select.options[select.selectedIndex];
    hidden.value = select.value && option ? option.text : '';
}

function renderSelectionMeta(selectId, metaId, prefix) {
    const select = document.getElementById(selectId);
    const meta = document.getElementById(metaId);
    if (!select || !meta) {
        return;
    }

    const option = select.options[select.selectedIndex];
    if (!select.value || !option) {
        meta.textContent = 'No selection';
        return;
    }

    meta.textContent = `${prefix} ID ${select.value}: ${option.text}`;
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
        const statusTone = statusToneClasses(run.status, isSelected);
        const progressLabel = `${formatNumber(run.last_page_scraped || 0)} / ${run.total_pages ? formatNumber(run.total_pages) : '-'}`;
        const updatedLabel = formatDateTime(run.updated_at || run.finished_at || run.started_at || '');

        return `
            <article class="rounded-2xl border ${isSelected ? 'border-emerald-500 bg-white shadow-sm' : 'border-stone-200 bg-white'} p-4 transition">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="truncate text-sm font-bold text-stone-900">#${escapeHtml(run.id)} - ${escapeHtml(run.source_label)}</div>
                            <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] ${statusTone}">
                                ${escapeHtml(humanize(run.status))}
                            </span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-stone-500">
                            <span>Pages ${escapeHtml(progressLabel)}</span>
                            <span>Inserted ${escapeHtml(formatNumber(run.total_records_inserted || 0))}</span>
                            <span>Updated ${escapeHtml(updatedLabel || '-')}</span>
                        </div>
                    </div>
                    <button type="button" data-view-run="${escapeAttribute(run.id)}" class="rounded-xl border border-stone-300 bg-white px-3 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-100">
                        View
                    </button>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" data-criteria-run="${escapeAttribute(run.id)}" class="rounded-xl border border-stone-300 bg-stone-50 px-3 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-100">
                        Search Criteria
                    </button>
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

    container.querySelectorAll('[data-criteria-run]').forEach((button) => {
        button.addEventListener('click', () => {
            const run = runs.find((item) => Number(item.id) === Number(button.dataset.criteriaRun));
            if (run) {
                openCriteriaModal(run);
            }
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

function openCriteriaModal(run) {
    const modal = document.getElementById('criteriaModal');
    const title = document.getElementById('criteriaModalTitle');
    const subtitle = document.getElementById('criteriaModalSubtitle');
    const body = document.getElementById('criteriaModalBody');
    const rows = buildCriteriaRows(run);

    title.textContent = `Search Criteria - Run #${run.id}`;
    subtitle.textContent = `${run.source_label} | ${humanize(run.status)}`;
    body.innerHTML = rows.length
        ? rows.map((row) => `
            <tr>
                <td class="px-4 py-3 font-medium text-stone-600">${escapeHtml(row.label)}</td>
                <td class="px-4 py-3 text-stone-900">${escapeHtml(row.value)}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="2" class="px-4 py-8 text-center text-stone-400">No criteria saved for this run.</td></tr>';

    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeCriteriaModal() {
    const modal = document.getElementById('criteriaModal');
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function buildCriteriaRows(run) {
    const criteria = run.criteria || {};
    const rows = [
        { label: 'Run ID', value: String(run.id) },
        { label: 'Source', value: run.source_label || '' },
        { label: 'Status', value: humanize(run.status) },
    ];

    [
        ['ministryId', 'ministryLabel', 'Ministry / Division / Organization'],
        ['departmentId', 'departmentLabel', 'Department / Organization'],
        ['officeId', 'officeLabel', 'Procuring Entity'],
    ].forEach(([idKey, labelKey, label]) => {
        const idValue = criteria[idKey];
        const labelValue = criteria[labelKey];
        if (idValue || labelValue) {
            rows.push({
                label,
                value: labelValue && idValue ? `${labelValue} (ID: ${idValue})` : String(labelValue || idValue || ''),
            });
        }
    });

    Object.entries(criteria).forEach(([key, value]) => {
        if (value === '' || value === null || value === undefined) {
            return;
        }

        if (['ministryId', 'ministryLabel', 'departmentId', 'departmentLabel', 'officeId', 'officeLabel'].includes(key)) {
            return;
        }

        if (key.endsWith('Id')) {
            const labelKey = `${key.slice(0, -2)}Label`;
            if (criteria[labelKey]) {
                return;
            }
        }

        rows.push({
            label: humanizeKeyJs(key),
            value: String(value),
        });
    });

    return rows;
}

async function postForm(formData) {
    const response = await fetch('api/grabber.php', {
        method: 'POST',
        body: formData,
    });
    return response.json();
}

async function fetchJson(url) {
    const response = await fetch(url, { method: 'GET' });
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

function formatDateTime(value) {
    if (!value) {
        return '';
    }

    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    }).format(date);
}

function humanize(value) {
    if (!value) {
        return 'Idle';
    }

    return String(value).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function humanizeKeyJs(value) {
    return humanize(String(value).replace(/([a-z])([A-Z])/g, '$1 $2'));
}

function statusToneClasses(status, isSelected) {
    if (status === 'running') {
        return 'bg-emerald-100 text-emerald-800';
    }
    if (status === 'completed') {
        return 'bg-sky-100 text-sky-800';
    }
    if (status === 'stopped') {
        return isSelected ? 'bg-amber-100 text-amber-800' : 'bg-stone-100 text-stone-700';
    }

    return 'bg-stone-100 text-stone-700';
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
