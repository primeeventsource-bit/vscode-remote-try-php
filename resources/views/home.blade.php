<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CRM Prime Online</title>
    <style>
        :root {
            color-scheme: light;
            --bg-1: #0f172a;
            --bg-2: #1d4ed8;
            --card: #ffffff;
            --ink: #0f172a;
            --muted: #475569;
            --accent: #2563eb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--ink);
            background: radial-gradient(circle at 20% 10%, #60a5fa 0%, transparent 35%),
                        radial-gradient(circle at 80% 80%, #93c5fd 0%, transparent 30%),
                        linear-gradient(135deg, var(--bg-1), var(--bg-2));
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .card {
            width: min(760px, 100%);
            background: var(--card);
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(2, 6, 23, 0.3);
            padding: 28px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: clamp(28px, 4vw, 42px);
        }

        p {
            margin: 0;
            color: var(--muted);
            font-size: 18px;
            line-height: 1.5;
        }

        .status {
            margin-top: 20px;
            display: inline-flex;
            gap: 8px;
            align-items: center;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 999px;
            padding: 8px 14px;
            font-weight: 600;
            font-size: 14px;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #16a34a;
            box-shadow: 0 0 0 4px #dcfce7;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>CRM Prime Online</h1>
        <p>Your Laravel application is now live and serving your custom site content.</p>
        <div class="status">
            <span class="dot" aria-hidden="true"></span>
            Live on crmprime.online
        </div>
    </main>
</body>
</html>
