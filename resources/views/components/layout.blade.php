<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        :root { --text: #1f2937; --muted: #6b7280; --border: #e5e7eb; --background: #ffffff; --surface: #f9fafb; --link: #2563eb; --passed: #15803d; --failed: #b91c1c; }
        @media (prefers-color-scheme: dark) {
            :root { --text: #e5e7eb; --muted: #9ca3af; --border: #374151; --background: #111827; --surface: #1f2937; --link: #60a5fa; --passed: #4ade80; --failed: #f87171; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px 16px; background: var(--background); color: var(--text); font: 15px/1.6 ui-sans-serif, system-ui, sans-serif; }
        main { max-width: 960px; margin: 0 auto; }
        a { color: var(--link); text-decoration: none; }
        a:hover { text-decoration: underline; }
        h1 { font-size: 24px; margin: 0 0 16px; }
        h2 { font-size: 19px; margin: 32px 0 8px; padding-top: 16px; border-top: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; display: block; overflow-x: auto; }
        th, td { padding: 6px 10px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: top; }
        th { color: var(--muted); font-weight: 600; font-size: 13px; }
        code { background: var(--surface); padding: 1px 4px; border-radius: 4px; font-size: 13px; }
        pre { background: var(--surface); padding: 12px; border-radius: 6px; overflow-x: auto; }
        pre code { padding: 0; }
        .muted { color: var(--muted); }
        .passed { color: var(--passed); font-weight: 600; }
        .failed { color: var(--failed); font-weight: 600; }
        .facts { display: flex; flex-wrap: wrap; gap: 4px 24px; margin: 0 0 24px; color: var(--muted); font-size: 14px; }
        .body > h1:first-child { display: none; }
    </style>
</head>
<body>
    <main>
        {{ $slot }}
    </main>
</body>
</html>
