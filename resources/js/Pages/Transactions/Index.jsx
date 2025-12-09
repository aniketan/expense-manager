import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';
import Pagination from '../../Components/Pagination';

export default function Index({ transactions = {}, categories = [], accounts = [], success, filters = {}, totals = {} }) {
    const [filtersCollapsed, setFiltersCollapsed] = useState(false);
    const [selectedTransactions, setSelectedTransactions] = useState([]);
    const [selectAll, setSelectAll] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState(filters.category || '');
    const [subcategories, setSubcategories] = useState([]);

    // Function to get color class based on amount value
    const getAmountColorClass = (amount, transactionType) => {
        const value = parseFloat(amount || 0);

        // Base color for income vs expense
        const baseColor = transactionType === 'income' ? 'text-success' : 'text-danger';

        // Amount-based color coding (based on absolute value)
        const absValue = Math.abs(value);

        if (absValue < 100) {
            return 'text-info'; // ₹1-99 - Blue
        } else if (absValue < 500) {
            return 'text-warning'; // ₹100-499 - Yellow
        } else if (absValue < 1000) {
            return 'text-orange'; // ₹500-999 - Orange (need custom CSS)
        } else if (absValue < 2000) {
            return 'text-danger'; // ₹1K-1.9K - Red
        } else if (absValue < 5000) {
            return 'text-dark'; // ₹2K-4.9K - Dark
        } else {
            return 'text-secondary'; // ₹5K+ - Gray
        }
    };

    // Get parent categories (where parent_id is null)
    const parentCategories = categories.filter(cat => cat.parent_id === null);

    // Function to get subcategories for a parent category
    const getSubcategories = (parentCategoryId) => {
        return categories.filter(cat => cat.parent_id === parseInt(parentCategoryId));
    };

    // Handle category change and update subcategories
    const handleCategoryChange = (e) => {
        const categoryId = e.target.value;
        setSelectedCategory(categoryId);

        if (categoryId) {
            const subs = getSubcategories(categoryId);
            setSubcategories(subs);
        } else {
            setSubcategories([]);
        }

        // Reset subcategory selection when category changes
        const subcategorySelect = document.getElementById('subcategory');
        if (subcategorySelect) {
            subcategorySelect.value = '';
        }
    };

    // Initialize subcategories on component mount if category is already selected
    useEffect(() => {
        if (selectedCategory && categories.length > 0) {
            const subs = getSubcategories(selectedCategory);
            setSubcategories(subs);
        }
    }, [selectedCategory, categories]);

    // Initialize category and subcategories from filters on mount
    useEffect(() => {
        if (filters.category && categories.length > 0) {
            setSelectedCategory(filters.category);
            const subs = getSubcategories(filters.category);
            setSubcategories(subs);
        }
    }, [filters.category, categories]);

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

    const handleFilterSubmit = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const filterParams = {};

        for (let [key, value] of formData.entries()) {
            if (value && value.trim() !== '') {
                filterParams[key] = value;
            }
        }

        router.get('/transactions', filterParams, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        router.get('/transactions', {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const deleteSelected = () => {
        if (selectedTransactions.length === 0) {
            alert('Please select transactions to delete.');
            return;
        }
        if (confirm(`Are you sure you want to delete ${selectedTransactions.length} transaction(s)?`)) {
            router.post('/transactions/bulk-destroy', {
                ids: selectedTransactions
            }, {
                onSuccess: () => {
                    setSelectedTransactions([]);
                    setSelectAll(false);
                }
            });
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
                                <form onSubmit={handleFilterSubmit}>
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
                                            <select
                                                className="form-select"
                                                id="category"
                                                name="category"
                                                value={selectedCategory}
                                                onChange={handleCategoryChange}
                                            >
                                                <option value="">All Categories</option>
                                                {parentCategories.map(cat => (
                                                    <option key={cat.id} value={cat.id}>
                                                        {cat.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="col-md-2">
                                            <label htmlFor="subcategory" className="form-label">Subcategory</label>
                                            <select
                                                className="form-select"
                                                id="subcategory"
                                                name="subcategory"
                                                defaultValue={filters.subcategory || ''}
                                            >
                                                <option value="">All Subcategories</option>
                                                {subcategories.map(subcat => (
                                                    <option key={subcat.id} value={subcat.id}>
                                                        {subcat.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="col-md-2">
                                            <label htmlFor="account" className="form-label">Account</label>
                                            <select className="form-select" id="account" name="account" defaultValue={filters.account || ''}>
                                                <option value="">All Accounts</option>
                                                {accounts.map(account => (
                                                    <option key={account.id} value={account.id}>
                                                        {account.name}
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
                                            <select className="form-select" id="sort_by" name="sort_by" defaultValue={filters.sort_by || 'date_desc'}>
                                                <option value="date_desc">Date (Newest First)</option>
                                                <option value="date_asc">Date (Oldest First)</option>
                                                <option value="amount_desc">Amount (High to Low)</option>
                                                <option value="amount_asc">Amount (Low to High)</option>
                                                <option value="category">Category (A-Z)</option>
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
                                            <select className="form-select" id="payment_method" name="payment_method" defaultValue={filters.payment_method || ''}>
                                                <option value="">All Methods</option>
                                                {paymentMethods.map(method => (
                                                    <option key={method} value={method}>
                                                        {method}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="col-md-2">
                                            <label htmlFor="status" className="form-label">Status</label>
                                            <select className="form-select" id="status" name="status" defaultValue={filters.status || ''}>
                                                <option value="">All Status</option>
                                                {statuses.map(status => (
                                                    <option key={status} value={status}>
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
                                            <button
                                                type="button"
                                                onClick={clearFilters}
                                                className="btn btn-outline-secondary w-100"
                                            >
                                                <i className="fas fa-times"></i>
                                            </button>
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
                                    <h3>₹{parseFloat(totals.total_income || 0).toFixed(2)}</h3>
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
                                    <h3>₹{parseFloat(totals.total_expenses || 0).toFixed(2)}</h3>
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
                                        ₹{parseFloat(totals.net_balance || 0).toFixed(2)}
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
                                                    <td>{new Date(transaction.transaction_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</td>
                                                    <td>{transaction.description}</td>
                                                    <td>
                                                        <span className={`badge ${transaction.transaction_type === 'income' ? 'bg-success' : 'bg-danger'}`}>
                                                            {transaction.category?.name || 'N/A'}
                                                        </span>
                                                    </td>
                                                    <td>{transaction.account?.name || 'N/A'}</td>
                                                    <td>
                                                        <span className={`fw-bold ${getAmountColorClass(transaction.amount, transaction.transaction_type)}`}>
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
                                                            <Link
                                                                href={`/transactions/${transaction.id}`}
                                                                className="btn btn-outline-primary btn-sm"
                                                                title="View Details"
                                                                aria-label="View Details"
                                                            >
                                                                <i className="fas fa-eye"></i>
                                                            </Link>
                                                            <Link
                                                                href={`/transactions/${transaction.id}/edit`}
                                                                className="btn btn-outline-warning btn-sm"
                                                                title="Edit"
                                                                aria-label="Edit"
                                                            >
                                                                <i className="fas fa-edit"></i>
                                                            </Link>
                                                            <button
                                                                type="button"
                                                                className="btn btn-outline-danger btn-sm"
                                                                title="Delete"
                                                                aria-label="Delete"
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
                                    router.get('/transactions', {
                                        ...filters,
                                        per_page: e.target.value
                                    }, {
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
