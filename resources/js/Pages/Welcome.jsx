import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import BootstrapLayout from '../Layouts/BootstrapLayout';

export default function Welcome({ stats, recentTransactions }) {
    const [showModal, setShowModal] = useState(false);
    const [featureName, setFeatureName] = useState('');

    const showComingSoon = (feature) => {
        setFeatureName(feature);
        setShowModal(true);
    };

    // Use actual stats from backend or fallback to zeros
    const dashboardStats = stats || {
        totalIncome: 0.00,
        totalExpenses: 0.00,
        netBalance: 0.00,
        totalTransactions: 0
    };

    return (
        <BootstrapLayout>
            <Head title="Welcome to Expense Manager" />
            
            <div className="container-fluid">
                {/* Welcome Section */}
                <div className="row mb-4">
                    <div className="col-12">
                        <div className="card bg-primary text-white">
                            <div className="card-body">
                                <h2 className="card-title mb-0">
                                    <i className="fas fa-chart-line me-2"></i>
                                    Welcome to Expense Manager
                                </h2>
                                <p className="card-text">Your comprehensive financial tracking solution</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Summary Statistics */}
                <div className="row mb-4">
                    <div className="col-md-3 col-sm-6 mb-3">
                        <div className="card bg-success text-white">
                            <div className="card-body">
                                <div className="d-flex justify-content-between">
                                    <div>
                                        <h6 className="card-title">Total Income</h6>
                                        <h3>₹{dashboardStats.totalIncome.toFixed(2)}</h3>
                                    </div>
                                    <div className="align-self-center">
                                        <i className="fas fa-arrow-up fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div className="col-md-3 col-sm-6 mb-3">
                        <div className="card bg-danger text-white">
                            <div className="card-body">
                                <div className="d-flex justify-content-between">
                                    <div>
                                        <h6 className="card-title">Total Expenses</h6>
                                        <h3>₹{dashboardStats.totalExpenses.toFixed(2)}</h3>
                                    </div>
                                    <div className="align-self-center">
                                        <i className="fas fa-arrow-down fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div className="col-md-3 col-sm-6 mb-3">
                        <div className="card bg-info text-white">
                            <div className="card-body">
                                <div className="d-flex justify-content-between">
                                    <div>
                                        <h6 className="card-title">Net Balance</h6>
                                        <h3 className="text-light">
                                            ₹{dashboardStats.netBalance.toFixed(2)}
                                        </h3>
                                    </div>
                                    <div className="align-self-center">
                                        <i className="fas fa-balance-scale fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div className="col-md-3 col-sm-6 mb-3">
                        <div className="card bg-warning text-white">
                            <div className="card-body">
                                <div className="d-flex justify-content-between">
                                    <div>
                                        <h6 className="card-title">Total Transactions</h6>
                                        <h3>{dashboardStats.totalTransactions}</h3>
                                    </div>
                                    <div className="align-self-center">
                                        <i className="fas fa-receipt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Core Features Section */}
                <div className="row mb-4">
                    <div className="col-12">
                        <h3 className="mb-3">
                            <i className="fas fa-star me-2"></i>Core Features
                        </h3>
                    </div>
                    
                    {/* Transaction Management */}
                    <div className="col-md-4 col-sm-6 mb-3">
                        <div className="card h-100 feature-card">
                            <div className="card-body">
                                <h5 className="card-title">
                                    <i className="fas fa-money-bill-wave me-2 text-primary"></i>
                                    Transaction Management
                                </h5>
                                <ul className="list-unstyled">
                                    <li><i className="fas fa-check text-success me-2"></i>Add/Edit/Delete Expenses</li>
                                    <li><i className="fas fa-check text-success me-2"></i>Multiple Account Support</li>
                                    <li><i className="fas fa-check text-success me-2"></i>Payment Method Tracking</li>
                                    <li><i className="fas fa-check text-success me-2"></i>Transaction Status</li>
                                </ul>
                                <div className="d-grid gap-2">
                                    <Link href="/transactions" className="btn btn-primary">
                                        <i className="fas fa-list me-2"></i>View Transactions
                                    </Link>
                                    <Link href="/transactions/create" className="btn btn-success">
                                        <i className="fas fa-plus me-2"></i>Add Transaction
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {/* Category Management */}
                    <div className="col-md-4 col-sm-6 mb-3">
                        <div className="card h-100 feature-card">
                            <div className="card-body">
                                <h5 className="card-title">
                                    <i className="fas fa-tags me-2 text-info"></i>
                                    Category & Subcategory
                                </h5>
                                <ul className="list-unstyled">
                                    <li><i className="fas fa-check text-success me-2"></i>17 Pre-built Categories</li>
                                    <li><i className="fas fa-check text-success me-2"></i>100+ Subcategories</li>
                                    <li><i className="fas fa-check text-success me-2"></i>Custom Category Creation</li>
                                    <li><i className="fas fa-check text-success me-2"></i>Category Analytics</li>
                                </ul>
                                <div className="d-grid gap-2">
                                    <Link href="/categories" className="btn btn-info">
                                        <i className="fas fa-tags me-2"></i>Manage Categories
                                    </Link>
                                    <button 
                                        className="btn btn-outline-info"
                                        onClick={() => showComingSoon('Category Analytics')}
                                    >
                                        <i className="fas fa-chart-pie me-2"></i>View Analytics
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {/* Budget Management */}
                    <div className="col-md-4 col-sm-6 mb-3">
                        <div className="card h-100 feature-card">
                            <div className="card-body">
                                <h5 className="card-title">
                                    <i className="fas fa-wallet me-2 text-warning"></i>
                                    Budget Management
                                </h5>
                                <ul className="list-unstyled">
                                    <li><i className="fas fa-check text-success me-2"></i>Budget Creation & Monitoring</li>
                                    <li><i className="fas fa-check text-success me-2"></i>Frequency-based Budgets</li>
                                    <li><i className="fas fa-check text-success me-2"></i>Budget Alerts</li>
                                    <li><i className="fas fa-check text-success me-2"></i>Multi-account Tracking</li>
                                </ul>
                                <div className="d-grid gap-2">
                                    <Link href="/budgets" className="btn btn-warning text-dark">
                                        <i className="fas fa-wallet me-2"></i>Manage Budgets
                                    </Link>
                                    <button 
                                        className="btn btn-outline-warning"
                                        onClick={() => showComingSoon('Budget Analytics')}
                                    >
                                        <i className="fas fa-chart-pie me-2"></i>Budget Analytics
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {/* Recurring Expenses */}
                    <div className="col-md-4 col-sm-6 mb-3">
                        <div className="card h-100 feature-card">
                            <div className="card-body">
                                <h5 className="card-title">
                                    <i className="fas fa-sync me-2 text-success"></i>
                                    Recurring Transactions
                                </h5>
                                <ul className="list-unstyled">
                                    <li><i className="fas fa-check text-success me-2"></i>Create & Manage Rules</li>
                                    <li><i className="fas fa-check text-success me-2"></i>Multiple Frequency Options</li>
                                    <li><i className="fas fa-check text-success me-2"></i>Automated Reminders</li>
                                    <li><i className="fas fa-check text-success me-2"></i>Auto Transaction Creation</li>
                                </ul>
                                <div className="d-grid gap-2">
                                    <Link href="/recurring/dashboard" className="btn btn-success">
                                        <i className="fas fa-sync me-2"></i>Recurring Dashboard
                                    </Link>
                                    <button 
                                        className="btn btn-outline-success"
                                        onClick={() => showComingSoon('Create New Rule')}
                                    >
                                        <i className="fas fa-plus me-2"></i>Create New Rule
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {/* Payee Management */}
                    <div className="col-md-4 col-sm-6 mb-3">
                        <div className="card h-100 feature-card">
                            <div className="card-body">
                                <h5 className="card-title">
                                    <i className="fas fa-address-book me-2 text-success"></i>
                                    Payee/Payer Management
                                </h5>
                                <ul className="list-unstyled">
                                    <li><i className="fas fa-check text-success me-2"></i>Contact Management</li>
                                    <li><i className="fas fa-check text-success me-2"></i>Address Book Integration</li>
                                    <li><i className="fas fa-check text-success me-2"></i>Transaction History</li>
                                    <li><i className="fas fa-check text-success me-2"></i>Frequent Payee Access</li>
                                </ul>
                                <div className="d-grid gap-2">
                                    <button 
                                        className="btn btn-success"
                                        onClick={() => showComingSoon('Payee Management')}
                                    >
                                        <i className="fas fa-address-book me-2"></i>Manage Payees
                                    </button>
                                    <button 
                                        className="btn btn-outline-success"
                                        onClick={() => showComingSoon('Payee History')}
                                    >
                                        <i className="fas fa-history me-2"></i>View History
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {/* Reports & Analytics */}
                    <div className="col-md-4 col-sm-6 mb-3">
                        <div className="card h-100 feature-card">
                            <div className="card-body">
                                <h5 className="card-title">
                                    <i className="fas fa-chart-line me-2 text-danger"></i>
                                    Reports & Analytics
                                </h5>
                                <ul className="list-unstyled">
                                    <li><i className="fas fa-chart-bar text-info me-2"></i>Monthly/Yearly Reports</li>
                                    <li><i className="fas fa-chart-pie text-info me-2"></i>Category Analysis</li>
                                    <li><i className="fas fa-chart-line text-info me-2"></i>Trend Analysis</li>
                                    <li><i className="fas fa-chart-area text-info me-2"></i>Budget Performance</li>
                                </ul>
                                <div className="d-grid gap-2">
                                    <button 
                                        className="btn btn-danger"
                                        onClick={() => showComingSoon('View Reports')}
                                    >
                                        <i className="fas fa-chart-line me-2"></i>View Reports
                                    </button>
                                    <button 
                                        className="btn btn-outline-danger"
                                        onClick={() => showComingSoon('Visualizations')}
                                    >
                                        <i className="fas fa-chart-bar me-2"></i>Visualizations
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Advanced Features Section */}
                <div className="row mb-4">
                    <div className="col-12">
                        <h3 className="mb-3">
                            <i className="fas fa-rocket me-2"></i>Advanced Features
                        </h3>
                    </div>
                    
                    <div className="col-md-3 col-sm-6 mb-3">
                        <div className="card">
                            <div className="card-body text-center">
                                <i className="fas fa-search fa-3x text-primary mb-3"></i>
                                <h6 className="card-title">Search & Filtering</h6>
                                <p className="card-text small">Advanced search with multi-criteria filtering</p>
                                <Link href="/transactions" className="btn btn-sm btn-primary">
                                    <i className="fas fa-search me-2"></i>Search Transactions
                                </Link>
                            </div>
                        </div>
                    </div>
                    
                    <div className="col-md-3 col-sm-6 mb-3">
                        <div className="card">
                            <div className="card-body text-center">
                                <i className="fas fa-file-import fa-3x text-success mb-3"></i>
                                <h6 className="card-title">Import/Export</h6>
                                <p className="card-text small">CSV import, bank statement processing</p>
                                <button 
                                    className="btn btn-sm btn-outline-success"
                                    onClick={() => showComingSoon('Import/Export')}
                                >
                                    Coming Soon
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div className="col-md-3 col-sm-6 mb-3">
                        <div className="card">
                            <div className="card-body text-center">
                                <i className="fas fa-robot fa-3x text-warning mb-3"></i>
                                <h6 className="card-title">AI Integration</h6>
                                <p className="card-text small">Smart categorization & insights</p>
                                <button 
                                    className="btn btn-sm btn-outline-warning"
                                    onClick={() => showComingSoon('AI Features')}
                                >
                                    Coming Soon
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div className="col-md-3 col-sm-6 mb-3">
                        <div className="card">
                            <div className="card-body text-center">
                                <i className="fas fa-mobile-alt fa-3x text-info mb-3"></i>
                                <h6 className="card-title">Mobile App</h6>
                                <p className="card-text small">Responsive mobile experience</p>
                                <button 
                                    className="btn btn-sm btn-outline-info"
                                    onClick={() => showComingSoon('Mobile App')}
                                >
                                    Coming Soon
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Recent Transactions */}
                <div className="row mb-4">
                    <div className="col-12">
                        <div className="card">
                            <div className="card-header d-flex justify-content-between align-items-center">
                                <h5 className="mb-0">
                                    <i className="fas fa-clock me-2"></i>Recent Transactions
                                </h5>
                                <Link href="/transactions" className="btn btn-sm btn-primary">
                                    View All <i className="fas fa-arrow-right ms-1"></i>
                                </Link>
                            </div>
                            <div className="card-body">
                                {recentTransactions && recentTransactions.length > 0 ? (
                                    <div className="table-responsive">
                                        <table className="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Description</th>
                                                    <th>Category</th>
                                                    <th>Account</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {recentTransactions.map((transaction, index) => (
                                                    <tr key={transaction.id}>
                                                        <td>
                                                            {new Date(transaction.transaction_date).toLocaleDateString()}
                                                        </td>
                                                        <td>
                                                            <div className="fw-medium">{transaction.description || 'No description'}</div>
                                                            {transaction.payment_method && (
                                                                <small className="text-muted">via {transaction.payment_method}</small>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <span className="badge bg-light text-dark">
                                                                {transaction.category?.name || 'Uncategorized'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span className="text-muted">
                                                                {transaction.account?.account_name || 'Unknown Account'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span className={`fw-bold ${
                                                                transaction.transaction_type === 'expense' || transaction.amount < 0 
                                                                    ? 'text-danger' 
                                                                    : 'text-success'
                                                            }`}>
                                                                {transaction.transaction_type === 'expense' || transaction.amount < 0 ? '-' : '+'}
                                                                ₹{Math.abs(transaction.amount).toFixed(2)}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <div className="text-center py-4">
                                        <i className="fas fa-receipt fa-3x text-muted mb-3"></i>
                                        <p className="text-muted">No recent transactions found.</p>
                                        <Link href="/transactions/create" className="btn btn-primary">
                                            <i className="fas fa-plus me-2"></i>Add Your First Transaction
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Quick Actions */}
                <div className="row mb-4">
                    <div className="col-12">
                        <div className="card bg-light">
                            <div className="card-body">
                                <h5 className="card-title">
                                    <i className="fas fa-bolt me-2"></i>Quick Actions
                                </h5>
                                <div className="row quick-actions">
                                    <div className="col-md-2 col-sm-4 col-6 mb-2">
                                        <div className="d-grid">
                                            <Link href="/transactions/create" className="btn btn-success">
                                                <i className="fas fa-plus me-2"></i>Add Expense
                                            </Link>
                                        </div>
                                    </div>
                                    <div className="col-md-2 col-sm-4 col-6 mb-2">
                                        <div className="d-grid">
                                            <Link href="/transactions" className="btn btn-primary">
                                                <i className="fas fa-list me-2"></i>View All
                                            </Link>
                                        </div>
                                    </div>
                                    <div className="col-md-2 col-sm-4 col-6 mb-2">
                                        <div className="d-grid">
                                            <button 
                                                className="btn btn-info"
                                                onClick={() => showComingSoon('Monthly Report')}
                                            >
                                                <i className="fas fa-chart-bar me-2"></i>Monthly Report
                                            </button>
                                        </div>
                                    </div>
                                    <div className="col-md-2 col-sm-4 col-6 mb-2">
                                        <div className="d-grid">
                                            <button 
                                                className="btn btn-warning"
                                                onClick={() => showComingSoon('Budget Setup')}
                                            >
                                                <i className="fas fa-wallet me-2"></i>Set Budget
                                            </button>
                                        </div>
                                    </div>
                                    <div className="col-md-2 col-sm-4 col-6 mb-2">
                                        <div className="d-grid">
                                            <button 
                                                className="btn btn-secondary"
                                                onClick={() => showComingSoon('Import Data')}
                                            >
                                                <i className="fas fa-upload me-2"></i>Import CSV
                                            </button>
                                        </div>
                                    </div>
                                    <div className="col-md-2 col-sm-4 col-6 mb-2">
                                        <div className="d-grid">
                                            <button 
                                                className="btn btn-danger"
                                                onClick={() => showComingSoon('Export Data')}
                                            >
                                                <i className="fas fa-download me-2"></i>Export Data
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Coming Soon Modal */}
            {showModal && (
                <div className="modal fade show" style={{ display: 'block' }} tabIndex="-1">
                    <div className="modal-dialog">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">
                                    <i className="fas fa-rocket me-2"></i>Coming Soon
                                </h5>
                                <button 
                                    type="button" 
                                    className="btn-close" 
                                    onClick={() => setShowModal(false)}
                                ></button>
                            </div>
                            <div className="modal-body">
                                <div className="text-center">
                                    <i className="fas fa-tools fa-3x text-warning mb-3"></i>
                                    <h5>{featureName}</h5>
                                    <p>This feature is under development and will be available soon!</p>
                                    <p className="text-muted">Stay tuned for updates as we build out the complete expense management solution.</p>
                                </div>
                            </div>
                            <div className="modal-footer">
                                <button 
                                    type="button" 
                                    className="btn btn-secondary" 
                                    onClick={() => setShowModal(false)}
                                >
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
            {showModal && <div className="modal-backdrop fade show"></div>}
        </BootstrapLayout>
    );
}
