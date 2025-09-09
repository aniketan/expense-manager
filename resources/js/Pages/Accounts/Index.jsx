import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';

export default function Index({ accounts, success }) {
    const handleDelete = (account) => {
        if (window.confirm(`Are you sure you want to delete ${account.name}?`)) {
            router.delete(`/accounts/${account.id}`);
        }
    };

    const handleToggleStatus = (account) => {
        router.patch(`/accounts/${account.id}/toggle-status`);
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

    const formatCurrency = (amount) => {
        return `₹${parseFloat(amount).toLocaleString('en-IN', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        })}`;
    };

    return (
        <Layout>
            <Head title="Accounts" />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Accounts</h1>
                        <p className="text-gray-600">Manage your bank accounts, credit cards, and cash accounts</p>
                    </div>
                    <Link
                        href="/accounts/create"
                        className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium"
                    >
                        Add New Account
                    </Link>
                </div>

                {/* Success Message */}
                {success && (
                    <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md">
                        {success}
                    </div>
                )}

                {/* Accounts Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {accounts.map((account) => (
                        <div key={account.id} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <div className="flex items-start justify-between">
                                <div className="flex-1">
                                    <div className="flex items-center space-x-2 mb-2">
                                        <h3 className="text-lg font-semibold text-gray-900">
                                            {account.name}
                                        </h3>
                                        <span className={`px-2 py-1 text-xs font-medium rounded-full ${getAccountTypeColor(account.type)}`}>
                                            {account.type_label || account.type}
                                        </span>
                                    </div>
                                    
                                    <p className="text-sm text-gray-500 mb-1">
                                        Code: <span className="font-mono">{account.code}</span>
                                    </p>
                                    
                                    {account.bank_name && (
                                        <p className="text-sm text-gray-500 mb-1">
                                            Bank: {account.bank_name}
                                        </p>
                                    )}
                                    
                                    {account.account_number && (
                                        <p className="text-sm text-gray-500 mb-3">
                                            A/C: {account.account_number}
                                        </p>
                                    )}
                                </div>
                                
                                <div className={`w-3 h-3 rounded-full ${account.is_active ? 'bg-green-400' : 'bg-red-400'}`}></div>
                            </div>

                            {/* Balance Information */}
                            <div className="space-y-2 mb-4">
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-600">Current Balance:</span>
                                    <span className={`text-sm font-semibold ${
                                        account.current_balance >= 0 ? 'text-green-600' : 'text-red-600'
                                    }`}>
                                        {formatCurrency(account.current_balance)}
                                    </span>
                                </div>
                                
                                {account.type === 'credit_card' && account.credit_limit > 0 && (
                                    <div className="flex justify-between">
                                        <span className="text-sm text-gray-600">Credit Limit:</span>
                                        <span className="text-sm text-gray-900">
                                            {formatCurrency(account.credit_limit)}
                                        </span>
                                    </div>
                                )}
                            </div>

                            {/* Actions */}
                            <div className="flex space-x-2">
                                <Link
                                    href={`/accounts/${account.id}`}
                                    className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-2 rounded text-sm font-medium text-center"
                                >
                                    View
                                </Link>
                                <Link
                                    href={`/accounts/${account.id}/edit`}
                                    className="flex-1 bg-indigo-100 hover:bg-indigo-200 text-indigo-800 px-3 py-2 rounded text-sm font-medium text-center"
                                >
                                    Edit
                                </Link>
                                <button
                                    onClick={() => handleToggleStatus(account)}
                                    className={`flex-1 px-3 py-2 rounded text-sm font-medium ${
                                        account.is_active
                                            ? 'bg-yellow-100 hover:bg-yellow-200 text-yellow-800'
                                            : 'bg-green-100 hover:bg-green-200 text-green-800'
                                    }`}
                                >
                                    {account.is_active ? 'Disable' : 'Enable'}
                                </button>
                                <button
                                    onClick={() => handleDelete(account)}
                                    className="px-3 py-2 bg-red-100 hover:bg-red-200 text-red-800 rounded text-sm font-medium"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Empty State */}
                {accounts.length === 0 && (
                    <div className="text-center py-12">
                        <div className="text-gray-400 text-6xl mb-4">💳</div>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No accounts yet</h3>
                        <p className="text-gray-600 mb-4">Get started by creating your first account</p>
                        <Link
                            href="/accounts/create"
                            className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium"
                        >
                            Add New Account
                        </Link>
                    </div>
                )}
            </div>
        </Layout>
    );
}
