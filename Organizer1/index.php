<?php
require_once "session_bootstrap.php";
if (isset($_SESSION['username'])) {
    if (in_array($_SESSION['role'] ?? '', ['organizer', 'admin'], true)) {
        header('Location: dashboard.php');
    } else {
        header('Location: ../Tournameet/index.html');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TournaMeet Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --orange: #F47B20;
            --orange-dark: #D96210;
            --orange-light: #FFF0E6;
            --white: #FFFFFF;
            --shadow: 0 2px 16px rgba(244,123,32,0.18);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: 'DM Sans', sans-serif;
            background: linear-gradient(180deg, #fff7ef 0%, #fafafa 100%);
            color: #1a1a1a;
            display: flex;
            flex-direction: column;
        }
        nav {
            position: sticky;
            top: 0;
            z-index: 5;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            background: var(--white);
            border-bottom: 2px solid var(--orange);
            box-shadow: var(--shadow);
        }
        .brand {
            font-family: 'Bebas Neue', sans-serif;
            letter-spacing: 2px;
            font-size: 1.7rem;
            color: var(--orange);
            text-decoration: none;
        }
        .nav-links {
            display: flex;
            gap: 10px;
        }
        .nav-links a {
            text-decoration: none;
            color: var(--orange);
            border: 1.5px solid var(--orange);
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 0.82rem;
            font-weight: 700;
            transition: all .2s ease;
        }
        .nav-links a:hover {
            background: var(--orange);
            color: #fff;
        }
        .page {
            width: 100%;
            max-width: 980px;
            margin: auto;
            padding: 36px 20px 50px;
            display: grid;
            gap: 22px;
        }
        .hero {
            background: var(--white);
            border-radius: 16px;
            border: 1.5px solid #f0e0d0;
            box-shadow: 0 4px 22px rgba(244,123,32,0.1);
            padding: 28px;
        }
        .hero h1 {
            font-family: 'Bebas Neue', sans-serif;
            letter-spacing: 2px;
            color: var(--orange);
            font-size: clamp(2rem, 6vw, 3.1rem);
            line-height: 1;
            margin-bottom: 10px;
        }
        .hero p {
            color: #666;
            line-height: 1.7;
            max-width: 680px;
            margin-bottom: 20px;
        }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            text-decoration: none;
            border: none;
            border-radius: 10px;
            padding: 12px 18px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: transform .15s ease, background .2s ease;
        }
        .btn-primary {
            background: linear-gradient(120deg, var(--orange), var(--orange-dark));
            color: #fff;
        }
        .btn-secondary {
            background: var(--orange-light);
            color: #8f4300;
            border: 1px solid #ffd1aa;
        }
        .btn:hover { transform: translateY(-1px); }
        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }
        .card {
            background: #fff;
            border: 1px solid #f1e2d3;
            border-radius: 12px;
            padding: 16px;
        }
        .card h3 {
            font-family: 'Bebas Neue', sans-serif;
            letter-spacing: 1px;
            color: #d06510;
            margin-bottom: 6px;
            font-size: 1.25rem;
        }
        .card p {
            font-size: 0.85rem;
            color: #777;
            line-height: 1.6;
        }
        @media (max-width: 820px) {
            .cards { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <nav>
        <a class="brand" href="index.php">TournaMeet Organizer</a>
        <div class="nav-links">
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        </div>
    </nav>
    <main class="page">
        <section class="hero">
            <h1>Create And Manage Tournaments</h1>
            <p>
                Organizer and athlete now share one tournament system. Create tournaments here, and they automatically
                appear in the athlete TournaMeet pages for registration.
            </p>
            <div class="actions">
                <a class="btn btn-primary" href="register.php">Start as Organizer</a>
                <a class="btn btn-secondary" href="login.php">I Already Have an Account</a>
            </div>
        </section>

        <section class="cards">
            <article class="card">
                <h3>Publish</h3>
                <p>Set title, schedule, location, slots, and fees with live preview before posting.</p>
            </article>
            <article class="card">
                <h3>Receive Joiners</h3>
                <p>Athlete registrations from TournaMeet are saved to your joiner list automatically.</p>
            </article>
            <article class="card">
                <h3>Review</h3>
                <p>Approve, reject, or waitlist participants and export approved joiners as CSV.</p>
            </article>
        </section>
    </main>
</body>
</html>
