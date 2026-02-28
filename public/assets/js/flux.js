/**
 * FluxUI — Real-time GitHub Actions-style workflow viewer
 * entreya/flux
 *
 * Fully configurable via PHP widgets:
 *   - Selectors:  cfg.sel.* — where to bind (element IDs)
 *   - Templates:  cfg.templates.step — HTML template for step creation
 *   - Plugins:    cfg.plugins.logPanel.* — behavior options
 *   - Events:     cfg.events.* — custom JS event hooks
 *
 * Public API:
 *   FluxUI.init(config)    — boot: { sseUrl, sel, templates, plugins, events }
 *   FluxUI.rerun()         — reset UI and reconnect SSE
 *   FluxUI.toggleTheme()   — dark ↔ light
 *   FluxUI.expandAll()     — expand all step accordions
 *   FluxUI.collapseAll()   — collapse all step accordions
 */
const FluxUI = (() => {

  // ── Default selector map (backward compat with legacy index.php) ────────
  const SEL_DEFAULTS = {
    badge: 'fx-badge',
    badgeText: 'fx-badge-text',
    jobList: 'fx-job-list',
    jobHeading: 'fx-job-heading',
    steps: 'fx-steps',
    progress: 'fx-progress',
    search: 'fx-search',
    tsBtn: 'fx-toolbar-ts-btn',
    themeBtn: 'fx-toolbar-theme-btn',
    expandBtn: 'fx-toolbar-expand-btn',
    collapseBtn: 'fx-toolbar-collapse-btn',
    rerunBtn: 'fx-toolbar-rerun-btn',
    dropzone: 'fx-dropzone',
    fileInput: 'fx-file-input',
  };

  const DEFAULT_STEP_TPL =
    '<details class="flux-step" id="{id}" data-job="{job}" data-step="{step}" data-status="pending" open>'
    + '<summary class="flux-step-summary">'
    + '<div class="flux-step-ico is-pending" id="{icon_id}"></div>'
    + '{phase}'
    + '<span class="flux-step-name">{name}</span>'
    + '<span class="flux-step-dur" id="{dur_id}"></span>'
    + '<i class="bi bi-chevron-right flux-step-chevron"></i>'
    + '</summary>'
    + '<div class="flux-log-body" id="{logs_id}"></div>'
    + '</details>';

  const DEFAULT_JOB_HEADER_TPL =
    '<div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary sticky-top" id="{header_id}">'
    + '<i class="bi bi-circle text-secondary" id="{icon_id}"></i>'
    + '<span class="fw-semibold small flex-grow-1">{name}</span>'
    + '<small class="text-body-secondary font-monospace" id="{prog_id}">0/{total_steps} steps</small>'
    + '<small class="text-body-secondary font-monospace" id="{dur_id}"></small>'
    + '</div>';

  const DEFAULT_JOB_ITEM_TPL =
    '<li class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2"'
    + ' id="{id}" data-flux-job="{job}" data-status="{status}" style="cursor:pointer">'
    + '<span class="flux-job-icon is-{status}" id="{icon_id}">{icon_char}</span>'
    + '<span class="flex-grow-1 text-truncate fw-medium small">{name}</span>'
    + '<small class="text-body-secondary font-monospace" id="{meta_id}">…</small>'
    + '</li>';

  // ── State ────────────────────────────────────────────────────────────────
  let es = null;
  let cfg = {};
  let sel = {};               // merged selector map
  let stepTpl = '';           // step HTML template
  let jobHeaderTpl = '';      // job header HTML template
  let jobItemTpl = '';        // sidebar job item template
  let collapseMethod = 'details'; // 'details' or 'accordion'
  let hooks = {};             // custom event hooks
  let lineIdx = {};           // { "jobId-stepKey": lineNumber }
  let stepFails = {};         // { "jobId-stepKey": true }
  let jobTimers = {};         // { jobId: startMs }
  let wfStart = null;
  let wfTimer = null;
  let jobTotal = 0;
  let jobsDone = 0;
  let showTs = false;
  let autoCollapse = true;
  let autoScroll = true;

  // ── DOM helpers ─────────────────────────────────────────────────────────
  const $ = id => document.getElementById(id);

  function el(tag, cls, attrs = {}) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    for (const [k, v] of Object.entries(attrs)) e.setAttribute(k, v);
    return e;
  }

  /**
   * Build a dynamic ID for job/step sub-elements.
   * Namespaced under sel.steps (e.g. 'myLogs-step-build-0')
   */
  function pfx(...parts) {
    return sel.steps + '-' + parts.join('-');
  }

  // Step status → inner char
  const STEP_ICONS = {
    pending: { cls: '', char: '' },
    running: { cls: '', char: '↻' },
    success: { cls: '', char: '✓' },
    failure: { cls: '', char: '✕' },
    skipped: { cls: '', char: '–' },
  };

  const JOB_ICON_CHARS = {
    running: '↻', success: '✓', failure: '✕', skipped: '–', pending: '',
  };

  const PHASE_HTML = {
    pre: '<span class="badge text-bg-warning me-1" style="font-size:9px">PRE</span>',
    post: '<span class="badge text-bg-info me-1" style="font-size:9px">POST</span>',
    main: '',
  };

  // ── Event hook helper ───────────────────────────────────────────────────
  function fire(event, ...args) {
    try { hooks[event]?.(...args); } catch (e) { console.warn('[Flux] hook error', event, e); }
  }

  // ── Bootstrap collapse helper (BS4 jQuery + BS5 vanilla) ────────────────
  function bsCollapse(el, action) {
    if (!el) return;
    // BS5: bootstrap.Collapse.getOrCreateInstance
    if (typeof bootstrap !== 'undefined' && bootstrap.Collapse?.getOrCreateInstance) {
      bootstrap.Collapse.getOrCreateInstance(el)[action]();
      // BS4: jQuery $(el).collapse('show'|'hide')
    } else if (typeof jQuery !== 'undefined') {
      jQuery(el).collapse(action);
    }
  }

  // ── Step collapse helpers (details vs accordion) ────────────────────────
  function stepOpen(stepId) {
    const el = $(stepId);
    if (!el) return;
    if (collapseMethod === 'accordion') {
      const colId = stepId.replace(/-step-/, '-collapse-');
      bsCollapse($(colId), 'show');
    } else {
      el.open = true;
    }
  }

  function stepClose(stepId) {
    const el = $(stepId);
    if (!el) return;
    if (collapseMethod === 'accordion') {
      const colId = stepId.replace(/-step-/, '-collapse-');
      bsCollapse($(colId), 'hide');
    } else {
      el.open = false;
    }
  }

  // ── SSE ──────────────────────────────────────────────────────────────────
  function connect(url) {
    if (es) { es.close(); es = null; }
    es = new EventSource(url);

    const on = (name, fn) => es.addEventListener(name, e => {
      if (!e.data) return;
      try { fn(JSON.parse(e.data)); } catch (err) { console.warn('[Flux] bad JSON in', name, err); }
    });

    on('workflow_start', onWorkflowStart);
    on('job_start', onJobStart);
    on('job_success', d => onJobDone(d, 'success'));
    on('job_failure', d => onJobDone(d, 'failure'));
    on('job_skipped', onJobSkipped);
    on('step_start', onStepStart);
    on('step_success', d => onStepDone(d, 'success'));
    on('step_failure', d => onStepDone(d, 'failure'));
    on('step_skipped', onStepSkipped);
    on('log', onLog);
    on('workflow_complete', () => onWorkflowEnd('success'));
    on('workflow_failed', d => onWorkflowEnd('failure', d));

    es.addEventListener('error', e => {
      if (!e.data) return;
      try {
        const d = JSON.parse(e.data);
        console.error('[Flux server error]', d.message);
        setBadge('failure');
        enableRerun();
        es.close();
      } catch { }
    });

    es.addEventListener('stream_close', () => {
      if (es) es.close();
    });

    es.onerror = () => {
      if (es) { es.close(); }
      setTimeout(() => {
        onWorkflowEnd('failure');
        document.querySelectorAll('[data-flux-job][data-status="running"]').forEach(item => {
          const jid = item.dataset.fluxJob;
          onJobDone({ id: jid }, 'failure');
          addAnnotation(jid, 'Connection was lost or timed out. Nginx/PHP killed the stream.');
        });
      }, 500);
    };
  }

  // ── Workflow events ──────────────────────────────────────────────────────
  function onWorkflowStart(d) {
    wfStart = Date.now();
    jobTotal = d.job_count ?? 0;
    jobsDone = 0;

    setBadge('running');
    setToolbarTitle(d.name, '');

    clearInterval(wfTimer);
    wfTimer = setInterval(() => {
      const badge = $(sel.badgeText);
      if (badge) badge.textContent = 'Running · ' + formatDur((Date.now() - wfStart) / 1000);
    }, 1000);

    updateProgress();
    fire('workflow_start', d);
  }

  function onJobStart(d) {
    jobTimers[d.id] = Date.now();
    upsertJobSidebarItem(d.id, d.name, 'running');
    ensureJobHeader(d.id, d.name, d.pre_step_count, d.step_count, d.post_step_count);
    setJobHeaderStatus(d.id, 'running');
    setToolbarTitle(d.id, d.name);
    fire('job_start', d);
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
    fire('job_' + status, d);
  }

  function onJobSkipped(d) {
    upsertJobSidebarItem(d.id, d.name ?? d.id, 'skipped');
    ensureJobHeader(d.id, d.name ?? d.id, 0, 0, 0);
    setJobHeaderStatus(d.id, 'skipped', '');
    jobsDone++;
    updateProgress();
    fire('job_skipped', d);
  }

  function onStepStart(d) {
    upsertStep(d.job, d.step, d.name, d.phase ?? 'main');
    setStepStatus(d.job, d.step, 'running');
    bumpStepProgress(d.job);
    fire('step_start', d);
  }

  function onStepDone(d, status) {
    setStepStatus(d.job, d.step, status, d.duration ?? null);
    const stepId = pfx('step', d.job, d.step);
    if (status === 'failure') {
      stepFails[`${d.job}-${d.step}`] = true;
      stepOpen(stepId);
    } else if (autoCollapse) {
      setTimeout(() => {
        if (!stepFails[`${d.job}-${d.step}`]) stepClose(stepId);
      }, 800);
    }
    fire('step_' + status, d);
  }

  function onStepSkipped(d) {
    upsertStep(d.job, d.step, d.name ?? d.step, d.phase ?? 'main');
    setStepStatus(d.job, d.step, 'skipped');
    fire('step_skipped', d);
  }

  function onLog(d) {
    appendLog(d.job, d.step, d.type, d.content);
    fire('log', d);
  }

  function onWorkflowEnd(status, d) {
    clearInterval(wfTimer);
    setBadge(status);

    const dur = wfStart ? formatDur((Date.now() - wfStart) / 1000) : '';
    const badgeEl = $(sel.badgeText);
    if (badgeEl) badgeEl.textContent = (status === 'success' ? 'Completed' : 'Failed') + (dur ? ' · ' + dur : '');

    if (status === 'success') {
      document.querySelectorAll('[data-flux-job][data-status="running"]').forEach(item => {
        setSidebarJobStatus(item.dataset.fluxJob, 'success', '');
      });
    }

    setProgressDone(status);
    if (es) es.close();
    enableRerun();
    fire('workflow_' + (status === 'success' ? 'complete' : 'failed'), d);
  }

  // ── Sidebar ──────────────────────────────────────────────────────────────
  function upsertJobSidebarItem(id, name, status) {
    const list = $(sel.jobList);
    if (!list) return;

    const ph = list.querySelector('.flux-sidebar-empty');
    if (ph) ph.remove();

    const itemId = pfx('job-item', id);
    if ($(itemId)) return;

    // Build from configurable template
    const html = jobItemTpl
      .replace(/\{id\}/g, itemId)
      .replace(/\{job\}/g, esc(id))
      .replace(/\{name\}/g, esc(name))
      .replace(/\{status\}/g, status)
      .replace(/\{icon_id\}/g, pfx('job-icon', id))
      .replace(/\{icon_char\}/g, JOB_ICON_CHARS[status] || '')
      .replace(/\{meta_id\}/g, pfx('job-meta', id));

    const tmp = document.createElement('div');
    tmp.innerHTML = html.trim();
    const item = tmp.firstElementChild;
    item.addEventListener('click', e => { e.preventDefault(); scrollToJob(id); });
    list.appendChild(item);
  }

  function setSidebarJobStatus(id, status, elapsed) {
    const item = $(pfx('job-item', id));
    if (item) {
      item.dataset.status = status;
      item.classList.remove('active');
    }

    const meta = $(pfx('job-meta', id));
    if (meta) {
      // Update badge color based on status
      meta.className = 'badge badge-pill badge-'
        + (status === 'success' ? 'success' : status === 'failure' ? 'danger' : 'primary');
      if (elapsed) meta.textContent = elapsed;
      else if (status === 'skipped') meta.textContent = 'skipped';
    }
  }

  // ── Job header in main area ──────────────────────────────────────────────
  function ensureJobHeader(id, name, preCount, stepCount, postCount) {
    if ($(pfx('job-header', id))) return;

    const steps = $(sel.steps);
    if (!steps) return;

    const group = el('div', '', { id: pfx('job-group', id), 'data-flux-job': id });

    const total = preCount + stepCount + postCount;

    // Replace placeholders in the header template
    const headerHtml = jobHeaderTpl
      .replace(/\{header_id\}/g, pfx('job-header', id))
      .replace(/\{icon_id\}/g, pfx('jhdr-icon', id))
      .replace(/\{prog_id\}/g, pfx('jhdr-prog', id))
      .replace(/\{dur_id\}/g, pfx('jhdr-dur', id))
      .replace(/\{name\}/g, esc(name))
      .replace(/\{job\}/g, esc(id))
      .replace(/\{total_steps\}/g, total);

    group.innerHTML = headerHtml + `\n      <div class="px-2 py-1" id="${pfx('job-steps', id)}"></div>\n    `;
    steps.appendChild(group);
  }

  function setJobHeaderStatus(id, status, elapsed) {
    const icon = $(pfx('jhdr-icon', id));
    if (icon) {
      const iconMap = { pending: 'bi-circle', running: 'bi-arrow-repeat', success: 'bi-check-circle-fill', failure: 'bi-x-circle-fill', skipped: 'bi-dash-circle' };
      const colorMap = { pending: 'text-secondary', running: 'text-primary', success: 'text-success', failure: 'text-danger', skipped: 'text-secondary' };
      icon.className = `bi ${iconMap[status] || 'bi-circle'} ${colorMap[status] || ''}`;
      if (status === 'running') icon.style.animation = 'spin .9s linear infinite';
      else icon.style.animation = '';
    }

    if (elapsed !== undefined) {
      const dur = $(pfx('jhdr-dur', id));
      if (dur) dur.textContent = elapsed;
    }
  }

  function bumpStepProgress(jobId) {
    const prog = $(pfx('jhdr-prog', jobId));
    if (!prog) return;
    const m = prog.textContent.match(/(\d+)\/(\d+)/);
    if (m) {
      const done = parseInt(m[1]) + 1;
      const total = parseInt(m[2]);
      prog.textContent = `${done}/${total} steps`;
    }
  }

  // ── Steps (template-driven) ─────────────────────────────────────────────
  function upsertStep(jobId, stepKey, name, phase) {
    const id = pfx('step', jobId, stepKey);
    if ($(id)) return;

    const container = $(pfx('job-steps', jobId));
    if (!container) return;

    // Replace placeholders in the template from PHP
    const html = stepTpl
      .replace(/\{id\}/g, id)
      .replace(/\{job\}/g, esc(jobId))
      .replace(/\{step\}/g, esc(stepKey))
      .replace(/\{icon_id\}/g, pfx('step-ico', jobId, stepKey))
      .replace(/\{dur_id\}/g, pfx('step-dur', jobId, stepKey))
      .replace(/\{logs_id\}/g, pfx('logs', jobId, stepKey))
      .replace(/\{collapse_id\}/g, pfx('collapse', jobId, stepKey))
      .replace(/\{name\}/g, esc(name))
      .replace(/\{phase\}/g, PHASE_HTML[phase] ?? '')
      .replace(/\{status\}/g, 'pending');

    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;
    const stepEl = wrapper.firstElementChild;
    container.appendChild(stepEl);
    stepEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function setStepStatus(jobId, stepKey, status, duration) {
    const stepEl = $(pfx('step', jobId, stepKey));
    if (stepEl) stepEl.dataset.status = status;

    const ico = $(pfx('step-ico', jobId, stepKey));
    if (ico) {
      ico.className = `flux-step-ico is-${status}`;
      ico.textContent = STEP_ICONS[status]?.char || '';
    }

    if (duration != null) {
      const dur = $(pfx('step-dur', jobId, stepKey));
      if (dur) dur.textContent = duration + 's';
    }
  }

  // ── Log lines ─────────────────────────────────────────────────────────────
  function appendLog(jobId, stepKey, type, content) {
    const container = $(pfx('logs', jobId, stepKey));
    if (!container) return;

    const key = `${jobId}-${stepKey}`;
    const num = (lineIdx[key] = (lineIdx[key] ?? 0) + 1);
    const ts = new Date().toISOString().slice(11, 23);

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
    if (autoScroll) {
      const stepsEl = $(sel.steps);
      if (stepsEl && (stepsEl.scrollHeight - stepsEl.scrollTop) < stepsEl.clientHeight + 200) {
        stepsEl.scrollTop = stepsEl.scrollHeight;
      }
    }

    const term = $(sel.search)?.value.trim();
    if (term) filterLine(line, term);
  }

  // ── Failure annotation ────────────────────────────────────────────────────
  function addAnnotation(jobId, message) {
    const container = $(pfx('job-steps', jobId));
    if (!container) return;

    const ann = el('div', 'alert alert-danger d-flex align-items-start gap-2 py-2 px-3 small mt-1 mb-0');
    ann.innerHTML = `
      <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
      <span class="font-monospace">${esc(message)}</span>
    `;
    container.appendChild(ann);
  }

  // ── Progress bar ──────────────────────────────────────────────────────────
  function updateProgress() {
    const bar = $(sel.progress);
    if (!bar) return;
    const pct = jobTotal > 0 ? (jobsDone / jobTotal) * 100 : 0;
    bar.style.width = pct + '%';
    bar.setAttribute('aria-valuenow', Math.round(pct));
  }

  function setProgressDone(status) {
    const bar = $(sel.progress);
    if (!bar) return;
    bar.style.width = '100%';
    bar.setAttribute('aria-valuenow', '100');
    bar.classList.remove('bg-primary', 'bg-success', 'bg-danger');
    bar.classList.add(status === 'success' ? 'bg-success' : 'bg-danger');
  }

  // ── Toolbar ───────────────────────────────────────────────────────────────
  function setToolbarTitle(jobId, jobName) {
    const t = $(sel.jobHeading);
    if (t) t.textContent = jobName || jobId;
  }

  // ── Search ────────────────────────────────────────────────────────────────
  function setupSearch() {
    const input = $(sel.search);
    if (!input) return;
    let timer;
    input.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => runSearch(input.value.trim()), 180);
    });
  }

  function runSearch(term) {
    document.querySelectorAll('.flux-log-line').forEach(l => filterLine(l, term));
    // Works for both <details> and .accordion-item step containers
    const stepSelector = collapseMethod === 'accordion' ? '.accordion-item' : '.flux-step';
    document.querySelectorAll(stepSelector).forEach(s => {
      if (!term) { s.style.display = ''; return; }
      const hits = s.querySelectorAll('.flux-log-line:not(.is-hidden)').length;
      s.style.display = hits > 0 ? '' : 'none';
      if (hits > 0) {
        if (collapseMethod === 'accordion') {
          const colEl = s.querySelector('.accordion-collapse');
          bsCollapse(colEl, 'show');
        } else {
          s.open = true;
        }
      }
    });
  }

  function filterLine(line, term) {
    if (!term) { line.classList.remove('is-hidden', 'is-match'); return; }
    const text = line.querySelector('.flux-log-content')?.textContent ?? '';
    let match = false;
    try { match = new RegExp(term, 'i').test(text); }
    catch { match = text.toLowerCase().includes(term.toLowerCase()); }
    line.classList.toggle('is-hidden', !match);
    line.classList.toggle('is-match', match);
  }

  // ── Drop zone ─────────────────────────────────────────────────────────────
  function setupDropZone() {
    const dz = $(sel.dropzone);
    const input = $(sel.fileInput);
    if (!dz) return;

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(ev =>
      document.body.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); }));

    ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, () => dz.classList.add('is-over')));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, () => dz.classList.remove('is-over')));
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
    const dz = $(sel.dropzone);
    if (dz) dz.innerHTML = `
      <div class="text-center p-4">
        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
        <p class="text-body-secondary small mt-2 mb-0">Uploading…</p>
      </div>`;
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
    const badge = $(sel.badge);
    if (!badge) return;
    badge.dataset.status = status;
    const labels = { pending: 'Connecting', running: 'Running', success: 'Completed', failure: 'Failed' };
    const textEl = $(sel.badgeText);
    if (textEl) textEl.textContent = labels[status] ?? status;
  }

  // ── Rerun ─────────────────────────────────────────────────────────────────
  function rerun() {
    if (!cfg.sseUrl) return;
    lineIdx = {};
    stepFails = {};
    jobTimers = {};
    jobTotal = 0;
    jobsDone = 0;
    wfStart = null;
    clearInterval(wfTimer);

    const list = $(sel.jobList);
    if (list) list.innerHTML = '<div class="flux-sidebar-empty text-body-secondary fst-italic small p-2">Waiting for workflow…</div>';

    const steps = $(sel.steps);
    if (steps) steps.innerHTML = '';

    const prog = $(sel.progress);
    if (prog) { prog.style.width = '0%'; prog.classList.remove('bg-success', 'bg-danger'); prog.classList.add('bg-primary'); }

    setToolbarTitle('', 'Initializing…');
    setBadge('pending');

    const btn = $(sel.rerunBtn);
    if (btn) btn.disabled = true;

    connect(cfg.sseUrl);
    fire('rerun');
  }

  function enableRerun() {
    const btn = $(sel.rerunBtn);
    if (btn) btn.disabled = false;
  }

  // ── Theme ─────────────────────────────────────────────────────────────────
  function applyTheme(t) {
    document.documentElement.setAttribute('data-bs-theme', t);
    try { localStorage.setItem('flux-theme', t); } catch { }
    const btn = $(sel.themeBtn);
    const icon = btn?.querySelector('i');
    if (icon) icon.className = (t === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars');
  }

  function toggleTheme() {
    let cur = 'dark';
    try { cur = localStorage.getItem('flux-theme') ?? 'dark'; } catch { }
    applyTheme(cur === 'dark' ? 'light' : 'dark');
  }

  // ── Timestamps toggle ─────────────────────────────────────────────────────
  function toggleTimestamps() {
    showTs = !showTs;
    const stepsEl = $(sel.steps);
    if (stepsEl) stepsEl.classList.toggle('show-ts', showTs);
    const btn = $(sel.tsBtn);
    if (btn) btn.classList.toggle('active', showTs);
  }

  // ── Expand / Collapse ─────────────────────────────────────────────────────
  function expandAll() {
    if (collapseMethod === 'accordion') {
      document.querySelectorAll('.accordion-collapse').forEach(c => bsCollapse(c, 'show'));
    } else {
      document.querySelectorAll('.flux-step').forEach(s => s.open = true);
    }
  }

  function collapseAll() {
    if (collapseMethod === 'accordion') {
      document.querySelectorAll('.accordion-collapse').forEach(c => bsCollapse(c, 'hide'));
    } else {
      document.querySelectorAll('.flux-step').forEach(s => s.open = false);
    }
  }

  // ── Scroll to job ─────────────────────────────────────────────────────────
  function scrollToJob(jobId) {
    const group = $(pfx('job-group', jobId));
    if (group) group.scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.querySelectorAll('[data-flux-job]').forEach(i => i.classList.remove('active'));
    $(pfx('job-item', jobId))?.classList.add('active');
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

    // Merge selectors with defaults
    sel = { ...SEL_DEFAULTS, ...(cfg.sel ?? {}) };

    // Templates from PHP or default
    stepTpl = cfg.templates?.step ?? DEFAULT_STEP_TPL;
    jobHeaderTpl = cfg.templates?.jobHeader ?? DEFAULT_JOB_HEADER_TPL;
    jobItemTpl = cfg.templates?.jobItem ?? DEFAULT_JOB_ITEM_TPL;

    // Plugin options
    const lp = cfg.plugins?.logPanel ?? {};
    collapseMethod = lp.collapseMethod ?? 'details';
    autoCollapse = lp.autoCollapse !== false;
    autoScroll = lp.autoScroll !== false;

    // Event hooks
    hooks = {};
    if (cfg.events) {
      for (const [event, handler] of Object.entries(cfg.events)) {
        hooks[event] = typeof handler === 'function'
          ? handler
          : new Function('return (' + handler + ')')();
      }
    }

    // Theme
    let theme = 'dark';
    try { theme = localStorage.getItem('flux-theme') ?? 'dark'; } catch { }
    applyTheme(theme);

    // Wire up interactive elements
    setupSearch();
    setupDropZone();

    // Wire toolbar button handlers via selectors
    const bind = (key, fn) => { const el = $(sel[key]); if (el) el.addEventListener('click', fn); };
    bind('tsBtn', toggleTimestamps);
    bind('themeBtn', toggleTheme);
    bind('expandBtn', expandAll);
    bind('collapseBtn', collapseAll);
    bind('rerunBtn', () => rerun(cfg.sseUrl));

    // Connect SSE
    if (cfg.sseUrl) connect(cfg.sseUrl);
  }

  return { init, rerun, toggleTheme, toggleTimestamps, expandAll, collapseAll, copyLine };

})();
