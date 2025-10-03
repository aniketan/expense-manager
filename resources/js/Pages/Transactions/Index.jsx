import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';
import Pagination from '../../Components/Pagination';

export default function Index({ transactions = {}, categories = [], accounts = [], success, filters = {} }) {
    const [filtersCollapsed, setFiltersCollapsed] = useState(false);
    const [selectedTransactions, setSelectedTransactions] = useState([]);
    const [selectAll, setSelectAll] = useState(false);
    
    // Extract data from paginated response
    const transactionData = transactions.data || [];
    const paginationInfo = {
        current_page: transactions.current_page || 1,
        last_page: transactions.last_page || 1,
        per_page: transactions.per_page || 15,
        total: transactions.total || 0,
        from: transactions.from || 0,
        to: transactions.to || 0
    };

    const paymentMethods = ['UPI', 'Bank Transfer', 'Credit Card', 'Debit Card', 'Cheque'];
    const statuses = ['Cleared', 'Pending', 'Imported'];

    const handleSelectAll = () => {
        if (selectAll) {
            setSelectedTransactions([]);
        } else {
            setSelectedTransactions(transactionData.map(t => t.id));
        }
        setSelectAll(!selectAll);
    };

    const handleTransactionSelect = (id) => {
        if (selectedTransactions.includes(id)) {
            setSelectedTransactions(selectedTransactions.filter(tid => tid !== id));
        } else {
            setSelectedTransactions([...selectedTransactions, id]);
        }
    };

    const deleteSelected = () => {
        if (selectedTransactions.length === 0) {
            alert('Please select transactions to delete.');
            return;
        }
        if (confirm(`Are you sure you want to delete ${selectedTransactions.length} transaction(s)?`)) {
            // Implementation for bulk delete
            console.log('Deleting transactions:', selectedTransactions);
        }
    };

    return (
        <BootstrapLayout>
            <Head title="Transaction Management" />
            
            {/* Header */}
            <div className="row">
                <div className="col-12">
                    <div className="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1><i className="fas fa-exchange-alt text-primary me-3"></i>Transaction Management</h1>
                            <small className="text-muted">
                                <i className="fas fa-info-circle me-1"></i>
                                Categories: 
                                <span className="badge bg-success me-1">Income</span>
                                <span className="badge bg-info me-1">₹1-99</span>
                                <span className="badge bg-warning me-1">₹100-499</span>
                                <span className="badge bg-orange me-1">₹500-999</span>
                                <span className="badge bg-danger me-1">₹1K-1.9K</span>
                                <span className="badge bg-dark me-1">₹2K-4.9K</span>
                                <span className="badge bg-secondary me-1">₹5K+</span>
                            </small>
                        </div>
                        <div>
                            <Link href="/transactions/create" className="btn btn-success">
                                <i className="fas fa-plus me-2"></i>Add Transaction
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

            {/* Filters and Search */}
            <div className="row mb-4">
                <div className="col-12">
                    <div className="card">
                        <div className="card-header">
                            <h5 className="mb-0">
                                <i className="fas fa-filter me-2"></i>Search & Filters
                                <button 
                                    className="btn btn-sm btn-outline-secondary float-end" 
                                    type="button"
                                    onClick={() => setFiltersCollapsed(!filtersCollapsed)}
                                >
                                    <i className={`fas fa-chevron-${filtersCollapsed ? 'down' : 'up'}`}></i>
                                </button>
                            </h5>
                        </div>
                        <div className={`collapse ${!filtersCollapsed ? 'show' : ''}`}>
                            <div className="card-body">
                                <form method="GET" action="/transactions">
                                    <div className="row">
                                        <div className="col-md-3">
                                            <label htmlFor="search" className="form-label">Search Description/Notes/Payee</label>
                                            <input 
                                                type="text" 
                                                className="form-control" 
                                                id="search" 
                                                name="search" 
                                                defaultValue={filters.search || ''} 
                                                placeholder="Search descriptions, notes, or payee names..."
                                            />
                                        </div>
                                        <div className="col-md-2">
                                            <label htmlFor="category" className="form-label">Category</label>
                                            <select className="form-select" id="category" name="category">
                                                <option value="">All Categories</option>
                                                {categories.map(cat => (
                                                    <option key={cat.id} value={cat.id} selected={filters.category === cat.id}>
                                                        {cat.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="col-md-2">
                                            <label htmlFor="subcategory" className="form-label">Subcategory</label>
                                            <select className="form-select" id="subcategory" name="subcategory">
                                                <option value="">All Subcategories</option>
                                            </select>
                                        </div>
                                        <div className="col-md-2">
                                            <label htmlFor="account" className="form-label">Account</label>
                                            <select className="form-select" id="account" name="account">
                                                <option value="">All Accounts</option>
                                                {accounts.map(account => (
                                                    <option key={account.id} value={account.id} selected={filters.account == account.id}>
                                                        {account.account_name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="col-md-2">
                                            <label htmlFor="date_from" className="form-label">From Date</label>
                                            <input 
                                                type="date" 
                                                className="form-control" 
                                                id="date_from" 
                                                name="date_from" 
                                                defaultValue={filters.date_from || ''}
                                            />
                                        </div>
                                        <div className="col-md-1 d-flex align-items-end">
                                            <button type="submit" className="btn btn-primary w-100">
                                                <i className="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div className="row mt-3">
                                        <div className="col-md-2">
                                            <label htmlFor="sort_by" className="form-label">Sort By</label>
                                            <select className="form-select" id="sort_by" name="sort_by">
                                                <option value="date_desc" selected={filters.sort_by === 'date_desc'}>Date (Newest First)</option>
                                                <option value="date_asc" selected={filters.sort_by === 'date_asc'}>Date (Oldest First)</option>
                                                <option value="amount_desc" selected={filters.sort_by === 'amount_desc'}>Amount (High to Low)</option>
                                                <option value="amount_asc" selected={filters.sort_by === 'amount_asc'}>Amount (Low to High)</option>
                                                <option value="category" selected={filters.sort_by === 'category'}>Category (A-Z)</option>
                                            </select>
                                        </div>
                                        <div className="col-md-2">
                                            <label htmlFor="date_to" className="form-label">To Date</label>
                                            <input 
                                                type="date" 
                                                className="form-control" 
                                                id="date_to" 
                                                name="date_to" 
                                                defaultValue={filters.date_to || ''}
                                            />
                                        </div>
                                        <div className="col-md-2">
                                            <label htmlFor="payment_method" className="form-label">Payment Method</label>
                                            <select className="form-select" id="payment_method" name="payment_method">
                                                <option value="">All Methods</option>
                                                {paymentMethods.map(method => (
                                                    <option key={method} value={method} selected={filters.payment_method === method}>
                                                        {method}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="col-md-2">
                                            <label htmlFor="status" className="form-label">Status</label>
                                            <select className="form-select" id="status" name="status">
                                                <option value="">All Status</option>
                                                {statuses.map(status => (
                                                    <option key={status} value={status} selected={filters.status === status}>
                                                        {status}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="col-md-2">
                                            <label htmlFor="tags" className="form-label">Tags</label>
                                            <input 
                                                type="text" 
                                                className="form-control" 
                                                id="tags" 
                                                name="tags" 
                                                defaultValue={filters.tags || ''} 
                                                placeholder="comma,separated"
                                            />
                                        </div>
                                        <div className="col-md-1 d-flex align-items-end">
                                            <button type="submit" className="btn btn-primary w-100">
                                                <i className="fas fa-search"></i>
                                            </button>
                                        </div>
                                        <div className="col-md-1 d-flex align-items-end">
                                            <Link href="/transactions" className="btn btn-outline-secondary w-100">
                                                <i className="fas fa-times"></i>
                                            </Link>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Summary Cards */}
            <div className="row mb-4">
                <div className="col-md-3">
                    <div className="card bg-primary text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between">
                                <div>
                                    <h6>Total Transactions</h6>
                                    <h3>{paginationInfo.total || 0}</h3>
                                </div>
                                <i className="fas fa-list fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="col-md-3">
                    <div className="card bg-success text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between">
                                <div>
                                    <h6>Total Income</h6>
                                    <h3>₹{transactionData.filter(t => t.transaction_type === 'income').reduce((sum, t) => sum + parseFloat(t.amount || 0), 0).toFixed(2)}</h3>
                                </div>
                                <i className="fas fa-arrow-up fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="col-md-3">
                    <div className="card bg-danger text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between">
                                <div>
                                    <h6>Total Expenses</h6>
                                    <h3>₹{transactionData.filter(t => t.transaction_type === 'expense').reduce((sum, t) => sum + parseFloat(t.amount || 0), 0).toFixed(2)}</h3>
                                </div>
                                <i className="fas fa-arrow-down fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="col-md-3">
                    <div className="card bg-info text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between">
                                <div>
                                    <h6>Net Balance</h6>
                                    <h3 className="text-light">
                                        ₹{(
                                            transactionData.filter(t => t.transaction_type === 'income').reduce((sum, t) => sum + parseFloat(t.amount || 0), 0) -
                                            transactionData.filter(t => t.transaction_type === 'expense').reduce((sum, t) => sum + parseFloat(t.amount || 0), 0)
                                        ).toFixed(2)}
                                    </h3>
                                </div>
                                <i className="fas fa-balance-scale fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Transactions Table */}
            <div className="row">
                <div className="col-12">
                    <div className="card">
                        <div className="card-header d-flex justify-content-between align-items-center">
                            <h5 className="mb-0">
                                <i className="fas fa-table me-2"></i>Transactions
                            </h5>
                            <div className="btn-group" role="group">
                                <button type="button" className="btn btn-sm btn-outline-primary" onClick={handleSelectAll}>
                                    <i className="fas fa-check-square me-1"></i>Select All
                                </button>
                                <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => {setSelectedTransactions([]); setSelectAll(false);}}>
                                    <i className="fas fa-square me-1"></i>Clear All
                                </button>
                                <button type="button" className="btn btn-sm btn-outline-danger" onClick={deleteSelected}>
                                    <i className="fas fa-trash me-1"></i>Delete Selected
                                </button>
                            </div>
                        </div>
                        <div className="card-body p-0">
                            {transactionData.length === 0 ? (
                                <div className="text-center py-5">
                                    <i className="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h5>No transactions found</h5>
                                    <p className="text-muted">Try adjusting your search filters or add a new transaction.</p>
                                    <Link href="/transactions/create" className="btn btn-primary">
                                        <i className="fas fa-plus me-2"></i>Add First Transaction
                                    </Link>
                                </div>
                            ) : (
                                <div className="table-responsive">
                                    <table className="table table-hover mb-0">
                                        <thead className="table-light">
                                            <tr>
                                                <th>
                                                    <input 
                                                        type="checkbox" 
                                                        className="form-check-input" 
                                                        checked={selectAll}
                                                        onChange={handleSelectAll}
                                                    />
                                                </th>
                                                <th>Date</th>
                                                <th>Description</th>
                                                <th>Category</th>
                                                <th>Account</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {transactionData.map(transaction => (
                                                <tr key={transaction.id}>
                                                    <td>
                                                        <input 
                                                            type="checkbox" 
                                                            className="form-check-input transaction-checkbox" 
                                                            value={transaction.id}
                                                            checked={selectedTransactions.includes(transaction.id)}
                                                            onChange={() => handleTransactionSelect(transaction.id)}
                                                        />
                                                    </td>
                                                    <td>{transaction.transaction_date}</td>
                                                    <td>{transaction.description}</td>
                                                    <td>
                                                        <span className={`badge ${transaction.transaction_type === 'income' ? 'bg-success' : 'bg-danger'}`}>
                                                            {transaction.category?.name || 'N/A'}
                                                        </span>
                                                    </td>
                                                    <td>{transaction.account?.account_name || 'N/A'}</td>
                                                    <td>
                                                        <span className={`fw-bold ${transaction.transaction_type === 'income' ? 'text-success' : 'text-danger'}`}>
                                                            {transaction.transaction_type === 'income' ? '+' : '-'}₹{parseFloat(transaction.amount || 0).toFixed(2)}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span className="badge bg-success">
                                                            {transaction.status || 'Cleared'}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div className="btn-group btn-group-sm">
                                                            <button 
                                                                type="button" 
                                                                className="btn btn-outline-primary btn-sm"
                                                                title="View Details"
                                                            >
                                                                <i className="fas fa-eye"></i>
                                                            </button>
                                                            <Link 
                                                                href={`/transactions/${transaction.id}/edit`} 
                                                                className="btn btn-outline-warning btn-sm"
                                                                title="Edit"
                                                            >
                                                                <i className="fas fa-edit"></i>
                                                            </Link>
                                                            <button 
                                                                type="button" 
                                                                className="btn btn-outline-danger btn-sm"
                                                                title="Delete"
                                                                onClick={() => {
                                                                    if (confirm('Are you sure you want to delete this transaction?')) {
                                                                        router.delete(`/transactions/${transaction.id}`);
                                                                    }
                                                                }}
                                                            >
                                                                <i className="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                                
                            {/* Pagination */}
                            <Pagination 
                                paginationData={paginationInfo}
                                onPerPageChange={(e) => {
                                    router.get('/transactions', { per_page: e.target.value }, { 
                                        preserveState: true, 
                                        preserveScroll: true 
                                    });
                                }}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </BootstrapLayout>
    );
}