import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';

export default function Show({ category, transactions, stats }) {
    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR'
        }).format(amount);
    };

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    };

    const getAmountColorClass = (amount) => {
        const absAmount = Math.abs(amount);
        if (absAmount < 100) return 'text-info';
        if (absAmount < 500) return 'text-warning';
        if (absAmount < 1000) return 'text-orange';
        if (absAmount < 2000) return 'text-danger';
        if (absAmount < 5000) return 'text-dark';
        return 'text-secondary';
    };

    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete the category "${category.name}"? This action cannot be undone.`)) {
            router.delete(`/categories/${category.id}`);
        }
    };

    return (
        <BootstrapLayout>
            <Head title={`Category: ${category.name}`} />

            <div className="container-fluid">
                {/* Header */}
                <div className="row mb-4">
                    <div className="col-12">
                        <div className="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 className="mb-1">
                                    <i className={`${category.icon || 'fas fa-folder'} me-2`} style={{ color: category.color }}></i>
                                    {category.name}
                                </h2>
                                <p className="text-muted mb-0">
                                    {category.parent ? (
                                        <>
                                            <i className={`${category.parent.icon || 'fas fa-folder'} me-1`} style={{ color: category.parent.color }}></i>
                                            {category.parent.name} <i className="fas fa-chevron-right mx-1"></i> {category.name}
                                        </>
                                    ) : (
                                        'Parent Category'
                                    )}
                                </p>
                            </div>
                            <div>
                                <Link
                                    href="/categories"
                                    className="btn btn-outline-secondary me-2"
                                >
                                    <i className="fas fa-arrow-left me-2"></i>Back to Categories
                                </Link>
                                <Link
                                    href={`/categories/${category.id}/edit`}
                                    className="btn btn-primary"
                                >
                                    <i className="fas fa-edit me-2"></i>Edit Category
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Statistics Cards */}
                <div className="row mb-4">
                    <div className="col-md-6">
                        <div className="card">
                            <div className="card-body">
                                <div className="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 className="text-muted mb-1">Total Amount</h6>
                                        <h3 className="mb-0 text-primary">
                                            {formatCurrency(stats.total_amount || 0)}
                                        </h3>
                                    </div>
                                    <div className="bg-primary bg-opacity-10 p-3 rounded">
                                        <i className="fas fa-rupee-sign fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="col-md-6">
                        <div className="card">
                            <div className="card-body">
                                <div className="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 className="text-muted mb-1">Total Transactions</h6>
                                        <h3 className="mb-0 text-success">
                                            {stats.transaction_count || 0}
                                        </h3>
                                    </div>
                                    <div className="bg-success bg-opacity-10 p-3 rounded">
                                        <i className="fas fa-receipt fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="row">
                    {/* Main Content */}
                    <div className="col-md-8">
                        {/* Category Details */}
                        <div className="card mb-4">
                            <div className="card-header">
                                <h5 className="mb-0">
                                    <i className="fas fa-info-circle me-2"></i>Category Information
                                </h5>
                            </div>
                            <div className="card-body">
                                <div className="row">
                                    <div className="col-md-6">
                                        <div className="mb-3">
                                            <label className="form-label text-muted">Category Name</label>
                                            <div>
                                                <h5 className="mb-0">{category.name}</h5>
                                            </div>
                                        </div>

                                        <div className="mb-3">
                                            <label className="form-label text-muted">Category Code</label>
                                            <div>
                                                <code className="fs-6">{category.code}</code>
                                            </div>
                                        </div>

                                        {category.parent && (
                                            <div className="mb-3">
                                                <label className="form-label text-muted">Parent Category</label>
                                                <div>
                                                    <Link
                                                        href={`/categories/${category.parent.id}`}
                                                        className="text-decoration-none"
                                                    >
                                                        <i className={`${category.parent.icon || 'fas fa-folder'} me-1`} style={{ color: category.parent.color }}></i>
                                                        {category.parent.name}
                                                    </Link>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    <div className="col-md-6">
                                        <div className="mb-3">
                                            <label className="form-label text-muted">Icon & Color</label>
                                            <div>
                                                <span
                                                    className="badge fs-5 text-white"
                                                    style={{ backgroundColor: category.color }}
                                                >
                                                    <i className={`${category.icon || 'fas fa-folder'} me-2`}></i>
                                                    {category.icon || 'No icon'}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="mb-3">
                                            <label className="form-label text-muted">Status</label>
                                            <div>
                                                <span className={`badge ${category.is_active ? 'bg-success' : 'bg-secondary'}`}>
                                                    <i className={`fas ${category.is_active ? 'fa-check-circle' : 'fa-times-circle'} me-1`}></i>
                                                    {category.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </div>
                                        </div>

                                        {category.is_system && (
                                            <div className="mb-3">
                                                <label className="form-label text-muted">Type</label>
                                                <div>
                                                    <span className="badge bg-info">
                                                        <i className="fas fa-cog me-1"></i>
                                                        System Category
                                                    </span>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {category.description && (
                                    <div className="mb-3">
                                        <label className="form-label text-muted">Description</label>
                                        <div className="p-3 bg-light rounded">
                                            <i className="fas fa-align-left me-2"></i>
                                            {category.description}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Subcategories Breakdown (for parent categories) */}
                        {category.parent_id === null && category.children && category.children.length > 0 && (
                            <div className="card mb-4">
                                <div className="card-header">
                                    <h5 className="mb-0">
                                        <i className="fas fa-sitemap me-2"></i>Subcategories ({category.children.length})
                                    </h5>
                                </div>
                                <div className="card-body">
                                    <div className="row">
                                        {stats.children_stats && stats.children_stats.map((child) => (
                                            <div key={child.id} className="col-md-6 mb-3">
                                                <Link
                                                    href={`/categories/${child.id}`}
                                                    className="text-decoration-none"
                                                >
                                                    <div className="card h-100 border-start border-3" style={{ borderColor: child.color }}>
                                                        <div className="card-body">
                                                            <div className="d-flex justify-content-between align-items-start mb-2">
                                                                <div>
                                                                    <i className={`${child.icon} me-2`} style={{ color: child.color }}></i>
                                                                    <strong>{child.name}</strong>
                                                                </div>
                                                                <span className="badge bg-light text-dark">
                                                                    {child.transaction_count} txns
                                                                </span>
                                                            </div>
                                                            <div className="text-end">
                                                                <h5 className="mb-0 text-primary">
                                                                    {formatCurrency(child.total_amount)}
                                                                </h5>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </Link>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Recent Transactions */}
                        <div className="card">
                            <div className="card-header d-flex justify-content-between align-items-center">
                                <h5 className="mb-0">
                                    <i className="fas fa-history me-2"></i>Recent Transactions
                                </h5>
                                <Link
                                    href={`/transactions?category=${category.id}`}
                                    className="btn btn-sm btn-outline-primary"
                                >
                                    View All
                                </Link>
                            </div>
                            <div className="card-body">
                                {transactions && transactions.length > 0 ? (
                                    <div className="table-responsive">
                                        <table className="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Description</th>
                                                    <th>Account</th>
                                                    <th className="text-end">Amount</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {transactions.map((transaction) => (
                                                    <tr key={transaction.id}>
                                                        <td>
                                                            <small className="text-muted">
                                                                {formatDate(transaction.transaction_date)}
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <div>{transaction.description || 'No description'}</div>
                                                            {transaction.reference_number && (
                                                                <small className="text-muted">
                                                                    Ref: {transaction.reference_number}
                                                                </small>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <small>
                                                                <i className="fas fa-university me-1"></i>
                                                                {transaction.account?.name || 'Unknown'}
                                                            </small>
                                                        </td>
                                                        <td className="text-end">
                                                            <strong className={getAmountColorClass(transaction.amount)}>
                                                                {formatCurrency(Math.abs(transaction.amount))}
                                                            </strong>
                                                        </td>
                                                        <td className="text-end">
                                                            <Link
                                                                href={`/transactions/${transaction.id}`}
                                                                className="btn btn-sm btn-outline-secondary"
                                                            >
                                                                <i className="fas fa-eye"></i>
                                                            </Link>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <div className="text-center text-muted py-4">
                                        <i className="fas fa-inbox fa-3x mb-3 d-block"></i>
                                        <p>No transactions found for this category</p>
                                        <Link
                                            href="/transactions/create"
                                            className="btn btn-primary mt-2"
                                        >
                                            <i className="fas fa-plus me-2"></i>Add Transaction
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="col-md-4">
                        {/* Actions Card */}
                        <div className="card mb-3">
                            <div className="card-header">
                                <h6 className="mb-0">
                                    <i className="fas fa-cog me-2"></i>Actions
                                </h6>
                            </div>
                            <div className="card-body">
                                <div className="d-grid gap-2">
                                    <Link
                                        href={`/categories/${category.id}/edit`}
                                        className="btn btn-primary"
                                    >
                                        <i className="fas fa-edit me-2"></i>Edit Category
                                    </Link>

                                    <Link
                                        href={`/transactions?category=${category.id}`}
                                        className="btn btn-outline-primary"
                                    >
                                        <i className="fas fa-list me-2"></i>View All Transactions
                                    </Link>

                                    <button
                                        type="button"
                                        className="btn btn-outline-danger"
                                        onClick={handleDelete}
                                        disabled={stats.transaction_count > 0}
                                    >
                                        <i className="fas fa-trash me-2"></i>Delete Category
                                    </button>
                                    {stats.transaction_count > 0 && (
                                        <small className="text-muted">
                                            <i className="fas fa-info-circle me-1"></i>
                                            Cannot delete category with transactions
                                        </small>
                                    )}

                                    <hr />

                                    <Link
                                        href="/categories/create"
                                        className="btn btn-success"
                                    >
                                        <i className="fas fa-plus me-2"></i>Add New Category
                                    </Link>
                                </div>
                            </div>
                        </div>

                        {/* Metadata Card */}
                        <div className="card">
                            <div className="card-header">
                                <h6 className="mb-0">
                                    <i className="fas fa-info me-2"></i>Metadata
                                </h6>
                            </div>
                            <div className="card-body">
                                <small className="text-muted">
                                    <div className="mb-2">
                                        <strong>Category ID:</strong><br />
                                        #{category.id}
                                    </div>
                                    <div className="mb-2">
                                        <strong>Created:</strong><br />
                                        {new Date(category.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}{' '}
                                        {new Date(category.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
                                    </div>
                                    {category.updated_at !== category.created_at && (
                                        <div>
                                            <strong>Last Updated:</strong><br />
                                            {new Date(category.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}{' '}
                                            {new Date(category.updated_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
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
