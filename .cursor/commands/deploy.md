---
name: deploy
description: Review the local diff, then upload the event landing to https://event.kuliksystem.us over SSH.
---

# /deploy — review + upload

Ship the kulik-invite landing after a local-diff review with **zero must-fix and zero should-fix** findings. Then upload over SSH.

**Target:** https://event.kuliksystem.us — always `./scripts/deploy.sh` (one site, no prod/dev split).

This command **is** explicit intent to deploy. Do not ask for confirmation of the upload. Stop immediately on a blocking finding or a failed deploy.

## Hard rules

- Never commit or push secrets: `.env.deploy`, `config.php`, credentials, keys.
- Deploy is **site files only** via `./scripts/deploy.sh`. Never overwrite remote `config.php` or `storage/*.jsonl` (the script preserves them).
- Never force-push. Never `--force` / `--force-with-lease` unless the user explicitly asks in this turn.
- Never skip hooks (`--no-verify`).
- No npm/CSS build step for this project (plain HTML/CSS/JS/PHP).

## Exclude from git / deploy (never stage)

- `.env.deploy`, `config.php`
- `storage/requests.jsonl`, `storage/*.tmp`
- `.DS_Store`, `*.log`, `desktop.png`, `mobile.png`
- Unrelated WIP the user did not ask to ship

If a secret is already staged, unstage it and warn before continuing.

## Severity (review gate)

| Level | Meaning | Action |
|-------|---------|--------|
| **Must fix** | Bugs, security, XSS, secret leak, broken form/submit, data loss | **STOP — do not deploy** |
| **Should fix** | Clear correctness/quality issues that should not ship | **STOP — do not deploy** |
| Nice to have | Nits, optional polish | Report only; continue |

**On any Must fix or Should fix → STOP.** Print the blocked report. Do not run `./scripts/deploy.sh`.

## Workflow (execute in order)

```
/deploy progress:
- [ ] 1. Inspect repo + local diff
- [ ] 2. Inline code review (block on must/should fix; no subagent)
- [ ] 3. Deploy to Hosting Ukraine
- [ ] 4. Report
```

### 1. Inspect repo + local diff

From the repo root, run in parallel:

```bash
git status 2>/dev/null || true
git diff && git diff --cached
ls -la
```

If this is not a git repo yet, say so and continue to review + deploy (skip commit/push).

### 2. Code review (inline — no subagent)

Review the files that will ship **yourself in this chat**. Do **not** launch Bugbot, Security Review, Task, or any other subagent.

Flag only:

- Secret leaks (`.env.deploy`, `config.php`, SMTP/CRM tokens)
- XSS / open redirects in `submit.php` / `lib/`
- Broken form submit or storage write paths
- Accidental overwrite of guest data

Print a compact table of findings. Apply the severity gate.

### 3. Deploy to Hosting Ukraine

Requires `.env.deploy` (gitignored). Tar-over-SSH; do not replace with rsync from macOS.

```bash
./scripts/deploy.sh
```

First-time check (optional): `./scripts/deploy.sh discover` if `DEPLOY_PATH` is wrong.

Dry run: `./scripts/deploy.sh --dry-run`

**On failure → STOP** and include the script output in the report.

If the server has no `config.php` yet, remind: copy `config.example.php` → `config.php` on the host and fill secrets. Set cron for `cron/retry.php` (see `docs/admin.md`).

### 4. Report

Lead with **SUCCESS** or **BLOCKED**.

#### Success

```markdown
## /deploy report — SUCCESS

- Target: https://event.kuliksystem.us
- Review: no must-fix / should-fix
- Hosting: `./scripts/deploy.sh`
- config.php on server: present / missing (action noted)
```

#### Blocked

```markdown
## /deploy report — BLOCKED

- Stopped at: <step name>
- Must fix / should fix:
  1. <issue> — <file:line> — <why>
- Not deployed
- Next: <concrete fix>, then re-run /deploy
```
