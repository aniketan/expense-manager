import React from 'react';
import { Link, usePage } from '@inertiajs/react';

export default function Layout({ children }) {
    const { url } = usePage();

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Navigation */}
            <nav className="bg-white shadow-sm border-b border-gray-200">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16">
                        <div className="flex">
                            <div className="flex-shrink-0 flex items-center">
                                <Link href="/" className="text-xl font-bold text-indigo-600">
                                    Expense Manager
                                </Link>
                            </div>
                            <div className="ml-6 flex space-x-4">
                                <Link 
                                    href="/accounts" 
                                    className={`px-3 py-2 text-sm font-medium rounded-md ${
                                        url.startsWith('/accounts') 
                                            ? 'text-indigo-600 bg-indigo-50' 
                                            : 'text-gray-900 hover:text-indigo-600'
                                    }`}
                                >
                                    Accounts
                                </Link>
                                <Link 
                                    href="/categories" 
                                    className={`px-3 py-2 text-sm font-medium rounded-md ${
                                        url.startsWith('/categories') 
                                            ? 'text-indigo-600 bg-indigo-50' 
                                            : 'text-gray-900 hover:text-indigo-600'
                                    }`}
                                >
                                    Categories
                                </Link>
                                <Link 
                                    href="/expenses" 
                                    className={`px-3 py-2 text-sm font-medium rounded-md ${
                                        url.startsWith('/expenses') 
                                            ? 'text-indigo-600 bg-indigo-50' 
                                            : 'text-gray-900 hover:text-indigo-600'
                                    }`}
                                >
                                    Expenses
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            {/* Main Content */}
            <main className="py-10">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {children}
                </div>
            </main>
        </div>
    );
}
