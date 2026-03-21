<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Sentient Brewer</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <div class="container">
        <header class="topbar">
            <div class="logo-sm">
                <span class="gear">&#9881;</span>
                <a href="/dashboard.php">Sentient<span class="accent">Brewer</span></a>
            </div>
            <div class="user-info">
                <span id="user-name"></span>
                <button id="logout-btn" class="btn btn-sm">Logout</button>
            </div>
        </header>

        <main>
            <div class="dashboard-header">
                <h2>Your Projects</h2>
                <button id="new-project-btn" class="btn btn-primary">+ New Project</button>
            </div>

            <div id="new-project-modal" class="modal hidden">
                <div class="modal-content panel">
                    <h3>New Project</h3>
                    <form id="new-project-form">
                        <input type="text" name="name" placeholder="Project name" required>
                        <textarea name="goal" placeholder="Describe the software you want built..." rows="6" required></textarea>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-sm" id="cancel-modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create & Build</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="projects-list" class="projects-grid">
                <p class="loading">Loading projects...</p>
            </div>
        </main>
    </div>

    <script src="/assets/app.js?v=4"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!App.getToken()) {
                window.location.href = '/';
                return;
            }
            App.initDashboard();
        });
    </script>
</body>
</html>
