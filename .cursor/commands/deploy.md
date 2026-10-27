---
name: deploy
description: Review the local diff, commit + push to GitHub, then upload the event landing to https://event.kuliksystem.us over SSH.
---

# /deploy — review + push + upload

Ship the kulik-invite landing after a local-diff review with **zero must-fix and zero should-fix** findings. Then commit/push to GitHub and upload over SSH.

**Targets:**
- Git: `origin` (`https://github.com/manhengest/kulik-invite.git`) — current branch
- Site: https://event.kuliksystem.us — always `./scripts/deploy.sh` (one site, no prod/dev split)

This command **is** explicit intent to deploy **and** push. Do not ask for confirmation of the commit, push, or upload. Stop immediately on a blocking finding or a failed step.

## Hard rules

- Never commit or push secrets: `.env.deploy`, `config.php`, credentials, keys.
- Deploy is **site files only** via `./scripts/deploy.sh`. Never overwrite remote `config.php` or `storage/*.jsonl` (the script preserves them).
- Never force-push. Never `--force` / `--force-with-lease` unless the user explicitly asks in this turn.
- Never skip hooks (`--no-verify`).
- Never update git config.
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
| **Must fix** | Bugs, security, XSS, secret leak, broken form/submit, data loss | **STOP — do not commit, push, or deploy** |
| **Should fix** | Clear correctness/quality issues that should not ship | **STOP — do not commit, push, or deploy** |
| Nice to have | Nits, optional polish | Report only; continue |

**On any Must fix or Should fix → STOP.** Print the blocked report. Do not commit, push, or run `./scripts/deploy.sh`.

## Workflow (execute in order)

```
/deploy progress:
- [ ] 1. Inspect repo + local diff
- [ ] 2. Inline code review (block on must/should fix; no subagent)
- [ ] 3. Commit (if dirty) + push to origin
- [ ] 4. Deploy to Hosting Ukraine
- [ ] 5. Report
```

### 1. Inspect repo + local diff

From the repo root, run in parallel:

```bash
git status 2>/dev/null || true
git diff && git diff --cached
git log -5 --oneline
git remote -v
ls -la
```

If this is not a git repo yet, say so and continue to review + site deploy only (skip commit/push).

### 2. Code review (inline — no subagent)

Review the files that will ship **yourself in this chat**. Do **not** launch Bugbot, Security Review, Task, or any other subagent.

Flag only:

- Secret leaks (`.env.deploy`, `config.php`, SMTP/CRM tokens)
- XSS / open redirects in `submit.php` / `lib/`
- Broken form submit or storage write paths
- Accidental overwrite of guest data

Print a compact table of findings. Apply the severity gate.

### 3. Commit + push to GitHub

`/deploy` is explicit permission to commit and push. Do not ask again.

1. If the working tree is dirty (after exclusions above): stage only shippable files, then commit with a concise message via HEREDOC (why over what; match recent `git log` style):

```bash
git add <paths>
git commit -m "$(cat <<'EOF'
<message>

EOF
)"
```

2. If already clean with nothing to commit, skip the commit and continue.

3. Push the current branch to `origin` (set upstream if missing):

```bash
git push -u origin HEAD
```

Use `required_permissions: ["all"]` for push (auth/network).

**On push failure → STOP.** Do not run `./scripts/deploy.sh`. Include the git error in the report.

If there is no `origin` remote, STOP and say so (site deploy without a remote is not allowed once this repo is connected).

### 4. Deploy to Hosting Ukraine

Requires `.env.deploy` (gitignored). Tar-over-SSH; do not replace with rsync from macOS.

```bash
./scripts/deploy.sh
```

First-time check (optional): `./scripts/deploy.sh discover` if `DEPLOY_PATH` is wrong.

Dry run: `./scripts/deploy.sh --dry-run`

**On failure → STOP** and include the script output in the report. (Git push already happened — note that in the report.)

If the server has no `config.php` yet, remind: copy `config.example.php` → `config.php` on the host and fill secrets. Set cron for `cron/retry.php` (see `docs/admin.md`).

### 5. Report

Lead with **SUCCESS** or **BLOCKED**.

#### Success

```markdown
## /deploy report — SUCCESS

- Target: https://event.kuliksystem.us
- Review: no must-fix / should-fix
- Git: committed <sha or "nothing to commit"> → pushed `origin/<branch>`
- Hosting: `./scripts/deploy.sh`
- config.php on server: present / missing (action noted)
```

#### Blocked

```markdown
## /deploy report — BLOCKED

- Stopped at: <step name>
- Must fix / should fix / git error:
  1. <issue> — <file:line or command> — <why>
- Git: not pushed / pushed <sha> (if push already ran)
- Hosting: not deployed / deployed (if site upload already ran)
- Next: <concrete fix>, then re-run /deploy
```
