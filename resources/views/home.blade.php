<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CRM Prime Online</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f172a, #1e3a8a 55%, #1d4ed8);
            color: #0f172a;
            min-height: 100vh;
        }
        .wrap {
            width: min(1100px, 92vw);
            margin: 36px auto;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 20px 55px rgba(15, 23, 42, 0.35);
            overflow: hidden;
        }
        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 22px;
            border-bottom: 1px solid #e2e8f0;
        }
        .brand { font-weight: 700; font-size: 20px; }
        .nav a {
            display: inline-block;
            text-decoration: none;
            color: #1e3a8a;
            margin-left: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-weight: 600;
        }
        .hero {
            padding: 42px 24px;
            display: grid;
            gap: 24px;
            grid-template-columns: 1.3fr 1fr;
        }
        .cta a {
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
            background: #1d4ed8;
            color: #fff;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 600;
        }
        .cta a.alt {
            background: #0f172a;
        }
        .panel {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 14px;
            padding: 16px;
        }
        .meta {
            margin-top: 14px;
            color: #334155;
            font-size: 14px;
        }
        @media (max-width: 900px) {
            .hero { grid-template-columns: 1fr; }
            .nav a { margin-top: 6px; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="top">
            <div class="brand">CRM Prime Online</div>
            <div class="nav">
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a href="{{ route('contacts') }}">Contacts</a>
                <a href="{{ route('deals') }}">Deals</a>
                <a href="{{ route('settings') }}">Settings</a>
            </div>
        </div>
        <div class="hero">
            <div>
                <h1>Your CRM Website Is Live</h1>
                <p>This is now an interactive website with clickable sections instead of the Laravel starter page.</p>
                <div class="cta">
                    <a href="{{ route('dashboard') }}">Open Dashboard</a>
                    <a class="alt" href="{{ route('contacts') }}">Manage Contacts</a>
                </div>
                <p class="meta">Domain: crmprime.online</p>
            </div>
            <div class="panel">
                <h3>Quick Actions</h3>
                <p><a href="{{ route('deals') }}">View Pipeline</a></p>
                <p><a href="{{ route('settings') }}">Project Settings</a></p>
                <p><a href="{{ route('dashboard') }}">Team Overview</a></p>
            </div>
        </div>
    </div>
</body>
</html>
