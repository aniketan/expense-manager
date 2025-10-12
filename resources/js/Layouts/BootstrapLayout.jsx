import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import Footer from '../Components/Footer';

export default function Layout({ children }) {
    const { url } = usePage();

    return (
        <div className="d-flex flex-column min-vh-100">
            {/* Navigation */}
            <nav className="navbar navbar-expand-lg navbar-dark">
                <div className="container">
                    <Link className="navbar-brand" href="/">
                        <i className="fas fa-chart-line me-2"></i>Expense Manager
                    </Link>
                    <div className="navbar-nav ms-auto">
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
                    </div>
                </div>
            </nav>

            {/* Main Content */}
            <main className="flex-grow-1">
                <div className="container mt-4">
                    {children}
                </div>
            </main>

            {/* Footer */}
            <Footer />
        </div>
    );
}