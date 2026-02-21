import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';
import Pagination from '../../Components/Pagination';

export default function Index({ budgets = {}, categories = [], filters = {}, success, error }) {
    const budgetData = budgets.data || [];
    const paginationInfo = {
        current_page: budgets.current_page || 1,
        last_page: budgets.last_page || 1,
        per_page: budgets.per_page || 15,
        total: budgets.total || 0,
        from: budgets.from || 0,
        to: budgets.to || 0
    };

    const getStatusBadgeClass = (status) => {
        switch (status) {
            case 'success':
                return 'bg-success';
            case 'warning':
                return 'bg-warning';
            case 'danger':
                return 'bg-danger';
            default:
                return 'bg-secondary';
        }
    };

    const getProgressBarClass = (percentage) => {
        if (percentage >= 100) {
            return 'bg-danger';
        } else if (percentage >= 80) {
            return 'bg-warning';
        } else {
            return 'bg-success';
        }
    };

    const handleDelete = (budgetId) => {
        if (confirm('Are you sure you want to delete this budget?')) {
            router.delete(`/budgets/${budgetId}`);
        }
    };

    const handleToggleStatus = (budgetId) => {
        router.patch(`/budgets/${budgetId}/toggle-status`);
    };

    const handleFilter = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const params = {};
        
        for (let [key, value] of formData.entries()) {
            if (value) {
                params[key] = value;
            }
        }
        
        router.get('/budgets', params, { preserveState: true });
    };

    const clearFilters = () => {
        router.get('/budgets');
    };

    return (
        <BootstrapLayout>
            <Head title="Budgets" />

            <div className="container-fluid py-4">
                {/* Header */}
                <div className="d-flex justify-content-between align-items-center mb-4">
                    <h1 className="h3 mb-0">Budget Management</h1>
                    <Link
                        href="/budgets/create" 
                        className="btn btn-primary"
                    >
                        <i className="fas fa-plus me-2"></i>
                        Create Budget
                    </Link>
                </div>

                {/* Success/Error Messages */}
                {success && (
                    <div className="alert alert-success alert-dismissible fade show" role="alert">
                        {success}
                        <button type="button" className="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                )}
                {error && (
                    <div className="alert alert-danger alert-dismissible fade show" role="alert">
                        {error}
                        <button type="button" className="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                )}

                {/* Filters */}
                <div className="card mb-4">
                    <div className="card-body">
                        <form onSubmit={handleFilter}>
                            <div className="row g-3">
                                <div className="col-md-3">
                                    <label className="form-label">Category</label>
                                    <select
                                        name="category"
                                        className="form-select"
                                        defaultValue={filters.category || ''}
                                    >
                                        <option value="">All Categories</option>
                                        {categories.map(cat => (
                                            <option key={cat.id} value={cat.id}>
                                                {cat.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="col-md-3">
                                    <label className="form-label">Period Type</label>
                                    <select
                                        name="period_type"
                                        className="form-select"
                                        defaultValue={filters.period_type || ''}
                                    >
                                        <option value="">All Periods</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly">Yearly</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                </div>
                                <div className="col-md-3">
                                    <label className="form-label">Status</label>
                                    <select
                                        name="status"
                                        className="form-select"
                                        defaultValue={filters.status || ''}
                                    >
                                        <option value="">All Status</option>
                                        <option value="active">Active</option>
                                        <option value="current">Current Period</option>
                                    </select>
                                </div>
                                <div className="col-md-3 d-flex align-items-end gap-2">
                                    <button type="submit" className="btn btn-primary flex-grow-1">
                                        <i className="fas fa-filter me-2"></i>Filter
                                    </button>
                                    <button
                                        type="button"
                                        onClick={clearFilters}
                                        className="btn btn-secondary"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Budgets Grid */}
                {budgetData.length === 0 ? (
                    <div className="card">
                        <div className="card-body text-center py-5">
                            <i className="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                            <h5>No budgets found</h5>
                            <p className="text-muted">Create your first budget to start tracking your spending.</p>
                            <Link href="/budgets/create" className="btn btn-primary">
                                Create Budget
                            </Link>
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="row g-4 mb-4">
                            {budgetData.map(budget => (
                                <div key={budget.id} className="col-md-6 col-lg-4">
                                    <div className="card h-100">
                                        <div className="card-header d-flex justify-content-between align-items-center">
                                            <h5 className="card-title mb-0">{budget.name}</h5>
                                            <span className={`badge ${getStatusBadgeClass(budget.status)}`}>
                                                {budget.percentage_used}%
                                            </span>
                                        </div>
                                        <div className="card-body">
                                            <div className="mb-3">
                                                <small className="text-muted">Category</small>
                                                <div className="fw-bold">{budget.category?.name}</div>
                                            </div>

                                            <div className="mb-3">
                                                <div className="d-flex justify-content-between mb-1">
                                                    <small className="text-muted">Spent</small>
                                                    <small className="text-muted">Budget</small>
                                                </div>
                                                <div className="d-flex justify-content-between fw-bold">
                                                    <span>₹{parseFloat(budget.spent_amount).toLocaleString()}</span>
                                                    <span>₹{parseFloat(budget.amount).toLocaleString()}</span>
                                                </div>
                                                <div className="progress mt-2" style={{ height: '10px' }}>
                                                    <div
                                                        className={`progress-bar ${getProgressBarClass(budget.percentage_used)}`}
                                                        role="progressbar"
                                                        style={{ width: `${Math.min(budget.percentage_used, 100)}%` }}
                                                        aria-valuenow={budget.percentage_used}
                                                        aria-valuemin="0"
                                                        aria-valuemax="100"
                                                    ></div>
                                                </div>
                                            </div>

                                            <div className="mb-3">
                                                <small className="text-muted">Remaining</small>
                                                <div className="fw-bold text-success">
                                                    ₹{parseFloat(budget.remaining_amount).toLocaleString()}
                                                </div>
                                            </div>

                                            <div className="mb-3">
                                                <small className="text-muted">Period</small>
                                                <div className="small">
                                                    {new Date(budget.start_date).toLocaleDateString()} - {new Date(budget.end_date).toLocaleDateString()}
                                                </div>
                                                <span className="badge bg-info text-capitalize mt-1">
                                                    {budget.period_type}
                                                </span>
                                                {budget.is_active && (
                                                    <span className="badge bg-success ms-1">Active</span>
                                                )}
                                            </div>
                                        </div>
                                        <div className="card-footer bg-transparent">
                                            <div className="d-flex gap-2">
                                                <Link
                                                    href={`/budgets/${budget.id}`}
                                                    className="btn btn-sm btn-outline-primary flex-grow-1"
                                                >
                                                    <i className="fas fa-eye me-1"></i>View
                                                </Link>
                                                <Link 
                                                    href={`/budgets/${budget.id}/edit`}
                                                    className="btn btn-sm btn-outline-secondary"
                                                >
                                                    <i className="fas fa-edit"></i>
                                                </Link>
                                                <button
                                                    onClick={() => handleToggleStatus(budget.id)}
                                                    className="btn btn-sm btn-outline-warning"
                                                    title={budget.is_active ? 'Deactivate' : 'Activate'}
                                                >
                                                    <i className={`fas fa-${budget.is_active ? 'pause' : 'play'}`}></i>
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(budget.id)}
                                                    className="btn btn-sm btn-outline-danger"
                                                >
                                                    <i className="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Pagination */}
                        <Pagination
                            currentPage={paginationInfo.current_page}
                            lastPage={paginationInfo.last_page}
                            total={paginationInfo.total}
                            from={paginationInfo.from}
                            to={paginationInfo.to}
                            links={budgets.links}
                        />
                    </>
                )}
            </div>
        </BootstrapLayout>
    );
}
