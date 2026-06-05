# AI Tools

Expense Manager's product direction is chat-first personal finance. The AI layer is intended to help users capture expenses quickly, classify transactions reliably, and perform safe workflow actions through explicit tools.

## Current Status

The public `main` branch includes the app foundation and Prism PHP dependency, but the chat controller, agent tools, and AI categorization workflows are not treated as complete public features yet. Active AI work should be tracked in dedicated issues and PRs before it is described as shipped.

## Intended AI Responsibilities

- Parse natural-language expense entries into structured transaction drafts.
- Suggest category and subcategory mappings with confidence and fallback behavior.
- Flag transactions that need more detail instead of guessing silently.
- Support controlled actions such as creating transactions or running sync only when the user intent is clear.
- Keep real financial data out of public fixtures, docs, and issue examples.

## Tool Boundary Principles

- AI should prepare or propose changes before mutating money data.
- Any balance-impacting action should be auditable through normal transaction records.
- Category IDs and account IDs should be resolved from the database, not invented by the model.
- Low-confidence classification should produce a review state, not a hidden best guess.
- Tool results should include enough context for debugging without leaking private local paths or real account data.

## Planned Work

- Chat-based transaction entry.
- AI category mapping with parent/subcategory accuracy.
- Statement row categorization assistance.
- Agent tools for safe sync/action workflows.
- Evaluation fixtures that use synthetic data only.

## Verification Expectations

AI changes should include focused tests for parsing, category resolution, fallback behavior, and unsafe-action prevention. UI changes should include a manual review note or screenshot when relevant.
