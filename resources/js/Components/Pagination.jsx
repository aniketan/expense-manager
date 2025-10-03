import React from 'react';
import { Link } from '@inertiajs/react';

/**
 * Bootstrap Pagination Component
 * 
 * @param {Object} paginationData - Pagination data object with current_page, last_page, per_page, total, from, to
 * @param {string} baseUrl - Base URL for pagination links (optional, defaults to current URL)
 * @param {boolean} showPerPageSelector - Whether to show the per-page selector dropdown (default: true)
 * @param {boolean} preserveScroll - Whether to preserve scroll position on navigation (default: true)
 * @param {boolean} preserveState - Whether to preserve form state on navigation (default: true)
 * @param {Function} onPerPageChange - Callback function for per-page selection change
 * 
 * @example
 * <Pagination 
 *   paginationData={paginationInfo}
 *   onPerPageChange={(e) => handlePerPageChange(e.target.value)}
 * />
 */
export default function Pagination({ 
    paginationData, 
    baseUrl = '', 
    showPerPageSelector = true, 
    preserveScroll = true, 
    preserveState = true,
    onPerPageChange = null 
}) {
    if (!paginationData || paginationData.last_page <= 1) {
        return null;
    }

    const { current_page, last_page, per_page, total, from, to } = paginationData;

    const getPageNumbers = () => {
        const maxVisible = 5;
        let pages = [];
        
        if (last_page <= maxVisible) {
            for (let i = 1; i <= last_page; i++) {
                pages.push(i);
            }
        } else if (current_page <= 3) {
            for (let i = 1; i <= maxVisible; i++) {
                pages.push(i);
            }
        } else if (current_page >= last_page - 2) {
            for (let i = last_page - maxVisible + 1; i <= last_page; i++) {
                pages.push(i);
            }
        } else {
            for (let i = current_page - 2; i <= current_page + 2; i++) {
                pages.push(i);
            }
        }
        
        return pages;
    };

    const buildUrl = (page) => {
        const url = new URL(window.location);
        url.searchParams.set('page', page);
        return url.pathname + url.search;
    };

    return (
        <div className="card-footer d-flex justify-content-between align-items-center">
            <div className="d-flex align-items-center">
                <div className="text-muted small me-3">
                    Showing {from} to {to} of {total} results
                </div>
                {showPerPageSelector && (
                    <div className="d-flex align-items-center">
                        <label className="text-muted small me-2">Per page:</label>
                        <select 
                            className="form-select form-select-sm" 
                            style={{width: 'auto'}}
                            value={per_page}
                            onChange={onPerPageChange}
                        >
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                )}
            </div>
            
            <nav aria-label="Page navigation">
                <ul className="pagination pagination-sm mb-0">
                    {/* Previous Page */}
                    <li className={`page-item ${current_page === 1 ? 'disabled' : ''}`}>
                        <Link
                            className="page-link"
                            href={buildUrl(current_page - 1)}
                            preserveScroll={preserveScroll}
                            preserveState={preserveState}
                        >
                            « Previous
                        </Link>
                    </li>
                    
                    {/* Page Numbers */}
                    {getPageNumbers().map(pageNumber => (
                        <li key={pageNumber} className={`page-item ${current_page === pageNumber ? 'active' : ''}`}>
                            <Link
                                className="page-link"
                                href={buildUrl(pageNumber)}
                                preserveScroll={preserveScroll}
                                preserveState={preserveState}
                            >
                                {pageNumber}
                            </Link>
                        </li>
                    ))}
                    
                    {/* Next Page */}
                    <li className={`page-item ${current_page === last_page ? 'disabled' : ''}`}>
                        <Link
                            className="page-link"
                            href={buildUrl(current_page + 1)}
                            preserveScroll={preserveScroll}
                            preserveState={preserveState}
                        >
                            Next »
                        </Link>
                    </li>
                </ul>
            </nav>
        </div>
    );
}