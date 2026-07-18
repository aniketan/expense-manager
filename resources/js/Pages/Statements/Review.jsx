import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';

function childList(category) {
    return category.active_children ?? category.activeChildren ?? category.children ?? [];
}

function rowHasLeafCategory(row, expenseParentsWithSubs) {
    if (row.type === 'income') {
        return Boolean(row.subcategory_id);
    }
    const pid = parseInt(row.category_id, 10);
    if (!pid) {
        return false;
    }
    const parent = expenseParentsWithSubs.find((p) => p.id === pid);
    if (!parent) {
        return false;
    }
    const subs = childList(parent);
    if (subs.length === 0) {
        return false;
    }
    return Boolean(row.subcategory_id);
}

/** Prefill category dropdowns when we matched an existing ledger row. */
function categoryDefaultsFromLedger(t) {
    const lc = t.ledger_category_id != null ? String(t.ledger_category_id) : '';
    const ls = t.ledger_subcategory_id != null ? String(t.ledger_subcategory_id) : '';
    if (t.type === 'income') {
        return { category_id: '', subcategory_id: ls };
    }
    return { category_id: lc, subcategory_id: ls };
}

function monthKeyFromRow(row) {
    const d = row?.date;
    if (!d || typeof d !== 'string') {
        return 'unknown';
    }
    const key = d.slice(0, 7);
    return /^\d{4}-\d{2}$/.test(key) ? key : 'unknown';
}

function sortedMonthKeys(transactionsList) {
    const keys = [...new Set((transactionsList ?? []).map(monthKeyFromRow))].sort();
    return keys.length ? keys : ['unknown'];
}

function activeMonthStorageKey() {
    if (typeof window === 'undefined') {
        return 'statement-review-active-month';
    }
    return `statement-review-active-month:${window.location.pathname}`;
}

function storedActiveMonth(monthKeys) {
    if (typeof window === 'undefined') {
        return monthKeys[0] ?? 'unknown';
    }
    const stored = window.sessionStorage.getItem(activeMonthStorageKey());
    return stored && monthKeys.includes(stored) ? stored : monthKeys[0] ?? 'unknown';
}

function formatMonthTabLabel(monthKey) {
    if (monthKey === 'unknown') {
        return 'Unknown date';
    }
    const [y, m] = monthKey.split('-');
    return new Date(parseInt(y, 10), parseInt(m, 10) - 1, 1).toLocaleString('en-IN', {
        month: 'short',
        year: 'numeric',
    });
}

function reconcileSummaryForRows(rowSubset) {
    const counts = { missing_in_db: 0, matched_complete: 0, matched_needs_enrichment: 0 };
    for (const r of rowSubset) {
        switch (r.reconcile_status) {
            case 'missing_in_db':
                counts.missing_in_db++;
                break;
            case 'matched_complete':
                counts.matched_complete++;
                break;
            case 'matched_needs_enrichment':
                counts.matched_needs_enrichment++;
                break;
            default:
                break;
        }
    }
    return counts;
}

/** @param {Record<string, string|string[]>|undefined} errors */
function importFieldError(errors, rowId, field, orderedRowIds) {
    if (!errors || !orderedRowIds?.length) {
        return null;
    }
    const idx = orderedRowIds.indexOf(rowId);
    if (idx < 0) {
        return null;
    }
    const raw = errors[`rows.${idx}.${field}`];
    if (Array.isArray(raw)) {
        return raw[0] ?? null;
    }
    return raw ?? null;
}

/** @param {Record<string, string|string[]>|undefined} errors */
function rowsLevelImportMessage(errors) {
    if (!errors?.rows) {
        return null;
    }
    return Array.isArray(errors.rows) ? errors.rows.join(' ') : errors.rows;
}

function reconcileRowSurface(row) {
    if (row.matched_via_bundle) {
        return { bg: '#f5f3ff', borderLeft: '4px solid #8b5cf6' };
    }
    switch (row.reconcile_status) {
        case 'matched_complete':
            return { bg: '#ecfdf5', borderLeft: '4px solid #10b981' };
        case 'matched_needs_enrichment':
            return { bg: '#fffbeb', borderLeft: '4px solid #f59e0b' };
        case 'missing_in_db':
            return { bg: '#fef2f2', borderLeft: '4px solid #ef4444' };
        default:
            return { bg: null, borderLeft: '4px solid transparent' };
    }
}

const NEEDS_DETAIL_REASON_LABELS = {
    existing_description_thin: 'Existing ledger description is thin',
    matched_by_date_amount_bucket: 'Matched by date + amount only',
    matched_by_amount_proximity: 'Matched by amount proximity',
    statement_date_differs_from_ledger: 'Statement date differs from ledger date',
    matched_via_split_bundle: 'Matched through split bundle',
};

function needsDetailReasonText(row) {
    const reasons = Array.isArray(row.needs_detail_reasons) ? row.needs_detail_reasons : [];
    return reasons
        .map((reason) => NEEDS_DETAIL_REASON_LABELS[reason] ?? reason)
        .filter(Boolean)
        .join(', ');
}

function matchedLedgerRowId(row) {
    if (row.reconcile_status !== 'matched_needs_enrichment' || !row.existing_transaction_id) {
        return null;
    }

    return row.existing_transaction_id;
}

function matchedLedgerRowLabel(row) {
    const id = matchedLedgerRowId(row);
    return id ? `Matched DB row ID: #${id}` : null;
}

function matchedLedgerRowEditUrl(row) {
    const id = matchedLedgerRowId(row);
    return id ? `/transactions/${id}/edit` : null;
}

