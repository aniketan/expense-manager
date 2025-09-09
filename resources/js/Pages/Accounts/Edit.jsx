import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';

export default function Edit({ account, accountTypes }) {
    const { data, setData, put, processing, errors } = useForm({
        code: account.code || '',
        name: account.name || '',
        type: account.type || 'savings',
        bank_name: account.bank_name || '',
        account_number: account.account_number || '',
        ifsc_code: account.ifsc_code || '',
        opening_balance: account.opening_balance || '0.00',
        current_balance: account.current_balance || '0.00',
        credit_limit: account.credit_limit || '0.00',
        is_active: account.is_active ?? true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/accounts/${account.id}`);
    };

    const isCreditCard = data.type === 'credit_card';
    const isCash = data.type === 'cash';

    return (
        <Layout>
            <Head title={`Edit ${account.name}`} />
            
            <div className="max-w-2xl mx-auto">
                {/* Header */}
                <div className="mb-6">
                    <div className="flex items-center space-x-3 mb-2">
                        <Link 
                            href="/accounts" 
                            className="text-gray-500 hover:text-gray-700"
                        >
                            ← Back to Accounts
                        </Link>
                    </div>
                    <h1 className="text-2xl font-bold text-gray-900">Edit Account</h1>
                    <p className="text-gray-600">Update account information</p>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
                    {/* Basic Information */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Account Code *
                            </label>
                            <input
                                type="text"
                                value={data.code}
                                onChange={e => setData('code', e.target.value.toUpperCase())}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                placeholder="e.g., SBI01, CASH"
                                required
                            />
                            {errors.code && <p className="text-red-600 text-sm mt-1">{errors.code}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Account Type *
                            </label>
                            <select
                                value={data.type}
                                onChange={e => setData('type', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                required
                            >
                                {Object.entries(accountTypes).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                            {errors.type && <p className="text-red-600 text-sm mt-1">{errors.type}</p>}
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Account Name *
                        </label>
                        <input
                            type="text"
                            value={data.name}
                            onChange={e => setData('name', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="e.g., SBI Savings Account, Cash Wallet"
                            required
                        />
                        {errors.name && <p className="text-red-600 text-sm mt-1">{errors.name}</p>}
                    </div>

                    {/* Bank Details - Hide for cash accounts */}
                    {!isCash && (
                        <>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Bank Name
                                </label>
                                <input
                                    type="text"
                                    value={data.bank_name}
                                    onChange={e => setData('bank_name', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    placeholder="e.g., State Bank of India"
                                />
                                {errors.bank_name && <p className="text-red-600 text-sm mt-1">{errors.bank_name}</p>}
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Account Number
                                    </label>
                                    <input
                                        type="text"
                                        value={data.account_number}
                                        onChange={e => setData('account_number', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        placeholder={isCreditCard ? "****-****-****-1234" : "Account number"}
                                    />
                                    {errors.account_number && <p className="text-red-600 text-sm mt-1">{errors.account_number}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        IFSC Code
                                    </label>
                                    <input
                                        type="text"
                                        value={data.ifsc_code}
                                        onChange={e => setData('ifsc_code', e.target.value.toUpperCase())}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        placeholder="e.g., SBIN0001234"
                                    />
                                    {errors.ifsc_code && <p className="text-red-600 text-sm mt-1">{errors.ifsc_code}</p>}
                                </div>
                            </div>
                        </>
                    )}

                    {/* Balance Information */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Opening Balance
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                value={data.opening_balance}
                                onChange={e => setData('opening_balance', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                placeholder="0.00"
                            />
                            {errors.opening_balance && <p className="text-red-600 text-sm mt-1">{errors.opening_balance}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Current Balance
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                value={data.current_balance}
                                onChange={e => setData('current_balance', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                placeholder="0.00"
                            />
                            {errors.current_balance && <p className="text-red-600 text-sm mt-1">{errors.current_balance}</p>}
                        </div>
                    </div>

                    {/* Credit Limit - Only for credit cards */}
                    {isCreditCard && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Credit Limit
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                value={data.credit_limit}
                                onChange={e => setData('credit_limit', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                placeholder="0.00"
                            />
                            {errors.credit_limit && <p className="text-red-600 text-sm mt-1">{errors.credit_limit}</p>}
                        </div>
                    )}

                    {/* Status */}
                    <div className="flex items-center">
                        <input
                            type="checkbox"
                            id="is_active"
                            checked={data.is_active}
                            onChange={e => setData('is_active', e.target.checked)}
                            className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                        />
                        <label htmlFor="is_active" className="ml-2 block text-sm text-gray-700">
                            Account is active
                        </label>
                    </div>

                    {/* Submit Buttons */}
                    <div className="flex space-x-3 pt-4">
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white px-4 py-2 rounded-md font-medium"
                        >
                            {processing ? 'Updating...' : 'Update Account'}
                        </button>
                        <Link
                            href="/accounts"
                            className="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md font-medium"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </Layout>
    );
}
