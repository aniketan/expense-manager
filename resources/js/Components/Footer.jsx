import React from 'react';

export default function Footer() {
    const currentYear = new Date().getFullYear();

    return (
        <footer className="bg-dark text-white mt-5 py-4">
            <div className="container">
                <div className="row">
                    <div className="col-md-8">
                        <div className="d-flex align-items-center">
                            <i className="fas fa-chart-line me-2"></i>
                            <span className="fw-bold">Expense Manager</span>
                            <span className="ms-2 text-muted">| Your comprehensive financial tracking solution</span>
                        </div>
                    </div>
                    <div className="col-md-4 text-md-end text-center mt-3 mt-md-0">
                        <div>
                            <i className="fas fa-code me-2"></i>
                            Developed by <a 
                                href="https://buffernow.com" 
                                target="_blank" 
                                rel="noopener noreferrer"
                                className="text-primary text-decoration-none fw-bold"
                            >
                                BufferNow
                            </a>
                        </div>
                        <div className="mt-2">
                            <small className="text-muted">© {currentYear} All rights reserved</small>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    );
}