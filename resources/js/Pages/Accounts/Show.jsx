import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';

export default function Show({ account }) {
    // Debug: Check account data
    console.log('Account data:', account);

    const handleDelete = () => {
        if (window.confirm(`Are you sure you want to delete ${account.name}?`)) {
            router.delete(`/accounts/${account.id}`);
        }
    };

    const handleToggleStatus = () => {
        router.patch(`/accounts/${account.id}/toggle-status`);
    };

    const formatCurrency = (amount) => {
        return `₹${parseFloat(amount).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })}`;
    };

    const getAccountTypeColor = (type) => {
        const colors = {
            savings: 'bg-success',
            current: 'bg-info',
            credit_card: 'bg-purple',
            cash: 'bg-warning',
            investment: 'bg-primary'
        };
        return colors[type] || 'bg-secondary';
    };

    const getAccountTypeLabel = (type) => {
        const types = {
            savings: 'Savings',
            current: 'Current',
            credit_card: 'Credit Card',
            cash: 'Cash',
            investment: 'Investment'
        };
        return types[type] || type;
    };

    return (
        <BootstrapLayout>
            <Head title={account.name} />

            <div className="container-fluid">
                {/* Header */}
                <div className="row mb-4">
                    <div className="col-12">
                        <div className="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <Link
                                    href="/accounts"
                                    className="btn btn-outline-secondary btn-sm mb-2"
                                >
                                    <i className="fas fa-arrow-left me-2"></i>Back to Accounts
                                </Link>
                                <div className="d-flex align-items-center">
                                    <h1 className="h2 mb-0 me-3">{account.name}</h1>
                                    <span className={`badge ${getAccountTypeColor(account.type)} me-2`}>
                                        {getAccountTypeLabel(account.type)}
                                    </span>
                                    <span className={`badge ${account.is_active ? 'bg-success' : 'bg-danger'}`}>
                                        {account.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                            </div>
                            <div className="btn-group">
                                <Link
                                    href={`/accounts/${account.id}/edit`}
                                    className="btn btn-primary"
                                >
                                    <i className="fas fa-edit me-2"></i>Edit Account
                                </Link>
                                <button
                                    type="button"
                                    onClick={handleToggleStatus}
                                    className={`btn ${account.is_active ? 'btn-warning' : 'btn-success'}`}
                                >
                                    <i className={`fas ${account.is_active ? 'fa-pause' : 'fa-play'} me-2`}></i>
                                    {account.is_active ? 'Disable' : 'Enable'}
                                </button>
                                <button
                                    type="button"
                                    onClick={handleDelete}
                                    className="btn btn-danger"
                                >
                                    <i className="fas fa-trash me-2"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="row">
                    {/* Account Details */}
                    <div className="col-lg-8">
                        {/* Basic Information */}
                        <div className="card mb-4">
                            <div className="card-header">
                                <h5 className="card-title mb-0">
                                    <i className="fas fa-info-circle me-2"></i>Account Information
                                </h5>
                            </div>
                            <div className="card-body">
                                <div className="row">
                                    <div className="col-md-6 mb-3">
                                        <label className="form-label text-muted">Account Code</label>
                                        <div className="fw-bold font-monospace">{account.code || 'N/A'}</div>
                                    </div>
                                    <div className="col-md-6 mb-3">
                                        <label className="form-label text-muted">Account Type</label>
                                        <div className="fw-bold">{getAccountTypeLabel(account.type)}</div>
                                    </div>
                                    <div className="col-md-6 mb-3">
                                        <label className="form-label text-muted">Status</label>
                                        <div>
                                            <span className={`badge ${account.is_active ? 'bg-success' : 'bg-danger'}`}>
                                                {account.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="col-md-6 mb-3">
                                        <label className="form-label text-muted">Created</label>
                                        <div className="fw-bold">
                                            {new Date(account.created_at).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'short',
                                                day: 'numeric'
                                            })}
                                        </div>
                                    </div>
                                    {account.description && (
                                        <div className="col-12">
                                            <label className="form-label text-muted">Description</label>
                                            <div className="fw-bold">{account.description}</div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Bank Details */}
                        {account.type !== 'cash' && (account.bank_name || account.account_number || account.ifsc_code) && (
                            <div className="card mb-4">
                                <div className="card-header">
                                    <h5 className="card-title mb-0">
                                        <i className="fas fa-university me-2"></i>Bank Details
                                    </h5>
                                </div>
                                <div className="card-body">
                                    <div className="row">
                                        {account.bank_name && (
                                            <div className="col-md-6 mb-3">
                                                <label className="form-label text-muted">Bank Name</label>
                                                <div className="fw-bold">{account.bank_name}</div>
                                            </div>
                                        )}
                                        {account.account_number && (
                                            <div className="col-md-6 mb-3">
                                                <label className="form-label text-muted">Account Number</label>
                                                <div className="fw-bold font-monospace">{account.account_number}</div>
                                            </div>
                                        )}
                                        {account.ifsc_code && (
                                            <div className="col-md-6 mb-3">
                                                <label className="form-label text-muted">IFSC Code</label>
                                                <div className="fw-bold font-monospace">{account.ifsc_code}</div>
                                            </div>
                                        )}
                                        {account.branch_name && (
                                            <div className="col-md-6 mb-3">
                                                <label className="form-label text-muted">Branch</label>
                                                <div className="fw-bold">{account.branch_name}</div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Balance Information */}
                    <div className="col-lg-4">
                        {/* Current Balance */}
                        <div className="card mb-4">
                            <div className="card-header">
                                <h5 className="card-title mb-0">
                                    <i className="fas fa-balance-scale me-2"></i>Balance Overview
                                </h5>
                            </div>
                            <div className="card-body text-center">
                                <div className="mb-4 p-3 bg-light rounded">
                                    <small className="text-muted d-block mb-1">Current Balance</small>
                                    <h3 className={`mb-0 ${account.current_balance >= 0 ? 'text-success' : 'text-danger'}`}>
                                        {formatCurrency(account.current_balance || 0)}
                                    </h3>
                                </div>

                                <div className="border-top pt-3">
                                    <div className="d-flex justify-content-between align-items-center mb-2">
                                        <span className="text-muted">Opening Balance:</span>
                                        <span className="fw-bold">
                                            {formatCurrency(account.opening_balance || 0)}
                                        </span>
                                    </div>

                                    {account.type === 'credit_card' && account.credit_limit > 0 && (
                                        <>
                                            <div className="d-flex justify-content-between align-items-center mb-2">
                                                <span className="text-muted">Credit Limit:</span>
                                                <span className="fw-bold">
                                                    {formatCurrency(account.credit_limit)}
                                                </span>
                                            </div>
                                            <div className="d-flex justify-content-between align-items-center">
                                                <span className="text-muted">Available Credit:</span>
                                                <span className="fw-bold text-success">
                                                    {formatCurrency(account.credit_limit + (account.current_balance || 0))}
                                                </span>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Quick Actions */}
                        <div className="card">
                            <div className="card-header">
                                <h5 className="card-title mb-0">
                                    <i className="fas fa-bolt me-2"></i>Quick Actions
                                </h5>
                            </div>
                            <div className="card-body">
                                <div className="d-grid gap-2">
                                    <Link
                                        href={`/transactions/create?account=${account.id}`}
                                        className="btn btn-outline-primary"
                                    >
                                        <i className="fas fa-plus me-2"></i>Add Transaction
                                    </Link>
                                    <Link
                                        href={`/transactions?account=${account.id}`}
                                        className="btn btn-outline-secondary"
                                    >
                                        <i className="fas fa-list me-2"></i>View Transactions
                                    </Link>
                                    <Link
                                        href={`/accounts/${account.id}/edit`}
                                        className="btn btn-outline-warning"
                                    >
                                        <i className="fas fa-edit me-2"></i>Edit Account
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </BootstrapLayout>
    );
}
