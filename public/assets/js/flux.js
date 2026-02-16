class FluxApp {
    constructor(config) {
        this.config = config;
        this.data = {
            activeJob: null,
            logs: {}
        };

        this.elements = {
            sidebar: document.getElementById('flux-sidebar'),
            stepsContainer: document.getElementById('flux-steps-container'),
            jobTitle: document.getElementById('flux-job-title'),
            statusBadge: document.getElementById('workflow-status')
        };

        this.init();
    }

    init() {
        // Theme defaults to dark, no switcher now
        this.setTheme('dark');
        this.loadThemeCss('dark');

        this.setupDragDrop();
        this.setupSearch();

        this.data.lineCounts = {}; // Track line numbers per step

        // Fix: Only setup SSE if URL is present (prevents console error on Data-Drop page)
        if (this.config.sseUrl) {
            this.setupSSE();
        }
    }

    showWorkflowFile(file) {
        fetch('?action=get_workflow_content&file=' + encodeURIComponent(file))
            .then(r => {
                if (!r.ok) throw new Error('File not found');
                return r.text();
            })
            .then(text => {
                const view = document.getElementById('file-content-view');
                if (view) view.textContent = text;
                const modalEl = document.getElementById('fileModal');
                if (modalEl && window.bootstrap) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                } else {
                    console.error('Bootstrap Modal not found or Bootstrap not loaded');
                }
            })
            .catch(e => alert(e.message));
    }

    setupSearch() {
        const input = document.getElementById('log-search');
        if (!input) return;

        // Anti-bounce for performance
        let timeout;
        input.addEventListener('input', (e) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const term = e.target.value.trim();
                const regex = term ? new RegExp(term, 'i') : null;

                document.querySelectorAll('.flux-step').forEach(step => {
                    const lines = step.querySelectorAll('.flux-log-line');
                    let matchCount = 0;

                    lines.forEach(line => {
                        if (!term) {
                            line.style.display = 'flex';
                            matchCount++;
                            return;
                        }

                        const content = line.querySelector('.flux-log-content').innerText;
                        const isMatch = regex.test(content);
                        line.style.display = isMatch ? 'flex' : 'none';
                        if (isMatch) matchCount++;
                    });

                    // Toggle step visibility based on matches
                    if (term && matchCount === 0) {
                        step.style.display = 'none';
                    } else {
                        step.style.display = 'block';
                        // Auto-expand if matches found
                        if (term) step.open = true;
                    }
                });
            }, 300);
        });
    }

    setupDragDrop() {
        const dropzone = document.getElementById('flux-dropzone');
        // We bind to BODY to catch drops anywhere
        const target = document.body;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            target.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        if (dropzone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, () => dropzone.classList.add('highlight'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, () => dropzone.classList.remove('highlight'), false);
            });

            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                this.handleFiles(files);
            });
        }

        // Also allow drop on body if dropzone is missing (active view)
        target.addEventListener('drop', (e) => {
            // If dropzone exists, the specific listener handles it. If not, we handle it here.
            if (!dropzone && e.dataTransfer.files.length > 0) {
                this.handleFiles(e.dataTransfer.files);
            }
        });

        const input = document.getElementById('file-upload');
        if (input) {
            input.addEventListener('change', () => this.handleFiles(input.files));
        }
    }

    handleFiles(files) {
        if (files.length > 0) {
            this.uploadFile(files[0]);
        }
    }

    uploadFile(file) {
        const url = '?action=upload';
        const formData = new FormData();
        formData.append('workflow_file', file);

        // Show loading state?
        const dropzone = document.getElementById('flux-dropzone');
        if (dropzone) {
            dropzone.innerHTML = '<div class="dropzone-content"><h1>⏳ Uploading...</h1></div>';
        }

        fetch(url, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.file) {
                    window.location.href = '?workflow=' + encodeURIComponent(data.file);
                } else {
                    alert('Upload failed: ' + (data.error || 'Unknown error'));
                    if (dropzone) dropzone.innerHTML = 'Error';
                }
            })
            .catch(err => {
                console.error(err);
                alert('Upload error');
            });
    }

    setTheme(theme) {
        document.body.className = '';
        document.body.classList.add('theme-' + theme);
        document.body.setAttribute('data-bs-theme', theme); // Bootstrap Theme
        localStorage.setItem('flux-theme', theme);

        // Update Icon
        const icon = document.getElementById('theme-icon');
        if (icon) {
            icon.className = theme === 'dark' ? 'bi bi-moon-stars-fill' : 'bi bi-sun-fill';
        }
    }

    toggleTheme() {
        const current = localStorage.getItem('flux-theme') || 'dark';
        const newTheme = current === 'dark' ? 'light' : 'dark';
        this.setTheme(newTheme);
        this.loadThemeCss(newTheme);
    }

    toggleSidebar() {
        const sidebar = document.getElementById('flux-sidebar');
        if (sidebar) sidebar.classList.toggle('show');
    }

    loadThemeCss(theme) {
        fetch(`?action=get_theme_css&theme=${theme}`)
            .then(r => r.text())
            .then(css => document.getElementById('flux-theme-style').innerHTML = css);
    }

    setupSSE() {
        if (this.es) this.es.close();

        this.es = new EventSource(this.config.sseUrl);

        this.es.addEventListener('workflow_start', e => {
            const d = JSON.parse(e.data);
            console.log('Workflow via SSE:', d.name);
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
            this.focusStep(d.job, d.step);

            // Mark job as running (spinner)
            this.setJobStatus(d.job, 'running');
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
            this.setJobStatus(d.job, 'failure');
        });

        this.es.addEventListener('workflow_complete', () => {
            this.setWorkflowStatus('success');
            document.querySelectorAll('.job-status-icon.status-running').forEach(el => {
                el.className = 'icon-status job-status-icon status-success';
            });
            this.es.close();
        });

        this.es.addEventListener('workflow_failed', (e) => {
            this.setWorkflowStatus('failure');
            this.es.close();
        });

        this.es.addEventListener('error', e => {
            const d = JSON.parse(e.data);
            console.error('Server Error:', d.message);
            this.setWorkflowStatus('failure');
            this.es.close();
        });

        this.es.onerror = (e) => {
            // Browser logs connection errors automatically
        };
    }

    addJob(job) {
        const div = document.createElement('div');
        div.className = 'flux-job-group';
        div.id = `job-sidebar-${job.id}`;
        div.innerHTML = `
            <div class="flux-job-item active">
                <span class="icon-status status-pending job-status-icon" id="job-icon-${job.id}"></span>
                <span class="job-name">${job.name}</span>
            </div>
        `;
        this.elements.sidebar.appendChild(div);

        if (this.elements.jobTitle) {
            this.elements.jobTitle.innerText = `${job.name}`;
        }
        this.data.activeJob = job.id;
    }

    setJobStatus(jobId, status) {
        const icon = document.getElementById(`job-icon-${jobId}`);
        if (icon) {
            icon.className = `icon-status status-${status} job-status-icon`;
        }
    }

    addStep(step) {
        const id = `step-${step.job}-${step.step}`;
        if (document.getElementById(id)) return;

        const details = document.createElement('details');
        details.className = 'flux-step';
        details.id = id;
        details.open = true;

        details.innerHTML = `
            <summary class="flux-step-summary">
                <div class="flux-step-title">
                    <span class="icon-status status-pending" id="icon-${step.job}-${step.step}"></span>
                    ${step.name}
                </div>
                <div class="flux-step-duration" id="meta-${step.job}-${step.step}"></div>
            </summary>
            <div class="flux-step-logs" id="logs-${step.job}-${step.step}"></div>
        `;

        this.elements.stepsContainer.appendChild(details);
        details.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }

    setStepStatus(jobId, stepIdx, status, duration = null) {
        const icon = document.getElementById(`icon-${jobId}-${stepIdx}`);
        if (icon) {
            icon.className = `icon-status status-${status}`;
        }

        if (duration) {
            const meta = document.getElementById(`meta-${jobId}-${stepIdx}`);
            if (meta) meta.innerText = duration.toFixed(1) + 's';
        }

        if (status === 'success') {
            const details = document.getElementById(`step-${jobId}-${stepIdx}`);
            if (details) {
                setTimeout(() => details.open = false, 1000);
            }
        }

        if (status === 'failure') {
            const details = document.getElementById(`step-${jobId}-${stepIdx}`);
            if (details) details.open = true;
        }
    }

    addLog(jobId, stepIdx, content, type) {
        const container = document.getElementById(`logs-${jobId}-${stepIdx}`);
        if (!container) return;

        const id = `${jobId}-${stepIdx}`;
        if (!this.data.lineCounts) this.data.lineCounts = {};
        if (!this.data.lineCounts[id]) this.data.lineCounts[id] = 1;
        const lineNum = this.data.lineCounts[id]++;

        const div = document.createElement('div');
        div.className = 'flux-log-line';
        const ts = new Date().toISOString().split('T')[1].substr(0, 12);

        const numSpan = `<span class="flux-lineno">${lineNum}</span>`;
        const prefix = type === 'command' ? '<span class="flux-cmd-prompt">$</span>' : '';
        const timestamp = `<span class="flux-log-ts">${ts}</span>`;
        const contentSpan = `<span class="flux-log-content">${prefix}${content}</span>`;

        div.innerHTML = `${numSpan}${timestamp}${contentSpan}`;
        container.appendChild(div);

        container.scrollTop = container.scrollHeight;
    }

    focusStep(jobId, stepIdx) {
        const details = document.getElementById(`step-${jobId}-${stepIdx}`);
        if (details) {
            details.open = true;
        }
    }

    setWorkflowStatus(status) {
        const el = this.elements.statusBadge;
        if (el) {
            el.className = `flux-status-badge ${status}`;
            el.innerText = status.toUpperCase();
        }
    }
}
