import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
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

export default function Review({ parsedData, suggestedAccount, accountMatch, accountMatchNote, accounts, categories }) {
    const { account_info, transactions } = parsedData;
    const ai = account_info ?? {};

    const showVal = (v) => (v != null && String(v).trim() !== '' ? String(v) : '—');

    const [rows, setRows] = useState(
        transactions.map((t, i) => ({
            ...t,
            _id: i,
            account_id: suggestedAccount?.id != null ? String(suggestedAccount.id) : '',
            category_id: '',
            subcategory_id: '',
            aiLoading: false,
            aiDone: false,
            selected: true,
        })),
    );
    const [importing, setImporting] = useState(false);

    const incomeParent = useMemo(
        () => categories.find((c) => c.code === 'INCOME'),
        [categories],
    );
    const expenseParents = useMemo(
        () => categories.filter((c) => c.code !== 'INCOME' && c.code !== 'ACCOUNTTR'),
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
                body: JSON.stringify({ description: row.description, type: row.type }),
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
        const unfilled = rows.filter(
            (r) => r.selected && !rowHasLeafCategory(r, expenseParentsWithSubs),
        );
        for (const row of unfilled) {
            await fillWithAI(row);
        }
    };

    const handleImport = () => {
        const selected = rows.filter((r) => r.selected && r.account_id);
        if (!selected.length) {
            alert('Select at least one row with an account.');
            return;
        }
        const missingCat = selected.filter((r) => !rowHasLeafCategory(r, expenseParentsWithSubs));
        if (missingCat.length) {
            alert(
                'Each selected row needs a valid leaf category: income rows require an income category; expense rows require a parent and subcategory where subs exist.',
            );
            return;
        }
        setImporting(true);

        const payload = selected.map((r) => ({
            date: r.date,
            description: r.description,
            amount: r.amount,
            type: r.type,
            account_id: parseInt(r.account_id, 10),
            category_id: parseInt(r.subcategory_id || r.category_id, 10),
            reference: r.reference || null,
        }));

        router.post('/statements/import', { rows: payload }, {
            onFinish: () => setImporting(false),
        });
    };

    const confidenceColor = { high: '#10b981', medium: '#f59e0b', low: '#ef4444' };
    const selectedCount = rows.filter((r) => r.selected).length;

    return (
        <BootstrapLayout>
            <Head title="Review Imported Statement" />

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

            <div className="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 className="mb-0 fw-bold">
                        <i className="fas fa-table me-2" style={{ color: '#667eea' }}></i>
                        Review Transactions
                        <span className="badge ms-2" style={{ background: '#667eea', fontSize: 12 }}>
                            {selectedCount} selected
                        </span>
                    </h5>
                    <small className="text-muted">Review, edit categories, then import</small>
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
                        <i className="fas fa-magic me-2"></i>Assign categories (AI) — all rows
                    </button>
                    <button
                        type="button"
                        onClick={handleImport}
                        disabled={importing || !selectedCount}
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
                                Import {selectedCount} Rows
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
                                        checked={rows.length > 0 && rows.every((r) => r.selected)}
                                        onChange={(e) => setRows((prev) => prev.map((r) => ({ ...r, selected: e.target.checked })))}
                                    />
                                </th>
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>DATE</th>
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>DESCRIPTION</th>
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>AMOUNT</th>
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>TYPE</th>
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>ACCOUNT</th>
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>CATEGORY</th>
                                <th style={{ padding: '12px 16px', fontWeight: 600, letterSpacing: '0.03em' }}>ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr
                                    key={row._id}
                                    style={{
                                        background: row.selected ? '#fff' : '#f9fafb',
                                        borderBottom: '1px solid #f1f5f9',
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
                                    <td style={{ padding: '12px 16px', verticalAlign: 'middle', whiteSpace: 'nowrap' }}>
                                        <span style={{ color: '#475569', fontWeight: 500 }}>{row.date}</span>
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
                                            title={row.description}
                                        >
                                            {row.description}
                                        </div>
                                        {row.aiReason && (
                                            <small style={{ color: confidenceColor[row.aiConfidence] || '#64748b', fontSize: 10 }}>
                                                <i className="fas fa-robot me-1"></i>
                                                {row.aiReason}
                                            </small>
                                        )}
                                    </td>
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
                                    </td>
                                    <td style={{ padding: '8px 12px', verticalAlign: 'middle', minWidth: 160 }}>
                                        <select
                                            className="form-select form-select-sm"
                                            value={row.account_id}
                                            onChange={(e) => updateRow(row._id, 'account_id', e.target.value)}
                                            style={{ borderRadius: 8, border: '1px solid #e2e8f0', fontSize: 12 }}
                                        >
                                            <option value="">Select Account</option>
                                            {accounts.map((a) => (
                                                <option key={a.id} value={a.id}>{a.name}</option>
                                            ))}
                                        </select>
                                    </td>
                                    <td style={{ padding: '8px 12px', verticalAlign: 'middle', minWidth: 200 }}>
                                        {row.type === 'income' ? (
                                            <select
                                                className="form-select form-select-sm"
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
                                                    className="form-select form-select-sm"
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
                                                        className="form-select form-select-sm"
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
                                </tr>
                            ))}
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
                                .filter((r) => r.type === 'income' && r.selected)
                                .reduce((s, r) => s + parseFloat(r.amount), 0)
                                .toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                        </span>
                        <span>
                            <i className="fas fa-arrow-down me-1" style={{ color: '#ef4444' }}></i>
                            Expense: ₹
                            {rows
                                .filter((r) => r.type === 'expense' && r.selected)
                                .reduce((s, r) => s + parseFloat(r.amount), 0)
                                .toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                        </span>
                    </div>
                    <button
                        type="button"
                        onClick={handleImport}
                        disabled={importing || !selectedCount}
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
                        Import {selectedCount} Transaction{selectedCount !== 1 ? 's' : ''}
                    </button>
                </div>
            </div>
        </BootstrapLayout>
    );
}
