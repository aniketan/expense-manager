import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';

export default function Index({ accounts, success }) {
    const handleDelete = (account) => {
        if (window.confirm(`Are you sure you want to delete ${account.account_name || account.name}?`)) {
            router.delete(`/accounts/${account.id}`);
        }
    };

    const handleToggleStatus = (account) => {
        router.patch(`/accounts/${account.id}/toggle-status`);
    };

    const getAccountTypeColor = (type) => {
        const colors = {
            savings: 'bg-success',
            current: 'bg-primary',
            credit_card: 'bg-warning',
            cash: 'bg-info',
            investment: 'bg-secondary'
        };
        return colors[type] || 'bg-dark';
    };

    const formatCurrency = (amount) => {
        return `₹${parseFloat(amount).toLocaleString('en-IN', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        })}`;
    };

    return (
        <BootstrapLayout>
            <Head title="Account Management" />
            
            {/* Header */}
            <div className="row">
                <div className="col-12">
                    <div className="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 className="h2 mb-1">
                                <i className="fas fa-university text-primary me-3"></i>
                                Account Management
                            </h1>
                            <small className="text-muted">
                                <i className="fas fa-info-circle me-1"></i>
                                Manage your bank accounts, credit cards, and cash accounts
                            </small>
                        </div>
                        <div>
                            <Link href="/accounts/create" className="btn btn-success">
                                <i className="fas fa-plus me-2"></i>Add New Account
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            {/* Success Message */}
            {success && (
                <div className="alert alert-success alert-dismissible fade show" role="alert">
                    {success}
                    <button type="button" className="btn-close" data-bs-dismiss="alert"></button>
                </div>
            )}

            {/* Summary Cards */}
            <div className="row mb-4">
                <div className="col-md-3">
                    <div className="card bg-primary text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between">
                                <div>
                                    <h6>Total Accounts</h6>
                                    <h3>{accounts.length}</h3>
                                </div>
                                <i className="fas fa-university fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="col-md-3">
                    <div className="card bg-success text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between">
                                <div>
                                    <h6>Active Accounts</h6>
                                    <h3>{accounts.filter(a => a.is_active).length}</h3>
                                </div>
                                <i className="fas fa-check-circle fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="col-md-3">
                    <div className="card bg-info text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between">
                                <div>
                                    <h6>Total Balance</h6>
                                    <h3>
                                        {formatCurrency(accounts.reduce((sum, acc) => sum + parseFloat(acc.current_balance || 0), 0))}
                                    </h3>
                                </div>
                                <i className="fas fa-wallet fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="col-md-3">
                    <div className="card bg-warning text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between">
                                <div>
                                    <h6>Credit Cards</h6>
                                    <h3>{accounts.filter(a => a.account_type === 'credit_card').length}</h3>
                                </div>
                                <i className="fas fa-credit-card fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Accounts Grid */}
            <div className="row">
                <div className="col-12">
                    {accounts.length === 0 ? (
                        <div className="card">
                            <div className="card-body text-center py-5">
                                <i className="fas fa-university fa-4x text-muted mb-4"></i>
                                <h4>No accounts yet</h4>
                                <p className="text-muted mb-4">Get started by creating your first account</p>
                                <Link href="/accounts/create" className="btn btn-primary">
                                    <i className="fas fa-plus me-2"></i>Add New Account
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <div className="row">
                            {accounts.map((account) => (
                                <div key={account.id} className="col-lg-4 col-md-6 mb-4">
                                    <div className="card h-100">
                                        <div className="card-header d-flex justify-content-between align-items-center">
                                            <div className="d-flex align-items-center">
                                                <h5 className="card-title mb-0 me-2">
                                                    {account.account_name || account.name}
                                                </h5>
                                                <span className={`badge ${getAccountTypeColor(account.account_type || account.type)} text-white`}>
                                                    {account.type_label || account.account_type || account.type}
                                                </span>
                                            </div>
                                            <span className={`badge ${account.is_active ? 'bg-success' : 'bg-danger'}`}>
                                                {account.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </div>
                                        
                                        <div className="card-body">
                                            <div className="mb-3">
                                                <small className="text-muted d-block">Account Code</small>
                                                <code className="text-primary">{account.account_code || account.code}</code>
                                            </div>
                                            
                                            {account.bank_name && (
                                                <div className="mb-3">
                                                    <small className="text-muted d-block">Bank Name</small>
                                                    <span>{account.bank_name}</span>
                                                </div>
                                            )}
                                            
                                            {account.account_number && (
                                                <div className="mb-3">
                                                    <small className="text-muted d-block">Account Number</small>
                                                    <span className="font-monospace">{account.account_number}</span>
                                                </div>
                                            )}

                                            {/* Balance Information */}
                                            <div className="border-top pt-3">
                                                <div className="d-flex justify-content-between align-items-center mb-2">
                                                    <small className="text-muted">Current Balance:</small>
                                                    <span className={`fw-bold ${
                                                        (account.current_balance || 0) >= 0 ? 'text-success' : 'text-danger'
                                                    }`}>
                                                        {formatCurrency(account.current_balance || 0)}
                                                    </span>
                                                </div>
                                                
                                                {(account.account_type === 'credit_card' || account.type === 'credit_card') && account.credit_limit > 0 && (
                                                    <div className="d-flex justify-content-between align-items-center">
                                                        <small className="text-muted">Credit Limit:</small>
                                                        <span className="text-muted">
                                                            {formatCurrency(account.credit_limit)}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {/* Actions */}
                                        <div className="card-footer">
                                            <div className="btn-group w-100" role="group">
                                                <Link
                                                    href={`/accounts/${account.id}`}
                                                    className="btn btn-outline-secondary btn-sm"
                                                    title="View Details"
                                                >
                                                    <i className="fas fa-eye"></i>
                                                </Link>
                                                <Link
                                                    href={`/accounts/${account.id}/edit`}
                                                    className="btn btn-outline-warning btn-sm"
                                                    title="Edit Account"
                                                >
                                                    <i className="fas fa-edit"></i>
                                                </Link>
                                                <button
                                                    onClick={() => handleToggleStatus(account)}
                                                    className={`btn btn-sm ${
                                                        account.is_active
                                                            ? 'btn-outline-warning'
                                                            : 'btn-outline-success'
                                                    }`}
                                                    title={account.is_active ? 'Disable Account' : 'Enable Account'}
                                                >
                                                    <i className={`fas ${account.is_active ? 'fa-pause' : 'fa-play'}`}></i>
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(account)}
                                                    className="btn btn-outline-danger btn-sm"
                                                    title="Delete Account"
                                                >
                                                    <i className="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </BootstrapLayout>
    );
}
