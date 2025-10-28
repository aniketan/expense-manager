import React, { useState, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';

export default function Edit({ transaction, categories, accounts }) {
    const [selectedCategory, setSelectedCategory] = useState('');
    const [subcategories, setSubcategories] = useState([]);

    const { data, setData, put, processing, errors } = useForm({
        expensed_date: transaction.transaction_date || '',
        transaction_time: transaction.transaction_time || '',
        amount: transaction.amount || '',
        account_id: transaction.account_id || '',
        payment_method: transaction.payment_method || '',
        description: transaction.description || '',
        category_id: transaction.category_id || '',
        payee_payer: transaction.payee_payer || '',
        reference_number: transaction.reference_number || '',
        tax: transaction.tax || '0',
        status: transaction.status || 'Pending',
        tags: transaction.tags || '',
        notes: transaction.notes || ''
    });

    // Get parent categories (where parent_id is null)
    const parentCategories = categories.filter(cat => cat.parent_id === null);
    
    // Function to get subcategories for a parent category
    const getSubcategories = (parentCategoryId) => {
        return categories.filter(cat => cat.parent_id === parentCategoryId);
    };

    // Function to find parent category by subcategory ID
    const findParentCategoryBySubcategoryId = (subcategoryId) => {
        const subcategory = categories.find(cat => cat.id === parseInt(subcategoryId));
        if (subcategory && subcategory.parent_id) {
            return categories.find(cat => cat.id === subcategory.parent_id);
        }
        return null;
    };

    // Initialize category and subcategories on component mount
    useEffect(() => {
        if (transaction.category_id && categories.length > 0) {
            const categoryId = parseInt(transaction.category_id);
            const parentCategory = findParentCategoryBySubcategoryId(categoryId);
            
            if (parentCategory) {
                setSelectedCategory(parentCategory.id);
                const subs = getSubcategories(parentCategory.id);
                setSubcategories(subs);
            } else {
                // If no parent found, the category_id might be a parent category itself
                const category = categories.find(cat => cat.id === categoryId);
                if (category && category.parent_id === null) {
                    setSelectedCategory(category.id);
                    setSubcategories(getSubcategories(category.id));
                }
            }
        }
    }, [transaction.category_id, categories]);

    const handleCategoryChange = (e) => {
        const parentCategoryId = parseInt(e.target.value);
        setSelectedCategory(parentCategoryId);
        
        if (parentCategoryId) {
            const subs = getSubcategories(parentCategoryId);
            setSubcategories(subs);
        } else {
            setSubcategories([]);
        }
        
        // Reset subcategory when parent category changes
        setData('category_id', '');
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/transactions/${transaction.id}`);
    };

    return (
        <BootstrapLayout>
            <Head title={`Edit Transaction #${transaction.id}`} />
            
            <div className="row">
                <div className="col-12">
                    <div className="d-flex justify-content-between align-items-center mb-4">
                        <h1>
                            <i className="fas fa-edit text-warning me-3"></i>
                            Edit Transaction #{transaction.id}
                        </h1>
                        <div>
                            <Link href={`/transactions/${transaction.id}`} className="btn btn-outline-info me-2">
                                <i className="fas fa-eye me-2"></i>View Transaction
                            </Link>
                            <Link href="/transactions" className="btn btn-outline-secondary">
                                <i className="fas fa-arrow-left me-2"></i>Back to Transactions
                            </Link>
                        </div>
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
                                            className={`form-control ${errors.expensed_date ? 'is-invalid' : ''}`}
                                            id="expensed_date" 
                                            value={data.expensed_date}
                                            onChange={e => setData('expensed_date', e.target.value)}
                                            required 
                                        />
                                        {errors.expensed_date && <div className="invalid-feedback">{errors.expensed_date}</div>}
                                    </div>
                                    <div className="col-md-4">
                                        <label htmlFor="transaction_time" className="form-label">Time</label>
                                        <input 
                                            type="time" 
                                            className={`form-control ${errors.transaction_time ? 'is-invalid' : ''}`}
                                            id="transaction_time" 
                                            value={data.transaction_time}
                                            onChange={e => setData('transaction_time', e.target.value)}
                                        />
                                        {errors.transaction_time && <div className="invalid-feedback">{errors.transaction_time}</div>}
                                    </div>
                                    <div className="col-md-4">
                                        <label htmlFor="amount" className="form-label">
                                            Amount (₹) <span className="text-danger">*</span>
                                        </label>
                                        <input 
                                            type="number" 
                                            className={`form-control ${errors.amount ? 'is-invalid' : ''}`}
                                            id="amount" 
                                            value={data.amount}
                                            onChange={e => setData('amount', e.target.value)}
                                            step="0.01" 
                                            min="0" 
                                            required 
                                        />
                                        {errors.amount && <div className="invalid-feedback">{errors.amount}</div>}
                                    </div>
                                </div>
                                
                                <div className="row mb-3">
                                    <div className="col-md-6">
                                        <label htmlFor="account" className="form-label">
                                            Account <span className="text-danger">*</span>
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
                                                    {account.account_name}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.account_id && <div className="invalid-feedback">{errors.account_id}</div>}
                                    </div>
                                    <div className="col-md-6">
                                        <label htmlFor="payment_method" className="form-label">Payment Method</label>
                                        <select 
                                            className={`form-select ${errors.payment_method ? 'is-invalid' : ''}`}
                                            id="payment_method"
                                            value={data.payment_method}
                                            onChange={e => setData('payment_method', e.target.value)}
                                        >
                                            <option value="">Select Method</option>
                                            <option value="UPI">UPI</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                            <option value="Credit Card">Credit Card</option>
                                            <option value="Debit Card">Debit Card</option>
                                            <option value="Cash">Cash</option>
                                            <option value="Cheque">Cheque</option>
                                        </select>
                                        {errors.payment_method && <div className="invalid-feedback">{errors.payment_method}</div>}
                                    </div>
                                </div>
                                
                                <div className="mb-3">
                                    <label htmlFor="description" className="form-label">
                                        Description <span className="text-danger">*</span>
                                    </label>
                                    <textarea 
                                        className={`form-control ${errors.description ? 'is-invalid' : ''}`}
                                        id="description" 
                                        rows="3" 
                                        value={data.description}
                                        onChange={e => setData('description', e.target.value)}
                                        required
                                    />
                                    {errors.description && <div className="invalid-feedback">{errors.description}</div>}
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
                                            className={`form-select ${errors.category ? 'is-invalid' : ''}`}
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
                                        {errors.category && <div className="invalid-feedback">{errors.category}</div>}
                                    </div>
                                    <div className="col-md-6">
                                        <label htmlFor="subcategory" className="form-label">
                                            Subcategory <span className="text-danger">*</span>
                                        </label>
                                        <select 
                                            className={`form-select ${errors.category_id ? 'is-invalid' : ''}`}
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
                                        {errors.category_id && <div className="invalid-feedback">{errors.category_id}</div>}
                                    </div>
                                </div>
                                
                                <div className="mb-3">
                                    <label htmlFor="payee_payer" className="form-label">Payee/Payer</label>
                                    <input 
                                        type="text" 
                                        className={`form-control ${errors.payee_payer ? 'is-invalid' : ''}`}
                                        id="payee_payer" 
                                        value={data.payee_payer}
                                        onChange={e => setData('payee_payer', e.target.value)}
                                        placeholder="Who did you pay or who paid you?" 
                                    />
                                    {errors.payee_payer && <div className="invalid-feedback">{errors.payee_payer}</div>}
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
                                            className={`form-control ${errors.reference_number ? 'is-invalid' : ''}`}
                                            id="reference_number" 
                                            value={data.reference_number}
                                            onChange={e => setData('reference_number', e.target.value)}
                                            placeholder="Receipt number, transaction ID, etc." 
                                        />
                                        {errors.reference_number && <div className="invalid-feedback">{errors.reference_number}</div>}
                                    </div>
                                    <div className="col-md-6">
                                        <label htmlFor="tax" className="form-label">Tax Amount (₹)</label>
                                        <input 
                                            type="number" 
                                            className={`form-control ${errors.tax ? 'is-invalid' : ''}`}
                                            id="tax" 
                                            value={data.tax}
                                            onChange={e => setData('tax', e.target.value)}
                                            step="0.01" 
                                            min="0" 
                                        />
                                        {errors.tax && <div className="invalid-feedback">{errors.tax}</div>}
                                    </div>
                                </div>
                                
                                <div className="row mb-3">
                                    <div className="col-md-6">
                                        <label htmlFor="status" className="form-label">Status</label>
                                        <select 
                                            className={`form-select ${errors.status ? 'is-invalid' : ''}`}
                                            id="status"
                                            value={data.status}
                                            onChange={e => setData('status', e.target.value)}
                                        >
                                            <option value="Pending">Pending</option>
                                            <option value="Cleared">Cleared</option>
                                            <option value="Cancelled">Cancelled</option>
                                        </select>
                                        {errors.status && <div className="invalid-feedback">{errors.status}</div>}
                                    </div>
                                    <div className="col-md-6">
                                        <label htmlFor="tags" className="form-label">Tags</label>
                                        <input 
                                            type="text" 
                                            className={`form-control ${errors.tags ? 'is-invalid' : ''}`}
                                            id="tags" 
                                            value={data.tags}
                                            onChange={e => setData('tags', e.target.value)}
                                            placeholder="comma, separated, tags" 
                                        />
                                        <div className="form-text">Use commas to separate multiple tags</div>
                                        {errors.tags && <div className="invalid-feedback">{errors.tags}</div>}
                                    </div>
                                </div>
                                
                                <div className="mb-3">
                                    <label htmlFor="notes" className="form-label">Notes</label>
                                    <textarea 
                                        className={`form-control ${errors.notes ? 'is-invalid' : ''}`}
                                        id="notes" 
                                        rows="3" 
                                        value={data.notes}
                                        onChange={e => setData('notes', e.target.value)}
                                        placeholder="Additional notes about this transaction"
                                    />
                                    {errors.notes && <div className="invalid-feedback">{errors.notes}</div>}
                                </div>
                                
                                <div className="row mt-4">
                                    <div className="col-12 text-end">
                                        <button type="button" className="btn btn-outline-secondary me-2" 
                                                onClick={() => window.history.back()}>
                                            <i className="fas fa-times me-2"></i>Cancel
                                        </button>
                                        <button type="submit" className="btn btn-warning" disabled={processing}>
                                            <i className="fas fa-save me-2"></i>
                                            {processing ? 'Updating...' : 'Update Transaction'}
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
                                <i className="fas fa-lightbulb me-2"></i>Edit Actions & Tips
                            </h6>
                            <div className="row">
                                <div className="col-md-4">
                                    <button type="button" className="btn btn-outline-primary btn-sm w-100 mb-2">
                                        <i className="fas fa-copy me-1"></i>Duplicate Transaction
                                    </button>
                                </div>
                                <div className="col-md-4">
                                    <button type="button" className="btn btn-outline-danger btn-sm w-100 mb-2">
                                        <i className="fas fa-trash me-1"></i>Delete Transaction
                                    </button>
                                </div>
                                <div className="col-md-4">
                                    <button type="button" className="btn btn-outline-info btn-sm w-100 mb-2">
                                        <i className="fas fa-history me-1"></i>View History
                                    </button>
                                </div>
                            </div>
                            <small className="text-muted">
                                <i className="fas fa-info-circle me-1"></i>
                                <strong>Tip:</strong> Make sure to update the category if you change the nature of the transaction.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </BootstrapLayout>
    );
}