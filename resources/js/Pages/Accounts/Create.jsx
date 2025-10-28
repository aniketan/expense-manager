import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';

export default function Create({ accountTypes }) {
    const { data, setData, post, processing, errors } = useForm({
        account_code: '',
        account_name: '',
        account_type: 'savings',
        bank_name: '',
        account_number: '',
        ifsc_code: '',
        opening_balance: '0.00',
        current_balance: '',
        credit_limit: '0.00',
        is_active: true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        
        // Set current_balance to opening_balance if not provided
        const submitData = {
            ...data,
            current_balance: data.current_balance || data.opening_balance
        };
        
        post('/accounts', submitData);
    };

    const isCreditCard = data.account_type === 'credit_card';
    const isCash = data.account_type === 'cash';

    return (
        <BootstrapLayout>
            <Head title="Create Account" />
            
            <div className="container-fluid">
                {/* Header */}
                <div className="row mb-4">
                    <div className="col-12">
                        <div className="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 className="mb-1">
                                    <i className="fas fa-plus-circle me-2"></i>
                                    Create New Account
                                </h2>
                                <p className="text-muted mb-0">Add a new account to track your finances</p>
                            </div>
                            <Link 
                                href="/accounts" 
                                className="btn btn-outline-secondary"
                            >
                                <i className="fas fa-arrow-left me-2"></i>Back to Accounts
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Form */}
                <div className="row">
                    <div className="col-lg-8 mx-auto">
                        <form onSubmit={handleSubmit}>
                            <div className="card">
                                <div className="card-header">
                                    <h5 className="mb-0">
                                        <i className="fas fa-info-circle me-2"></i>Account Information
                                    </h5>
                                </div>
                                <div className="card-body">
                                    {/* Basic Information */}
                                    <div className="row mb-3">
                                        <div className="col-md-6">
                                            <label className="form-label">
                                                Account Code <span className="text-danger">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                value={data.account_code}
                                                onChange={e => setData('account_code', e.target.value.toUpperCase())}
                                                className={`form-control ${errors.account_code ? 'is-invalid' : ''}`}
                                                placeholder="e.g., SBI01, CASH"
                                                required
                                            />
                                            {errors.account_code && <div className="invalid-feedback">{errors.account_code}</div>}
                                        </div>

                                        <div className="col-md-6">
                                            <label className="form-label">
                                                Account Type <span className="text-danger">*</span>
                                            </label>
                                            <select
                                                value={data.account_type}
                                                onChange={e => setData('account_type', e.target.value)}
                                                className={`form-select ${errors.account_type ? 'is-invalid' : ''}`}
                                                required
                                            >
                                                {Object.entries(accountTypes).map(([value, label]) => (
                                                    <option key={value} value={value}>{label}</option>
                                                ))}
                                            </select>
                                            {errors.account_type && <div className="invalid-feedback">{errors.account_type}</div>}
                                        </div>
                                    </div>

                                    <div className="mb-3">
                                        <label className="form-label">
                                            Account Name <span className="text-danger">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={data.account_name}
                                            onChange={e => setData('account_name', e.target.value)}
                                            className={`form-control ${errors.account_name ? 'is-invalid' : ''}`}
                                            placeholder="e.g., SBI Savings Account, Cash Wallet"
                                            required
                                        />
                                        {errors.account_name && <div className="invalid-feedback">{errors.account_name}</div>}
                                    </div>

                                    {/* Bank Details - Hide for cash accounts */}
                                    {!isCash && (
                                        <>
                                            <div className="mb-3">
                                                <label className="form-label">Bank Name</label>
                                                <input
                                                    type="text"
                                                    value={data.bank_name}
                                                    onChange={e => setData('bank_name', e.target.value)}
                                                    className={`form-control ${errors.bank_name ? 'is-invalid' : ''}`}
                                                    placeholder="e.g., State Bank of India"
                                                />
                                                {errors.bank_name && <div className="invalid-feedback">{errors.bank_name}</div>}
                                            </div>

                                            <div className="row mb-3">
                                                <div className="col-md-6">
                                                    <label className="form-label">Account Number</label>
                                                    <input
                                                        type="text"
                                                        value={data.account_number}
                                                        onChange={e => setData('account_number', e.target.value)}
                                                        className={`form-control ${errors.account_number ? 'is-invalid' : ''}`}
                                                        placeholder={isCreditCard ? "****-****-****-1234" : "Account number"}
                                                    />
                                                    {errors.account_number && <div className="invalid-feedback">{errors.account_number}</div>}
                                                </div>

                                                <div className="col-md-6">
                                                    <label className="form-label">IFSC Code</label>
                                                    <input
                                                        type="text"
                                                        value={data.ifsc_code}
                                                        onChange={e => setData('ifsc_code', e.target.value.toUpperCase())}
                                                        className={`form-control ${errors.ifsc_code ? 'is-invalid' : ''}`}
                                                        placeholder="e.g., SBIN0001234"
                                                    />
                                                    {errors.ifsc_code && <div className="invalid-feedback">{errors.ifsc_code}</div>}
                                                </div>
                                            </div>
                                        </>
                                    )}

                                    {/* Balance Information */}
                                    <div className="row mb-3">
                                        <div className="col-md-6">
                                            <label className="form-label">Opening Balance</label>
                                            <div className="input-group">
                                                <span className="input-group-text">₹</span>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    value={data.opening_balance}
                                                    onChange={e => setData('opening_balance', e.target.value)}
                                                    className={`form-control ${errors.opening_balance ? 'is-invalid' : ''}`}
                                                    placeholder="0.00"
                                                />
                                                {errors.opening_balance && <div className="invalid-feedback">{errors.opening_balance}</div>}
                                            </div>
                                        </div>

                                        <div className="col-md-6">
                                            <label className="form-label">Current Balance</label>
                                            <div className="input-group">
                                                <span className="input-group-text">₹</span>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    value={data.current_balance}
                                                    onChange={e => setData('current_balance', e.target.value)}
                                                    className={`form-control ${errors.current_balance ? 'is-invalid' : ''}`}
                                                    placeholder="Leave empty to use opening balance"
                                                />
                                                {errors.current_balance && <div className="invalid-feedback">{errors.current_balance}</div>}
                                            </div>
                                            <small className="text-muted">Leave empty to use opening balance</small>
                                        </div>
                                    </div>

                                    {/* Credit Limit - Only for credit cards */}
                                    {isCreditCard && (
                                        <div className="mb-3">
                                            <label className="form-label">Credit Limit</label>
                                            <div className="input-group">
                                                <span className="input-group-text">₹</span>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    value={data.credit_limit}
                                                    onChange={e => setData('credit_limit', e.target.value)}
                                                    className={`form-control ${errors.credit_limit ? 'is-invalid' : ''}`}
                                                    placeholder="0.00"
                                                />
                                                {errors.credit_limit && <div className="invalid-feedback">{errors.credit_limit}</div>}
                                            </div>
                                        </div>
                                    )}

                                    {/* Status */}
                                    <div className="mb-3">
                                        <div className="form-check">
                                            <input
                                                type="checkbox"
                                                id="is_active"
                                                checked={data.is_active}
                                                onChange={e => setData('is_active', e.target.checked)}
                                                className="form-check-input"
                                            />
                                            <label htmlFor="is_active" className="form-check-label">
                                                Account is active
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                {/* Submit Buttons */}
                                <div className="card-footer">
                                    <div className="d-flex gap-2">
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="btn btn-success"
                                        >
                                            <i className="fas fa-check me-2"></i>
                                            {processing ? 'Creating...' : 'Create Account'}
                                        </button>
                                        <Link
                                            href="/accounts"
                                            className="btn btn-secondary"
                                        >
                                            <i className="fas fa-times me-2"></i>
                                            Cancel
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </BootstrapLayout>
    );
}
