<!-- craftcms-claude-skills -->
# Git Workflow

- Conventional commits: `feat(scope):`, `fix(scope):`, `refactor(scope):`, `docs:`, `test:`, `chore:`. Match the existing log style — see `git log --oneline` for examples (`feat: debug toolbar panel replaces dev logging`, `chore: bump to 5.0.0`).
- **Subject line only** for most commits. One line, under 72 characters, describes the what.
- **Body only when the why isn't obvious** — a security fix, an architectural decision, a non-obvious behavior change. Keep it to 3-5 lines max. If the commit message is longer than the diff, something is wrong.
- Never include: "Verification" sections, "How to undo" sections, "Follow-up" sections, file-by-file change lists, or test count reports. The diff shows what changed.
- `--amend` for fixes to the most recent unpushed commit. New commit once pushed.
- Run `ddev composer check-cs` and `ddev composer phpstan` before every commit.
- No AI attribution in commit messages, PR descriptions, PR comments, or issue comments. No `Co-Authored-By` lines referencing AI tools.
- All comments, commit messages, and documentation in English only.
- `main` is the default and only long-lived branch. Topic branches for non-trivial work, merged via PR. Use `gh pr create` — the CLI is authenticated.
- Use absolute paths in git commands. Never `cd path && git commit` — the target directory may have untrusted hooks.
