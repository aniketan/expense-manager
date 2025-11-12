import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';
import {
    validateAmount,
    validateName,
    validateCode,
    validateIFSC,
    sanitizeText,
    handleAmountInput
} from '../../utils/inputValidation';

export default function Create({ accountTypes }) {
    const [validationErrors, setValidationErrors] = useState({});

    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        type: 'savings',
        bank_name: '',
        account_number: '',
        ifsc_code: '',
        opening_balance: '0.00',
        current_balance: '',
        credit_limit: '0.00',
        is_active: true,
    });

    // Handle code with validation
    const handleCodeChange = (e) => {
        const validation = validateCode(e.target.value.toUpperCase(), 20);
        setData('code', validation.value || e.target.value.toUpperCase());

        if (!validation.isValid) {
            setValidationErrors({ ...validationErrors, code: validation.error });
        } else {
            setValidationErrors({ ...validationErrors, code: null });
        }
    };

    // Handle name with validation
    const handleNameChange = (e) => {
        const sanitized = sanitizeText(e.target.value, 100);
        setData('name', sanitized);

        const validation = validateName(sanitized, 2, 100);
        if (!validation.isValid) {
            setValidationErrors({ ...validationErrors, name: validation.error });
        } else {
            setValidationErrors({ ...validationErrors, name: null });
        }
    };

    // Handle bank name with sanitization
    const handleBankNameChange = (e) => {
        const sanitized = sanitizeText(e.target.value, 100);
        setData('bank_name', sanitized);
    };

    // Handle account number with sanitization
    const handleAccountNumberChange = (e) => {
        const sanitized = sanitizeText(e.target.value, 50);
        setData('account_number', sanitized);
    };

    // Handle IFSC with validation
    const handleIFSCChange = (e) => {
        const validation = validateIFSC(e.target.value.toUpperCase(), false);
        setData('ifsc_code', validation.value);

        if (!validation.isValid && e.target.value) {
            setValidationErrors({ ...validationErrors, ifsc_code: validation.error });
        } else {
            setValidationErrors({ ...validationErrors, ifsc_code: null });
        }
    };

    // Prevent scroll on number inputs
    const handleWheel = (e) => {
        e.target.blur();
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        // Validate code
        const codeValidation = validateCode(data.code, 20);
        if (!codeValidation.isValid) {
            setValidationErrors({ ...validationErrors, code: codeValidation.error });
            return;
        }

        // Validate name
        const nameValidation = validateName(data.name, 2, 100);
        if (!nameValidation.isValid) {
            setValidationErrors({ ...validationErrors, name: nameValidation.error });
            return;
        }

        // Validate IFSC if provided
        if (data.ifsc_code) {
            const ifscValidation = validateIFSC(data.ifsc_code, false);
            if (!ifscValidation.isValid) {
                setValidationErrors({ ...validationErrors, ifsc_code: ifscValidation.error });
                return;
            }
        }

        // Set current_balance to opening_balance if not provided
        const submitData = {
            ...data,
            current_balance: data.current_balance || data.opening_balance
        };

        post('/accounts', submitData);
    };

    const isCreditCard = data.type === 'credit_card';
    const isCash = data.type === 'cash';

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
                                                value={data.code}
                                                onChange={handleCodeChange}
                                                className={`form-control ${errors.code || validationErrors.code ? 'is-invalid' : ''}`}
                                                placeholder="e.g., SBI01, CASH"
                                                maxLength="20"
                                                required
                                            />
                                            {(errors.code || validationErrors.code) && (
                                                <div className="invalid-feedback">
                                                    {errors.code || validationErrors.code}
                                                </div>
                                            )}
                                            <small className="text-muted">Letters, numbers, hyphens, underscores only</small>
                                        </div>

                                        <div className="col-md-6">
                                            <label className="form-label">
                                                Account Type <span className="text-danger">*</span>
                                            </label>
                                            <select
                                                value={data.type}
                                                onChange={e => setData('type', e.target.value)}
                                                className={`form-select ${errors.type ? 'is-invalid' : ''}`}
                                                required
                                            >
                                                {Object.entries(accountTypes).map(([value, label]) => (
                                                    <option key={value} value={value}>{label}</option>
                                                ))}
                                            </select>
                                            {errors.type && <div className="invalid-feedback">{errors.type}</div>}
                                        </div>
                                    </div>

                                    <div className="mb-3">
                                        <label className="form-label">
                                            Account Name <span className="text-danger">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={handleNameChange}
                                            className={`form-control ${errors.name || validationErrors.name ? 'is-invalid' : ''}`}
                                            placeholder="e.g., SBI Savings Account, Cash Wallet"
                                            maxLength="100"
                                            required
                                        />
                                        {(errors.name || validationErrors.name) && (
                                            <div className="invalid-feedback">
                                                {errors.name || validationErrors.name}
                                            </div>
                                        )}
                                    </div>

                                    {/* Bank Details - Hide for cash accounts */}
                                    {!isCash && (
                                        <>
                                            <div className="mb-3">
                                                <label className="form-label">Bank Name</label>
                                                <input
                                                    type="text"
                                                    value={data.bank_name}
                                                    onChange={handleBankNameChange}
                                                    className={`form-control ${errors.bank_name ? 'is-invalid' : ''}`}
                                                    placeholder="e.g., State Bank of India"
                                                    maxLength="100"
                                                />
                                                {errors.bank_name && <div className="invalid-feedback">{errors.bank_name}</div>}
                                            </div>

                                            <div className="row mb-3">
                                                <div className="col-md-6">
                                                    <label className="form-label">Account Number</label>
                                                    <input
                                                        type="text"
                                                        value={data.account_number}
                                                        onChange={handleAccountNumberChange}
                                                        className={`form-control ${errors.account_number ? 'is-invalid' : ''}`}
                                                        placeholder={isCreditCard ? "****-****-****-1234" : "Account number"}
                                                        maxLength="50"
                                                    />
                                                    {errors.account_number && <div className="invalid-feedback">{errors.account_number}</div>}
                                                </div>

                                                <div className="col-md-6">
                                                    <label className="form-label">IFSC Code</label>
                                                    <input
                                                        type="text"
                                                        value={data.ifsc_code}
                                                        onChange={handleIFSCChange}
                                                        className={`form-control ${errors.ifsc_code || validationErrors.ifsc_code ? 'is-invalid' : ''}`}
                                                        placeholder="e.g., SBIN0001234"
                                                        maxLength="11"
                                                    />
                                                    {(errors.ifsc_code || validationErrors.ifsc_code) && (
                                                        <div className="invalid-feedback">
                                                            {errors.ifsc_code || validationErrors.ifsc_code}
                                                        </div>
                                                    )}
                                                    <small className="text-muted">Format: ABCD0123456</small>
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
                                                    type="text"
                                                    inputMode="decimal"
                                                    value={data.opening_balance}
                                                    onChange={(e) => handleAmountInput(e, (value) => setData('opening_balance', value))}
                                                    onWheel={handleWheel}
                                                    className={`form-control ${errors.opening_balance ? 'is-invalid' : ''}`}
                                                    placeholder="0.00"
                                                />
                                                {errors.opening_balance && <div className="invalid-feedback">{errors.opening_balance}</div>}
                                            </div>
                                            <small className="text-muted">Can be negative for liabilities</small>
                                        </div>

                                        <div className="col-md-6">
                                            <label className="form-label">Current Balance</label>
                                            <div className="input-group">
                                                <span className="input-group-text">₹</span>
                                                <input
                                                    type="text"
                                                    inputMode="decimal"
                                                    value={data.current_balance}
                                                    onChange={(e) => handleAmountInput(e, (value) => setData('current_balance', value))}
                                                    onWheel={handleWheel}
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
                                                    type="text"
                                                    inputMode="decimal"
                                                    value={data.credit_limit}
                                                    onChange={(e) => handleAmountInput(e, (value) => setData('credit_limit', value))}
                                                    onWheel={handleWheel}
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
