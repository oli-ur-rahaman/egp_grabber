const state = {
    activeRunId: null,
    isRunning: false,
    loopHandle: null,
};

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('scraperForm');
    const startButton = document.getElementById('startGrabBtn');
    const stopButton = document.getElementById('stopGrabBtn');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (state.isRunning) {
            return;
        }

        await startRun(new FormData(form));
    });

    stopButton.addEventListener('click', async () => {
        if (!state.activeRunId) {
            return;
        }

        await stopRun();
    });

    refreshStatus();
});

async function startRun(formData) {
    setRunningState(true);
    setStatusMessage('Creating a new scrape run...');

    formData.append('action', 'start');

    try {
        const response = await fetch('api/grabber.php', {
            method: 'POST',
            body: formData,
        });
        const payload = await response.json();

        if (!payload.success) {
            throw new Error(payload.error || 'Unable to start the scrape.');
        }

        state.activeRunId = payload.runId;
        renderStatus(payload.status);
        setStatusMessage('Scrape run started. Importing page 1...');
        await processLoop();
    } catch (error) {
        setRunningState(false);
        setStatusMessage(error.message || 'Unable to start the scrape.');
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

        const response = await fetch('api/grabber.php', {
            method: 'POST',
            body: formData,
        });
        const payload = await response.json();

        if (!payload.success) {
            throw new Error(payload.error || 'Scrape step failed.');
        }

        renderStatus(payload.status);

        if (payload.completed) {
            setRunningState(false);
            setStatusMessage('Scrape completed.');
            return;
        }

        const pageText = payload.pageProcessed ? `Processed page ${payload.pageProcessed}.` : 'Processing next page...';
        setStatusMessage(`${pageText} Inserted ${payload.recordsInserted} new rows this step.`);

        state.loopHandle = window.setTimeout(() => {
            processLoop();
        }, 350);
    } catch (error) {
        setRunningState(false);
        setStatusMessage(error.message || 'Scrape halted due to an error.');
    }
}

async function stopRun() {
    try {
        const formData = new FormData();
        formData.append('action', 'stop');
        formData.append('runId', String(state.activeRunId));

        const response = await fetch('api/grabber.php', {
            method: 'POST',
            body: formData,
        });
        const payload = await response.json();

        if (!payload.success) {
            throw new Error(payload.error || 'Unable to stop the scrape.');
        }

        setRunningState(false);
        renderStatus(payload.status);
        setStatusMessage('Scrape stopped.');
    } catch (error) {
        setStatusMessage(error.message || 'Unable to stop the scrape.');
    }
}

async function refreshStatus() {
    try {
        const response = await fetch('api/grabber.php?action=status', {
            method: 'GET',
        });
        const payload = await response.json();

        if (!payload.success) {
            throw new Error(payload.error || 'Unable to fetch status.');
        }

        renderStatus(payload);

        const run = payload.run;
        if (run && run.status === 'running') {
            state.activeRunId = Number(run.id);
            setRunningState(true);
            setStatusMessage('Resuming active scrape run...');
            await processLoop();
            return;
        }

        setRunningState(false);
    } catch (error) {
        setStatusMessage(error.message || 'Unable to load status.');
    }
}

function renderStatus(payload) {
    const summary = payload.summary || {};
    const run = payload.run || null;
    const records = payload.records || [];

    document.getElementById('statTotalRecords').textContent = formatNumber(summary.total_records || 0);
    document.getElementById('statTotalTenders').textContent = formatNumber(summary.total_tenders || 0);
    document.getElementById('statRunStatus').textContent = run ? humanize(run.status) : 'Idle';

    document.getElementById('runIdValue').textContent = run ? run.id : '-';
    document.getElementById('runPagesValue').textContent = run
        ? `${formatNumber(run.last_page_scraped || 0)} / ${run.total_pages ? formatNumber(run.total_pages) : '-'}`
        : '0 / -';
    document.getElementById('runSeenValue').textContent = run ? formatNumber(run.total_records_seen || 0) : '0';
    document.getElementById('runInsertedValue').textContent = run ? formatNumber(run.total_records_inserted || 0) : '0';

    renderProgress(run);
    renderRecords(records);
}

function renderProgress(run) {
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');

    if (!run) {
        progressBar.style.width = '0%';
        progressText.textContent = 'Idle';
        return;
    }

    const totalPages = Number(run.total_pages || 0);
    const currentPage = Number(run.last_page_scraped || 0);
    const percentage = totalPages > 0 ? Math.min(100, Math.round((currentPage / totalPages) * 100)) : 0;

    progressBar.style.width = `${percentage}%`;
    progressText.textContent = totalPages > 0
        ? `${percentage}%`
        : humanize(run.status);
}

function renderRecords(records) {
    const tbody = document.getElementById('recordsTableBody');

    if (!records.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-stone-400">No records imported yet.</td></tr>';
        return;
    }

    tbody.innerHTML = records.map((record) => `
        <tr class="align-top">
            <td class="px-4 py-3 font-mono text-xs text-stone-800">${escapeHtml(record.tender_id || '')}</td>
            <td class="px-4 py-3 text-xs text-stone-700">${escapeHtml(record.invitation_ref_no || '')}</td>
            <td class="px-4 py-3 text-xs text-stone-700">
                <div class="font-medium text-stone-900">${escapeHtml(record.tender_title || '')}</div>
                <a href="${escapeAttribute(record.detail_url || '#')}" target="_blank" rel="noreferrer" class="mt-1 inline-block text-emerald-700 hover:text-emerald-900">detail</a>
            </td>
            <td class="px-4 py-3 text-xs text-stone-700">
                <div>${escapeHtml(record.procuring_entity || '')}</div>
                <div class="mt-1 text-stone-500">${escapeHtml(record.procurement_method || '')}</div>
            </td>
            <td class="px-4 py-3 text-xs text-stone-700">${escapeHtml(record.district || '')}</td>
            <td class="px-4 py-3 text-xs text-stone-700">${escapeHtml(record.notification_of_award_raw || '')}</td>
            <td class="px-4 py-3 text-xs text-stone-700">${escapeHtml(record.contract_award_to || '')}</td>
            <td class="px-4 py-3 text-xs font-medium text-stone-800">${escapeHtml(record.contract_value_raw || '')}</td>
        </tr>
    `).join('');
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

function formatNumber(value) {
    return new Intl.NumberFormat().format(Number(value || 0));
}

function humanize(value) {
    if (!value) {
        return 'Idle';
    }

    return String(value)
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
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
