/**
 * FluxUI — Real-time workflow log viewer
 * entreya/flux
 *
 * Public API:
 *   FluxUI.init(config)    — boot: { sseUrl, uploadUrl }
 *   FluxUI.rerun()         — reset UI and reconnect SSE
 *   FluxUI.toggleTheme()   — dark ↔ light
 *   FluxUI.expandAll()     — expand all step accordions
 */

const FluxUI = (() => {

  // ── State ────────────────────────────────────────────────────────────────
  let es        = null;
  let cfg       = {};
  let lineIdx   = {};   // { "jobId-stepKey": lineNumber }
  let stepFails = {};   // { "jobId-stepKey": true }
  let jobTimers = {};   // { jobId: startTimestamp }

  // ── DOM helpers ──────────────────────────────────────────────────────────
  const $  = id => document.getElementById(id);
  const el = (tag, cls = '', attrs = {}) => {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    for (const [k, v] of Object.entries(attrs)) e.setAttribute(k, v);
    return e;
  };

  // Bootstrap Icons (bi-*) mapped to status
  const ICONS = {
    pending: 'bi-circle',
    running: 'bi-arrow-repeat',
    success: 'bi-check-circle-fill',
    failure: 'bi-x-circle-fill',
    skipped: 'bi-dash-circle',
  };

  const PHASE_HTML = {
    pre:  '<span class="flux-phase-badge flux-phase-pre">pre</span>',
    post: '<span class="flux-phase-badge flux-phase-post">post</span>',
    main: '',
  };

  // ── SSE ──────────────────────────────────────────────────────────────────
  function connect(url) {
    if (es) { es.close(); es = null; }
    es = new EventSource(url);

    // Helper: listen for a named server-sent event and JSON-parse its data safely
    const on = (name, fn) => es.addEventListener(name, e => {
      if (!e.data) return; // guard: browser error events have no data
      try { fn(JSON.parse(e.data)); } catch(err) { console.warn('[Flux] bad JSON in', name, err); }
    });

    on('workflow_start',    onWorkflowStart);
    on('job_start',         onJobStart);
    on('job_success',       d => onJobDone(d, 'success'));
    on('job_failure',       d => onJobDone(d, 'failure'));
    on('job_skipped',       onJobSkipped);
    on('step_start',        onStepStart);
    on('step_success',      d => onStepDone(d, 'success'));
    on('step_failure',      d => onStepDone(d, 'failure'));
    on('step_skipped',      onStepSkipped);
    on('log',               onLog);
    on('workflow_complete', () => onWorkflowDone('success'));
    on('workflow_failed',   d => onWorkflowDone('failure', d.message));

    // Server-sent `error` event (named, has JSON data with a message field)
    es.addEventListener('error', e => {
      if (!e.data) return; // ignore browser-level connection drops here
      try {
        const d = JSON.parse(e.data);
        console.error('[Flux server error]', d.message);
        setRunBadge('failure');
        enableRerun();
        // Stop reconnecting — this is a fatal server error (auth, not found, etc.)
        es.close();
      } catch {}
    });

    // stream_close = server intentionally ended the stream
    es.addEventListener('stream_close', () => es.close());

    // Browser-level connection error (network issue, server down, etc.)
    // EventSource will auto-retry — we just update the badge.
    // If it stays CLOSED, enable the rerun button.
    es.onerror = () => {
      setTimeout(() => {
        if (es && es.readyState === EventSource.CLOSED) {
          setRunBadge('failure');
          enableRerun();
        }
      }, 1000);
    };
  }

  // ── Workflow events ──────────────────────────────────────────────────────
  function onWorkflowStart(d) {
    setRunBadge('running');
    setText('fx-job-heading', d.name);
  }

  function onJobStart(d) {
    jobTimers[d.id] = Date.now();
    upsertJobItem(d.id, d.name, 'running');
    setText('fx-job-heading', d.name);
  }

  function onJobDone(d, status) {
    const elapsed = jobTimers[d.id]
      ? ((Date.now() - jobTimers[d.id]) / 1000).toFixed(1) + 's'
      : '';

    setJobStatus(d.id, status);

    const durEl = $(`job-dur-${d.id}`);
    if (durEl) durEl.textContent = elapsed;

    addJobSeparator(d.id, d.name ?? d.id, elapsed, status === 'failure');
  }

  function onJobSkipped(d) {
    upsertJobItem(d.id, d.name ?? d.id, 'skipped');
    setJobStatus(d.id, 'skipped');
    addJobSeparator(d.id, d.name ?? d.id, '', false, true);
  }

  function onStepStart(d) {
    upsertStep(d.job, d.step, d.name, d.phase ?? 'main');
    setStepStatus(d.job, d.step, 'running');
  }

  function onStepDone(d, status) {
    setStepStatus(d.job, d.step, status, d.duration ?? null);

    if (status === 'failure') {
      stepFails[`${d.job}-${d.step}`] = true;
      const stepEl = $(`step-${d.job}-${d.step}`);
      if (stepEl) stepEl.open = true;
    } else {
      // Auto-collapse successful steps after a short pause
      setTimeout(() => {
        const stepEl = $(`step-${d.job}-${d.step}`);
        if (stepEl && !stepFails[`${d.job}-${d.step}`]) stepEl.open = false;
      }, 900);
    }
  }

  function onStepSkipped(d) {
    upsertStep(d.job, d.step, d.name ?? d.step, d.phase ?? 'main');
    setStepStatus(d.job, d.step, 'skipped');
  }

  function onLog(d) {
    appendLog(d.job, d.step, d.type, d.content);
  }

  function onWorkflowDone(status) {
    setRunBadge(status);
    if (status === 'success') {
      document.querySelectorAll('.flux-job-item[data-status="running"]')
        .forEach(item => setJobStatus(item.dataset.jobId, 'success'));
    }
    if (es) es.close();
    enableRerun();
  }

  // ── Sidebar ──────────────────────────────────────────────────────────────
  function upsertJobItem(id, name, status) {
    const list = $('fx-job-list');
    if (!list) return;

    // Clear placeholder on first job
    const ph = list.querySelector('.flux-sidebar-empty');
    if (ph) ph.remove();

    if ($(`job-item-${id}`)) return;

    const item = el('div', 'flux-job-item', {
      id: `job-item-${id}`,
      'data-job-id': id,
      'data-status': status,
    });
    item.innerHTML = `
      <span class="flux-status-dot" id="job-dot-${id}"></span>
      <span class="flux-job-name">${esc(name)}</span>
      <span class="flux-job-dur text-muted" id="job-dur-${id}"></span>
    `;
    item.addEventListener('click', () => scrollToJob(id));
    list.appendChild(item);
    setJobDot(id, status);
  }

  function setJobStatus(id, status) {
    const item = $(`job-item-${id}`);
    if (item) {
      item.dataset.status = status;
      item.classList.toggle('is-active', status === 'running');
    }
    setJobDot(id, status);
  }

  function setJobDot(id, status) {
    const dot = $(`job-dot-${id}`);
    if (!dot) return;
    dot.className = `flux-status-dot is-${status}`;
    dot.textContent = { success: '✓', failure: '✕', skipped: '–', pending: '', running: '' }[status] ?? '';
  }

  // ── Steps ────────────────────────────────────────────────────────────────
  function upsertStep(jobId, stepKey, name, phase) {
    const id = `step-${jobId}-${stepKey}`;
    if ($(id)) return;

    const steps = $('fx-steps');
    if (!steps) return;

    const details = el('details', 'flux-step', {
      id,
      'data-job':    jobId,
      'data-step':   stepKey,
      'data-status': 'pending',
    });
    details.open = true;

    details.innerHTML = `
      <summary class="flux-step-summary">
        <i class="bi ${ICONS.pending} flux-step-icon is-pending" id="step-icon-${jobId}-${stepKey}"></i>
        ${PHASE_HTML[phase] ?? ''}
        <span class="flux-step-name">${esc(name)}</span>
        <span class="flux-step-duration text-muted" id="step-dur-${jobId}-${stepKey}"></span>
        <i class="bi bi-chevron-right flux-step-chevron ms-1"></i>
      </summary>
      <div class="flux-log-body" id="logs-${jobId}-${stepKey}"></div>
    `;

    steps.appendChild(details);
    details.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function setStepStatus(jobId, stepKey, status, duration = null) {
    const stepEl = $(`step-${jobId}-${stepKey}`);
    if (stepEl) stepEl.dataset.status = status;

    const icon = $(`step-icon-${jobId}-${stepKey}`);
    if (icon) icon.className = `bi ${ICONS[status] ?? ICONS.pending} flux-step-icon is-${status}`;

    if (duration !== null) {
      const dur = $(`step-dur-${jobId}-${stepKey}`);
      if (dur) dur.textContent = `${duration}s`;
    }
  }

  // ── Job separator line (between jobs, like GitHub Actions) ───────────────
  function addJobSeparator(jobId, jobName, elapsed = '', failed = false, skipped = false) {
    const steps = $('fx-steps');
    if (!steps) return;

    const color  = skipped ? 'var(--bs-secondary-color)' : failed ? 'var(--flux-danger)' : 'var(--flux-success)';
    const icon   = skipped ? 'bi-dash-circle' : failed ? 'bi-x-circle-fill' : 'bi-check-circle-fill';
    const label  = `${esc(jobName)}${elapsed ? ' · ' + elapsed : ''}`;

    const sep = el('div', 'd-flex align-items-center gap-2 py-2 px-1');
    sep.innerHTML = `
      <div style="flex:1;height:1px;background:var(--bs-border-color)"></div>
      <span class="small text-muted d-flex align-items-center gap-1">
        <i class="bi ${icon}" style="color:${color};font-size:12px"></i>
        ${label}
      </span>
      <div style="flex:1;height:1px;background:var(--bs-border-color)"></div>
    `;
    steps.appendChild(sep);
  }

  // ── Log lines ─────────────────────────────────────────────────────────────
  function appendLog(jobId, stepKey, type, content) {
    const container = $(`logs-${jobId}-${stepKey}`);
    if (!container) return;

    const key    = `${jobId}-${stepKey}`;
    const num    = (lineIdx[key] = (lineIdx[key] ?? 0) + 1);
    const ts     = new Date().toISOString().slice(11, 23);

    const line = el('div', 'flux-log-line', { 'data-type': type });
    line.dataset.raw = content;
    line.innerHTML = `
      <span class="flux-lineno">${num}</span>
      <span class="flux-log-ts">${ts}</span>
      <span class="flux-log-content">${content}</span>
    `;

    container.appendChild(line);

    // Scroll the step area if near bottom
    const steps = $('fx-steps');
    if (steps && (steps.scrollHeight - steps.scrollTop) < steps.clientHeight + 150) {
      steps.scrollTop = steps.scrollHeight;
    }

    // Honour active search
    const term = $('fx-search')?.value.trim();
    if (term) filterLine(line, term);
  }

  // ── Search ────────────────────────────────────────────────────────────────
  function setupSearch() {
    const input = $('fx-search');
    if (!input) return;
    let timer;
    input.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => runSearch(input.value.trim()), 200);
    });
  }

  function runSearch(term) {
    document.querySelectorAll('.flux-log-line').forEach(line => filterLine(line, term));
    document.querySelectorAll('.flux-step').forEach(step => {
      if (!term) { step.style.display = ''; return; }
      const hits = step.querySelectorAll('.flux-log-line:not(.is-hidden)').length;
      step.style.display = hits > 0 ? '' : 'none';
      if (hits > 0) step.open = true;
    });
  }

  function filterLine(line, term) {
    if (!term) { line.classList.remove('is-hidden', 'is-match'); return; }
    const text = line.querySelector('.flux-log-content')?.textContent ?? '';
    let match = false;
    try { match = new RegExp(term, 'i').test(text); }
    catch { match = text.toLowerCase().includes(term.toLowerCase()); }
    line.classList.toggle('is-hidden', !match);
    line.classList.toggle('is-match',   match);
  }

  // ── Drop zone ─────────────────────────────────────────────────────────────
  function setupDropZone() {
    const dz    = $('fx-dropzone');
    const input = $('fx-file-input');
    if (!dz) return;

    ['dragenter','dragover','dragleave','drop'].forEach(ev =>
      document.body.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); }));

    ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, () => dz.classList.add('is-over')));
    ['dragleave','drop'].forEach(ev  => dz.addEventListener(ev, () => dz.classList.remove('is-over')));
    dz.addEventListener('drop', e => handleFiles(e.dataTransfer.files));
    input?.addEventListener('change', () => handleFiles(input.files));
  }

  function handleFiles(files) {
    const file = files?.[0];
    if (!file) return;
    if (!/\.(ya?ml)$/i.test(file.name)) { alert('Select a .yaml or .yml file.'); return; }
    uploadFile(file);
  }

  function uploadFile(file) {
    const dz = $('fx-dropzone');
    if (dz) dz.innerHTML = `
      <div class="flux-dz-icon mb-2"><i class="bi bi-arrow-repeat" style="font-size:36px;animation:spin .9s linear infinite"></i></div>
      <p class="text-muted small">Uploading…</p>`;
    const fd = new FormData();
    fd.append('workflow_file', file);
    fetch(cfg.uploadUrl ?? 'upload.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (d.workflow) location.href = `index.php?workflow=${encodeURIComponent(d.workflow)}`;
        else alert(d.error ?? 'Upload failed');
      })
      .catch(() => alert('Upload failed. Check server logs.'));
  }

  // ── Status badge ──────────────────────────────────────────────────────────
  function setRunBadge(status) {
    const badge = $('fx-status-badge');
    if (!badge) return;
    badge.dataset.status = status;
    const labels = { pending: 'Connecting', running: 'Running', success: 'Completed', failure: 'Failed' };
    badge.querySelector('.flux-run-text').textContent = labels[status] ?? status;
  }

  // ── Rerun ─────────────────────────────────────────────────────────────────
  function rerun() {
    if (!cfg.sseUrl) return;
    lineIdx   = {};
    stepFails = {};
    jobTimers = {};

    const list = $('fx-job-list');
    if (list) list.innerHTML = `<div class="flux-sidebar-empty text-muted small fst-italic">Waiting for workflow…</div>`;

    const steps = $('fx-steps');
    if (steps) steps.innerHTML = '';

    setText('fx-job-heading', 'Initializing…');
    setRunBadge('pending');

    const btn = $('fx-rerun-btn');
    if (btn) btn.disabled = true;

    connect(cfg.sseUrl);
  }

  function enableRerun() {
    const btn = $('fx-rerun-btn');
    if (btn) btn.disabled = false;
  }

  // ── Theme ─────────────────────────────────────────────────────────────────
  function applyTheme(t) {
    document.documentElement.setAttribute('data-bs-theme', t);
    localStorage.setItem('flux-theme', t);
    const icon = $('fx-theme-icon');
    if (icon) icon.className = t === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
  }

  function toggleTheme() {
    const cur = localStorage.getItem('flux-theme') ?? 'dark';
    applyTheme(cur === 'dark' ? 'light' : 'dark');
  }

  // ── Utilities ─────────────────────────────────────────────────────────────
  function scrollToJob(jobId) {
    const first = document.querySelector(`.flux-step[data-job="${jobId}"]`);
    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.querySelectorAll('.flux-job-item').forEach(i => i.classList.remove('is-active'));
    $(`job-item-${jobId}`)?.classList.add('is-active');
  }

  function expandAll() {
    document.querySelectorAll('.flux-step').forEach(s => { s.open = true; });
  }

  function setText(id, text) {
    const e = $(id);
    if (e) e.textContent = text;
  }

  function esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  function init(config) {
    cfg = config ?? {};
    applyTheme(localStorage.getItem('flux-theme') ?? 'dark');
    setupSearch();
    setupDropZone();
    if (cfg.sseUrl) connect(cfg.sseUrl);
  }

  return { init, rerun, toggleTheme, expandAll };

})();
