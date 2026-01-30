# Finnish Mastodon users

## Adding new users

1. Edit `~/suomalaiset-mastodon-kayttajat/following_accounts.csv` (source file)
2. Run `cd ~/suomalaiset-mastodon-kayttajat && php fetch.php`
3. Copy CSV to this repo: `cp ~/suomalaiset-mastodon-kayttajat/following_accounts.csv ~/finnish-mastodon-users/`
4. Commit with usernames
5. Push

## Commits and code style

- CRITICAL: Always lint before committing changes!
- 2 space indents
- Always commit build and asset files
- One logical change per commit
- Keep commit messages concise (one line), use sentence case
- Reference Linear issues at end: `Fix navigation bug, Ref: DEV-123`
- Do not use @ in commits as it highlights wrong users on GitHub
- Use sentence case for headings (not Title Case)
- Never use bold text as headings, use proper heading levels instead
- Always add an empty line after headings
- Never use Claude watermark in commits (FORBIDDEN: "Co-Authored-By")
- No emojis in commits or code

## Claude Code workflow

- Always add tasks to the Claude Code to-do list and keep it up to date.
- Review your to-do list and prioritize before starting.
- If new tasks come in, don't jump to them right away—add them to the list in order of urgency and finish your current work first.
- Do not ever guess features, always proof them via looking up official docs, GitHub code, issues, if possible.
- When looking things up, do not use years in search terms like 2024 or 2025.
