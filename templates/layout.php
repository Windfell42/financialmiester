<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinancialMiester &mdash; PDF Financial Analyzer</title>
    <style>
        :root {
            --color-bg: #0f1117;
            --color-surface: #1a1d2e;
            --color-surface-alt: #232740;
            --color-border: #2e3348;
            --color-primary: #6c8cff;
            --color-primary-dim: #4a6ae0;
            --color-text: #e1e4f0;
            --color-text-muted: #8b90a8;
            --color-green: #3ddc84;
            --color-red: #ff5c5c;
            --color-yellow: #ffc857;
            --color-orange: #ff8a50;
            --radius: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 960px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        header {
            text-align: center;
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--color-border);
        }

        header h1 {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--color-primary);
        }

        header p {
            color: var(--color-text-muted);
            margin-top: 0.4rem;
            font-size: 0.95rem;
        }

        /* ─── Upload Form ─────────────────── */
        .upload-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 640px) {
            .upload-section { grid-template-columns: 1fr; }
        }

        .upload-card {
            background: var(--color-surface);
            border: 2px dashed var(--color-border);
            border-radius: var(--radius);
            padding: 2rem 1.5rem;
            text-align: center;
            transition: border-color 0.2s, background 0.2s;
            position: relative;
        }

        .upload-card:hover,
        .upload-card.dragover {
            border-color: var(--color-primary);
            background: var(--color-surface-alt);
        }

        .upload-card h3 {
            font-size: 1.05rem;
            margin-bottom: 0.5rem;
        }

        .upload-card .icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            opacity: 0.6;
        }

        .upload-card p {
            font-size: 0.85rem;
            color: var(--color-text-muted);
            margin-bottom: 1rem;
        }

        .upload-card input[type="file"] {
            display: none;
        }

        .upload-card label.btn {
            display: inline-block;
            padding: 0.6rem 1.4rem;
            background: var(--color-primary-dim);
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.15s;
        }

        .upload-card label.btn:hover {
            background: var(--color-primary);
        }

        .file-name {
            margin-top: 0.75rem;
            font-size: 0.85rem;
            color: var(--color-green);
            min-height: 1.4em;
        }

        .submit-row {
            text-align: center;
            margin-bottom: 2rem;
        }

        button.analyze-btn {
            padding: 0.8rem 3rem;
            background: var(--color-primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
        }

        button.analyze-btn:hover {
            background: #5a7bff;
            transform: translateY(-1px);
        }

        button.analyze-btn:disabled {
            background: var(--color-border);
            cursor: not-allowed;
            transform: none;
        }

        /* ─── Errors ──────────────────────── */
        .error-box {
            background: rgba(255, 92, 92, 0.1);
            border: 1px solid var(--color-red);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            color: var(--color-red);
        }

        /* ─── Results ─────────────────────── */
        .score-hero {
            text-align: center;
            padding: 2.5rem 1.5rem;
            background: var(--color-surface);
            border-radius: var(--radius);
            margin-bottom: 2rem;
        }

        .score-circle {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            border: 6px solid;
        }

        .score-circle.excellent { border-color: var(--color-green); color: var(--color-green); }
        .score-circle.good      { border-color: #6ccf6c; color: #6ccf6c; }
        .score-circle.fair      { border-color: var(--color-yellow); color: var(--color-yellow); }
        .score-circle.below     { border-color: var(--color-orange); color: var(--color-orange); }
        .score-circle.poor      { border-color: var(--color-red); color: var(--color-red); }
        .score-circle.critical  { border-color: #cc0000; color: #cc0000; }

        .score-label {
            font-size: 1.4rem;
            font-weight: 600;
        }

        .appraisal-box {
            background: var(--color-surface);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .appraisal-box h2 {
            font-size: 1.15rem;
            margin-bottom: 0.75rem;
            color: var(--color-primary);
        }

        .appraisal-box p {
            color: var(--color-text-muted);
            font-size: 0.95rem;
        }

        /* ─── Metrics Table ───────────────── */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background: var(--color-surface);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
        }

        .metric-card .label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--color-text-muted);
            margin-bottom: 0.25rem;
        }

        .metric-card .value {
            font-size: 1.4rem;
            font-weight: 700;
        }

        /* ─── Flags & Opportunities ───────── */
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--color-border);
        }

        .flag-list, .opp-list {
            list-style: none;
            margin-bottom: 2rem;
        }

        .flag-list li, .opp-list li {
            background: var(--color-surface);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
            border-left: 4px solid;
        }

        .flag-list li { border-left-color: var(--color-red); }
        .flag-list li.severity-critical { border-left-color: #cc0000; background: rgba(204, 0, 0, 0.08); }
        .flag-list li.severity-high     { border-left-color: var(--color-red); }
        .flag-list li.severity-medium   { border-left-color: var(--color-orange); }
        .flag-list li.severity-low      { border-left-color: var(--color-yellow); }

        .opp-list li { border-left-color: var(--color-green); }

        .flag-list li .title, .opp-list li .title {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .flag-list li .category, .opp-list li .category {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--color-text-muted);
            margin-bottom: 0.2rem;
        }

        .flag-list li .detail, .opp-list li .detail {
            font-size: 0.88rem;
            color: var(--color-text-muted);
            margin-top: 0.3rem;
        }

        .severity-badge {
            display: inline-block;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
            margin-left: 0.5rem;
            font-weight: 600;
        }

        .severity-badge.critical { background: rgba(204, 0, 0, 0.2); color: #ff3333; }
        .severity-badge.high     { background: rgba(255, 92, 92, 0.2); color: var(--color-red); }
        .severity-badge.medium   { background: rgba(255, 138, 80, 0.2); color: var(--color-orange); }
        .severity-badge.low      { background: rgba(255, 200, 87, 0.2); color: var(--color-yellow); }

        .recommendation-box {
            background: var(--color-surface-alt);
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            white-space: pre-line;
        }

        .recommendation-box h2 {
            font-size: 1.15rem;
            color: var(--color-primary);
            margin-bottom: 0.75rem;
        }

        .recommendation-box p {
            font-size: 0.93rem;
            color: var(--color-text-muted);
        }

        .back-link {
            display: inline-block;
            color: var(--color-primary);
            text-decoration: none;
            font-size: 0.95rem;
            margin-top: 1rem;
        }

        .back-link:hover { text-decoration: underline; }

        @media (max-width: 640px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); }
        }

        footer {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid var(--color-border);
            color: var(--color-text-muted);
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>FinancialMiester</h1>
            <p>Upload an Income Statement and a Balance Sheet to receive a comprehensive financial analysis.</p>
        </header>

        <?php echo $content; ?>

        <footer>
            FinancialMiester &mdash; Financial PDF Analyzer
        </footer>
    </div>
</body>
</html>
