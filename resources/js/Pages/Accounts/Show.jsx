import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';

export default function Show({ account }) {
    const handleDelete = () => {
        if (window.confirm(`Are you sure you want to delete ${account.name}?`)) {
            router.delete(`/accounts/${account.id}`);
        }
    };

    const handleToggleStatus = () => {
        router.patch(`/accounts/${account.id}/toggle-status`);
    };

    const formatCurrency = (amount) => {
        return `₹${parseFloat(amount).toLocaleString('en-IN', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        })}`;
    };

    const getAccountTypeColor = (type) => {
        const colors = {
            savings: 'bg-green-100 text-green-800',
            current: 'bg-blue-100 text-blue-800',
            credit_card: 'bg-purple-100 text-purple-800',
            cash: 'bg-yellow-100 text-yellow-800',
            investment: 'bg-indigo-100 text-indigo-800'
        };
        return colors[type] || 'bg-gray-100 text-gray-800';
    };

    const getAccountTypeLabel = (type) => {
        const types = {
            savings: 'Savings',
            current: 'Current',
            credit_card: 'Credit Card',
            cash: 'Cash',
            investment: 'Investment'
        };
        return types[type] || type;
    };

    return (
        <Layout>
            <Head title={account.name} />
            
            <div className="max-w-4xl mx-auto">
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
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-3">
                            <h1 className="text-2xl font-bold text-gray-900">{account.name}</h1>
                            <span className={`px-3 py-1 text-sm font-medium rounded-full ${getAccountTypeColor(account.type)}`}>
                                {getAccountTypeLabel(account.type)}
                            </span>
                            <div className={`w-3 h-3 rounded-full ${account.is_active ? 'bg-green-400' : 'bg-red-400'}`}></div>
                        </div>
                        <div className="flex space-x-2">
                            <Link
                                href={`/accounts/${account.id}/edit`}
                                className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium"
                            >
                                Edit Account
                            </Link>
                            <button
                                onClick={handleToggleStatus}
                                className={`px-4 py-2 rounded-md font-medium ${
                                    account.is_active
                                        ? 'bg-yellow-600 hover:bg-yellow-700 text-white'
                                        : 'bg-green-600 hover:bg-green-700 text-white'
                                }`}
                            >
                                {account.is_active ? 'Disable' : 'Enable'}
                            </button>
                            <button
                                onClick={handleDelete}
                                className="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-medium"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Account Details */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Basic Information */}
                        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Account Information</h2>
                            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Account Code</dt>
                                    <dd className="mt-1 text-sm text-gray-900 font-mono">{account.code}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Account Type</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{getAccountTypeLabel(account.type)}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Status</dt>
                                    <dd className="mt-1">
                                        <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                                            account.is_active 
                                                ? 'bg-green-100 text-green-800' 
                                                : 'bg-red-100 text-red-800'
                                        }`}>
                                            {account.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Created</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {new Date(account.created_at).toLocaleDateString('en-IN', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric'
                                        })}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        {/* Bank Details */}
                        {account.type !== 'cash' && (account.bank_name || account.account_number || account.ifsc_code) && (
                            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                                <h2 className="text-lg font-semibold text-gray-900 mb-4">Bank Details</h2>
                                <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    {account.bank_name && (
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Bank Name</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{account.bank_name}</dd>
                                        </div>
                                    )}
                                    {account.account_number && (
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Account Number</dt>
                                            <dd className="mt-1 text-sm text-gray-900 font-mono">{account.account_number}</dd>
                                        </div>
                                    )}
                                    {account.ifsc_code && (
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">IFSC Code</dt>
                                            <dd className="mt-1 text-sm text-gray-900 font-mono">{account.ifsc_code}</dd>
                                        </div>
                                    )}
                                </dl>
                            </div>
                        )}
                    </div>

                    {/* Balance Information */}
                    <div className="space-y-6">
                        {/* Current Balance */}
                        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Balance Overview</h2>
                            <div className="space-y-4">
                                <div className="text-center p-4 bg-gray-50 rounded-lg">
                                    <p className="text-sm text-gray-600 mb-1">Current Balance</p>
                                    <p className={`text-2xl font-bold ${
                                        account.current_balance >= 0 ? 'text-green-600' : 'text-red-600'
                                    }`}>
                                        {formatCurrency(account.current_balance)}
                                    </p>
                                </div>
                                
                                <div className="border-t pt-4">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600">Opening Balance:</span>
                                        <span className="text-gray-900 font-medium">
                                            {formatCurrency(account.opening_balance)}
                                        </span>
                                    </div>
                                    
                                    {account.type === 'credit_card' && account.credit_limit > 0 && (
                                        <>
                                            <div className="flex justify-between text-sm mt-2">
                                                <span className="text-gray-600">Credit Limit:</span>
                                                <span className="text-gray-900 font-medium">
                                                    {formatCurrency(account.credit_limit)}
                                                </span>
                                            </div>
                                            <div className="flex justify-between text-sm mt-2">
                                                <span className="text-gray-600">Available Credit:</span>
                                                <span className="text-green-600 font-medium">
                                                    {formatCurrency(account.credit_limit + account.current_balance)}
                                                </span>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Quick Actions */}
                        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
                            <div className="space-y-2">
                                <Link
                                    href={`/expenses/create?account=${account.id}`}
                                    className="w-full bg-blue-50 hover:bg-blue-100 text-blue-700 px-4 py-3 rounded-md font-medium text-center block"
                                >
                                    Add Expense
                                </Link>
                                <Link
                                    href={`/expenses?account=${account.id}`}
                                    className="w-full bg-gray-50 hover:bg-gray-100 text-gray-700 px-4 py-3 rounded-md font-medium text-center block"
                                >
                                    View Transactions
                                </Link>
                                <Link
                                    href={`/accounts/${account.id}/edit`}
                                    className="w-full bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-4 py-3 rounded-md font-medium text-center block"
                                >
                                    Edit Account
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Layout>
    );
}
