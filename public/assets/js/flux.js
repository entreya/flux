/**
 * FluxUI — Real-time GitHub Actions-style workflow viewer
 * entreya/flux
 *
 * Public API:
 *   FluxUI.init(config)    — boot: { sseUrl, uploadUrl }
 *   FluxUI.rerun()         — reset UI and reconnect SSE
 *   FluxUI.toggleTheme()   — dark ↔ light
 *   FluxUI.expandAll()     — expand all step accordions
 *   FluxUI.collapseAll()   — collapse all step accordions
 */
const FluxUI = (() => {

  // ── State ────────────────────────────────────────────────────────────────
  let es         = null;
  let cfg        = {};
  let lineIdx    = {};   // { "jobId-stepKey": lineNumber }
  let stepFails  = {};   // { "jobId-stepKey": true }
  let jobTimers  = {};   // { jobId: startMs }
  let wfStart    = null; // workflow start timestamp
  let wfTimer    = null; // setInterval handle for elapsed timer
  let jobTotal   = 0;    // total jobs expected
  let jobsDone   = 0;    // jobs completed (success or failure)
  let showTs     = false;// timestamp toggle state
  let jobIds     = {};   // { jobId: true } for maintaining order

  // ── DOM ──────────────────────────────────────────────────────────────────
  const $ = id => document.getElementById(id);

  function el(tag, cls, attrs = {}) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    for (const [k, v] of Object.entries(attrs)) e.setAttribute(k, v);
    return e;
  }

  // Step status → Bootstrap Icon class + inner char
  const STEP_ICONS = {
    pending: { cls: '',         char: '' },
    running: { cls: '',         char: '↻' },
    success: { cls: '',         char: '✓' },
    failure: { cls: '',         char: '✕' },
    skipped: { cls: '',         char: '–' },
  };

  const JOB_ICON_CHARS = {
    running: '↻', success: '✓', failure: '✕', skipped: '–', pending: '',
  };

  const PHASE_HTML = {
    pre:  '<span class="flux-phase-tag pre">pre</span>',
    post: '<span class="flux-phase-tag post">post</span>',
    main: '',
  };

  // ── SSE ──────────────────────────────────────────────────────────────────
  function connect(url) {
    if (es) { es.close(); es = null; }
    es = new EventSource(url);

    const on = (name, fn) => es.addEventListener(name, e => {
      if (!e.data) return;
      try { fn(JSON.parse(e.data)); } catch (err) { console.warn('[Flux] bad JSON in', name, err); }
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
    on('workflow_complete', () => onWorkflowEnd('success'));
    on('workflow_failed',   d  => onWorkflowEnd('failure', d));

    es.addEventListener('error', e => {
      if (!e.data) return;
      try {
        const d = JSON.parse(e.data);
        console.error('[Flux server error]', d.message);
        setBadge('failure');
        enableRerun();
        es.close();
      } catch {}
    });

    es.addEventListener('stream_close', () => {
      if (es) es.close();
    });

    es.onerror = () => {
      setTimeout(() => {
        if (es && es.readyState === EventSource.CLOSED) {
          setBadge('failure');
          enableRerun();
        }
      }, 1500);
    };
  }

  // ── Workflow events ──────────────────────────────────────────────────────
  function onWorkflowStart(d) {
    wfStart   = Date.now();
    jobTotal  = d.job_count ?? 0;
    jobsDone  = 0;

    setBadge('running');
    setToolbarTitle(d.name, '');

    // Start the elapsed timer in the badge
    clearInterval(wfTimer);
    wfTimer = setInterval(() => {
      const el = $('fx-badge-text');
      if (el) el.textContent = 'Running · ' + formatDur((Date.now() - wfStart) / 1000);
    }, 1000);

    updateProgress();
  }

  function onJobStart(d) {
    jobTimers[d.id] = Date.now();
    upsertJobSidebarItem(d.id, d.name, 'running');
    ensureJobHeader(d.id, d.name, d.pre_step_count, d.step_count, d.post_step_count);
    setJobHeaderStatus(d.id, 'running');
    setToolbarTitle(d.id, d.name);
  }

  function onJobDone(d, status) {
    const elapsed = jobTimers[d.id]
      ? formatDur((Date.now() - jobTimers[d.id]) / 1000)
      : '';

    jobsDone++;
    setSidebarJobStatus(d.id, status, elapsed);
    setJobHeaderStatus(d.id, status, elapsed);
    updateProgress();

    if (status === 'failure' && d.name) {
      addAnnotation(d.id, `Job "${d.name}" failed`);
    }
  }

  function onJobSkipped(d) {
    upsertJobSidebarItem(d.id, d.name ?? d.id, 'skipped');
    ensureJobHeader(d.id, d.name ?? d.id, 0, 0, 0);
    setJobHeaderStatus(d.id, 'skipped', '');
    jobsDone++;
    updateProgress();
  }

  function onStepStart(d) {
    upsertStep(d.job, d.step, d.name, d.phase ?? 'main');
    setStepStatus(d.job, d.step, 'running');
    bumpStepProgress(d.job);
  }

  function onStepDone(d, status) {
    setStepStatus(d.job, d.step, status, d.duration ?? null);
    if (status === 'failure') {
      stepFails[`${d.job}-${d.step}`] = true;
      const s = $(`step-${d.job}-${d.step}`);
      if (s) s.open = true;
    } else {
      setTimeout(() => {
        const s = $(`step-${d.job}-${d.step}`);
        if (s && !stepFails[`${d.job}-${d.step}`]) s.open = false;
      }, 800);
    }
  }

  function onStepSkipped(d) {
    upsertStep(d.job, d.step, d.name ?? d.step, d.phase ?? 'main');
    setStepStatus(d.job, d.step, 'skipped');
  }

  function onLog(d) {
    appendLog(d.job, d.step, d.type, d.content);
  }

  function onWorkflowEnd(status, d) {
    clearInterval(wfTimer);
    setBadge(status);

    const dur = wfStart ? formatDur((Date.now() - wfStart) / 1000) : '';
    const badgeEl = $('fx-badge-text');
    if (badgeEl) badgeEl.textContent = (status === 'success' ? 'Completed' : 'Failed') + (dur ? ' · ' + dur : '');

    // Mark remaining running jobs as success if workflow succeeded
    if (status === 'success') {
      document.querySelectorAll('.fx-job-item[data-status="running"]').forEach(item => {
        setSidebarJobStatus(item.dataset.jobId, 'success', '');
      });
    }

    setProgressDone(status);
    if (es) es.close();
    enableRerun();
  }

  // ── Sidebar ──────────────────────────────────────────────────────────────
  function upsertJobSidebarItem(id, name, status) {
    const list = $('fx-job-list');
    if (!list) return;

    const ph = list.querySelector('.flux-sidebar-empty');
    if (ph) ph.remove();

    if ($(`job-item-${id}`)) return;

    const item = el('div', 'fx-job-item', {
      id: `job-item-${id}`,
      'data-job-id': id,
      'data-status': status,
    });
    item.innerHTML = `
      <div class="fx-job-icon ${status === 'running' ? 'is-running' : ''}" id="job-icon-${id}">${JOB_ICON_CHARS[status] || ''}</div>
      <div class="fx-job-label">
        <span class="fx-job-name">${esc(name)}</span>
        <span class="fx-job-meta" id="job-meta-${id}">…</span>
      </div>
    `;
    item.addEventListener('click', () => scrollToJob(id));
    list.appendChild(item);
  }

  function setSidebarJobStatus(id, status, elapsed) {
    const item = $(`job-item-${id}`);
    if (item) {
      item.dataset.status = status;
      item.classList.toggle('is-active', false);
    }

    const icon = $(`job-icon-${id}`);
    if (icon) {
      icon.className = `fx-job-icon is-${status}`;
      icon.textContent = JOB_ICON_CHARS[status] || '';
    }

    const meta = $(`job-meta-${id}`);
    if (meta && elapsed) meta.textContent = elapsed;
    else if (meta && status === 'skipped') meta.textContent = 'skipped';
  }

  // ── Job header in main area ──────────────────────────────────────────────
  function ensureJobHeader(id, name, preCount, stepCount, postCount) {
    if ($(`job-header-${id}`)) return;

    const steps = $('fx-steps');
    if (!steps) return;

    const group = el('div', '', { id: `job-group-${id}`, 'data-job-id': id });

    const total = preCount + stepCount + postCount;
    group.innerHTML = `
      <div class="fx-job-header" id="job-header-${id}">
        <i class="fx-job-header-icon bi bi-circle is-pending" id="jhdr-icon-${id}"></i>
        <span class="fx-job-header-name">${esc(name)}</span>
        <span class="fx-job-header-progress text-muted" id="jhdr-prog-${id}">0/${total} steps</span>
        <span class="fx-job-header-dur" id="jhdr-dur-${id}"></span>
      </div>
      <div class="fx-steps-pad" id="job-steps-${id}"></div>
    `;
    steps.appendChild(group);
  }

  function setJobHeaderStatus(id, status, elapsed) {
    const icon = $(`jhdr-icon-${id}`);
    if (icon) {
      const iconMap = { pending: 'bi-circle', running: 'bi-arrow-repeat', success: 'bi-check-circle-fill', failure: 'bi-x-circle-fill', skipped: 'bi-dash-circle' };
      icon.className = `fx-job-header-icon bi ${iconMap[status] || 'bi-circle'} is-${status}`;
    }

    if (elapsed !== undefined) {
      const dur = $(`jhdr-dur-${id}`);
      if (dur) dur.textContent = elapsed;
    }
  }

  function bumpStepProgress(jobId) {
    const prog = $(`jhdr-prog-${jobId}`);
    if (!prog) return;
    const m = prog.textContent.match(/(\d+)\/(\d+)/);
    if (m) {
      const done = parseInt(m[1]) + 1;
      const total = parseInt(m[2]);
      prog.textContent = `${done}/${total} steps`;
    }
  }

  // ── Steps ────────────────────────────────────────────────────────────────
  function upsertStep(jobId, stepKey, name, phase) {
    const id = `step-${jobId}-${stepKey}`;
    if ($(id)) return;

    const container = $(`job-steps-${jobId}`);
    if (!container) return;

    const details = el('details', 'flux-step', {
      id,
      'data-job':    jobId,
      'data-step':   stepKey,
      'data-status': 'pending',
    });
    details.open = true;

    details.innerHTML = `
      <summary class="flux-step-summary">
        <div class="flux-step-ico is-pending" id="step-ico-${jobId}-${stepKey}"></div>
        ${PHASE_HTML[phase] ?? ''}
        <span class="flux-step-name">${esc(name)}</span>
        <span class="flux-step-dur" id="step-dur-${jobId}-${stepKey}"></span>
        <i class="bi bi-chevron-right flux-step-chevron"></i>
      </summary>
      <div class="flux-log-body" id="logs-${jobId}-${stepKey}"></div>
    `;

    container.appendChild(details);
    details.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function setStepStatus(jobId, stepKey, status, duration) {
    const stepEl = $(`step-${jobId}-${stepKey}`);
    if (stepEl) stepEl.dataset.status = status;

    const ico = $(`step-ico-${jobId}-${stepKey}`);
    if (ico) {
      ico.className = `flux-step-ico is-${status}`;
      ico.textContent = STEP_ICONS[status]?.char || '';
    }

    if (duration != null) {
      const dur = $(`step-dur-${jobId}-${stepKey}`);
      if (dur) dur.textContent = duration + 's';
    }
  }

  // ── Log lines ─────────────────────────────────────────────────────────────
  function appendLog(jobId, stepKey, type, content) {
    const container = $(`logs-${jobId}-${stepKey}`);
    if (!container) return;

    const key = `${jobId}-${stepKey}`;
    const num = (lineIdx[key] = (lineIdx[key] ?? 0) + 1);
    const ts  = new Date().toISOString().slice(11, 23);

    const line = el('div', 'flux-log-line', { 'data-type': type });
    line.dataset.raw = content;
    line.innerHTML = `
      <span class="flux-lineno">${num}</span>
      <span class="flux-log-ts">${ts}</span>
      <span class="flux-log-content">${content}</span>
      <button class="flux-log-copy" onclick="FluxUI.copyLine(this)" title="Copy line">Copy</button>
    `;

    container.appendChild(line);

    // Auto-scroll if near bottom
    const stepsEl = $('fx-steps');
    if (stepsEl && (stepsEl.scrollHeight - stepsEl.scrollTop) < stepsEl.clientHeight + 200) {
      stepsEl.scrollTop = stepsEl.scrollHeight;
    }

    const term = $('fx-search')?.value.trim();
    if (term) filterLine(line, term);
  }

  // ── Failure annotation ────────────────────────────────────────────────────
  function addAnnotation(jobId, message) {
    const container = $(`job-steps-${jobId}`);
    if (!container) return;

    const ann = el('div', 'flux-annotation err');
    ann.innerHTML = `
      <i class="bi bi-exclamation-circle ann-ico"></i>
      <span class="ann-body">${esc(message)}</span>
    `;
    container.appendChild(ann);
  }

  // ── Progress bar ──────────────────────────────────────────────────────────
  function updateProgress() {
    const bar = $('fx-progress');
    if (!bar) return;
    const pct = jobTotal > 0 ? (jobsDone / jobTotal) * 100 : 0;
    bar.style.width = pct + '%';
  }

  function setProgressDone(status) {
    const bar = $('fx-progress');
    if (!bar) return;
    bar.style.width = '100%';
    bar.classList.toggle('done',   status === 'success');
    bar.classList.toggle('failed', status === 'failure');
  }

  // ── Toolbar ───────────────────────────────────────────────────────────────
  function setToolbarTitle(jobId, jobName) {
    const t = $('fx-job-heading');
    if (t) t.textContent = jobName || jobId;
  }

  // ── Search ────────────────────────────────────────────────────────────────
  function setupSearch() {
    const input = $('fx-search');
    if (!input) return;
    let timer;
    input.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => runSearch(input.value.trim()), 180);
    });
  }

  function runSearch(term) {
    document.querySelectorAll('.flux-log-line').forEach(l => filterLine(l, term));
    document.querySelectorAll('.flux-step').forEach(s => {
      if (!term) { s.style.display = ''; return; }
      const hits = s.querySelectorAll('.flux-log-line:not(.is-hidden)').length;
      s.style.display = hits > 0 ? '' : 'none';
      if (hits > 0) s.open = true;
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
    dz.addEventListener('click', () => input?.click());
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
      <div class="flux-dz-icon"><i class="bi bi-arrow-repeat" style="animation:spin .9s linear infinite"></i></div>
      <p class="flux-dz-sub">Uploading…</p>`;
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
  function setBadge(status) {
    const badge = $('fx-badge');
    if (!badge) return;
    badge.dataset.status = status;
    const labels = { pending: 'Connecting', running: 'Running', success: 'Completed', failure: 'Failed' };
    const textEl = $('fx-badge-text');
    if (textEl) textEl.textContent = labels[status] ?? status;
  }

  // ── Rerun ─────────────────────────────────────────────────────────────────
  function rerun() {
    if (!cfg.sseUrl) return;
    lineIdx   = {};
    stepFails = {};
    jobTimers = {};
    jobTotal  = 0;
    jobsDone  = 0;
    wfStart   = null;
    clearInterval(wfTimer);

    const list = $('fx-job-list');
    if (list) list.innerHTML = `<div class="flux-sidebar-empty">Waiting for workflow…</div>`;

    const steps = $('fx-steps');
    if (steps) steps.innerHTML = '';

    const prog = $('fx-progress');
    if (prog) { prog.style.width = '0%'; prog.classList.remove('done','failed'); }

    setToolbarTitle('', 'Initializing…');
    setBadge('pending');

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
    try { localStorage.setItem('flux-theme', t); } catch {}
    const icon = $('fx-theme-icon');
    if (icon) icon.className = 'bi ' + (t === 'dark' ? 'bi-sun' : 'bi-moon-stars');
  }

  function toggleTheme() {
    let cur = 'dark';
    try { cur = localStorage.getItem('flux-theme') ?? 'dark'; } catch {}
    applyTheme(cur === 'dark' ? 'light' : 'dark');
  }

  // ── Timestamps toggle ─────────────────────────────────────────────────────
  function toggleTimestamps() {
    showTs = !showTs;
    const stepsEl = $('fx-steps');
    if (stepsEl) stepsEl.classList.toggle('show-ts', showTs);
    const btn = $('fx-ts-btn');
    if (btn) btn.classList.toggle('active', showTs);
  }

  // ── Expand / Collapse ─────────────────────────────────────────────────────
  function expandAll()   { document.querySelectorAll('.flux-step').forEach(s => s.open = true); }
  function collapseAll() { document.querySelectorAll('.flux-step').forEach(s => s.open = false); }

  // ── Scroll to job ─────────────────────────────────────────────────────────
  function scrollToJob(jobId) {
    const group = $(`job-group-${jobId}`);
    if (group) group.scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.querySelectorAll('.fx-job-item').forEach(i => i.classList.remove('is-active'));
    $(`job-item-${jobId}`)?.classList.add('is-active');
  }

  // ── Copy line ─────────────────────────────────────────────────────────────
  function copyLine(btn) {
    const line = btn.closest('.flux-log-line');
    const text = line?.dataset.raw ?? line?.querySelector('.flux-log-content')?.textContent ?? '';
    navigator.clipboard?.writeText(text).then(() => {
      const orig = btn.textContent;
      btn.textContent = 'Copied!';
      setTimeout(() => { btn.textContent = orig; }, 1200);
    });
  }

  // ── Utilities ─────────────────────────────────────────────────────────────
  function formatDur(secs) {
    secs = Math.round(secs);
    if (secs < 60) return secs + 's';
    const m = Math.floor(secs / 60);
    const s = secs % 60;
    return s > 0 ? `${m}m ${s}s` : `${m}m`;
  }

  function esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  function init(config) {
    cfg = config ?? {};
    let theme = 'dark';
    try { theme = localStorage.getItem('flux-theme') ?? 'dark'; } catch {}
    applyTheme(theme);
    setupSearch();
    setupDropZone();
    if (cfg.sseUrl) connect(cfg.sseUrl);
  }

  return { init, rerun, toggleTheme, toggleTimestamps, expandAll, collapseAll, copyLine };

})();
