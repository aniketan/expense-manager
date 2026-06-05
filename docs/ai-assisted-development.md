# AI-Assisted Development

Expense Manager uses AI-assisted development as a disciplined engineering workflow, not as a replacement for review. GitHub remains the public source of truth for issues, pull requests, review, and CI.

## Working Model

- GitHub issues define the visible task scope and acceptance criteria.
- GitHub Project tracks status across planning, ready, in progress, review, and done.
- AI-assisted work happens in focused implementation lanes.
- Private planning notes remain long-term product memory and planning context.
- The repo remains implementation truth.

## Branching

- Use short-lived branches with the `codex/` prefix for AI-assisted work.
- Keep each branch tied to a single issue or tight issue cluster.
- Use isolated worktrees when the main local checkout has unrelated WIP.

## Pull Requests

Each PR should include:

- Linked issue.
- Summary of changes.
- Verification commands and results.
- Risks, warnings, or known limitations.
- Screenshots or notes for UI-facing changes.

The human maintainer should review the diff and CI before merge. Squash merge is preferred for small focused branches so `main` keeps a clean portfolio-grade history.

## Privacy Rules

Never commit:

- `.env` files.
- Private SQLite databases.
- Bank statements or real financial exports.
- Machine-specific assistant context such as local agent files.
- Personal local paths, tokens, account numbers, UPI IDs, or other sensitive identifiers.

## Review Rhythm

1. Clarify acceptance criteria on the issue.
2. Create a scoped branch/worktree.
3. Implement only the approved scope.
4. Run practical tests/builds.
5. Open a draft or ready PR with verification notes.
6. Human reviewer checks files changed, CI, privacy, and behavior.
7. Merge after review, then update the board/notes.
