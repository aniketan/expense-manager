import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';

export default function Create({ categories, accounts }) {
    const [selectedCategory, setSelectedCategory] = useState('');
    const [subcategories, setSubcategories] = useState([]);

    const { data, setData, post, processing, errors } = useForm({
        expensed_date: new Date().toISOString().split('T')[0],
        transaction_time: new Date().toTimeString().slice(0, 5),
        amount: '',
        account_id: '',
        payment_method: '',
        description: '',
        category: '',
        category_id: '',
        payee_payer: '',
        reference_number: '',
        tax: '0',
        status: 'Pending',
        tags: '',
        notes: ''
    });

    // Get parent categories (where parent_id is null)
    const parentCategories = categories.filter(cat => cat.parent_id === null);
    
    // Function to get subcategories for a parent category
    const getSubcategories = (parentCategoryId) => {
        return categories.filter(cat => cat.parent_id === parentCategoryId);
    };

    const updateSubcategories = (parentCategoryId) => {
        const subs = getSubcategories(parentCategoryId);
        setSubcategories(subs);
        setData('category_id', '');
    };

    const handleCategoryChange = (e) => {
        const parentCategoryId = parseInt(e.target.value);
        setSelectedCategory(parentCategoryId);
        updateSubcategories(parentCategoryId);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
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
                                        <label htmlFor="expensed_date" className="form-label">
                                            Date <span className="text-danger">*</span>
                                        </label>
                                        <input 
                                            type="date" 
                                            className="form-control" 
                                            id="expensed_date" 
                                            value={data.expensed_date}
                                            onChange={e => setData('expensed_date', e.target.value)}
                                            required 
                                        />
                                    </div>
                                    <div className="col-md-4">
                                        <label htmlFor="transaction_time" className="form-label">Time</label>
                                        <input 
                                            type="time" 
                                            className="form-control" 
                                            id="transaction_time" 
                                            value={data.transaction_time}
                                            onChange={e => setData('transaction_time', e.target.value)}
                                        />
                                    </div>
                                    <div className="col-md-4">
                                        <label htmlFor="amount" className="form-label">
                                            Amount (₹) <span className="text-danger">*</span>
                                        </label>
                                        <input 
                                            type="number" 
                                            className="form-control" 
                                            id="amount" 
                                            value={data.amount}
                                            onChange={e => setData('amount', e.target.value)}
                                            step="0.01" 
                                            min="0" 
                                            required 
                                        />
                                    </div>
                                </div>
                                
                                <div className="row mb-3">
                                    <div className="col-md-6">
                                        <label htmlFor="account" className="form-label">
                                            Account <span className="text-danger">*</span>
                                        </label>
                                        <select 
                                            className="form-select" 
                                            id="account" 
                                            value={data.account_id}
                                            onChange={e => setData('account_id', e.target.value)}
                                            required
                                        >
                                            <option value="">Select Account</option>
                                            {accounts.map(account => (
                                                <option key={account.id} value={account.id}>
                                                    {account.account_name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="col-md-6">
                                        <label htmlFor="payment_method" className="form-label">Payment Method</label>
                                        <select 
                                            className="form-select" 
                                            id="payment_method"
                                            value={data.payment_method}
                                            onChange={e => setData('payment_method', e.target.value)}
                                        >
                                            <option value="">Select Method</option>
                                            <option value="UPI">UPI</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                            <option value="Credit Card">Credit Card</option>
                                            <option value="Debit Card">Debit Card</option>
                                            <option value="Cheque">Cheque</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div className="mb-3">
                                    <label htmlFor="description" className="form-label">
                                        Description <span className="text-danger">*</span>
                                    </label>
                                    <textarea 
                                        className="form-control" 
                                        id="description" 
                                        rows="3" 
                                        value={data.description}
                                        onChange={e => setData('description', e.target.value)}
                                        required
                                    />
                                </div>
                                
                                <div className="row mb-4">
                                    <div className="col-12">
                                        <h6 className="text-primary">
                                            <i className="fas fa-tags me-2"></i>Categorization
                                        </h6>
                                        <hr />
                                    </div>
                                </div>
                                
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
                                            {parentCategories.map(cat => (
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
                                
                                <div className="mb-3">
                                    <label htmlFor="payee_payer" className="form-label">Payee/Payer</label>
                                    <input 
                                        type="text" 
                                        className="form-control" 
                                        id="payee_payer" 
                                        value={data.payee_payer}
                                        onChange={e => setData('payee_payer', e.target.value)}
                                        placeholder="Who did you pay or who paid you?" 
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
                                            onChange={e => setData('reference_number', e.target.value)}
                                            placeholder="Receipt number, transaction ID, etc." 
                                        />
                                    </div>
                                    <div className="col-md-6">
                                        <label htmlFor="tax" className="form-label">Tax Amount (₹)</label>
                                        <input 
                                            type="number" 
                                            className="form-control" 
                                            id="tax" 
                                            value={data.tax}
                                            onChange={e => setData('tax', e.target.value)}
                                            step="0.01" 
                                            min="0" 
                                        />
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
                                            className="form-control" 
                                            id="tags" 
                                            value={data.tags}
                                            onChange={e => setData('tags', e.target.value)}
                                            placeholder="comma, separated, tags" 
                                        />
                                        <div className="form-text">Use commas to separate multiple tags</div>
                                    </div>
                                </div>
                                
                                <div className="mb-3">
                                    <label htmlFor="notes" className="form-label">Notes</label>
                                    <textarea 
                                        className="form-control" 
                                        id="notes" 
                                        rows="3" 
                                        value={data.notes}
                                        onChange={e => setData('notes', e.target.value)}
                                        placeholder="Additional notes about this transaction"
                                    />
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
