import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';
import {
    validateAmount,
    validateDate,
    validateTime,
    sanitizeText,
    validateTags,
    handleAmountInput,
    getMaxDate,
    getMinDate
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

    // Get parent categories (where parent_id is null)
    const parentCategories = categories.filter(cat => cat.parent_id === null);

    // Get income category
    const incomeCategory = parentCategories.find(cat => cat.code === 'INCOME');

    // Get expense categories (excluding income and account transfer)
    const expenseCategories = parentCategories.filter(cat =>
        cat.code !== 'INCOME' && cat.code !== 'ACCOUNTTR'
    );

    // Function to get subcategories for a parent category
    const getSubcategories = (parentCategoryId) => {
        return categories.filter(cat => cat.parent_id === parentCategoryId);
    };

    const updateSubcategories = (parentCategoryId) => {
        const subs = getSubcategories(parentCategoryId);
        setSubcategories(subs);
        if (!subs || subs.length === 0) {
            alert('No subcategories found for the selected category. Please pick a different category.');
            setSelectedCategory('');
            setSubcategories([]);
        }
        setData('category_id', '');
    };

    const handleCategoryChange = (e) => {
        const parentCategoryId = parseInt(e.target.value);
        setSelectedCategory(parentCategoryId);
        updateSubcategories(parentCategoryId);
    };

    const handleTransactionTypeChange = (type) => {
        setTransactionType(type);
        setData('transaction_type', type);
        setSelectedCategory('');
        setSubcategories([]);
        setData('category_id', '');

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
                setValidationErrors({ ...validationErrors, amount: null });
            }
        });
    };

    // Handle date with validation
    const handleDateChange = (e) => {
        const value = e.target.value;
        setData('transaction_date', value);

        const validation = validateDate(value, false, 10);
        if (!validation.isValid) {
            setValidationErrors({ ...validationErrors, transaction_date: validation.error });
        } else {
            setValidationErrors({ ...validationErrors, transaction_date: null });
        }
    };

    // Handle description with validation
    const handleDescriptionChange = (e) => {
        const validation = validateDescription(e.target.value, 1000, false);
        setData('description', validation.value);
        if (!validation.isValid) {
            setValidationErrors({ ...validationErrors, description: validation.error });
        } else {
            setValidationErrors({ ...validationErrors, description: null });
        }
    };

    // Handle tags with validation
    const handleTagsChange = (e) => {
        const validation = validateTags(e.target.value, 10, 30);
        setData('tags', validation.value);

        if (!validation.isValid) {
            setValidationErrors({ ...validationErrors, tags: validation.error });
        } else {
            setValidationErrors({ ...validationErrors, tags: null });
        }
    };

    // Handle notes with sanitization
    const handleNotesChange = (e) => {
        const sanitized = sanitizeText(e.target.value, 2000);
        setData('notes', sanitized);
    };

    // Handle payee/payer with sanitization
    const handlePayeePayerChange = (e) => {
        const sanitized = sanitizeText(e.target.value, 100);
        setData('payee_payer', sanitized);
    };

    // Handle reference with sanitization
    const handleReferenceChange = (e) => {
        const sanitized = sanitizeText(e.target.value, 50);
        setData('reference_number', sanitized);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        // Validate amount
        const amountValidation = validateAmount(data.amount, false, 999999999.99);
        if (!amountValidation.isValid) {
            setValidationErrors({ ...validationErrors, amount: amountValidation.error });
            return;
        }

        // Validate date
        const dateValidation = validateDate(data.transaction_date, false, 10,data.transaction_time);
        if (!dateValidation.isValid) {
            setValidationErrors({ ...validationErrors, transaction_date: dateValidation.error });
            return;
        }

        // Validate time if provided
        if (data.transaction_time) {
            const timeValidation = validateTime(data.transaction_time);
            if (!timeValidation.isValid) {
                setValidationErrors({ ...validationErrors, transaction_time: timeValidation.error });
                return;
            }
        }

        // Validate category for non-transfer
        if (transactionType !== 'transfer' && !data.category_id) {
            alert('Please select a category');
            return;
        }

        // Validate transfer accounts
        if (transactionType === 'transfer') {
            if (!data.transfer_to_account_id) {
                alert('Please select destination account');
                return;
            }
            if (data.account_id === data.transfer_to_account_id) {
                alert('Source and destination accounts must be different');
                return;
            }
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
                            <form onSubmit={handleSubmit}>
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
                                            className={`form-select ${errors.account_id ? 'is-invalid' : ''}`}
                                            id="account"
                                            value={data.account_id}
                                            onChange={e => setData('account_id', e.target.value)}
                                            required
                                        >
                                            <option value="">Select Account</option>
                                            {accounts.map(account => (
                                                <option key={account.id} value={account.id}>
                                                    {account.name}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.account_id && <div className="invalid-feedback">{errors.account_id}</div>}
                                    </div>
                                    {transactionType === 'transfer' ? (
                                        <div className="col-md-6">
                                            <label htmlFor="transfer_to_account" className="form-label">
                                                To Account <span className="text-danger">*</span>
                                            </label>
                                            <select
                                                className={`form-select ${errors.transfer_to_account_id ? 'is-invalid' : ''}`}
                                                id="transfer_to_account"
                                                value={data.transfer_to_account_id}
                                                onChange={e => setData('transfer_to_account_id', e.target.value)}
                                                required
                                            >
                                                <option value="">Select Account</option>
                                                {accounts.filter(acc => acc.id != data.account_id).map(account => (
                                                    <option key={account.id} value={account.id}>
                                                        {account.name}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors.transfer_to_account_id && <div className="invalid-feedback">{errors.transfer_to_account_id}</div>}
                                        </div>
                                    ) : (
                                        <div className="col-md-6">
                                            <label htmlFor="payment_method" className="form-label">
                                                Payment Method <span className="text-danger">*</span>
                                            </label>
                                            <select
                                                className={`form-select ${errors.payment_method ? 'is-invalid' : ''}`}
                                                id="payment_method"
                                                value={data.payment_method}
                                                onChange={e => setData('payment_method', e.target.value)}
                                                required
                                            >
                                                <option value="">Select Method</option>
                                                <option value="UPI">UPI</option>
                                                <option value="Bank Transfer">Bank Transfer</option>
                                                <option value="Credit Card">Credit Card</option>
                                                <option value="Debit Card">Debit Card</option>
                                                <option value="Cheque">Cheque</option>
                                            </select>
                                            {errors.payment_method && <div className="invalid-feedback">{errors.payment_method}</div>}
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
                                                    className="form-select"
                                                    id="subcategory"
                                                    value={data.category_id}
                                                    onChange={e => setData('category_id', e.target.value)}
                                                    required
                                                >
                                                    <option value="">Select Income Category</option>
                                                    {subcategories.map(subcat => (
                                                        <option key={subcat.id} value={subcat.id}>
                                                            {subcat.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        ) : (
                                            /* Expense: Show both category and subcategory */
                                            <div className="row mb-3">
                                                <div className="col-md-6">
                                                    <label htmlFor="category" className="form-label">
                                                        Category <span className="text-danger">*</span>
                                                    </label>
                                                    <select
                                                        className="form-select"
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
                                                </div>
                                                <div className="col-md-6">
                                                    <label htmlFor="subcategory" className="form-label">
                                                        Subcategory <span className="text-danger">*</span>
                                                    </label>
                                                    <select
                                                        className="form-select"
                                                        id="subcategory"
                                                        value={data.category_id}
                                                        onChange={e => setData('category_id', e.target.value)}
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
                                                </div>
                                            </div>
                                        )}
                                    </>
                                )}

                                <div className="mb-3">
                                    <label htmlFor="payee_payer" className="form-label">Payee/Payer</label>
                                    <input
                                        type="text"
                                        className="form-control"
                                        id="payee_payer"
                                        value={data.payee_payer}
                                        onChange={handlePayeePayerChange}
                                        placeholder="Who did you pay or who paid you?"
                                        maxLength="100"
                                    />
                                </div>

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
                                            maxLength="50"
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
