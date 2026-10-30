<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="theme-color" content="#f4f6f1">
    <title>KIYU API | Home</title>
    <style>
        :root {
            color-scheme: light;
            font-family: "Segoe UI", sans-serif;
            color: #17231d;
            background: #f4f6f1;
            font-synthesis: none;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-width: 320px;
            min-height: 100vh;
            margin: 0;
            background-image: linear-gradient(rgba(23, 35, 29, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(23, 35, 29, 0.035) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 28px clamp(24px, 7vw, 104px);
            border-bottom: 1px solid rgba(23, 35, 29, 0.12);
        }

        .wordmark,
        .eyebrow,
        .endpoint,
        .status {
            font-family: ui-monospace, "Cascadia Code", monospace;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .wordmark {
            font-size: 14px;
            font-weight: 700;
        }

        .eyebrow {
            color: #5b6b61;
            font-size: 11px;
        }

        main {
            display: grid;
            min-height: calc(100vh - 77px);
            place-content: center;
            padding: 64px 24px;
        }

        .content {
            width: min(100%, 720px);
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 13px;
            border: 1px solid #b7d8c0;
            background: #e7f3e9;
            color: #236239;
            font-size: 11px;
            font-weight: 700;
        }

        .status::before {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #329853;
            content: "";
            animation: pulse 1.8s ease-in-out infinite;
        }

        h1 {
            margin: 28px 0 16px;
            font-family: Georgia, serif;
            font-size: 56px;
            font-weight: 400;
            line-height: 1.05;
        }

        p {
            margin: 0;
            color: #5b6b61;
            font-size: 17px;
            line-height: 1.6;
        }

        .endpoint {
            display: inline-block;
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid rgba(23, 35, 29, 0.18);
            color: #5b6b61;
            font-size: 11px;
        }

        @keyframes pulse {
            50% {
                opacity: 0.4;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
            }
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 44px;
            }

            header {
                padding-inline: 20px;
            }
        }
    </style>
</head>

<body>
    <header>
        <span class="wordmark">KIYU</span>
        <span class="eyebrow">API status</span>
    </header>
    <main>
        <section class="content" aria-labelledby="page-title">
            <span class="status" role="status">Operational</span>
            <h1 id="page-title">API is working.</h1>
            <p>The service is up and responding to requests.</p>
            <span class="endpoint">GET / &nbsp;·&nbsp; HTTP 200</span>
        </section>
    </main>
</body>

</html>
