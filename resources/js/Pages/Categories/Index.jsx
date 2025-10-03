import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';

export default function Index({ categories = {}, success, error }) {
    // Extract data from paginated response
    const categoryData = categories.data || [];
    const paginationInfo = {
        current_page: categories.current_page || 1,
        last_page: categories.last_page || 1,
        per_page: categories.per_page || 5,
        total: categories.total || 0,
        from: categories.from || 0,
        to: categories.to || 0,
        parent_total: categories.parent_total || 0
    };
    const handleDelete = (category) => {
        if (window.confirm(`Are you sure you want to delete ${category.name}? This will also delete all subcategories.`)) {
            router.delete(`/categories/${category.id}`);
        }
    };

    const handleToggleStatus = (category) => {
        router.patch(`/categories/${category.id}/toggle-status`);
    };

    const formatCurrency = (amount) => {
        return `₹${parseFloat(amount || 0).toLocaleString('en-IN', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        })}`;
    };

    const getCategoryType = (category) => {
        return category.parent_id ? 'Subcategory' : 'Main Category';
    };

    // Since data is already grouped and flattened from backend, we can display it directly
    // But we need to recreate the grouping for proper display
    const groupCategories = (categories) => {
        const result = [];
        let currentParent = null;
        
        categories.forEach(category => {
            if (!category.parent_id) {
                // This is a parent category
                currentParent = {
                    ...category,
                    children: []
                };
                result.push(currentParent);
            } else {
                // This is a child category
                if (currentParent) {
                    currentParent.children.push(category);
                }
            }
        });
        
        return result;
    };

    const groupedCategories = groupCategories(categoryData);

    return (
        <BootstrapLayout>
            <Head title="Category Management" />
            
            {/* Header */}
            <div className="row">
                <div className="col-12">
                    <div className="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 className="h2 mb-1">
                                <i className="fas fa-tags text-primary me-3"></i>
                                Category Management
                            </h1>
                            <small className="text-muted">
                                <i className="fas fa-info-circle me-1"></i>
                                Manage your income and expense categories and subcategories
                            </small>
                        </div>
                        <div>
                            <Link href="/categories/create" className="btn btn-success">
                                <i className="fas fa-plus me-2"></i>Add New Category
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

            {/* Error Message */}
            {error && (
                <div className="alert alert-danger alert-dismissible fade show" role="alert">
                    {error}
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
                                    <h6>Total Categories</h6>
                                    <h3>{paginationInfo.total || 0}</h3>
                                </div>
                                <i className="fas fa-tags fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="col-md-3">
                    <div className="card bg-success text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between">
                                <div>
                                    <h6>Active Categories</h6>
                                    <h3>{categoryData.filter(c => c.is_active).length}</h3>
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
                                    <h6>Main Categories</h6>
                                    <h3>{paginationInfo.parent_total || 0}</h3>
                                </div>
                                <i className="fas fa-folder fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="col-md-3">
                    <div className="card bg-warning text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between">
                                <div>
                                    <h6>Subcategories</h6>
                                    <h3>{categoryData.filter(c => c.parent_id).length}</h3>
                                </div>
                                <i className="fas fa-folder-open fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Categories Table */}
            <div className="row">
                <div className="col-12">
                    <div className="card">
                        <div className="card-header">
                            <h5 className="mb-0">
                                <i className="fas fa-table me-2"></i>Categories & Subcategories
                            </h5>
                        </div>
                        <div className="card-body p-0">
                            {groupedCategories.length === 0 ? (
                                <div className="text-center py-5">
                                    <i className="fas fa-tags fa-3x text-muted mb-3"></i>
                                    <h5>No categories found</h5>
                                    <p className="text-muted">Get started by creating your first category.</p>
                                    <Link href="/categories/create" className="btn btn-primary">
                                        <i className="fas fa-plus me-2"></i>Add First Category
                                    </Link>
                                </div>
                            ) : (
                                <div className="table-responsive">
                                    <table className="table table-hover mb-0">
                                        <thead className="table-light">
                                            <tr>
                                                <th>Category</th>
                                                <th>Code</th>
                                                <th>Type</th>
                                                <th>Transactions</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {groupedCategories.map((parentCategory) => (
                                                <React.Fragment key={parentCategory.id}>
                                                    {/* Parent Category Row */}
                                                    <tr className="table-light">
                                                        <td>
                                                            <div className="d-flex align-items-center">
                                                                <div 
                                                                    className="me-2 d-flex align-items-center justify-content-center"
                                                                    style={{
                                                                        backgroundColor: parentCategory.color || '#3B82F6',
                                                                        color: 'white',
                                                                        width: '32px',
                                                                        height: '32px',
                                                                        borderRadius: '6px',
                                                                        fontSize: '14px'
                                                                    }}
                                                                >
                                                                    <i className={parentCategory.icon || 'fas fa-tag'}></i>
                                                                </div>
                                                                <div>
                                                                    <span className="fw-bold text-dark">
                                                                        {parentCategory.name}
                                                                    </span>
                                                                    {parentCategory.description && (
                                                                        <div className="text-muted small">
                                                                            {parentCategory.description}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <code className="bg-light text-dark px-2 py-1 rounded">
                                                                {parentCategory.code}
                                                            </code>
                                                        </td>
                                                        <td>
                                                            <span className="badge bg-primary">
                                                                Main Category
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span className="badge bg-info">
                                                                {parentCategory.transactions_count || 0}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span className="fw-bold text-success">
                                                                {formatCurrency(parentCategory.total_amount || 0)}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span className={`badge ${parentCategory.is_active ? 'bg-success' : 'bg-danger'}`}>
                                                                {parentCategory.is_active ? 'Active' : 'Inactive'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div className="btn-group btn-group-sm">
                                                                <Link
                                                                    href={`/categories/${parentCategory.id}`}
                                                                    className="btn btn-outline-primary btn-sm"
                                                                    title="View Details"
                                                                >
                                                                    <i className="fas fa-eye"></i>
                                                                </Link>
                                                                <Link
                                                                    href={`/categories/${parentCategory.id}/edit`}
                                                                    className="btn btn-outline-warning btn-sm"
                                                                    title="Edit"
                                                                >
                                                                    <i className="fas fa-edit"></i>
                                                                </Link>
                                                                <Link
                                                                    href={`/categories/create?parent_id=${parentCategory.id}`}
                                                                    className="btn btn-outline-success btn-sm"
                                                                    title="Add Subcategory"
                                                                >
                                                                    <i className="fas fa-plus"></i>
                                                                </Link>
                                                                <button
                                                                    onClick={() => handleToggleStatus(parentCategory)}
                                                                    className={`btn btn-sm ${parentCategory.is_active ? 'btn-outline-warning' : 'btn-outline-success'}`}
                                                                    title={parentCategory.is_active ? 'Deactivate' : 'Activate'}
                                                                >
                                                                    <i className={`fas ${parentCategory.is_active ? 'fa-pause' : 'fa-play'}`}></i>
                                                                </button>
                                                                <button
                                                                    onClick={() => handleDelete(parentCategory)}
                                                                    className="btn btn-outline-danger btn-sm"
                                                                    title="Delete"
                                                                >
                                                                    <i className="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                    {/* Subcategory Rows */}
                                                    {parentCategory.children.map((childCategory) => (
                                                        <tr key={childCategory.id}>
                                                            <td>
                                                                <div className="d-flex align-items-center">
                                                                    <span className="text-muted me-2" style={{ fontSize: '16px' }}>└─</span>
                                                                    <div 
                                                                        className="me-2 d-flex align-items-center justify-content-center"
                                                                        style={{
                                                                            backgroundColor: childCategory.color || '#6B7280',
                                                                            color: 'white',
                                                                            width: '28px',
                                                                            height: '28px',
                                                                            borderRadius: '4px',
                                                                            fontSize: '12px'
                                                                        }}
                                                                    >
                                                                        <i className={childCategory.icon || 'fas fa-tag'}></i>
                                                                    </div>
                                                                    <div>
                                                                        <span className="text-dark">
                                                                            {childCategory.name}
                                                                        </span>
                                                                        {childCategory.description && (
                                                                            <div className="text-muted small">
                                                                                {childCategory.description}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <code className="bg-light text-dark px-2 py-1 rounded">
                                                                    {childCategory.code}
                                                                </code>
                                                            </td>
                                                            <td>
                                                                <span className="badge bg-secondary">
                                                                    Subcategory
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span className="badge bg-info">
                                                                    {childCategory.transactions_count || 0}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span className="fw-bold text-success">
                                                                    {formatCurrency(childCategory.total_amount || 0)}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span className={`badge ${childCategory.is_active ? 'bg-success' : 'bg-danger'}`}>
                                                                    {childCategory.is_active ? 'Active' : 'Inactive'}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div className="btn-group btn-group-sm">
                                                                    <Link
                                                                        href={`/categories/${childCategory.id}`}
                                                                        className="btn btn-outline-primary btn-sm"
                                                                        title="View Details"
                                                                    >
                                                                        <i className="fas fa-eye"></i>
                                                                    </Link>
                                                                    <Link
                                                                        href={`/categories/${childCategory.id}/edit`}
                                                                        className="btn btn-outline-warning btn-sm"
                                                                        title="Edit"
                                                                    >
                                                                        <i className="fas fa-edit"></i>
                                                                    </Link>
                                                                    <button
                                                                        onClick={() => handleToggleStatus(childCategory)}
                                                                        className={`btn btn-sm ${childCategory.is_active ? 'btn-outline-warning' : 'btn-outline-success'}`}
                                                                        title={childCategory.is_active ? 'Deactivate' : 'Activate'}
                                                                    >
                                                                        <i className={`fas ${childCategory.is_active ? 'fa-pause' : 'fa-play'}`}></i>
                                                                    </button>
                                                                    <button
                                                                        onClick={() => handleDelete(childCategory)}
                                                                        className="btn btn-outline-danger btn-sm"
                                                                        title="Delete"
                                                                    >
                                                                        <i className="fas fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </React.Fragment>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            
                            {/* Pagination */}
                            {paginationInfo.last_page > 1 && (
                                <div className="card-footer d-flex justify-content-between align-items-center">
                                    <div className="d-flex align-items-center">
                                        <div className="text-muted small me-3">
                                            Showing {paginationInfo.from} to {paginationInfo.to} of {paginationInfo.total} categories
                                        </div>
                                        <div className="d-flex align-items-center">
                                            <label className="text-muted small me-2">Categories per page:</label>
                                            <select 
                                                className="form-select form-select-sm" 
                                                style={{width: 'auto'}}
                                                value={paginationInfo.per_page}
                                                onChange={(e) => {
                                                    router.get('/categories', { per_page: e.target.value }, { 
                                                        preserveState: true, 
                                                        preserveScroll: true 
                                                    });
                                                }}
                                            >
                                                <option value="5">5</option>
                                                <option value="10">10</option>
                                                <option value="15">15</option>
                                                <option value="20">20</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <nav aria-label="Page navigation">
                                        <ul className="pagination pagination-sm mb-0">
                                            {/* Previous Page */}
                                            <li className={`page-item ${paginationInfo.current_page === 1 ? 'disabled' : ''}`}>
                                                <Link
                                                    className="page-link"
                                                    href={`/categories?page=${paginationInfo.current_page - 1}`}
                                                    preserveScroll
                                                    preserveState
                                                >
                                                    « Previous
                                                </Link>
                                            </li>
                                            
                                            {/* Page Numbers */}
                                            {[...Array(Math.min(5, paginationInfo.last_page))].map((_, index) => {
                                                let pageNumber;
                                                if (paginationInfo.last_page <= 5) {
                                                    pageNumber = index + 1;
                                                } else if (paginationInfo.current_page <= 3) {
                                                    pageNumber = index + 1;
                                                } else if (paginationInfo.current_page >= paginationInfo.last_page - 2) {
                                                    pageNumber = paginationInfo.last_page - 4 + index;
                                                } else {
                                                    pageNumber = paginationInfo.current_page - 2 + index;
                                                }
                                                
                                                return (
                                                    <li key={pageNumber} className={`page-item ${paginationInfo.current_page === pageNumber ? 'active' : ''}`}>
                                                        <Link
                                                            className="page-link"
                                                            href={`/categories?page=${pageNumber}`}
                                                            preserveScroll
                                                            preserveState
                                                        >
                                                            {pageNumber}
                                                        </Link>
                                                    </li>
                                                );
                                            })}
                                            
                                            {/* Next Page */}
                                            <li className={`page-item ${paginationInfo.current_page === paginationInfo.last_page ? 'disabled' : ''}`}>
                                                <Link
                                                    className="page-link"
                                                    href={`/categories?page=${paginationInfo.current_page + 1}`}
                                                    preserveScroll
                                                    preserveState
                                                >
                                                    Next »
                                                </Link>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </BootstrapLayout>
    );
}
