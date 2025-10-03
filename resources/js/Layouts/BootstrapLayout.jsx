import React from 'react';
import { Link, usePage } from '@inertiajs/react';

export default function Layout({ children }) {
    const { url } = usePage();

    return (
        <div>
            {/* Navigation */}
            <nav className="navbar navbar-expand-lg navbar-dark">
                <div className="container">
                    <Link className="navbar-brand" href="/">
                        <i className="fas fa-chart-line me-2"></i>Expense Manager
                    </Link>
                    <div className="navbar-nav ms-auto">
                        <Link className="nav-link" href="/">
                            <i className="fas fa-home me-1"></i>Dashboard
                        </Link>
                        <Link className="nav-link" href="/transactions">
                            <i className="fas fa-list me-1"></i>Transactions
                        </Link>
                        <Link className="nav-link" href="/transactions/create">
                            <i className="fas fa-plus me-1"></i>Add Transaction
                        </Link>
                        <Link className="nav-link" href="/categories">
                            <i className="fas fa-tags me-1"></i>Categories
                        </Link>
                        <Link className="nav-link" href="/accounts">
                            <i className="fas fa-university me-1"></i>Accounts
                        </Link>
                        <Link className="nav-link" href="/budgets">
                            <i className="fas fa-piggy-bank me-1"></i>Budgets
                        </Link>
                        <Link className="nav-link" href="/recurring/dashboard">
                            <i className="fas fa-sync-alt me-1"></i>Recurring
                        </Link>
                        <Link className="nav-link" href="/etl/dashboard">
                            <i className="fas fa-upload me-1"></i>ETL Sync
                        </Link>
                    </div>
                </div>
            </nav>

            <div className="container mt-4">
                {children}
            </div>
        </div>
    );
}