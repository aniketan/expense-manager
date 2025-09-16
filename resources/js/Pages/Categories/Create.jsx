import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import Layout from '../../Layouts/Layout';

export default function Create({ categoryTypes, parentCategories }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        code: '',
        parent_id: '',
        description: '',
        icon: '',
        color: '#3B82F6',
        is_active: true,
    });

    const [showColorPicker, setShowColorPicker] = useState(false);

    const colorPresets = [
        '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6',
        '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16',
        '#06B6D4', '#EAB308', '#DC2626', '#059669', '#7C3AED'
    ];

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/categories');
    };

    const generateCode = () => {
        if (data.name) {
            const code = data.name.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 10);
            setData('code', code);
        }
    };

    return (
        <Layout>
            <Head title="Create Category" />
            
            <div className="max-w-2xl mx-auto">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-center space-x-2 mb-2">
                        <Link 
                            href="/categories" 
                            className="text-gray-500 hover:text-gray-700"
                        >
                            Categories
                        </Link>
                        <span className="text-gray-400">/</span>
                        <span className="text-gray-900 font-medium">Create</span>
                    </div>
                    <h1 className="text-2xl font-bold text-gray-900">Create New Category</h1>
                    <p className="text-gray-600">Add a new category to organize your transactions</p>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
                    {/* Name */}
                    <div>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                            Category Name <span className="text-red-500">*</span>
                        </label>
                        <input
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            onBlur={generateCode}
                            className={`w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 ${
                                errors.name ? 'border-red-300' : 'border-gray-300'
                            }`}
                            placeholder="e.g., Food & Dining, Salary, Transportation"
                        />
                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                    </div>

                    {/* Code */}
                    <div>
                        <label htmlFor="code" className="block text-sm font-medium text-gray-700 mb-1">
                            Category Code <span className="text-red-500">*</span>
                        </label>
                        <div className="flex space-x-2">
                            <input
                                id="code"
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                className={`flex-1 px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 font-mono ${
                                    errors.code ? 'border-red-300' : 'border-gray-300'
                                }`}
                                placeholder="FOOD"
                                maxLength="50"
                            />
                            <button
                                type="button"
                                onClick={generateCode}
                                className="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md text-sm font-medium"
                            >
                                Generate
                            </button>
                        </div>
                        {errors.code && <p className="mt-1 text-sm text-red-600">{errors.code}</p>}
                        <p className="mt-1 text-xs text-gray-500">Unique identifier for this category</p>
                    </div>

                    {/* Parent Category */}
                    <div>
                        <label htmlFor="parent_id" className="block text-sm font-medium text-gray-700 mb-1">
                            Parent Category
                        </label>
                        <select
                            id="parent_id"
                            value={data.parent_id}
                            onChange={(e) => setData('parent_id', e.target.value)}
                            className={`w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 ${
                                errors.parent_id ? 'border-red-300' : 'border-gray-300'
                            }`}
                        >
                            <option value="">None (Main Category)</option>
                            {parentCategories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                        {errors.parent_id && <p className="mt-1 text-sm text-red-600">{errors.parent_id}</p>}
                        <p className="mt-1 text-xs text-gray-500">Leave empty to create a main category</p>
                    </div>

                    {/* Description */}
                    <div>
                        <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1">
                            Description
                        </label>
                        <textarea
                            id="description"
                            rows="3"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            className={`w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 ${
                                errors.description ? 'border-red-300' : 'border-gray-300'
                            }`}
                            placeholder="Optional description for this category"
                        />
                        {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                    </div>

                    {/* Icon and Color */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Icon */}
                        <div>
                            <label htmlFor="icon" className="block text-sm font-medium text-gray-700 mb-1">
                                Icon
                            </label>
                            <input
                                id="icon"
                                type="text"
                                value={data.icon}
                                onChange={(e) => setData('icon', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 ${
                                    errors.icon ? 'border-red-300' : 'border-gray-300'
                                }`}
                                placeholder="🍔 or 💰"
                                maxLength="10"
                            />
                            {errors.icon && <p className="mt-1 text-sm text-red-600">{errors.icon}</p>}
                            <p className="mt-1 text-xs text-gray-500">Emoji or short text (optional)</p>
                        </div>

                        {/* Color */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Color
                            </label>
                            <div className="flex items-center space-x-2">
                                <div
                                    className="w-10 h-10 rounded-md border border-gray-300 cursor-pointer"
                                    style={{ backgroundColor: data.color }}
                                    onClick={() => setShowColorPicker(!showColorPicker)}
                                />
                                <input
                                    type="text"
                                    value={data.color}
                                    onChange={(e) => setData('color', e.target.value)}
                                    className="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 font-mono"
                                    placeholder="#3B82F6"
                                />
                            </div>
                            {showColorPicker && (
                                <div className="mt-2 p-2 bg-white border border-gray-300 rounded-md shadow-lg">
                                    <div className="grid grid-cols-5 gap-2">
                                        {colorPresets.map((color) => (
                                            <button
                                                key={color}
                                                type="button"
                                                className="w-8 h-8 rounded-md border border-gray-300 hover:scale-110 transition-transform"
                                                style={{ backgroundColor: color }}
                                                onClick={() => {
                                                    setData('color', color);
                                                    setShowColorPicker(false);
                                                }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                            {errors.color && <p className="mt-1 text-sm text-red-600">{errors.color}</p>}
                        </div>
                    </div>

                    {/* Active Status */}
                    <div>
                        <label className="flex items-center">
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            />
                            <span className="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                        <p className="mt-1 text-xs text-gray-500">Inactive categories won't appear in transaction forms</p>
                    </div>

                    {/* Preview */}
                    <div className="border-t border-gray-200 pt-6">
                        <h4 className="text-sm font-medium text-gray-700 mb-2">Preview</h4>
                        <div className="flex items-center space-x-3 p-3 bg-gray-50 rounded-md">
                            <div
                                className="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                                style={{ backgroundColor: data.color }}
                            >
                                {data.icon || (data.name ? data.name.charAt(0) : '?')}
                            </div>
                            <div>
                                <div className="font-medium text-gray-900">
                                    {data.name || 'Category Name'}
                                </div>
                                <div className="text-sm text-gray-500 font-mono">
                                    {data.code || 'CODE'}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                        <Link
                            href="/categories"
                            className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            {processing ? 'Creating...' : 'Create Category'}
                        </button>
                    </div>
                </form>
            </div>
        </Layout>
    );
}
