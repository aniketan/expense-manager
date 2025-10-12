import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';

export default function Show({ transaction }) {
    // Debug: Check transaction data
    console.log('Transaction data:', transaction);
    
    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR'
        }).format(amount);
    };

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleDateString('en-IN', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };

    return (
        <BootstrapLayout>
            <Head title={`Transaction #${transaction.id}`} />
            
            <div className="container-fluid">
                {/* Header */}
                <div className="row mb-4">
                    <div className="col-12">
                        <div className="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 className="mb-1">
                                    <i className="fas fa-receipt me-2"></i>
                                    Transaction Details
                                </h2>
                                <p className="text-muted mb-0">Transaction ID: #{transaction.id}</p>
                            </div>
                            <div>
                                <Link 
                                    href="/transactions" 
                                    className="btn btn-outline-secondary me-2"
                                >
                                    <i className="fas fa-arrow-left me-2"></i>Back to Transactions
                                </Link>
                                <Link 
                                    href={`/transactions/${transaction.id}/edit`} 
                                    className="btn btn-primary"
                                >
                                    <i className="fas fa-edit me-2"></i>Edit Transaction
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Transaction Details Card */}
                <div className="row">
                    <div className="col-md-8">
                        <div className="card">
                            <div className="card-header">
                                <h5 className="mb-0">
                                    <i className="fas fa-info-circle me-2"></i>Transaction Information
                                </h5>
                            </div>
                            <div className="card-body">
                                <div className="row">
                                    <div className="col-md-6">
                                        <div className="mb-3">
                                            <label className="form-label text-muted">Transaction Type</label>
                                            <div>
                                                <span className={`badge fs-6 ${
                                                    transaction.transaction_type === 'expense' ? 'bg-danger' :
                                                    transaction.transaction_type === 'income' ? 'bg-success' : 'bg-info'
                                                }`}>
                                                    <i className={`fas ${
                                                        transaction.transaction_type === 'expense' ? 'fa-arrow-down' :
                                                        transaction.transaction_type === 'income' ? 'fa-arrow-up' : 'fa-exchange-alt'
                                                    } me-1`}></i>
                                                    {transaction.transaction_type?.charAt(0).toUpperCase() + transaction.transaction_type?.slice(1)}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="mb-3">
                                            <label className="form-label text-muted">Amount</label>
                                            <div>
                                                <h4 className={`mb-0 ${
                                                    transaction.transaction_type === 'expense' || transaction.amount < 0 
                                                        ? 'text-danger' 
                                                        : 'text-success'
                                                }`}>
                                                    {formatCurrency(Math.abs(transaction.amount))}
                                                </h4>
                                            </div>
                                        </div>

                                        <div className="mb-3">
                                            <label className="form-label text-muted">Date</label>
                                            <div>
                                                <i className="fas fa-calendar me-2"></i>
                                                {formatDate(transaction.transaction_date)}
                                            </div>
                                        </div>

                                        <div className="mb-3">
                                            <label className="form-label text-muted">Account</label>
                                            <div>
                                                <i className="fas fa-university me-2"></i>
                                                {transaction.account?.account_name || 'Unknown Account'}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="col-md-6">
                                        <div className="mb-3">
                                            <label className="form-label text-muted">Category</label>
                                            <div>
                                                <span className="badge bg-light text-dark fs-6">
                                                    <i className="fas fa-tag me-1"></i>
                                                    {transaction.category.parent?.name || 'Uncategorized'}
                                                </span>
                                            </div>
                                        </div>

                                      
                                        <div className="mb-3">
                                            <label className="form-label text-muted">Sub Category</label>
                                            <div>
                                                <span className="badge bg-light text-dark fs-6">
                                                    <i className="fas fa-tag me-1"></i>
                                                    {transaction.category?.name || 'Uncategorized'}
                                                </span>
                                            </div>
                                        </div>

                                        {transaction.payment_method && (
                                            <div className="mb-3">
                                                <label className="form-label text-muted">Payment Method</label>
                                                <div>
                                                    <i className="fas fa-credit-card me-2"></i>
                                                    {transaction.payment_method}
                                                </div>
                                            </div>
                                        )}

                                        {transaction.reference_number && (
                                            <div className="mb-3">
                                                <label className="form-label text-muted">Reference Number</label>
                                                <div>
                                                    <i className="fas fa-hashtag me-2"></i>
                                                    <code>{transaction.reference_number}</code>
                                                </div>
                                            </div>
                                        )}

                                        {transaction.location && (
                                            <div className="mb-3">
                                                <label className="form-label text-muted">Location</label>
                                                <div>
                                                    <i className="fas fa-map-marker-alt me-2"></i>
                                                    {transaction.location}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {transaction.description && (
                                    <div className="mb-3">
                                        <label className="form-label text-muted">Description</label>
                                        <div className="p-3 bg-light rounded">
                                            <i className="fas fa-comment me-2"></i>
                                            {transaction.description}
                                        </div>
                                    </div>
                                )}

                                {transaction.tags && (
                                    <div className="mb-3">
                                        <label className="form-label text-muted">Tags</label>
                                        <div>
                                            {transaction.tags.split(',').map((tag, index) => (
                                                <span key={index} className="badge bg-secondary me-1">
                                                    <i className="fas fa-tag me-1"></i>
                                                    {tag.trim()}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Side Panel */}
                    <div className="col-md-4">
                        <div className="card">
                            <div className="card-header">
                                <h6 className="mb-0">
                                    <i className="fas fa-cog me-2"></i>Actions
                                </h6>
                            </div>
                            <div className="card-body">
                                <div className="d-grid gap-2">
                                    <Link 
                                        href={`/transactions/${transaction.id}/edit`} 
                                        className="btn btn-primary"
                                    >
                                        <i className="fas fa-edit me-2"></i>Edit Transaction
                                    </Link>
                                    
                                    <button 
                                        className="btn btn-outline-danger"
                                        onClick={() => {
                                            if (confirm('Are you sure you want to delete this transaction?')) {
                                                // Handle delete
                                                router.delete(`/transactions/${transaction.id}`);
                                            }
                                        }}
                                    >
                                        <i className="fas fa-trash me-2"></i>Delete Transaction
                                    </button>

                                    <hr />
                                    
                                    <Link 
                                        href="/transactions/create" 
                                        className="btn btn-success"
                                    >
                                        <i className="fas fa-plus me-2"></i>Add New Transaction
                                    </Link>
                                </div>
                            </div>
                        </div>

                        {/* Transaction Metadata */}
                        <div className="card mt-3">
                            <div className="card-header">
                                <h6 className="mb-0">
                                    <i className="fas fa-info me-2"></i>Metadata
                                </h6>
                            </div>
                            <div className="card-body">
                                <small className="text-muted">
                                    <div className="mb-2">
                                        <strong>Created:</strong><br />
                                        {new Date(transaction.created_at).toLocaleString('en-IN')}
                                    </div>
                                    {transaction.updated_at !== transaction.created_at && (
                                        <div>
                                            <strong>Last Updated:</strong><br />
                                            {new Date(transaction.updated_at).toLocaleString('en-IN')}
                                        </div>
                                    )}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </BootstrapLayout>
    );
}