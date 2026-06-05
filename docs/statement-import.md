# Statement Import

Statement import is a priority product lane, but it is not part of the public `main` branch as a finished workflow yet. This document defines the intended shape so implementation PRs can stay aligned.

## Goal

Imported bank or card statements should become clean, reviewable transaction candidates without exposing private financial data in the repository.

## Intended Flow

1. Upload or provide a statement file.
2. Parse rows into normalized candidate transactions.
3. Detect duplicates and already-imported rows.
4. Match statement rows to existing transactions when possible.
5. Suggest categories and mark uncertain rows for review.
6. Let the user approve, skip, or correct rows before import.
7. Preserve enough metadata to audit how imported transactions were created.

## Reconciliation Principles

- Matching should rely on stable keys such as date, amount, account, description normalization, and reference data when available.
- Duplicate detection should prefer false negatives over silent false positives for money data.
- Balance mismatches should be visible and explainable.
- Manual corrections should be preserved rather than overwritten by later automation.

## Data Privacy

- Test fixtures must use synthetic names, accounts, descriptions, references, and balances.
- Real bank statements, account numbers, UPI IDs, private paths, and local exports must not be committed.
- Public docs should describe behavior without including real financial examples.

## Current Limitations

- Statement parser, reconciliation, and review UI work is tracked in active WIP outside the public `main` baseline.
- The docs should be updated as those implementation slices merge.
