import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';
import 'font-awesome/css/font-awesome.min.css';


export default function Index({ categories, success, error }) {
    const handleDelete = (category) => {
        if (window.confirm(`Are you sure you want to delete ${category.name}? This will also delete all subcategories.`)) {
            router.delete(`/categories/${category.id}`);
        }
    };

    const handleToggleStatus = (category) => {
        router.patch(`/categories/${category.id}/toggle-status`);
    };

    const IconComponent = ({ iconClass }) => {
        return <i className={iconClass} aria-hidden="true"></i>;
    }

    const getCategoryTypeColor = (type) => {
        const colors = {
            income: 'bg-green-100 text-green-800',
            expense: 'bg-red-100 text-red-800',
            both: 'bg-blue-100 text-blue-800'
        };
        return colors[type] || 'bg-gray-100 text-gray-800';
    };

    const groupCategories = (categories) => {
        const parentCategories = categories.filter(cat => !cat.parent_id);
        const childCategories = categories.filter(cat => cat.parent_id);
        
        return parentCategories.map(parent => ({
            ...parent,
            children: childCategories.filter(child => child.parent_id === parent.id)
        }));
    };

    const groupedCategories = groupCategories(categories);

    return (
        <Layout>
            <Head title="Categories" />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Categories</h1>
                        <p className="text-gray-600">Manage your income and expense categories</p>
                    </div>
                    <Link
                        href="/categories/create"
                        className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium"
                    >
                        Add New Category
                    </Link>
                </div>

                {/* Success Message */}
                {success && (
                    <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md">
                        {success}
                    </div>
                )}

                {/* Error Message */}
                {error && (
                    <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
                        {error}
                    </div>
                )}

                {/* Categories List */}
                {groupedCategories.length > 0 ? (
                    <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div className="divide-y divide-gray-200">
                            {groupedCategories.map((parentCategory) => (
                                <div key={parentCategory.id}>
                                    {/* Parent Category */}
                                    <div className="p-6 bg-gray-50">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center space-x-4">
                                                <IconComponent iconClass={parentCategory.icon} />
                                          
                                                <div>
                                                    <div className="flex items-center space-x-3">
                                                        <h3 className="text-lg font-semibold text-gray-900">
                                                            {parentCategory.name}
                                                        </h3>
                                                        <span className="text-sm text-gray-500 font-mono">
                                                            {parentCategory.code}
                                                        </span>
                                                        <div className={`w-2 h-2 rounded-full ${parentCategory.is_active ? 'bg-green-400' : 'bg-red-400'}`}></div>
                                                    </div>
                                                    {parentCategory.description && (
                                                        <p className="text-sm text-gray-600 mt-1">
                                                            {parentCategory.description}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <Link
                                                    href={`/categories/${parentCategory.id}`}
                                                    className="text-indigo-600 hover:text-indigo-900 text-sm font-medium"
                                                >
                                                    View
                                                </Link>
                                                <Link
                                                    href={`/categories/${parentCategory.id}/edit`}
                                                    className="text-yellow-600 hover:text-yellow-900 text-sm font-medium"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    onClick={() => handleToggleStatus(parentCategory)}
                                                    className={`text-sm font-medium ${
                                                        parentCategory.is_active 
                                                            ? 'text-red-600 hover:text-red-900' 
                                                            : 'text-green-600 hover:text-green-900'
                                                    }`}
                                                >
                                                    {parentCategory.is_active ? 'Deactivate' : 'Activate'}
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(parentCategory)}
                                                    className="text-red-600 hover:text-red-900 text-sm font-medium"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Child Categories */}
                                    {parentCategory.children.length > 0 && (
                                        <div className="divide-y divide-gray-100">
                                            {parentCategory.children.map((childCategory) => (
                                                <div key={childCategory.id} className="p-4 pl-12">
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center space-x-3">
                                                           
                                                              <IconComponent iconClass={childCategory.icon} />
                                          
                                                            <div>
                                                                <div className="flex items-center space-x-3">
                                                                    <span className="text-gray-900 font-medium">
                                                                        {childCategory.name}
                                                                    </span>
                                                                    <span className="text-sm text-gray-500 font-mono">
                                                                        {childCategory.code}
                                                                    </span>
                                                                    <div className={`w-2 h-2 rounded-full ${childCategory.is_active ? 'bg-green-400' : 'bg-red-400'}`}></div>
                                                                </div>
                                                                {childCategory.description && (
                                                                    <p className="text-sm text-gray-600 mt-1">
                                                                        {childCategory.description}
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div className="flex items-center space-x-2">
                                                            <Link
                                                                href={`/categories/${childCategory.id}`}
                                                                className="text-indigo-600 hover:text-indigo-900 text-sm font-medium"
                                                            >
                                                                View
                                                            </Link>
                                                            <Link
                                                                href={`/categories/${childCategory.id}/edit`}
                                                                className="text-yellow-600 hover:text-yellow-900 text-sm font-medium"
                                                            >
                                                                Edit
                                                            </Link>
                                                            <button
                                                                onClick={() => handleToggleStatus(childCategory)}
                                                                className={`text-sm font-medium ${
                                                                    childCategory.is_active 
                                                                        ? 'text-red-600 hover:text-red-900' 
                                                                        : 'text-green-600 hover:text-green-900'
                                                                }`}
                                                            >
                                                                {childCategory.is_active ? 'Deactivate' : 'Activate'}
                                                            </button>
                                                            <button
                                                                onClick={() => handleDelete(childCategory)}
                                                                className="text-red-600 hover:text-red-900 text-sm font-medium"
                                                            >
                                                                Delete
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {/* Add Subcategory Button */}
                                    <div className="px-6 py-3 bg-gray-50 border-t border-gray-200">
                                        <Link
                                            href={`/categories/create?parent_id=${parentCategory.id}`}
                                            className="text-sm text-indigo-600 hover:text-indigo-900 font-medium"
                                        >
                                            + Add subcategory to {parentCategory.name}
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ) : (
                    <div className="text-center py-12">
                        <div className="text-gray-400 mb-4">
                            <svg className="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </div>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No categories found</h3>
                        <p className="text-gray-500 mb-4">Get started by creating your first category.</p>
                        <Link
                            href="/categories/create"
                            className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium"
                        >
                            Create First Category
                        </Link>
                    </div>
                )}
            </div>
        </Layout>
    );
}
