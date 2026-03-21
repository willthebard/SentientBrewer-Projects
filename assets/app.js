/**
 * Sentient Brewer — Frontend Application
 * SSE client + UI updates
 */
const App = {
    API: '',

    getToken() {
        return localStorage.getItem('sb_token');
    },

    setToken(token) {
        localStorage.setItem('sb_token', token);
    },

    setUser(user) {
        localStorage.setItem('sb_user', JSON.stringify(user));
    },

    getUser() {
        try { return JSON.parse(localStorage.getItem('sb_user')); } catch { return null; }
    },

    logout() {
        localStorage.removeItem('sb_token');
        localStorage.removeItem('sb_user');
        window.location.href = '/';
    },

    async apiFetch(endpoint, options = {}) {
        const token = this.getToken();
        const headers = { 'Content-Type': 'application/json', ...options.headers };
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const res = await fetch(this.API + endpoint, { ...options, headers });
        return res;
    },

    // ─── Auth Page ───
    initAuth() {
        if (this.getToken()) {
            window.location.href = '/dashboard.php';
            return;
        }

        const tabs = document.querySelectorAll('.tab');
        const loginForm = document.getElementById('login-form');
        const registerForm = document.getElementById('register-form');
        const errorEl = document.getElementById('auth-error');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const target = tab.dataset.tab;
                loginForm.classList.toggle('hidden', target !== 'login');
                registerForm.classList.toggle('hidden', target !== 'register');
                errorEl.classList.add('hidden');
            });
        });

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorEl.classList.add('hidden');
            const data = Object.fromEntries(new FormData(loginForm));
            data.action = 'login';

            try {
                const res = await fetch('/api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                });
                const result = await res.json();
                if (result.success) {
                    this.setToken(result.token);
                    this.setUser(result.user);
                    window.location.href = '/dashboard.php';
                } else {
                    errorEl.textContent = result.message || result.error;
                    errorEl.classList.remove('hidden');
                }
            } catch (err) {
                errorEl.textContent = 'Connection error';
                errorEl.classList.remove('hidden');
            }
        });

        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorEl.classList.add('hidden');
            const data = Object.fromEntries(new FormData(registerForm));
            data.action = 'register';

            try {
                const res = await fetch('/api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                });
                const result = await res.json();
                if (result.success) {
                    this.setToken(result.token);
                    this.setUser(result.user);
                    window.location.href = '/dashboard.php';
                } else {
                    errorEl.textContent = result.message || result.error;
                    errorEl.classList.remove('hidden');
                }
            } catch (err) {
                errorEl.textContent = 'Connection error';
                errorEl.classList.remove('hidden');
            }
        });
    },

    // ─── Dashboard ───
    async initDashboard() {
        const user = this.getUser();
        const nameEl = document.getElementById('user-name');
        if (nameEl && user) nameEl.textContent = user.name || user.email;

        document.getElementById('logout-btn')?.addEventListener('click', () => this.logout());

        // Load projects
        await this.loadProjects();

        // New project modal
        const modal = document.getElementById('new-project-modal');
        document.getElementById('new-project-btn')?.addEventListener('click', () => {
            modal.classList.remove('hidden');
        });
        document.getElementById('cancel-modal')?.addEventListener('click', () => {
            modal.classList.add('hidden');
        });

        document.getElementById('new-project-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));

            const res = await this.apiFetch('/api/projects.php', {
                method: 'POST',
                body: JSON.stringify(data),
            });
            const project = await res.json();
            if (project.id) {
                window.location.href = `/project.php?id=${project.id}`;
            }
        });
    },

    async loadProjects() {
        const container = document.getElementById('projects-list');
        try {
            const res = await this.apiFetch('/api/projects.php');
            const projects = await res.json();

            if (!projects.length) {
                container.innerHTML = '<p class="loading">No projects yet. Create your first one!</p>';
                return;
            }

            container.innerHTML = projects.map(p => `
                <div class="project-card" onclick="window.location.href='/project.php?id=${p.id}'">
                    <h4>${this.esc(p.name)}</h4>
                    <p class="goal">${this.esc(p.goal.substring(0, 120))}${p.goal.length > 120 ? '...' : ''}</p>
                    <div class="meta">
                        <span class="badge badge-${p.status}">${p.status}</span>
                        <span style="color: var(--text-dim); font-size: 11px;">${new Date(p.created_at).toLocaleDateString()}</span>
                    </div>
                </div>
            `).join('');
        } catch (err) {
            container.innerHTML = '<p class="error">Failed to load projects</p>';
        }
    },

    // ─── Project View ───
    async initProject(projectId) {
        this.projectId = projectId;

        document.getElementById('logout-btn')?.addEventListener('click', () => this.logout());

        // Load project details
        const res = await this.apiFetch(`/api/projects.php?id=${projectId}`);
        if (!res.ok) {
            window.location.href = '/dashboard.php';
            return;
        }

        const project = await res.json();
        document.getElementById('project-name').textContent = project.name;
        const statusEl = document.getElementById('project-status');
        statusEl.textContent = project.status;
        statusEl.className = `badge badge-${project.status}`;

        this.renderTasks(project.tasks || []);

        // Load agent run history into terminal
        await this.loadHistory(projectId);

        if (project.status === 'complete' || project.status === 'failed') {
            this.showDownload(projectId);
        }

        // If build is in progress, reconnect to SSE status stream
        if (['planning', 'building', 'reviewing', 'testing'].includes(project.status)) {
            this.connectStatus(projectId);
        }

        // Run button
        document.getElementById('run-btn')?.addEventListener('click', () => {
            this.runBuild(projectId);
        });
    },

    async loadHistory(projectId) {
        try {
            const res = await this.apiFetch(`/api/history.php?id=${projectId}`);
            const runs = await res.json();
            if (!runs.length) return;

            const terminal = document.getElementById('terminal');
            terminal.innerHTML = '';
            for (const run of runs) {
                const statusClass = run.status === 'success' ? 'ok' : (run.status === 'error' ? 'err' : 'info');
                const time = new Date(run.created_at).toLocaleTimeString();
                const preview = run.response_preview ? run.response_preview.substring(0, 200) : '';
                this.termLog(run.agent_type, `[${run.status}] ${preview}`, statusClass);
            }
        } catch (e) {}
    },

    connectStatus(projectId) {
        const runBtn = document.getElementById('run-btn');
        runBtn.disabled = true;
        runBtn.textContent = 'Building...';
        this.termLog('system', 'Build in progress — reconnected...', 'info');

        const token = this.getToken();
        const es = new EventSource(`/api/status.php?id=${projectId}&token=${encodeURIComponent(token)}`);

        es.addEventListener('agent_update', (e) => {
            const data = JSON.parse(e.data);
            const statusClass = data.status === 'success' ? 'ok' : (data.status === 'error' ? 'err' : 'info');
            this.termLog(data.agent_type, `[${data.status}] ${(data.response_preview || '').substring(0, 200)}`, statusClass);
        });

        es.addEventListener('project_status', async (e) => {
            const data = JSON.parse(e.data);
            es.close();
            runBtn.disabled = false;
            runBtn.textContent = 'Run Build';

            const statusEl = document.getElementById('project-status');
            statusEl.textContent = data.status;
            statusEl.className = `badge badge-${data.status}`;

            if (data.status === 'complete') {
                this.termLog('system', 'Build complete!', 'ok');
                this.showDownload(projectId);
            } else if (data.status === 'failed') {
                this.termLog('system', 'Build failed — check task list for errors. You can try Run Build again.', 'err');
                this.showDownload(projectId);
            }

            // Reload tasks
            const res = await this.apiFetch(`/api/projects.php?id=${projectId}`);
            if (res.ok) {
                const project = await res.json();
                this.renderTasks(project.tasks || []);
            }
        });

        es.addEventListener('done', () => es.close());
        es.onerror = () => { es.close(); runBtn.disabled = false; runBtn.textContent = 'Run Build'; };
    },

    renderTasks(tasks) {
        const container = document.getElementById('task-list');
        if (!tasks.length) {
            container.innerHTML = '<p class="dim">No tasks yet. Click "Run Build" to start.</p>';
            return;
        }

        container.innerHTML = tasks.map(t => `
            <div class="task-item ${t.status}" data-task-id="${t.id}">
                <span class="agent-tag">${t.assigned_agent}</span>
                <span class="task-desc">${this.esc(t.task_description.substring(0, 100))}</span>
                <span class="badge badge-${t.status}" style="font-size:9px">${t.status}</span>
            </div>
        `).join('');
    },

    async runBuild(projectId) {
        const terminal = document.getElementById('terminal');
        const runBtn = document.getElementById('run-btn');
        runBtn.disabled = true;
        runBtn.textContent = 'Building...';

        terminal.innerHTML = '';
        this.termLog('system', 'Initiating build...', 'info');

        try {
            const res = await fetch('/api/run.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.getToken()}`,
                },
                body: JSON.stringify({ project_id: projectId }),
            });

            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop();

                let eventType = '';
                for (const line of lines) {
                    if (line.startsWith('event: ')) {
                        eventType = line.slice(7).trim();
                    } else if (line.startsWith('data: ')) {
                        try {
                            const data = JSON.parse(line.slice(6));
                            this.handleSSE(eventType, data);
                        } catch {}
                    }
                }
            }
        } catch (err) {
            this.termLog('system', `Error: ${err.message}`, 'err');
        }

        runBtn.disabled = false;
        runBtn.textContent = 'Run Build';

        // Reload project state
        const res = await this.apiFetch(`/api/projects.php?id=${projectId}`);
        if (res.ok) {
            const project = await res.json();
            this.renderTasks(project.tasks || []);
            const statusEl = document.getElementById('project-status');
            statusEl.textContent = project.status;
            statusEl.className = `badge badge-${project.status}`;

            if (project.status === 'complete') {
                const dl = document.getElementById('download-btn');
                dl.href = `/api/download.php?id=${projectId}&token=${encodeURIComponent(this.getToken())}`;
                dl.classList.remove('hidden');
            }
        }
    },

    handleSSE(event, data) {
        switch (event) {
            case 'status':
            case 'plan':
                this.termLog(data.agent || 'system', data.message, 'info');
                if (data.tasks) {
                    this.renderTasks(data.tasks.map((t, i) => ({
                        id: i,
                        assigned_agent: t.agent,
                        task_description: t.description,
                        status: 'pending',
                    })));
                }
                break;

            case 'task':
                const statusClass = data.status === 'complete' ? 'ok' : (data.status === 'failed' ? 'err' : 'info');
                this.termLog(data.agent, data.message, statusClass);
                // Update task item
                const taskEl = document.querySelector(`[data-task-id="${data.task_id}"]`);
                if (taskEl) {
                    taskEl.className = `task-item ${data.status}`;
                }
                break;

            case 'complete':
                this.termLog('system', data.message, 'ok');
                break;

            case 'error':
                this.termLog('system', data.message, 'err');
                break;

            case 'done':
                break;
        }
    },

    termLog(agent, message, type = 'info') {
        const terminal = document.getElementById('terminal');
        const line = document.createElement('p');
        line.className = 'terminal-line';

        const time = new Date().toLocaleTimeString();
        line.innerHTML = `<span style="color:var(--text-dim)">[${time}]</span> <span class="agent-label">[${this.esc(agent)}]</span> <span class="status-${type}">${this.esc(message)}</span>`;

        terminal.appendChild(line);
        terminal.scrollTop = terminal.scrollHeight;
    },

    showDownload(projectId) {
        const dl = document.getElementById('download-btn');
        dl.href = `/api/download.php?id=${projectId}&token=${encodeURIComponent(this.getToken())}`;
        dl.classList.remove('hidden');
    },

    esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },
};

// Auto-init auth page
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('auth-panel')) {
        App.initAuth();
    }
});
