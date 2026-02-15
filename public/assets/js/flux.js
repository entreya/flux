class FluxApp {
    constructor(config) {
        this.config = config;
        this.data = {
            jobs: {},
            activeJob: null,
            activeStep: null,
            logs: {} // job-step -> html
        };

        this.elements = {
            sidebar: document.getElementById('flux-sidebar'),
            logContainer: document.getElementById('flux-log-lines'),
            logTitle: document.getElementById('flux-log-title'),
            statusBadge: document.getElementById('workflow-status')
        };

        this.init();
    }

    init() {
        const savedTheme = localStorage.getItem('flux-theme') || 'dark';
        this.setTheme(savedTheme);
        this.loadThemeCss(savedTheme);
        document.getElementById('theme').value = savedTheme;

        this.setupSSE();
        this.setupTheme();
    }

    setTheme(theme) {
        document.body.className = '';
        document.body.classList.add('theme-' + theme);
        localStorage.setItem('flux-theme', theme);
    }

    loadThemeCss(theme) {
        fetch(`?action=get_theme_css&theme=${theme}`)
            .then(r => r.text())
            .then(css => document.getElementById('flux-theme-style').innerHTML = css);
    }

    setupSSE() {
        if (this.es) {
            this.es.close();
        }

        this.es = new EventSource(this.config.sseUrl);

        this.es.addEventListener('workflow_start', e => {
            const d = JSON.parse(e.data);
            console.log('Workflow Active:', d.name);
            this.setWorkflowStatus('running');
        });

        this.es.addEventListener('job_start', e => {
            const d = JSON.parse(e.data);
            this.addJob(d);
        });

        this.es.addEventListener('step_start', e => {
            const d = JSON.parse(e.data);
            this.addStep(d);
            this.setStepStatus(d.job, d.step, 'running');
            this.focusStep(d.job, d.step); // Auto-focus executing step
        });

        this.es.addEventListener('log', e => {
            const d = JSON.parse(e.data);
            this.addLog(d.job, d.step, d.content, d.type);
        });

        this.es.addEventListener('step_success', e => {
            const d = JSON.parse(e.data);
            this.setStepStatus(d.job, d.step, 'success', d.duration);
        });

        this.es.addEventListener('step_failure', e => {
            const d = JSON.parse(e.data);
            this.setStepStatus(d.job, d.step, 'failure');
        });

        this.es.addEventListener('workflow_complete', () => {
            this.setWorkflowStatus('success');
            this.es.close();
        });

        this.es.addEventListener('workflow_failed', (e) => {
            this.setWorkflowStatus('failure');
            this.es.close();
        });

        this.es.addEventListener('error', e => { // Custom 'error' event from server
            const d = JSON.parse(e.data);
            console.error('Server Sent Error:', d.message);
            this.addLog(this.data.activeJob || 'system', 0, `<span style="color:red">Error: ${d.message}</span>`, 'error');
            this.setWorkflowStatus('failure');
            this.es.close(); // STOP RECONNECTING
        });

        this.es.onerror = (e) => {
            console.error('SSE Network/Connection Error', e);
        };
    }

    addJob(job) {
        if (this.data.jobs[job.id]) return;

        this.data.jobs[job.id] = { ...job, steps: [] };

        const div = document.createElement('div');
        div.className = 'flux-job-group';
        div.id = `job-${job.id}`;
        div.innerHTML = `
            <div class="flux-job-title">
                <span class="icon-job">📦</span> ${job.name}
            </div>
            <div class="flux-job-steps" id="job-steps-${job.id}"></div>
        `;
        this.elements.sidebar.appendChild(div);
    }

    addStep(step) {
        // job: jobId, step: index, name: stepName
        const container = document.getElementById(`job-steps-${step.job}`);
        if (!container) return; // job might not be rendered yet?

        // Check if step exists
        if (document.getElementById(`step-${step.job}-${step.step}`)) return;

        const div = document.createElement('div');
        div.className = 'flux-step-item';
        div.id = `step-${step.job}-${step.step}`;
        div.innerHTML = `
            <div class="flux-step-name">
                <span class="icon-status status-pending" id="icon-${step.job}-${step.step}"></span>
                ${step.name}
            </div>
            <span class="flux-step-duration" id="dur-${step.job}-${step.step}"></span>
        `;
        div.onclick = () => this.focusStep(step.job, step.step);
        container.appendChild(div);

        // Init logs
        this.data.logs[`${step.job}-${step.step}`] = '';
    }

    setStepStatus(jobId, stepIdx, status, duration = null) {
        const icon = document.getElementById(`icon-${jobId}-${stepIdx}`);
        if (icon) {
            icon.className = `icon-status status-${status}`;
        }
        if (duration) {
            document.getElementById(`dur-${jobId}-${stepIdx}`).innerText = duration.toFixed(1) + 's';
        }
    }

    setWorkflowStatus(status) {
        const el = this.elements.statusBadge;
        el.className = `flux-status-badge ${status}`;
        el.innerText = status.toUpperCase();
    }

    addLog(jobId, stepIdx, content, type) {
        // Format timestamp
        const ts = new Date().toISOString().split('T')[1].substr(0, 12);

        let htmlContent = content;

        const key = `${jobId}-${stepIdx}`;
        const lineClass = type === 'command' ? 'flux-log-command' : 'flux-log-content';
        const prefix = type === 'command' ? '▶ ' : '';

        const html = `
            <div class="flux-log-line">
                <span class="flux-log-ts">${ts}</span>
                <span class="${lineClass}">${prefix}${htmlContent}</span>
            </div>
        `;

        if (!this.data.logs[key]) this.data.logs[key] = '';
        this.data.logs[key] += html;

        // If this is active step, append to DOM
        if (this.data.activeJob === jobId && this.data.activeStep === stepIdx) {
            this.elements.logContainer.insertAdjacentHTML('beforeend', html);
            this.scrollToBottom();
        }
    }

    focusStep(jobId, stepIdx) {
        // Highlight Sidebar
        document.querySelectorAll('.flux-step-item').forEach(el => el.classList.remove('active'));
        const stepEl = document.getElementById(`step-${jobId}-${stepIdx}`);
        if (stepEl) stepEl.classList.add('active');

        // Switch Logs
        this.data.activeJob = jobId;
        this.data.activeStep = stepIdx;

        // Update Title
        const job = this.data.jobs[jobId];
        this.elements.logTitle.innerText = `Logs: Job ${job?.name || jobId} / Step #${stepIdx + 1}`;

        // Render stored logs
        this.elements.logContainer.innerHTML = this.data.logs[`${jobId}-${stepIdx}`] || '';
        this.scrollToBottom();
    }

    scrollToBottom() {
        const el = this.elements.logContainer.parentElement;
        el.scrollTop = el.scrollHeight;
    }

    setupTheme() {
        document.getElementById('theme').addEventListener('change', (e) => {
            const theme = e.target.value;
            this.setTheme(theme);
            this.loadThemeCss(theme);
        });
    }
}
