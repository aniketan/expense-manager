import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';
import FormErrorSummary from '../../Components/FormErrorSummary';
import {
    validateDate,
    validateDescription,
    sanitizeText,
    validateTags,
    handleAmountInput,
    getMaxDate,
    getMinDate,
    TRANSACTION_ERROR_ORDER,
    clearValidationFieldError,
    clearValidationFieldErrors,
    getErrorEntries,
    getTransactionErrorLabels,
    setValidationFieldError,
    validateTransactionForm,
} from '../../utils/inputValidation';

export default function Create({ categories, accounts }) {
    const [selectedCategory, setSelectedCategory] = useState('');
    const [subcategories, setSubcategories] = useState([]);
    const [transactionType, setTransactionType] = useState('expense');
    const [validationErrors, setValidationErrors] = useState({});

    const { data, setData, post, processing, errors } = useForm({
        transaction_date: new Date().toISOString().split('T')[0],
        transaction_time: new Date().toTimeString().slice(0, 5),
        amount: '',
        account_id: '',
        transfer_to_account_id: '',
        payment_method: '',
        description: '',
        category: '',
        category_id: '',
        payee_payer: '',
        reference_number: '',
        tax: '0',
        status: 'Pending',
        tags: '',
        notes: '',
        transaction_type: 'expense'
    });

    const errorLabels = getTransactionErrorLabels(transactionType);
    const errorEntries = getErrorEntries(validationErrors, errors, TRANSACTION_ERROR_ORDER);

    const setFieldError = (field, message) => {
        setValidationFieldError(setValidationErrors, field, message);
    };

    const clearFieldError = (field) => {
        clearValidationFieldError(setValidationErrors, field);
    };

    // Get parent categories (where parent_id is null)
    const parentCategories = categories.filter(cat => cat.parent_id === null);

    // Get income category
    const incomeCategory = parentCategories.find(cat => String(cat.code).toUpperCase() === 'INCOME');

    // Get expense categories (excluding income and account transfer)
    const expenseCategories = parentCategories.filter(cat => {
        const code = String(cat.code).toUpperCase();
        return code !== 'INCOME' && code !== 'ACCOUNT_TRANSFER';
    });

    // Function to get subcategories for a parent category
    const getSubcategories = (parentCategoryId) => {
        return categories.filter(cat => cat.parent_id === parentCategoryId);
    };

    const updateSubcategories = (parentCategoryId) => {
        const subs = getSubcategories(parentCategoryId);
        setSubcategories(subs);
        if (!subs || subs.length === 0) {
            setFieldError('category', 'No subcategories found for the selected category. Please pick a different category.');
            setSelectedCategory('');
            setSubcategories([]);
        }
        setData('category_id', '');
        clearFieldError('category_id');
    };

    const handleCategoryChange = (e) => {
        const parentCategoryId = parseInt(e.target.value);
        setSelectedCategory(parentCategoryId);
        clearFieldError('category');
        clearFieldError('category_id');
        updateSubcategories(parentCategoryId);
    };

    const handleTransactionTypeChange = (type) => {
        setTransactionType(type);
        setData('transaction_type', type);
        setSelectedCategory('');
        setSubcategories([]);
        setData('category_id', '');
        if (type === 'transfer') {
            setData('payee_payer', '');
        }
        clearValidationFieldErrors(setValidationErrors, [
            'category',
            'category_id',
            'payment_method',
            'transfer_to_account_id',
            'transfer',
        ]);

        // Auto-select category for income
        if (type === 'income' && incomeCategory) {
            setSelectedCategory(incomeCategory.id);
            updateSubcategories(incomeCategory.id);
        }
    };

    // Prevent number input scroll behavior
    const handleWheel = (e) => {
        e.target.blur();
    };

    // Handle amount with validation
    const handleAmountChange = (e) => {
        handleAmountInput(e, (value) => {
            setData('amount', value);
            if (validationErrors.amount) {
                clearFieldError('amount');
            }
        });
    };

    // Handle date with validation
    const handleDateChange = (e) => {
        const value = e.target.value;
        setData('transaction_date', value);

        const validation = validateDate(value, false, 10);
        if (!validation.isValid) {
            setFieldError('transaction_date', validation.error);
        } else {
            clearFieldError('transaction_date');
        }
    };

    // Handle description with validation
    const handleDescriptionChange = (e) => {
        const validation = validateDescription(e.target.value, 1000, false);
        setData('description', validation.value);
        if (!validation.isValid) {
            setFieldError('description', validation.error);
        } else {
            clearFieldError('description');
        }
    };

    // Handle tags with validation
    const handleTagsChange = (e) => {
        const validation = validateTags(e.target.value, 10, 30);
        setData('tags', validation.value);

        if (!validation.isValid) {
            setFieldError('tags', validation.error);
        } else {
            clearFieldError('tags');
        }
    };

    // Handle notes with sanitization
    const handleNotesChange = (e) => {
        const sanitized = sanitizeText(e.target.value, 2000);
        setData('notes', sanitized);
    };

    // Handle payee/payer with sanitization
    const handlePayeePayerChange = (e) => {
        const sanitized = sanitizeText(e.target.value, 255);
        setData('payee_payer', sanitized);
    };

    // Handle reference with sanitization
    const handleReferenceChange = (e) => {
        const sanitized = sanitizeText(e.target.value, 100);
        setData('reference_number', sanitized);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        const nextErrors = validateTransactionForm(data, {
            transactionType,
            selectedCategory,
            dateField: 'transaction_date',
            includeTimeInDate: true,
            maxPastYears: 10,
            maxAmount: 999999999.99,
            tagsMax: 10,
            tagMaxLength: 30,
        });

        setValidationErrors(nextErrors);

        if (Object.keys(nextErrors).length > 0) {
            return;
        }

        post('/transactions');
    };

    return (
        <BootstrapLayout>
            <Head title="Add New Transaction" />

            <div className="row">
                <div className="col-12">
                    <div className="d-flex justify-content-between align-items-center mb-4">
                        <h1>
                            <i className="fas fa-plus text-success me-3"></i>
                            Add New Transaction
                        </h1>
                        <Link href="/transactions" className="btn btn-outline-secondary">
                            <i className="fas fa-arrow-left me-2"></i>Back to Transactions
                        </Link>
                    </div>
                </div>
            </div>

            <div className="row justify-content-center">
                <div className="col-lg-8">
                    <div className="card">
                        <div className="card-header">
                            <h5 className="mb-0">
                                <i className="fas fa-form me-2"></i>Transaction Details
                            </h5>
                        </div>
                        <div className="card-body">
                            <form onSubmit={handleSubmit} noValidate>
                                <FormErrorSummary errorEntries={errorEntries} errorLabels={errorLabels} />

                                {/* Transaction Type - Moved to top */}
                                <div className="row mb-4">
                                    <div className="col-12">
                                        <h6 className="text-primary">
                                            <i className="fas fa-exchange-alt me-2"></i>Transaction Type
                                        </h6>
                                        <hr />
                                    </div>
                                </div>

                                <div className="mb-4">
                                    <div className="btn-group w-100" role="group">
                                        <input
                                            type="radio"
                                            className="btn-check"
                                            name="transaction_type"
                                            id="type_income"
                                            value="income"
                                            checked={transactionType === 'income'}
                                            onChange={() => handleTransactionTypeChange('income')}
                                        />
                                        <label className="btn btn-outline-success" htmlFor="type_income">
                                            <i className="fas fa-arrow-down me-2"></i>Income
                                        </label>

                                        <input
                                            type="radio"
                                            className="btn-check"
                                            name="transaction_type"
                                            id="type_expense"
                                            value="expense"
                                            checked={transactionType === 'expense'}
                                            onChange={() => handleTransactionTypeChange('expense')}
                                        />
                                        <label className="btn btn-outline-danger" htmlFor="type_expense">
                                            <i className="fas fa-arrow-up me-2"></i>Expense
                                        </label>

                                        <input
                                            type="radio"
                                            className="btn-check"
                                            name="transaction_type"
                                            id="type_transfer"
                                            value="transfer"
                                            checked={transactionType === 'transfer'}
                                            onChange={() => handleTransactionTypeChange('transfer')}
                                        />
                                        <label className="btn btn-outline-primary" htmlFor="type_transfer">
                                            <i className="fas fa-exchange-alt me-2"></i>Account Transfer
                                        </label>
                                    </div>
                                </div>

                                <div className="row mb-4">
                                    <div className="col-12">
                                        <h6 className="text-primary">
                                            <i className="fas fa-info-circle me-2"></i>Basic Information
                                        </h6>
                                        <hr />
                                    </div>
                                </div>

                                <div className="row mb-3">
                                    <div className="col-md-4">
                                        <label htmlFor="transaction_date" className="form-label">
                                            Date <span className="text-danger">*</span>
                                        </label>
                                        <input
                                            type="date"
                                            className={`form-control ${errors.transaction_date || validationErrors.transaction_date ? 'is-invalid' : ''}`}
                                            id="transaction_date"
                                            value={data.transaction_date}
                                            onChange={handleDateChange}
                                            max={getMaxDate()}
                                            min={getMinDate(10)}
                                            required
                                        />
                                        {(errors.transaction_date || validationErrors.transaction_date) && (
                                            <div className="invalid-feedback">
                                                {errors.transaction_date || validationErrors.transaction_date}
                                            </div>
                                        )}
                                    </div>
                                    <div className="col-md-4">
                                        <label htmlFor="transaction_time" className="form-label">Time</label>
                                        <input
                                            type="time"
                                            className={`form-control ${errors.transaction_time || validationErrors.transaction_time ? 'is-invalid' : ''}`}
                                            id="transaction_time"
                                            value={data.transaction_time}
                                            onChange={e => setData('transaction_time', e.target.value)}
                                        />
                                        {(errors.transaction_time || validationErrors.transaction_time) && (
                                            <div className="invalid-feedback d-block">
                                                {errors.transaction_time || validationErrors.transaction_time}
                                            </div>
                                        )}
                                    </div>
                                    <div className="col-md-4">
                                        <label htmlFor="amount" className="form-label">
                                            Amount (₹) <span className="text-danger">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            inputMode="decimal"
                                            className={`form-control ${errors.amount || validationErrors.amount ? 'is-invalid' : ''}`}
                                            id="amount"
                                            value={data.amount}
                                            onChange={handleAmountChange}
                                            onWheel={handleWheel}
                                            placeholder="0.00"
                                            required
                                        />
                                        {(errors.amount || validationErrors.amount) && (
                                            <div className="invalid-feedback">
                                                {errors.amount || validationErrors.amount}
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div className="row mb-3">
                                    <div className="col-md-6">
                                        <label htmlFor="account" className="form-label">
                                            {transactionType === 'transfer' ? 'From Account' : 'Account'} <span className="text-danger">*</span>
                                        </label>
                                        <select
                                            className={`form-select ${errors.account_id || validationErrors.account_id ? 'is-invalid' : ''}`}
                                            id="account"
                                            value={data.account_id}
                                            onChange={e => {
                                                setData('account_id', e.target.value);
                                                clearFieldError('account_id');
                                                clearFieldError('transfer');
                                            }}
                                            required
                                        >
                                            <option value="">Select Account</option>
                                            {accounts.map(account => (
                                                <option key={account.id} value={account.id}>
                                                    {account.name}
                                                </option>
                                            ))}
                                        </select>
                                        {(errors.account_id || validationErrors.account_id) && (
                                            <div className="invalid-feedback">
                                                {errors.account_id || validationErrors.account_id}
                                            </div>
                                        )}
                                    </div>
                                    {transactionType === 'transfer' ? (
                                        <div className="col-md-6">
                                            <label htmlFor="transfer_to_account" className="form-label">
                                                To Account <span className="text-danger">*</span>
                                            </label>
                                            <select
                                                className={`form-select ${errors.transfer_to_account_id || validationErrors.transfer_to_account_id || validationErrors.transfer ? 'is-invalid' : ''}`}
                                                id="transfer_to_account"
                                                value={data.transfer_to_account_id}
                                                onChange={e => {
                                                    setData('transfer_to_account_id', e.target.value);
                                                    clearFieldError('transfer_to_account_id');
                                                    clearFieldError('transfer');
                                                }}
                                                required
                                            >
                                                <option value="">Select Account</option>
                                                {accounts.filter(acc => acc.id != data.account_id).map(account => (
                                                    <option key={account.id} value={account.id}>
                                                        {account.name}
                                                    </option>
                                                ))}
                                            </select>
                                            {(errors.transfer_to_account_id || validationErrors.transfer_to_account_id || validationErrors.transfer) && (
                                                <div className="invalid-feedback">
                                                    {errors.transfer_to_account_id || validationErrors.transfer_to_account_id || validationErrors.transfer}
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="col-md-6">
                                            <label htmlFor="payment_method" className="form-label">
                                                Payment Method <span className="text-danger">*</span>
                                            </label>
                                            <select
                                                className={`form-select ${errors.payment_method || validationErrors.payment_method ? 'is-invalid' : ''}`}
                                                id="payment_method"
                                                value={data.payment_method}
                                                onChange={e => {
                                                    setData('payment_method', e.target.value);
                                                    clearFieldError('payment_method');
                                                }}
                                                required
                                            >
                                                <option value="">Select Method</option>
                                                <option value="UPI">UPI</option>
                                                <option value="Bank Transfer">Bank Transfer</option>
                                                <option value="Credit Card">Credit Card</option>
                                                <option value="Debit Card">Debit Card</option>
                                                <option value="Cash">Cash</option>
                                                <option value="Cheque">Cheque</option>
                                            </select>
                                            {(errors.payment_method || validationErrors.payment_method) && (
                                                <div className="invalid-feedback">
                                                    {errors.payment_method || validationErrors.payment_method}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="mb-3">
                                    <label htmlFor="description" className="form-label">
                                        Description
                                    </label>
                                    <textarea
                                        className={`form-control ${errors.description || validationErrors.description ? 'is-invalid' : ''}`}
                                        id="description"
                                        rows="3"
                                        value={data.description}
                                        onChange={handleDescriptionChange}
                                        placeholder="Optional: Add transaction details"
                                        maxLength="1000"
                                    />
                                    {validationErrors.description && <div className="invalid-feedback d-block">{validationErrors.description}</div>}
                                    {errors.description && <div className="invalid-feedback">{errors.description}</div>}
                                    <small className="text-muted">{data.description.length}/1000 characters</small>
                                </div>

                                {/* Category Section - Conditional based on transaction type */}
                                {transactionType !== 'transfer' && (
                                    <>
                                        <div className="row mb-4">
                                            <div className="col-12">
                                                <h6 className="text-primary">
                                                    <i className="fas fa-tags me-2"></i>Categorization
                                                </h6>
                                                <hr />
                                            </div>
                                        </div>

                                        {transactionType === 'income' ? (
                                            /* Income: Show only subcategory dropdown */
                                            <div className="mb-3">
                                                <label htmlFor="subcategory" className="form-label">
                                                    Income Category <span className="text-danger">*</span>
                                                </label>
                                                <select
                                                    className={`form-select ${errors.category_id || validationErrors.category_id ? 'is-invalid' : ''}`}
                                                    id="subcategory"
                                                    value={data.category_id}
                                                    onChange={e => {
                                                        setData('category_id', e.target.value);
                                                        clearFieldError('category_id');
                                                    }}
                                                    required
                                                >
                                                    <option value="">Select Income Category</option>
                                                    {subcategories.map(subcat => (
                                                        <option key={subcat.id} value={subcat.id}>
                                                            {subcat.name}
                                                        </option>
                                                    ))}
                                                </select>
                                                {(errors.category_id || validationErrors.category_id) && (
                                                    <div className="invalid-feedback">
                                                        {errors.category_id || validationErrors.category_id}
                                                    </div>
                                                )}
                                            </div>
                                        ) : (
                                            /* Expense: Show both category and subcategory */
                                            <div className="row mb-3">
                                                <div className="col-md-6">
                                                    <label htmlFor="category" className="form-label">
                                                        Category <span className="text-danger">*</span>
                                                    </label>
                                                    <select
                                                        className={`form-select ${validationErrors.category ? 'is-invalid' : ''}`}
                                                        id="category"
                                                        value={selectedCategory}
                                                        onChange={handleCategoryChange}
                                                        required
                                                    >
                                                        <option value="">Select Category</option>
                                                        {expenseCategories.map(cat => (
                                                            <option key={cat.id} value={cat.id}>
                                                                {cat.name}
                                                            </option>
                                                        ))}
                                                    </select>
                                                    {validationErrors.category && (
                                                        <div className="invalid-feedback">{validationErrors.category}</div>
                                                    )}
                                                </div>
                                                <div className="col-md-6">
                                                    <label htmlFor="subcategory" className="form-label">
                                                        Subcategory <span className="text-danger">*</span>
                                                    </label>
                                                    <select
                                                        className={`form-select ${errors.category_id || validationErrors.category_id ? 'is-invalid' : ''}`}
                                                        id="subcategory"
                                                        value={data.category_id}
                                                        onChange={e => {
                                                            setData('category_id', e.target.value);
                                                            clearFieldError('category_id');
                                                        }}
                                                        required
                                                        disabled={!selectedCategory}
                                                    >
                                                        <option value="">Select Subcategory</option>
                                                        {subcategories.map(subcat => (
                                                            <option key={subcat.id} value={subcat.id}>
                                                                {subcat.name}
                                                            </option>
                                                        ))}
                                                    </select>
                                                    {(errors.category_id || validationErrors.category_id) && (
                                                        <div className="invalid-feedback">
                                                            {errors.category_id || validationErrors.category_id}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </>
                                )}

                                {transactionType !== 'transfer' && (
                                    <div className="mb-3">
                                        <label htmlFor="payee_payer" className="form-label">Payee/Payer</label>
                                        <input
                                            type="text"
                                            className="form-control"
                                            id="payee_payer"
                                            value={data.payee_payer}
                                            onChange={handlePayeePayerChange}
                                            placeholder="Who did you pay or who paid you?"
                                            maxLength="255"
                                        />
                                    </div>
                                )}

                                <div className="row mb-4">
                                    <div className="col-12">
                                        <h6 className="text-primary">
                                            <i className="fas fa-plus-circle me-2"></i>Additional Details
                                        </h6>
                                        <hr />
                                    </div>
                                </div>

                                <div className="row mb-3">
                                    <div className="col-md-6">
                                        <label htmlFor="reference_number" className="form-label">Reference Number</label>
                                        <input
                                            type="text"
                                            className="form-control"
                                            id="reference_number"
                                            value={data.reference_number}
                                            onChange={handleReferenceChange}
                                            placeholder="Receipt number, transaction ID, etc."
                                            maxLength="100"
                                        />
                                    </div>
                                    <div className="col-md-6">
                                        <label htmlFor="tax" className="form-label">Tax Amount (₹)</label>
                                        <input
                                            type="text"
                                            inputMode="decimal"
                                            className="form-control"
                                            id="tax"
                                            value={data.tax}
                                            onChange={(e) => handleAmountInput(e, (value) => setData('tax', value))}
                                            onWheel={handleWheel}
                                            placeholder="0.00"
                                        />
                                        <small className="text-muted">Optional tax amount</small>
                                    </div>
                                </div>

                                <div className="row mb-3">
                                    <div className="col-md-6">
                                        <label htmlFor="status" className="form-label">Status</label>
                                        <select
                                            className="form-select"
                                            id="status"
                                            value={data.status}
                                            onChange={e => setData('status', e.target.value)}
                                        >
                                            <option value="Pending">Pending</option>
                                            <option value="Cleared">Cleared</option>
                                            <option value="Cancelled">Cancelled</option>
                                        </select>
                                    </div>
                                    <div className="col-md-6">
                                        <label htmlFor="tags" className="form-label">Tags</label>
                                        <input
                                            type="text"
                                            className={`form-control ${validationErrors.tags ? 'is-invalid' : ''}`}
                                            id="tags"
                                            value={data.tags}
                                            onChange={handleTagsChange}
                                            placeholder="comma, separated, tags"
                                        />
                                        {validationErrors.tags && (
                                            <div className="invalid-feedback">{validationErrors.tags}</div>
                                        )}
                                        <div className="form-text">Use commas to separate multiple tags (max 10)</div>
                                    </div>
                                </div>

                                <div className="mb-3">
                                    <label htmlFor="notes" className="form-label">Notes</label>
                                    <textarea
                                        className="form-control"
                                        id="notes"
                                        rows="3"
                                        value={data.notes}
                                        onChange={handleNotesChange}
                                        placeholder="Additional notes about this transaction"
                                        maxLength="2000"
                                    />
                                    <small className="text-muted">{data.notes.length}/2000 characters</small>
                                </div>

                                <div className="row mt-4">
                                    <div className="col-12 text-end">
                                        <button type="button" className="btn btn-outline-secondary me-2"
                                                onClick={() => window.history.back()}>
                                            <i className="fas fa-times me-2"></i>Cancel
                                        </button>
                                        <button type="submit" className="btn btn-success" disabled={processing}>
                                            <i className="fas fa-plus me-2"></i>
                                            {processing ? 'Adding...' : 'Add Transaction'}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div className="row mt-4">
                <div className="col-12">
                    <div className="card bg-light">
                        <div className="card-body">
                            <h6 className="mb-3">
                                <i className="fas fa-lightbulb me-2"></i>Quick Actions & Tips
                            </h6>
                            <div className="row">
                                <div className="col-md-4">
                                    <button type="button" className="btn btn-outline-primary btn-sm w-100 mb-2">
                                        <i className="fas fa-magic me-1"></i>Fill Sample Data
                                    </button>
                                </div>
                                <div className="col-md-4">
                                    <button type="button" className="btn btn-outline-info btn-sm w-100 mb-2" disabled>
                                        <i className="fas fa-copy me-1"></i>Duplicate & Edit
                                    </button>
                                </div>
                                <div className="col-md-4">
                                    <button type="button" className="btn btn-outline-warning btn-sm w-100 mb-2">
                                        <i className="fas fa-eraser me-1"></i>Clear Form
                                    </button>
                                </div>
                            </div>
                            <small className="text-muted">
                                <i className="fas fa-info-circle me-1"></i>
                                <strong>Tip:</strong> Use tags to organize transactions for easy searching later.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </BootstrapLayout>
    );
}
