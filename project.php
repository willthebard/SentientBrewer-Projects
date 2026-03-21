<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project — Sentient Brewer</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <div class="container project-layout">
        <header class="topbar">
            <div class="logo-sm">
                <span class="gear">&#9881;</span>
                <a href="/dashboard.php">Sentient<span class="accent">Brewer</span></a>
            </div>
            <div class="user-info">
                <span id="project-name"></span>
                <span id="project-status" class="badge"></span>
            </div>
        </header>

        <main class="split-panel">
            <section class="panel-left">
                <h3>Task Plan</h3>
                <div id="task-list" class="task-list">
                    <p class="loading">Loading...</p>
                </div>
                <div class="panel-actions">
                    <button id="run-btn" class="btn btn-primary">Run Build</button>
                    <a id="download-btn" class="btn btn-sm hidden" href="#">Download ZIP</a>
                </div>
            </section>

            <section class="panel-right">
                <h3>Agent Terminal <span class="blink">_</span></h3>
                <div id="terminal" class="terminal">
                    <p class="terminal-line dim">Waiting for build to start...</p>
                </div>
            </section>
        </main>
    </div>

    <script src="/assets/app.js?v=4"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!App.getToken()) {
                window.location.href = '/';
                return;
            }
            const params = new URLSearchParams(window.location.search);
            const id = params.get('id');
            if (!id) {
                window.location.href = '/dashboard.php';
                return;
            }
            App.initProject(parseInt(id));
        });
    </script>
</body>
</html>
