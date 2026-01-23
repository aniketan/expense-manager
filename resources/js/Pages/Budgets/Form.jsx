import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';

export default function Form({ budget, categories, periodTypes, isEdit = false }) {
    const { data, setData, post, put, processing, errors } = useForm({
        category_id: budget?.category_id || '',
        name: budget?.name || '',
        amount: budget?.amount || '',
        period_type: budget?.period_type || 'monthly',
        start_date: budget?.start_date || new Date().toISOString().split('T')[0],
        end_date: budget?.end_date || '',
        is_active: budget?.is_active ?? true,
        notes: budget?.notes || '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();

        if (isEdit) {
            put(`/budgets/${budget.id}`);
        } else {
            post('/budgets');
        }
    };

    const handlePeriodTypeChange = (e) => {
        const periodType = e.target.value;
        setData('period_type', periodType);

        // Auto-set date range based on period type
        const today = new Date();
        let startDate, endDate;

        if (periodType === 'monthly') {
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        } else if (periodType === 'yearly') {
            startDate = new Date(today.getFullYear(), 0, 1);
            endDate = new Date(today.getFullYear(), 11, 31);
        } else {
            // Custom - don't auto-set
            return;
        }

        setData({
            ...data,
            period_type: periodType,
            start_date: startDate.toISOString().split('T')[0],
            end_date: endDate.toISOString().split('T')[0],
        });
    };

    return (
        <BootstrapLayout>
            <Head title={isEdit ? 'Edit Budget' : 'Create Budget'} />

            <div className="container-fluid py-4">
                <div className="row justify-content-center">
                    <div className="col-md-8 col-lg-6">
                        <div className="card">
                            <div className="card-header">
                                <h4 className="mb-0">
                                    {isEdit ? 'Edit Budget' : 'Create New Budget'}
                                </h4>
                            </div>
                            <div className="card-body">
                                <form onSubmit={handleSubmit}>
                                    {/* Name */}
                                    <div className="mb-3">
                                        <label htmlFor="name" className="form-label">
                                            Budget Name <span className="text-danger">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            id="name"
                                            className={`form-control ${errors.name ? 'is-invalid' : ''}`}
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder="e.g., Monthly Groceries Budget"
                                            required
                                        />
                                        {errors.name && (
                                            <div className="invalid-feedback">{errors.name}</div>
                                        )}
                                    </div>

                                    {/* Category */}
                                    <div className="mb-3">
                                        <label htmlFor="category_id" className="form-label">
                                            Category <span className="text-danger">*</span>
                                        </label>
                                        <select
                                            id="category_id"
                                            className={`form-select ${errors.category_id ? 'is-invalid' : ''}`}
                                            value={data.category_id}
                                            onChange={(e) => setData('category_id', e.target.value)}
                                            required
                                        >
                                            <option value="">Select a category</option>
                                            {categories.map(cat => (
                                                <option key={cat.id} value={cat.id}>
                                                    {cat.parent_id ? `  └─ ${cat.name}` : cat.name}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.category_id && (
                                            <div className="invalid-feedback">{errors.category_id}</div>
                                        )}
                                    </div>

                                    {/* Amount */}
                                    <div className="mb-3">
                                        <label htmlFor="amount" className="form-label">
                                            Budget Amount (₹) <span className="text-danger">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            id="amount"
                                            className={`form-control ${errors.amount ? 'is-invalid' : ''}`}
                                            value={data.amount}
                                            onChange={(e) => setData('amount', e.target.value)}
                                            placeholder="0.00"
                                            step="0.01"
                                            min="0.01"
                                            required
                                        />
                                        {errors.amount && (
                                            <div className="invalid-feedback">{errors.amount}</div>
                                        )}
                                    </div>

                                    {/* Period Type */}
                                    <div className="mb-3">
                                        <label htmlFor="period_type" className="form-label">
                                            Period Type <span className="text-danger">*</span>
                                        </label>
                                        <select
                                            id="period_type"
                                            className={`form-select ${errors.period_type ? 'is-invalid' : ''}`}
                                            value={data.period_type}
                                            onChange={handlePeriodTypeChange}
                                            required
                                        >
                                            <option value="monthly">Monthly</option>
                                            <option value="yearly">Yearly</option>
                                            <option value="custom">Custom</option>
                                        </select>
                                        {errors.period_type && (
                                            <div className="invalid-feedback">{errors.period_type}</div>
                                        )}
                                    </div>

                                    {/* Date Range */}
                                    <div className="row mb-3">
                                        <div className="col-md-6">
                                            <label htmlFor="start_date" className="form-label">
                                                Start Date <span className="text-danger">*</span>
                                            </label>
                                            <input
                                                type="date"
                                                id="start_date"
                                                className={`form-control ${errors.start_date ? 'is-invalid' : ''}`}
                                                value={data.start_date}
                                                onChange={(e) => setData('start_date', e.target.value)}
                                                required
                                            />
                                            {errors.start_date && (
                                                <div className="invalid-feedback">{errors.start_date}</div>
                                            )}
                                        </div>
                                        <div className="col-md-6">
                                            <label htmlFor="end_date" className="form-label">
                                                End Date <span className="text-danger">*</span>
                                            </label>
                                            <input
                                                type="date"
                                                id="end_date"
                                                className={`form-control ${errors.end_date ? 'is-invalid' : ''}`}
                                                value={data.end_date}
                                                onChange={(e) => setData('end_date', e.target.value)}
                                                min={data.start_date}
                                                required
                                            />
                                            {errors.end_date && (
                                                <div className="invalid-feedback">{errors.end_date}</div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Active Status */}
                                    <div className="mb-3">
                                        <div className="form-check">
                                            <input
                                                type="checkbox"
                                                id="is_active"
                                                className="form-check-input"
                                                checked={data.is_active}
                                                onChange={(e) => setData('is_active', e.target.checked)}
                                            />
                                            <label htmlFor="is_active" className="form-check-label">
                                                Active
                                            </label>
                                        </div>
                                        <small className="text-muted">
                                            Only active budgets will be tracked and shown in alerts
                                        </small>
                                    </div>

                                    {/* Notes */}
                                    <div className="mb-3">
                                        <label htmlFor="notes" className="form-label">
                                            Notes
                                        </label>
                                        <textarea
                                            id="notes"
                                            className={`form-control ${errors.notes ? 'is-invalid' : ''}`}
                                            value={data.notes}
                                            onChange={(e) => setData('notes', e.target.value)}
                                            rows="3"
                                            placeholder="Add any notes about this budget..."
                                        ></textarea>
                                        {errors.notes && (
                                            <div className="invalid-feedback">{errors.notes}</div>
                                        )}
                                    </div>

                                    {/* Buttons */}
                                    <div className="d-flex gap-2">
                                        <button
                                            type="submit"
                                            className="btn btn-primary"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <>
                                                    <span className="spinner-border spinner-border-sm me-2"></span>
                                                    Saving...
                                                </>
                                            ) : (
                                                <>
                                                    <i className="fas fa-save me-2"></i>
                                                    {isEdit ? 'Update Budget' : 'Create Budget'}
                                                </>
                                            )}
                                        </button>
                                        <Link
                                            href="/budgets"
                                            className="btn btn-secondary"
                                        >
                                            Cancel
                                        </Link>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </BootstrapLayout>
    );
}
