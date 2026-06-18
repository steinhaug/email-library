# CLAUDE.md

Local single-user tool for batch-transcribing YouTube videos via AssemblyAI (with diarization) and browsing the results in a categorized video wall. Windows + Apache + PHP + MySQL. See `spec.md` for the full design.

## Read first
- `spec.md` — full architecture, schema, pipeline
- `docs-ai/PHP Coding Rules - AI Instructions.md` — backticks on columns, 0/1 for bools, every file requires `environment.php`

## Don't open
- `/vendor/` — Composer deps, read-only
- `/logs/` — Apache vhost logs, only enter when investigating a logged issue

## Architecture quick reference
```
environment.php          bootstrap: paths, credentials
credentials.php          gitignored: Email credentials for Gmail and Imap account
www/                     Apache docroot
  index.php              Hello world
```

## Stack assumptions
- PHP 8.2, MySQL 8.x via the `steinhaug/mysqli` wrapper (`$mysqli->execute()` / `execute1()` — not raw mysqli prepare)
- Frontend: vanilla HTML + JS, no build step, no Node
- All column/table names wrapped in backticks
