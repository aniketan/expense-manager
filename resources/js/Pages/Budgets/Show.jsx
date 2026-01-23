import React from 'react';
import { Head, Link } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';

export default function Show({ budget, transactions }) {
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

    return (
        <BootstrapLayout>
            <Head title={`Budget: ${budget.name}`} />

            <div className="container-fluid py-4">
                {/* Header */}
                <div className="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <Link href="/budgets" className="text-muted mb-2 d-inline-block">
                            <i className="fas fa-arrow-left me-2"></i>Back to Budgets
                        </Link>
                        <h1 className="h3 mb-0">{budget.name}</h1>
                    </div>
                    <div className="d-flex gap-2">
                        <Link
                            href={`/budgets/${budget.id}/edit`}
                            className="btn btn-primary"
                        >
                            <i className="fas fa-edit me-2"></i>Edit Budget
                        </Link>
                    </div>
                </div>

                <div className="row g-4">
                    {/* Budget Overview */}
                    <div className="col-md-4">
                        <div className="card h-100">
                            <div className="card-header">
                                <h5 className="mb-0">Budget Overview</h5>
                            </div>
                            <div className="card-body">
                                <div className="mb-3">
                                    <small className="text-muted">Category</small>
                                    <div className="fw-bold">{budget.category?.name}</div>
                                </div>

                                <div className="mb-3">
                                    <small className="text-muted">Period</small>
                                    <div className="small">
                                        {new Date(budget.start_date).toLocaleDateString()} - {new Date(budget.end_date).toLocaleDateString()}
                                    </div>
                                    <span className="badge bg-info text-capitalize mt-1">
                                        {budget.period_type}
                                    </span>
                                </div>

                                <div className="mb-3">
                                    <small className="text-muted">Status</small>
                                    <div>
                                        {budget.is_active && (
                                            <span className="badge bg-success">Active</span>
                                        )}
                                        {!budget.is_active && (
                                            <span className="badge bg-secondary">Inactive</span>
                                        )}
                                    </div>
                                </div>

                                {budget.notes && (
                                    <div className="mb-3">
                                        <small className="text-muted">Notes</small>
                                        <div className="small">{budget.notes}</div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Budget Progress */}
                    <div className="col-md-8">
                        <div className="card h-100">
                            <div className="card-header d-flex justify-content-between align-items-center">
                                <h5 className="mb-0">Spending Progress</h5>
                                <span className={`badge ${getStatusBadgeClass(budget.status)} fs-6`}>
                                    {budget.percentage_used}%
                                </span>
                            </div>
                            <div className="card-body">
                                <div className="mb-4">
                                    <div className="d-flex justify-content-between mb-2">
                                        <span className="text-muted">Spent</span>
                                        <span className="text-muted">Budget</span>
                                    </div>
                                    <div className="d-flex justify-content-between mb-2">
                                        <span className="h4 mb-0">₹{parseFloat(budget.spent_amount).toLocaleString()}</span>
                                        <span className="h4 mb-0">₹{parseFloat(budget.amount).toLocaleString()}</span>
                                    </div>
                                    <div className="progress" style={{ height: '25px' }}>
                                        <div
                                            className={`progress-bar ${getProgressBarClass(budget.percentage_used)}`}
                                            role="progressbar"
                                            style={{ width: `${Math.min(budget.percentage_used, 100)}%` }}
                                            aria-valuenow={budget.percentage_used}
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                        >
                                            {budget.percentage_used > 10 && `${budget.percentage_used}%`}
                                        </div>
                                    </div>
                                </div>

                                <div className="row text-center">
                                    <div className="col-4">
                                        <div className="border rounded p-3">
                                            <div className="text-muted small mb-1">Budget</div>
                                            <div className="h5 mb-0">₹{parseFloat(budget.amount).toLocaleString()}</div>
                                        </div>
                                    </div>
                                    <div className="col-4">
                                        <div className="border rounded p-3">
                                            <div className="text-muted small mb-1">Spent</div>
                                            <div className="h5 mb-0 text-danger">₹{parseFloat(budget.spent_amount).toLocaleString()}</div>
                                        </div>
                                    </div>
                                    <div className="col-4">
                                        <div className="border rounded p-3">
                                            <div className="text-muted small mb-1">Remaining</div>
                                            <div className="h5 mb-0 text-success">₹{parseFloat(budget.remaining_amount).toLocaleString()}</div>
                                        </div>
                                    </div>
                                </div>

                                {budget.percentage_used >= 100 && (
                                    <div className="alert alert-danger mt-3 mb-0">
                                        <i className="fas fa-exclamation-triangle me-2"></i>
                                        Budget exceeded! You've spent ₹{(parseFloat(budget.spent_amount) - parseFloat(budget.amount)).toLocaleString()} over budget.
                                    </div>
                                )}
                                {budget.percentage_used >= 80 && budget.percentage_used < 100 && (
                                    <div className="alert alert-warning mt-3 mb-0">
                                        <i className="fas fa-exclamation-circle me-2"></i>
                                        Approaching budget limit! Only ₹{parseFloat(budget.remaining_amount).toLocaleString()} remaining.
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Transactions */}
                    <div className="col-12">
                        <div className="card">
                            <div className="card-header">
                                <h5 className="mb-0">Related Transactions</h5>
                            </div>
                            <div className="card-body">
                                {transactions.length === 0 ? (
                                    <div className="text-center py-4 text-muted">
                                        <i className="fas fa-inbox fa-2x mb-2"></i>
                                        <p>No transactions found for this budget period.</p>
                                    </div>
                                ) : (
                                    <div className="table-responsive">
                                        <table className="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Description</th>
                                                    <th>Account</th>
                                                    <th className="text-end">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {transactions.map(transaction => (
                                                    <tr key={transaction.id}>
                                                        <td>{new Date(transaction.transaction_date).toLocaleDateString()}</td>
                                                        <td>{transaction.description || '-'}</td>
                                                        <td>{transaction.account?.name}</td>
                                                        <td className="text-end text-danger fw-bold">
                                                            ₹{parseFloat(transaction.amount).toLocaleString()}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colSpan="3" className="text-end fw-bold">Total:</td>
                                                    <td className="text-end fw-bold text-danger">
                                                        ₹{parseFloat(budget.spent_amount).toLocaleString()}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </BootstrapLayout>
    );
}
