import React from 'react';
import { Head, Link } from '@inertiajs/react';
import Layout from '../Layouts/Layout';

export default function Welcome() {
    return (
        <Layout>
            <Head title="Welcome to Expense Manager" />
            
            <div className="text-center">
                <h1 className="text-4xl font-bold text-gray-900 mb-4">
                    Welcome to Expense Manager
                </h1>
                <p className="text-xl text-gray-600 mb-8">
                    Manage your finances with ease
                </p>
                
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-4xl mx-auto">
                    <Link 
                        href="/accounts"
                        className="bg-white p-6 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow"
                    >
                        <div className="text-3xl mb-4">💳</div>
                        <h2 className="text-lg font-semibold text-gray-900 mb-2">Accounts</h2>
                        <p className="text-gray-600">Manage your bank accounts, credit cards, and cash</p>
                    </Link>
                    
                    <Link 
                        href="/categories"
                        className="bg-white p-6 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow"
                    >
                        <div className="text-3xl mb-4">📁</div>
                        <h2 className="text-lg font-semibold text-gray-900 mb-2">Categories</h2>
                        <p className="text-gray-600">Organize your expenses into categories</p>
                    </Link>
                    
                    <Link 
                        href="/expenses"
                        className="bg-white p-6 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow"
                    >
                        <div className="text-3xl mb-4">💰</div>
                        <h2 className="text-lg font-semibold text-gray-900 mb-2">Expenses</h2>
                        <p className="text-gray-600">Track and manage your daily expenses</p>
                    </Link>
                </div>
            </div>
        </Layout>
    );
}
