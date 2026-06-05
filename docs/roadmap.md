# Roadmap

Expense Manager is in active development. The roadmap is intentionally centered on the core money-entry workflow before dashboard polish or broad product expansion.

## Current Priority

1. Stabilize statement import and reconciliation WIP.
2. Make chat-based expense entry reliable.
3. Improve category mapping quality.
4. Make transactions and balances easier to audit.
5. Keep public repo workflow, CI, and docs professional.

## Product Lanes

### Statement Import + Reconciliation

Clean up statement parsing, review behavior, duplicate detection, reconciliation, and tests so imported rows can be trusted.

### Chat Entry + Agent Tools

Build fast chat-based transaction creation and safe agent tools for user-approved actions.

### Category Mapping + Classifier

Improve parent/subcategory accuracy, fallback behavior, and statement row categorization.

### Transactions + Audit Trail

Improve transaction list/edit/export behavior, balance correctness, audit views, and missing-detail workflows.

### Sync + Data Integrity

Harden external database sync, anomaly checks, duplicate cleanup, and balance mismatch detection.

### UX / Dashboard / Product Notes

Polish monthly review and dashboard experiences after the core entry/import/audit loop is dependable.

## Portfolio Standard

The public repository should show professional engineering habits:

- Clear issue scope and acceptance criteria.
- Focused PRs with review notes.
- Passing CI.
- Accurate docs that do not overclaim unfinished features.
- Synthetic test data and no private local artifacts.
