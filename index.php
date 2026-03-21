<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sentient Brewer — AI Software Factory</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <div class="container">
        <header class="hero">
            <div class="logo">
                <span class="gear">&#9881;</span>
                <h1>Sentient<span class="accent">Brewer</span></h1>
            </div>
            <p class="tagline">Describe your software. Watch AI agents build it.</p>
        </header>

        <div id="auth-panel" class="panel">
            <div class="tabs">
                <button class="tab active" data-tab="login">Login</button>
                <button class="tab" data-tab="register">Register</button>
            </div>

            <form id="login-form" class="auth-form">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>

            <form id="register-form" class="auth-form hidden">
                <input type="text" name="name" placeholder="Name">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password (8+ chars)" required minlength="8">
                <button type="submit" class="btn btn-primary">Register</button>
            </form>

            <div id="auth-error" class="error hidden"></div>
        </div>

        <footer>
            <p>&copy; 2026 <a href="https://sentientbean.net">Sentient Bean</a> &middot; A <a href="https://createdbywill.us">Created By Will</a> Product</p>
        </footer>
    </div>

    <script src="/assets/app.js?v=4"></script>
</body>
</html>
