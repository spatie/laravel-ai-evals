<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex">
    <title>{{ $title }}</title>
    <style>
        :root {
            color-scheme: light dark;
            --background: #ffffff;
            --surface: #f5f6f8;
            --surface-strong: #eceef2;
            --border: #e2e5ea;
            --border-strong: #cdd2da;
            --text: #15181d;
            --muted: #586070;
            --link: #1f5fcc;
            --focus: #1f5fcc;
            --selection: #cfe0ff;
            --meter-empty: #cfd4db;
            --passed: #17703f;
            --passed-background: #e5f4eb;
            --passed-fill: #2f9e5f;
            --partial: #8a5000;
            --partial-background: #fcf0d9;
            --partial-fill: #d68a1a;
            --failed: #b42318;
            --failed-background: #fdebe9;
            --failed-fill: #d9402f;
            --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --background: #101216;
                --surface: #181b21;
                --surface-strong: #20242b;
                --border: #272c34;
                --border-strong: #3a414c;
                --text: #e7e9ec;
                --muted: #a0a8b4;
                --link: #86b4ff;
                --focus: #86b4ff;
                --selection: #2b4778;
                --meter-empty: #3b424d;
                --passed: #72d49b;
                --passed-background: rgba(63, 178, 111, 0.16);
                --passed-fill: #3fb26f;
                --partial: #f0bb5a;
                --partial-background: rgba(214, 138, 26, 0.18);
                --partial-fill: #d99a2b;
                --failed: #ff8c82;
                --failed-background: rgba(229, 83, 63, 0.18);
                --failed-fill: #e5533f;
            }
        }

        * { box-sizing: border-box; }
        ::selection { background: var(--selection); }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; padding: 40px 16px 64px; background: var(--background); color: var(--text); font: 15px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { max-width: 1000px; margin: 0 auto; }

        a { color: var(--link); text-decoration: none; text-underline-offset: 2px; }
        a:hover { text-decoration: underline; }
        :focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; border-radius: 3px; }

        h1 { font-size: 22px; line-height: 1.3; font-weight: 650; letter-spacing: -0.01em; margin: 0; text-wrap: balance; }
        h2 { font-size: 17px; line-height: 1.35; font-weight: 650; margin: 0; }
        h3 { font-size: 14px; line-height: 1.4; font-weight: 600; margin: 0; }

        code { font-family: var(--mono); font-size: 0.86em; background: var(--surface-strong); padding: 1px 5px; border-radius: 4px; overflow-wrap: anywhere; }
        pre { background: var(--surface); padding: 12px 14px; border-radius: 8px; overflow-x: auto; font-size: 13px; line-height: 1.5; }
        pre code { padding: 0; background: none; font-size: inherit; overflow-wrap: normal; }

        .muted { color: var(--muted); }
        .numeric { font-variant-numeric: tabular-nums; }
        .visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }

        .table-scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: top; }
        th { color: var(--muted); font-weight: 600; font-size: 12.5px; white-space: nowrap; }
        thead th { border-bottom-color: var(--border-strong); }
        th:first-child, td:first-child { padding-left: 0; }
        th:last-child, td:last-child { padding-right: 0; }

        .status { display: inline-flex; align-items: center; gap: 6px; padding: 1px 9px 1px 7px; border-radius: 999px; font-size: 13px; font-weight: 600; line-height: 22px; white-space: nowrap; }
        .status::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
        .status-passed { color: var(--passed); background: var(--passed-background); }
        .status-failed { color: var(--failed); background: var(--failed-background); }
        .status-unknown, .status-unjudged { color: var(--muted); background: var(--surface-strong); }

        .runs { display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .runs-count { font-weight: 600; }
        .runs-passed .runs-count { color: var(--passed); }
        .runs-partial .runs-count { color: var(--partial); }
        .runs-failed .runs-count { color: var(--failed); }
        .meter { display: inline-flex; gap: 3px; }
        .meter span { width: 7px; height: 14px; border-radius: 2px; background: var(--meter-empty); }
        .runs-passed .meter .is-filled { background: var(--passed-fill); }
        .runs-partial .meter .is-filled { background: var(--partial-fill); }

        .prose > :first-child { margin-top: 0; }
        .prose > :last-child { margin-bottom: 0; }
        .prose p, .prose ul, .prose ol, .prose pre, .prose blockquote, .prose table { margin: 0 0 12px; }
        .prose ul, .prose ol { padding-left: 22px; }
        .prose li + li { margin-top: 3px; }
        .prose h5, .prose h6 { margin: 20px 0 8px; font-size: 15px; }
        .prose blockquote { padding: 2px 0 2px 14px; border-left: 1px solid var(--border-strong); color: var(--muted); }
        .prose a { text-decoration: underline; text-decoration-color: color-mix(in srgb, var(--link) 45%, transparent); }
        .prose hr { border: 0; border-top: 1px solid var(--border); margin: 16px 0; }
    </style>
    @stack('styles')
</head>
<body>
    <main>
        {{ $slot }}
    </main>
    @stack('scripts')
</body>
</html>