function formatRupee(value) {
    if (value == null || value === '' || Number.isNaN(Number(value))) {
        return '—';
    }
    return `₹${Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

const IMPORT_ROW_ORDER_STORAGE_KEY = 'statement_review_import_row_order';

export default function Review({
    parsedData,
    suggestedAccount,
    accountMatch,
    accountMatchNote,
    accounts,
    categories,
    reconcilePeriod = {},
    reconcileSummary = {},
    reconcileDebug = false,
}) {
    const { account_info, transactions } = parsedData;
    const ai = account_info ?? {};

    const page = usePage();
    const inertiaErrors = page.props.errors ?? {};
    const flash = page.props.flash ?? {};

    const showVal = (v) => (v != null && String(v).trim() !== '' ? String(v) : '—');

    const summary = reconcileSummary ?? {};
    const balanceInfo = summary.balance ?? {};
    const showBalanceCol = transactions.some((t) => t.balance_after != null && t.balance_after !== '');

    const isImportableRow = (t) => t.reconcile_status === 'missing_in_db' || t.reconcile_status === 'unscoped';
    const isNeedsDetailRow = (t) => t.reconcile_status === 'matched_needs_enrichment' && t.existing_transaction_id;
    const defaultSelected = (t) => isImportableRow(t) || isNeedsDetailRow(t);

    const [rows, setRows] = useState(
        transactions.map((t, i) => {
            const cat = categoryDefaultsFromLedger(t);
            return {
                ...t,
                _id: i,
                account_id: suggestedAccount?.id != null ? String(suggestedAccount.id) : '',
                category_id: cat.category_id,
                subcategory_id: cat.subcategory_id,
                aiLoading: false,
                aiDone: false,
                selected: defaultSelected(t),
            };
        }),
    );
    const [importing, setImporting] = useState(false);
    const [enriching, setEnriching] = useState(false);
    const [autoFixingDateId, setAutoFixingDateId] = useState(null);
    const [bundleLedgerId, setBundleLedgerId] = useState('');
    const [bundleLinking, setBundleLinking] = useState(false);

    useEffect(() => {
        setRows(
            transactions.map((t, i) => {
                const cat = categoryDefaultsFromLedger(t);
                return {
                    ...t,
                    _id: i,
                    account_id: suggestedAccount?.id != null ? String(suggestedAccount.id) : '',
                    category_id: cat.category_id,
                    subcategory_id: cat.subcategory_id,
                    aiLoading: false,
                    aiDone: false,
                    selected: defaultSelected(t),
                };
            }),
        );
    }, [transactions, suggestedAccount?.id]);

    const monthKeys = useMemo(() => sortedMonthKeys(rows), [rows]);

    const [activeMonth, setActiveMonth] = useState(() => storedActiveMonth(sortedMonthKeys(transactions)));

    useEffect(() => {
        setActiveMonth((prev) => {
            const keys = sortedMonthKeys(rows);
            const stored = storedActiveMonth(keys);
            return keys.includes(prev) ? prev : stored;
        });
    }, [rows]);

    useEffect(() => {
        if (typeof window !== 'undefined' && monthKeys.includes(activeMonth)) {
            window.sessionStorage.setItem(activeMonthStorageKey(), activeMonth);
        }
    }, [activeMonth, monthKeys]);

    const visibleRows = useMemo(
        () => rows.filter((r) => monthKeyFromRow(r) === activeMonth),
        [rows, activeMonth],
    );

    const monthSummary = useMemo(() => reconcileSummaryForRows(visibleRows), [visibleRows]);

    const orderedIdsForImportErrors = useMemo(() => {
        const hasRowFieldErrors = Object.keys(inertiaErrors).some((k) => /^rows\.\d+\.\w+$/.test(k));
        if (!hasRowFieldErrors) {
            return [];
        }
        try {
            const raw = sessionStorage.getItem(IMPORT_ROW_ORDER_STORAGE_KEY);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed.map(Number) : [];
        } catch {
            return [];
        }
    }, [inertiaErrors]);

    const incomeParent = useMemo(
        () => categories.find((c) => String(c.code ?? '').toUpperCase() === 'INCOME'),
        [categories],
    );
    const expenseParents = useMemo(
        () =>
            categories.filter((c) => {
                const code = String(c.code ?? '').toUpperCase();
                return code !== 'INCOME' && code !== 'ACCOUNT_TRANSFER';
            }),
        [categories],
    );
    const expenseParentsWithSubs = useMemo(
        () => expenseParents.filter((p) => childList(p).length > 0),
        [expenseParents],
    );

    const getSubcategories = (parentId) => {
        const c = categories.find((x) => x.id === parseInt(parentId, 10));
        return c ? childList(c) : [];
    };

    const updateRow = (id, field, value) =>
        setRows((prev) => prev.map((r) => (r._id === id ? { ...r, [field]: value } : r)));

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const fillWithAI = async (row) => {
        updateRow(row._id, 'aiLoading', true);
        try {
            const res = await fetch('/ai/categorize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: JSON.stringify({ description: rowDescription(row), type: row.type }),
            });
            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.error || 'Categorization failed');
            }
            setRows((prev) =>
                prev.map((r) => {
                    if (r._id !== row._id) {
                        return r;
                    }
                    const base = {
                        ...r,
                        aiLoading: false,
                        aiDone: true,
                        aiConfidence: data.confidence,
                        aiReason: data.reason,
                    };
                    if (r.type === 'income') {
                        return {
                            ...base,
                            category_id: '',
                            subcategory_id: data.subcategory_id != null ? String(data.subcategory_id) : '',
                        };
                    }
                    return {
                        ...base,
                        category_id: data.category_id != null ? String(data.category_id) : '',
                        subcategory_id: data.subcategory_id != null ? String(data.subcategory_id) : '',
                    };
                }),
            );
        } catch {
            updateRow(row._id, 'aiLoading', false);
        }
    };

    const fillAllWithAI = async () => {
        const unfilled = visibleRows.filter(
            (r) => r.selected && !rowHasLeafCategory(r, expenseParentsWithSubs),
        );
        for (const row of unfilled) {
            await fillWithAI(row);
        }
    };

    const handleImport = () => {
        const selectedForImport = visibleRows.filter((r) => r.selected && r.account_id && isImportableRow(r));
        if (!selectedForImport.length) {
            alert('Nothing to import in this month. Rows already marked “In ledger” are skipped.');
            return;
        }
        const missingCat = selectedForImport.filter((r) => !rowHasLeafCategory(r, expenseParentsWithSubs));
        if (missingCat.length) {
            alert(
                'Each selected row needs a valid leaf category: income rows require an income category; expense rows require a parent and subcategory where subs exist.',
            );
            return;
        }
        setImporting(true);

        const orderedIds = selectedForImport.map((r) => r._id);
        sessionStorage.setItem(IMPORT_ROW_ORDER_STORAGE_KEY, JSON.stringify(orderedIds));

        const payload = selectedForImport.map((r) => ({
            date: r.date,
            description: String(r.description || r.display_description || '').trim(),
            amount: r.amount,
            type: r.type,
            account_id: parseInt(r.account_id, 10),
            category_id: parseInt(r.subcategory_id || r.category_id, 10),
            reference: r.reference || null,
            review_row_index: r._id,
        }));

        router.post('/statements/import', { rows: payload }, {
            preserveScroll: true,
            // router.post defaults preserveState:true — same Review instance keeps stale rows[] state after redirect.
            preserveState: false,
            onFinish: () => setImporting(false),
            onSuccess: () => sessionStorage.removeItem(IMPORT_ROW_ORDER_STORAGE_KEY),
        });
    };

    const visibleNeedsDetailRows = visibleRows.filter(isNeedsDetailRow);
    const enrichmentCandidates = visibleNeedsDetailRows.filter((r) => r.selected);

    const handleSaveEnrichment = () => {
        if (!enrichmentCandidates.length) {
            alert('Select at least one row marked “needs detail” to save descriptions.');
            return;
        }
        setEnriching(true);
        router.post(
            '/statements/enrich',
            {
                updates: enrichmentCandidates.map((r) => ({
                    transaction_id: r.existing_transaction_id,
                    description: r.description || r.display_description,
                    reference: r.reference ?? null,
                    ...(r.date ? { transaction_date: r.date } : {}),
                })),
            },
            {
                preserveScroll: true,
                preserveState: false,
                onFinish: () => setEnriching(false),
            },
        );
    };

    const handleAutoFixDate = (row) => {
        const transactionId = matchedLedgerRowId(row);
        if (!transactionId || !row.date) {
            return;
        }

        const description = String(row.db_description_snapshot || rowDescription(row)).trim();
        if (!description) {
            alert('Cannot auto-fix date because this ledger row has no description. Open the edit page and add details manually.');
            return;
        }

        setAutoFixingDateId(row._id);
        router.post(
            '/statements/enrich',
            {
                updates: [
                    {
                        transaction_id: transactionId,
                        description,
                        reference: row.db_reference_snapshot || row.reference || null,
                        transaction_date: row.date,
                    },
                ],
            },
            {
                preserveScroll: true,
                preserveState: false,
                onFinish: () => setAutoFixingDateId(null),
            },
        );
    };

    const handleBundleLink = () => {
        const selected = visibleRows.filter((r) => r.selected);
        if (selected.length < 2) {
            alert('Select at least two statement rows in this month to link as a split bundle.');
            return;
        }
        const ledgerId = parseInt(bundleLedgerId, 10);
        if (!ledgerId) {
            alert('Enter the ledger transaction ID this bundle should match (from Transactions).');
            return;
        }
        const accountIds = [...new Set(selected.map((r) => r.account_id).filter(Boolean))];
        if (accountIds.length !== 1) {
            alert('All selected rows must use the same account.');
            return;
        }
        setBundleLinking(true);
        router.post(
            '/statements/bundle-link',
            {
                account_id: parseInt(accountIds[0], 10),
                ledger_transaction_id: ledgerId,
                rows: selected.map((r) => ({
                    statement_sequence:
                        r.statement_sequence != null && r.statement_sequence !== ''
                            ? parseInt(r.statement_sequence, 10)
                            : null,
                    date: r.date,
                    amount: parseFloat(r.amount),
                    type: r.type,
                    description: r.description ?? r.display_description ?? null,
                })),
            },
            {
                preserveScroll: true,
                preserveState: false,
                onFinish: () => setBundleLinking(false),
            },
        );
    };

    const rowDescription = (row) => row.display_description ?? row.description ?? '';

    const reconcileBadge = (row) => {
        if (row.matched_via_bundle) {
            return { label: 'Split bundle', className: 'text-white', style: { backgroundColor: '#7c3aed' } };
        }
        switch (row.reconcile_status) {
            case 'missing_in_db':
                return { label: 'New / anomaly', className: 'bg-danger' };
            case 'matched_complete':
                return { label: 'In ledger', className: 'bg-success' };
            case 'matched_needs_enrichment':
                return { label: 'Needs detail', className: 'bg-warning text-dark' };
            case 'unscoped':
                return { label: 'Pick account', className: 'bg-info text-dark' };
            default:
                return null;
        }
    };

    const confidenceColor = { high: '#10b981', medium: '#f59e0b', low: '#ef4444' };
    const selectedCount = rows.filter((r) => r.selected).length;
    const visibleSelectedCount = visibleRows.filter((r) => r.selected).length;
    const visibleImportableRows = visibleRows.filter((r) => isImportableRow(r));
    const visibleImportableSelectedCount = visibleImportableRows.filter((r) => r.selected && r.account_id).length;
    const selectedOutsideActiveMonth = rows.some(
        (r) => r.selected && monthKeyFromRow(r) !== activeMonth,
    );
    const activeMonthIdx = monthKeys.indexOf(activeMonth);

    const rowFieldErrorKeys = Object.keys(inertiaErrors).filter((k) => /^rows\.\d+\./.test(k));
    const visibleRowIds = new Set(visibleRows.map((r) => r._id));
    const rowFieldErrorsApplyToVisibleRows = rowFieldErrorKeys.length > 0 &&
        orderedIdsForImportErrors.some((id) => visibleRowIds.has(id));
    const rowsImportAlert = rowsLevelImportMessage(inertiaErrors);
    const showImportErrorAlert = rowFieldErrorsApplyToVisibleRows || Boolean(rowsImportAlert && visibleImportableSelectedCount > 0);
    const allVisibleRowsAlreadyInLedger = visibleRows.length > 0 && visibleImportableRows.length === 0;
    return (
        <BootstrapLayout>
            <Head title="Review Imported Statement" />

            {flash.success ? (
                <div className="alert alert-success mb-3" style={{ borderRadius: 10 }}>
                    <i className="fas fa-check-circle me-2"></i>
                    {flash.success}
                </div>
            ) : null}
            {flash.warning ? (
                <div className="alert alert-warning mb-3" style={{ borderRadius: 10 }}>
                    <i className="fas fa-exclamation-triangle me-2"></i>
                    {flash.warning}
                </div>
            ) : null}
            {showImportErrorAlert && (
                <div className="alert alert-danger mb-3" style={{ borderRadius: 10 }}>
                    {rowsImportAlert ? <div className="mb-1">{rowsImportAlert}</div> : null}
                    <div className="small mb-0">
                        Fix the highlighted importable rows below (validation applies to your last import attempt).
                    </div>
                </div>
            )}

            <div
                className="card mb-4"
                style={{
                    background: 'linear-gradient(135deg,#667eea 0%,#764ba2 100%)',
                    color: '#fff',
                    borderRadius: 14,
                    border: 'none',
                }}
            >
                <div className="card-body py-3">
                    <div className="row align-items-center mb-3">
                        <div className="col-auto">
                            <div
                                style={{
                                    width: 50,
                                    height: 50,
                                    borderRadius: '50%',
                                    background: 'rgba(255,255,255,0.2)',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                <i className="fas fa-university fa-lg"></i>
                            </div>
                        </div>
                        <div className="col">
                            <h5 className="mb-0 fw-bold">
                                {showVal(ai.bank_name) !== '—' ? ai.bank_name : 'Statement details'}
                            </h5>
                            <small style={{ opacity: 0.9 }}>
                                Values below come from the statement header (parsed with AI where needed). Empty fields were not found in the file.
                            </small>
                        </div>
                        <div className="col-auto text-end">
                            <div style={{ background: 'rgba(255,255,255,0.2)', borderRadius: 8, padding: '8px 16px' }}>
                                <div style={{ fontSize: 22, fontWeight: 700 }}>{rows.length}</div>
                                <div style={{ fontSize: 11, opacity: 0.85 }}>Transactions found</div>
                            </div>
                        </div>
                    </div>
                    <div
                        className="row g-3 pt-2"
                        style={{
                            borderTop: '1px solid rgba(255,255,255,0.25)',
                            fontSize: 13,
                        }}
                    >
                        <div className="col-6 col-md-4">
                            <div style={{ opacity: 0.8, fontSize: 11, textTransform: 'uppercase', letterSpacing: '0.04em' }}>Account holder</div>
                            <div className="fw-semibold mt-1" style={{ wordBreak: 'break-word' }}>{showVal(ai.account_holder_name)}</div>
                        </div>
                        <div className="col-6 col-md-4">
                            <div style={{ opacity: 0.8, fontSize: 11, textTransform: 'uppercase', letterSpacing: '0.04em' }}>Account number (file)</div>
                            <div className="fw-semibold mt-1" style={{ wordBreak: 'break-all' }}>{showVal(ai.account_number)}</div>
                        </div>
                        <div className="col-6 col-md-4">
                            <div style={{ opacity: 0.8, fontSize: 11, textTransform: 'uppercase', letterSpacing: '0.04em' }}>IFSC</div>
                            <div className="fw-semibold mt-1">{showVal(ai.ifsc_code)}</div>
                        </div>
                        <div className="col-6 col-md-4">
                            <div style={{ opacity: 0.8, fontSize: 11, textTransform: 'uppercase', letterSpacing: '0.04em' }}>Account type</div>
                            <div className="fw-semibold mt-1">{showVal(ai.account_type)}</div>
                        </div>
                        <div className="col-12 col-md-8">
                            <div style={{ opacity: 0.8, fontSize: 11, textTransform: 'uppercase', letterSpacing: '0.04em' }}>Statement period</div>
                            <div className="fw-semibold mt-1">{showVal(ai.statement_period)}</div>
                        </div>
                    </div>
                </div>
            </div>

            {suggestedAccount && accountMatchNote && (
                <div className="alert alert-success d-flex align-items-start gap-2 mb-3" style={{ borderRadius: 10 }}>
                    <i className="fas fa-link mt-1"></i>
                    <div>
                        <strong>Account auto-selected</strong>
                        <div className="small mb-1">{suggestedAccount.name}</div>
                        <span className="badge bg-success text-white me-2">
                            {accountMatch === 'full_number' ? 'Full number match' : accountMatch === 'last_four' ? 'Last 4 digits' : 'Matched'}
                        </span>
                        <span className="small text-muted">{accountMatchNote}</span>
                    </div>
                </div>
            )}
            {!suggestedAccount && accountMatchNote && (
                <div className="alert alert-warning d-flex align-items-start gap-2 mb-3" style={{ borderRadius: 10 }}>
                    <i className="fas fa-exclamation-triangle mt-1"></i>
                    <div>
                        <strong>Account not auto-selected</strong>
                        <div className="small">{accountMatchNote}</div>
                    </div>
                </div>
            )}

            {reconcilePeriod?.start && reconcilePeriod?.end && (
                <div className="alert alert-light border mb-3" style={{ borderRadius: 10 }}>
                    <strong className="me-2">
                        <i className="fas fa-calendar-alt me-1 text-primary"></i>
                        Reconcile period (from statement rows)
                    </strong>
                    <span className="text-muted">
                        {reconcilePeriod.start} → {reconcilePeriod.end}
                    </span>
                    <div className="mt-2 d-flex flex-wrap gap-2 align-items-center small">
                        <span className="badge bg-secondary text-white">
                            Month view: {formatMonthTabLabel(activeMonth)}
                        </span>
                        <span className="badge bg-danger">New / anomaly: {monthSummary.missing_in_db ?? 0}</span>
                        <span className="badge bg-success">In ledger: {monthSummary.matched_complete ?? 0}</span>
                        <span className="badge bg-warning text-dark">
                            Needs detail: {monthSummary.matched_needs_enrichment ?? 0}
                        </span>
                        {balanceInfo.net_delta_statement_minus_db != null &&
                            Math.abs(Number(balanceInfo.net_delta_statement_minus_db)) > 0.001 && (
                                <span className="badge bg-dark">
                                    Statement vs ledger net (period): ₹
                                    {Number(balanceInfo.net_delta_statement_minus_db).toLocaleString('en-IN', {
                                        minimumFractionDigits: 2,
                                    })}
                                </span>
                            )}
                    </div>
                    <div className="small text-muted mt-2 mb-0">
                        Counts above reflect <strong>{formatMonthTabLabel(activeMonth)}</strong> only ({visibleRows.length}{' '}
                        rows in the table). Statement-wide reconcile period: {reconcilePeriod.start} →{' '}
                        {reconcilePeriod.end}. Bank date wins when you save descriptions; date-drift rows need Save
                        descriptions (or edit the date manually). Row colors: green = already recorded · red = missing from
                        ledger · amber = needs ledger fix (thin description and/or bank date correction).
                    </div>
                </div>
            )}

            {balanceInfo.chain_ok === false && (
                <div className="alert alert-danger mb-3" style={{ borderRadius: 10 }}>
                    <strong>Balance mismatch on statement</strong>
                    <div className="small mt-1">
                        Running balances do not chain correctly at statement sequence{' '}
                        {balanceInfo.broken_at_statement_sequence != null
                            ? `#${balanceInfo.broken_at_statement_sequence}`
                            : `(row index ${balanceInfo.broken_at_index})`}
                        . This is the statement row number from the source file, not a transaction ID.
                    </div>
                    {balanceInfo.broken_at_detail ? (
                        <div className="mt-2 p-2 bg-white border rounded small text-dark">
                            <div className="fw-semibold mb-1">Mismatch detail</div>
                            <div className="row g-2">
                                <div className="col-md-3">
                                    <span className="text-muted">Previous row:</span>{' '}
                                    #{balanceInfo.broken_at_detail.previous_statement_sequence ?? balanceInfo.broken_at_detail.previous_index}
                                </div>
                                <div className="col-md-3">
                                    <span className="text-muted">Previous balance:</span>{' '}
                                    {formatRupee(balanceInfo.broken_at_detail.previous_balance_after)}
                                </div>
                                <div className="col-md-3">
                                    <span className="text-muted">Current row:</span>{' '}
                                    #{balanceInfo.broken_at_detail.current_statement_sequence ?? balanceInfo.broken_at_detail.current_index}
                                </div>
                                <div className="col-md-3">
                                    <span className="text-muted">Date:</span>{' '}
                                    {showVal(balanceInfo.broken_at_detail.current_date)}
                                </div>
                                <div className="col-md-6">
                                    <span className="text-muted">Description:</span>{' '}
                                    {showVal(balanceInfo.broken_at_detail.current_description)}
                                </div>
                                <div className="col-md-3">
                                    <span className="text-muted">Debit:</span>{' '}
                                    {formatRupee(balanceInfo.broken_at_detail.current_debit_amount)}
                                </div>
                                <div className="col-md-3">
                                    <span className="text-muted">Credit:</span>{' '}
                                    {formatRupee(balanceInfo.broken_at_detail.current_credit_amount)}
                                </div>
                                <div className="col-md-4">
                                    <span className="text-muted">Expected balance:</span>{' '}
                                    {formatRupee(balanceInfo.broken_at_detail.expected_balance_after)}
                                </div>
                                <div className="col-md-4">
                                    <span className="text-muted">Actual balance:</span>{' '}
                                    {formatRupee(balanceInfo.broken_at_detail.actual_balance_after)}
                                </div>
                                <div className="col-md-4">
                                    <span className="text-muted">Difference:</span>{' '}
                                    {formatRupee(balanceInfo.broken_at_detail.difference)}
                                </div>
                            </div>
                            <div className="text-muted mt-2">
                                Formula checked: previous balance - debit + credit = expected balance.
                            </div>
                        </div>
                    ) : null}
                </div>
            )}

            <div className="card mb-3 border-0 shadow-sm" style={{ borderRadius: 12 }}>
                <div className="card-body py-3">
                    <div className="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span className="small text-muted fw-semibold text-uppercase me-2">Statement month</span>
                        <button
                            type="button"
                            className="btn btn-sm btn-outline-secondary"
                            disabled={activeMonthIdx <= 0}
                            onClick={() => activeMonthIdx > 0 && setActiveMonth(monthKeys[activeMonthIdx - 1])}
                        >
                            <i className="fas fa-chevron-left"></i>
                        </button>
                        <span className="fw-bold px-2">{formatMonthTabLabel(activeMonth)}</span>
                        <button
                            type="button"
                            className="btn btn-sm btn-outline-secondary"
                            disabled={activeMonthIdx < 0 || activeMonthIdx >= monthKeys.length - 1}
                            onClick={() =>
                                activeMonthIdx >= 0 &&
                                activeMonthIdx < monthKeys.length - 1 &&
                                setActiveMonth(monthKeys[activeMonthIdx + 1])
                            }
                        >
                            <i className="fas fa-chevron-right"></i>
                        </button>
                        <span className="small text-muted ms-2">{visibleRows.length} rows</span>
                    </div>
                    <div className="d-flex flex-wrap gap-2">
                        {monthKeys.map((mk) => {
                            const cnt = rows.filter((r) => monthKeyFromRow(r) === mk).length;
                            const active = mk === activeMonth;
                            return (
                                <button
                                    key={mk}
                                    type="button"
                                    className={`btn btn-sm ${active ? 'btn-primary' : 'btn-outline-secondary'}`}
                                    style={{ borderRadius: 20 }}
                                    onClick={() => setActiveMonth(mk)}
                                >
                                    {formatMonthTabLabel(mk)}
                                    <span className={`badge ms-2 ${active ? 'bg-light text-primary' : 'bg-secondary'}`}>
                                        {cnt}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            </div>

            {selectedOutsideActiveMonth ? (
                <div className="alert alert-info py-2 mb-3 small" style={{ borderRadius: 10 }}>
                    You have selections in other months. Import, Save descriptions, bundle link, and AI batch actions
                    apply only to <strong>{formatMonthTabLabel(activeMonth)}</strong>.
                </div>
            ) : null}

            <div className="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 className="mb-0 fw-bold">
                        <i className="fas fa-table me-2" style={{ color: '#667eea' }}></i>
                        Review Transactions
                        <span className="badge ms-2" style={{ background: '#667eea', fontSize: 12 }}>
                            {visibleSelectedCount} selected · {selectedCount} total
                        </span>
                    </h5>
                    <small className="text-muted d-block mt-1">
                        Import only applies to red “New / anomaly” rows. Green “In ledger” rows are duplicates and are skipped.
                    </small>
                    {allVisibleRowsAlreadyInLedger ? (
                        <div className="alert alert-success py-2 mt-2 mb-0 small" style={{ borderRadius: 10 }}>
                            All {visibleRows.length} rows in {formatMonthTabLabel(activeMonth)} are already in ledger. Nothing to import.
                        </div>
                    ) : null}
                </div>
                <div className="d-flex gap-2">
                    <button
                        type="button"
                        onClick={fillAllWithAI}
                        className="btn"
                        style={{
                            background: 'linear-gradient(135deg,#f59e0b,#ef4444)',
                            color: '#fff',
                            borderRadius: 8,
                            fontWeight: 600,
                            fontSize: 13,
                        }}
                    >
                        <i className="fas fa-magic me-2"></i>Assign categories (AI) — visible month
                    </button>
                    {(monthSummary.matched_needs_enrichment ?? 0) > 0 && (
                        <button
                            type="button"
                            onClick={handleSaveEnrichment}
                            disabled={enriching || enrichmentCandidates.length === 0}
                            className="btn btn-outline-dark"
                            style={{
                                borderRadius: 8,
                                fontWeight: 600,
                                fontSize: 13,
                            }}
                            title="Save statement narratives onto selected rows marked ‘Needs detail’."
                        >
                            {enriching ? (
                                <>
                                    <span className="spinner-border spinner-border-sm me-2"></span>
                                    Saving...
                                </>
                            ) : (
                                <>
                                    <i className="fas fa-save me-2"></i>
                                    Save descriptions ({enrichmentCandidates.length})
                                </>
                            )}
                        </button>
                    )}
                    <div className="d-flex align-items-center gap-2 flex-wrap">
                        <input
                            type="number"
                            min={1}
                            className="form-control form-control-sm"
                            style={{ width: 140, borderRadius: 8 }}
                            placeholder="Ledger txn ID"
                            value={bundleLedgerId}
                            onChange={(e) => setBundleLedgerId(e.target.value)}
                            title="Transactions list ID for one ledger row whose amount equals the sum of selected rows"
                        />
                        <button
                            type="button"
                            onClick={handleBundleLink}
                            disabled={bundleLinking || visibleRows.filter((r) => r.selected).length < 2}
                            className="btn btn-outline-secondary btn-sm"
                            style={{ borderRadius: 8, fontWeight: 600, fontSize: 13 }}
                            title="Save when multiple bank lines map to one ledger amount (e.g. ₹20+₹10 → ₹30). Re-upload statement after saving."
                        >
                            {bundleLinking ? (
                                <>
                                    <span className="spinner-border spinner-border-sm me-2"></span>
                                    Saving bundle...
                                </>
                            ) : (
                                <>
                                    <i className="fas fa-link me-2"></i>
                                    Link split rows ({visibleRows.filter((r) => r.selected).length})
                                </>
                            )}
                        </button>
                    </div>
                    <button
                        type="button"
                        onClick={handleImport}
                        disabled={importing || visibleImportableSelectedCount === 0}
                        className="btn"
                        style={{
                            background: 'linear-gradient(135deg,#667eea,#764ba2)',
                            color: '#fff',
                            borderRadius: 8,
                            fontWeight: 600,
                            fontSize: 13,
                        }}
                    >
                        {importing ? (
                            <>
                                <span className="spinner-border spinner-border-sm me-2"></span>
                                Importing...
                            </>
                        ) : (
                            <>
                                <i className="fas fa-download me-2"></i>
                                Import {visibleImportableSelectedCount} Rows
                            </>
                        )}
                    </button>
                </div>
            </div>

            <div className="card shadow-sm" style={{ borderRadius: 14, overflow: 'hidden', border: '1px solid #e5e7eb' }}>
                <div className="table-responsive">
                    <table className="table mb-0" style={{ fontSize: 13 }}>
                        <thead style={{ background: '#1e293b', color: '#fff' }}>
                            <tr>
                                <th style={{ width: 40, padding: '12px 16px' }}>
                                    <input
                                        type="checkbox"
                                        className="form-check-input"
                                        checked={
                                            visibleRows.length > 0 &&
                                            visibleRows.every((r) => r.selected)
                                        }
                                        onChange={(e) => {
                                            const checked = e.target.checked;
                                            const ids = new Set(visibleRows.map((r) => r._id));
                                            setRows((prev) =>
                                                prev.map((r) =>
                                                    ids.has(r._id) ? { ...r, selected: checked } : r,
                                                ),
                                            );
                                        }}
                                    />
                                </th>
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>STATUS</th>
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>DATE</th>
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>DESCRIPTION</th>
                                {showBalanceCol && (
                                    <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>
                                        BALANCE
                                    </th>
                                )}
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>AMOUNT</th>
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>TYPE</th>
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>ACCOUNT</th>
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>CATEGORY</th>
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>ACTION</th>
                                {reconcileDebug && (
                                    <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>
                                        MATCH DEBUG
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {visibleRows.map((row) => {
                                const surface = reconcileRowSurface(row);
                                const rowBg = surface.bg ?? (row.selected ? '#fff' : '#f9fafb');
                                const accountErr = importFieldError(
                                    inertiaErrors,
                                    row._id,
                                    'account_id',
                                    orderedIdsForImportErrors,
                                );
                                const categoryErr = importFieldError(
                                    inertiaErrors,
                                    row._id,
                                    'category_id',
                                    orderedIdsForImportErrors,
                                );
                                const descriptionErr = importFieldError(
                                    inertiaErrors,
                                    row._id,
                                    'description',
                                    orderedIdsForImportErrors,
                                );
                                const amountErr = importFieldError(
                                    inertiaErrors,
                                    row._id,
                                    'amount',
                                    orderedIdsForImportErrors,
                                );
                                const dateErr = importFieldError(
                                    inertiaErrors,
                                    row._id,
                                    'date',
                                    orderedIdsForImportErrors,
                                );
                                const typeErr = importFieldError(
                                    inertiaErrors,
                                    row._id,
                                    'type',
                                    orderedIdsForImportErrors,
                                );
                                return (
                                <tr
                                    key={row._id}
                                    style={{
                                        background: rowBg,
                                        borderBottom: '1px solid #f1f5f9',
                                        borderLeft: surface.borderLeft,
                                        opacity: row.selected ? 1 : 0.55,
                                        transition: 'background 0.15s',
                                    }}
                                >
                                    <td style={{ padding: '12px 16px', verticalAlign: 'middle' }}>
                                        <input
                                            type="checkbox"
                                            className="form-check-input"
                                            checked={row.selected}
                                            onChange={(e) => updateRow(row._id, 'selected', e.target.checked)}
                                        />
                                    </td>
                                    <td style={{ padding: '12px 16px', verticalAlign: 'middle' }}>
                                        {(() => {
                                            const b = reconcileBadge(row);
                                            const reasonText = needsDetailReasonText(row);
                                            const matchedLedgerText = matchedLedgerRowLabel(row);
                                            const matchedLedgerUrl = matchedLedgerRowEditUrl(row);
                                            const badgeTitle = [reasonText, matchedLedgerText].filter(Boolean).join(' • ') || undefined;
                                            return b ? (
                                                <>
                                                    <span
                                                        className={`badge ${b.className}`}
                                                        style={{ fontSize: 10, ...(b.style ?? {}) }}
                                                        title={badgeTitle}
                                                    >
                                                        {b.label}
                                                    </span>
                                                    {reasonText && row.reconcile_status === 'matched_needs_enrichment' ? (
                                                        <div className="small text-muted mt-1" style={{ fontSize: 10, maxWidth: 170 }}>
                                                            Why: {reasonText}
                                                        </div>
                                                    ) : null}
                                                    {matchedLedgerUrl ? (
                                                        <div className="small text-muted mt-1" style={{ fontSize: 10, maxWidth: 170 }}>
                                                            Matched DB row:{' '}
                                                            <a
                                                                className="link-primary text-decoration-underline"
                                                                href={matchedLedgerUrl}
                                                                style={{ fontSize: 10 }}
                                                                title={`Open transaction #${matchedLedgerRowId(row)} edit page`}
                                                            >
                                                                #{matchedLedgerRowId(row)}
                                                            </a>
                                                        </div>
                                                    ) : null}
                                                </>
                                            ) : (
                                                '—'
                                            );
                                        })()}
                                    </td>
                                    <td style={{ padding: '12px 16px', verticalAlign: 'middle', whiteSpace: 'nowrap' }}>
                                        <div className="d-flex align-items-center gap-2">
                                            <span style={{ color: '#475569', fontWeight: 500 }}>{row.date}</span>
                                            {row.date_drift && matchedLedgerRowId(row) ? (
                                                <button
                                                    type="button"
                                                    className="btn btn-sm btn-outline-warning p-1"
                                                    style={{ lineHeight: 1, borderRadius: 8 }}
                                                    title={`Auto-fix ledger date from ${row.ledger_transaction_date || 'old date'} to statement date ${row.date}`}
                                                    onClick={() => handleAutoFixDate(row)}
                                                    disabled={autoFixingDateId === row._id}
                                                >
                                                    {autoFixingDateId === row._id ? (
                                                        <span className="spinner-border spinner-border-sm" style={{ width: 12, height: 12 }}></span>
                                                    ) : (
                                                        <i className="fas fa-magic"></i>
                                                    )}
                                                </button>
                                            ) : null}
                                        </div>
                                        {row.date_drift && row.ledger_transaction_date ? (
                                            <div className="small text-warning-emphasis mt-1" style={{ fontSize: 10 }}>
                                                Ledger: {row.ledger_transaction_date}
                                            </div>
                                        ) : null}
                                        {dateErr ? (
                                            <div className="text-danger small mt-1 text-wrap">{dateErr}</div>
                                        ) : null}
                                    </td>
                                    <td style={{ padding: '12px 16px', verticalAlign: 'middle', maxWidth: 260 }}>
                                        <div
                                            style={{
                                                fontWeight: 500,
                                                color: '#1e293b',
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                                whiteSpace: 'nowrap',
                                            }}
                                            title={rowDescription(row)}
                                        >
                                            {rowDescription(row)}
                                        </div>
                                        {row.aiReason && (
                                            <small style={{ color: confidenceColor[row.aiConfidence] || '#64748b', fontSize: 10 }}>
                                                <i className="fas fa-robot me-1"></i>
                                                {row.aiReason}
                                            </small>
                                        )}
                                        {descriptionErr ? (
                                            <div className="text-danger small mt-1">{descriptionErr}</div>
                                        ) : null}
                                    </td>
                                    {showBalanceCol && (
                                        <td style={{ padding: '12px 16px', verticalAlign: 'middle', whiteSpace: 'nowrap' }}>
                                            {row.balance_after != null ? (
                                                <span style={{ color: '#475569', fontWeight: 600, fontSize: 12 }}>
                                                    ₹
                                                    {parseFloat(row.balance_after).toLocaleString('en-IN', {
                                                        minimumFractionDigits: 2,
                                                    })}
                                                </span>
                                            ) : (
                                                <span className="text-muted">—</span>
                                            )}
                                        </td>
                                    )}
                                    <td style={{ padding: '12px 16px', verticalAlign: 'middle', whiteSpace: 'nowrap' }}>
                                        <span
                                            style={{
                                                fontWeight: 700,
                                                fontSize: 14,
                                                color: row.type === 'income' ? '#10b981' : '#ef4444',
                                            }}
                                        >
                                            ₹{parseFloat(row.amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                                        </span>
                                        {amountErr ? (
                                            <div className="text-danger small mt-1">{amountErr}</div>
                                        ) : null}
                                    </td>
                                    <td style={{ padding: '12px 16px', verticalAlign: 'middle' }}>
                                        <span
                                            style={{
                                                padding: '4px 10px',
                                                borderRadius: 20,
                                                fontSize: 11,
                                                fontWeight: 700,
                                                background: row.type === 'income' ? '#d1fae5' : '#fee2e2',
                                                color: row.type === 'income' ? '#065f46' : '#991b1b',
                                            }}
                                        >
                                            {row.type === 'income' ? '▲ Income' : '▼ Expense'}
                                        </span>
                                        {typeErr ? (
                                            <div className="text-danger small mt-1">{typeErr}</div>
                                        ) : null}
                                    </td>
                                    <td style={{ padding: '8px 12px', verticalAlign: 'middle', minWidth: 160 }}>
                                        <select
                                            className={`form-select form-select-sm${accountErr ? ' is-invalid' : ''}`}
                                            value={row.account_id}
                                            onChange={(e) => updateRow(row._id, 'account_id', e.target.value)}
                                            style={{ borderRadius: 8, border: '1px solid #e2e8f0', fontSize: 12 }}
                                        >
                                            <option value="">Select Account</option>
                                            {accounts.map((a) => (
                                                <option key={a.id} value={a.id}>{a.name}</option>
                                            ))}
                                        </select>
                                        {accountErr ? (
                                            <div className="invalid-feedback d-block">{accountErr}</div>
                                        ) : null}
                                    </td>
                                    <td style={{ padding: '8px 12px', verticalAlign: 'middle', minWidth: 200 }}>
                                        {row.type === 'income' ? (
                                            <select
                                                className={`form-select form-select-sm${categoryErr ? ' is-invalid' : ''}`}
                                                value={row.subcategory_id}
                                                onChange={(e) => {
                                                    updateRow(row._id, 'category_id', '');
                                                    updateRow(row._id, 'subcategory_id', e.target.value);
                                                }}
                                                style={{ borderRadius: 8, border: '1px solid #e2e8f0', fontSize: 12 }}
                                            >
                                                <option value="">Income category</option>
                                                {incomeParent ? childList(incomeParent).map((s) => (
                                                    <option key={s.id} value={s.id}>{s.name}</option>
                                                )) : null}
                                            </select>
                                        ) : (
                                            <div className="d-flex flex-column gap-1">
                                                <select
                                                    className={`form-select form-select-sm${categoryErr ? ' is-invalid' : ''}`}
                                                    value={row.category_id}
                                                    onChange={(e) => {
                                                        updateRow(row._id, 'category_id', e.target.value);
                                                        updateRow(row._id, 'subcategory_id', '');
                                                    }}
                                                    style={{ borderRadius: 8, border: '1px solid #e2e8f0', fontSize: 12 }}
                                                >
                                                    <option value="">Expense category</option>
                                                    {expenseParentsWithSubs.map((c) => (
                                                        <option key={c.id} value={c.id}>{c.name}</option>
                                                    ))}
                                                </select>
                                                {row.category_id && getSubcategories(row.category_id).length > 0 && (
                                                    <select
                                                        className={`form-select form-select-sm${categoryErr ? ' is-invalid' : ''}`}
                                                        value={row.subcategory_id}
                                                        onChange={(e) => updateRow(row._id, 'subcategory_id', e.target.value)}
                                                        style={{ borderRadius: 8, border: '1px solid #e2e8f0', fontSize: 12 }}
                                                    >
                                                        <option value="">Subcategory</option>
                                                        {getSubcategories(row.category_id).map((s) => (
                                                            <option key={s.id} value={s.id}>{s.name}</option>
                                                        ))}
                                                    </select>
                                                )}
                                            </div>
                                        )}
                                        {categoryErr ? (
                                            <div className="invalid-feedback d-block">{categoryErr}</div>
                                        ) : null}
                                        {row.aiDone && row.aiConfidence && (
                                            <div style={{ marginTop: 2 }}>
                                                <span
                                                    style={{
                                                        fontSize: 10,
                                                        padding: '1px 6px',
                                                        borderRadius: 10,
                                                        background: `${confidenceColor[row.aiConfidence]}20`,
                                                        color: confidenceColor[row.aiConfidence],
                                                        fontWeight: 600,
                                                    }}
                                                >
                                                    AI: {row.aiConfidence} confidence
                                                </span>
                                            </div>
                                        )}
                                    </td>
                                    <td style={{ padding: '8px 12px', verticalAlign: 'middle' }}>
                                        <button
                                            type="button"
                                            onClick={() => fillWithAI(row)}
                                            disabled={row.aiLoading}
                                            title="Suggest category and subcategory from description (does not change amounts or dates)"
                                            style={{
                                                padding: '6px 10px',
                                                borderRadius: 8,
                                                border: 'none',
                                                background: row.aiDone
                                                    ? '#d1fae5'
                                                    : 'linear-gradient(135deg,#f59e0b,#ef4444)',
                                                color: row.aiDone ? '#065f46' : '#fff',
                                                fontSize: 11,
                                                fontWeight: 700,
                                                cursor: 'pointer',
                                                whiteSpace: 'nowrap',
                                                transition: 'all 0.2s',
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 4,
                                            }}
                                        >
                                            {row.aiLoading ? (
                                                <>
                                                    <span className="spinner-border" style={{ width: 12, height: 12, borderWidth: 2 }}></span>
                                                    AI...
                                                </>
                                            ) : row.aiDone ? (
                                                <>
                                                    <i className="fas fa-check-circle"></i> Done
                                                </>
                                            ) : (
                                                <>
                                                    <i className="fas fa-magic"></i> Category (AI)
                                                </>
                                            )}
                                        </button>
                                    </td>
                                    {reconcileDebug && (
                                        <td
                                            style={{
                                                padding: '8px 12px',
                                                verticalAlign: 'middle',
                                                fontSize: 10,
                                                color: '#64748b',
                                                maxWidth: 140,
                                                wordBreak: 'break-word',
                                            }}
                                        >
                                            {row.match_attempt_reason ?? '—'}
                                        </td>
                                    )}
                                </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <div
                    style={{
                        padding: '16px 24px',
                        background: '#f8fafc',
                        borderTop: '1px solid #e2e8f0',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        flexWrap: 'wrap',
                        gap: 12,
                    }}
                >
                    <div style={{ fontSize: 13, color: '#64748b' }}>
                        <span className="me-4">
                            <i className="fas fa-arrow-up me-1" style={{ color: '#10b981' }}></i>
                            Income: ₹
                            {rows
                                .filter((r) => monthKeyFromRow(r) === activeMonth && r.type === 'income' && r.selected)
                                .reduce((s, r) => s + parseFloat(r.amount), 0)
                                .toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                        </span>
                        <span>
                            <i className="fas fa-arrow-down me-1" style={{ color: '#ef4444' }}></i>
                            Expense: ₹
                            {rows
                                .filter((r) => monthKeyFromRow(r) === activeMonth && r.type === 'expense' && r.selected)
                                .reduce((s, r) => s + parseFloat(r.amount), 0)
                                .toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                        </span>
                    </div>
                    <button
                        type="button"
                        onClick={handleImport}
                        disabled={importing || visibleImportableSelectedCount === 0}
                        style={{
                            padding: '10px 28px',
                            borderRadius: 10,
                            border: 'none',
                            background: 'linear-gradient(135deg,#667eea,#764ba2)',
                            color: '#fff',
                            fontWeight: 700,
                            fontSize: 14,
                            cursor: 'pointer',
                        }}
                    >
                        <i className="fas fa-download me-2"></i>
                        Import {visibleImportableSelectedCount} Transaction{visibleImportableSelectedCount !== 1 ? 's' : ''}
                    </button>
                </div>
            </div>
        </BootstrapLayout>
    );
}
