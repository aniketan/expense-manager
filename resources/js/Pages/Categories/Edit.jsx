import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';

export default function Edit({ category, categoryTypes, parentCategories }) {
    const { data, setData, put, processing, errors } = useForm({
        name: category.name || '',
        code: category.code || '',
        parent_id: category.parent_id || '',
        description: category.description || '',
        icon: category.icon || '',
        color: category.color || '#3B82F6',
        is_active: category.is_active ?? true,
    });

    const [showColorPicker, setShowColorPicker] = useState(false);

    const colorPresets = [
        '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6',
        '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16',
        '#06B6D4', '#EAB308', '#DC2626', '#059669', '#7C3AED'
    ];

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/categories/${category.id}`);
    };

    const generateCode = () => {
        if (data.name) {
            const code = data.name.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 10);
            setData('code', code);
        }
    };

    return (
        <BootstrapLayout>
            <Head title={`Edit Category - ${category.name}`} />
            
            <div className="row">
                <div className="col-12">
                    <div className="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 className="h2 mb-1">
                                <i className="fas fa-edit text-warning me-3"></i>
                                Edit Category
                            </h1>
                            <nav aria-label="breadcrumb">
                                <ol className="breadcrumb">
                                    <li className="breadcrumb-item">
                                        <Link href="/categories" className="text-decoration-none">
                                            Categories
                                        </Link>
                                    </li>
                                    <li className="breadcrumb-item active" aria-current="page">
                                        Edit {category.name}
                                    </li>
                                </ol>
                            </nav>
                        </div>
                        <div>
                            <Link href={`/categories/${category.id}`} className="btn btn-outline-info me-2">
                                <i className="fas fa-eye me-2"></i>View Category
                            </Link>
                            <Link href="/categories" className="btn btn-outline-secondary">
                                <i className="fas fa-arrow-left me-2"></i>Back to Categories
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
                                <i className="fas fa-form me-2"></i>Category Details
                            </h5>
                        </div>
                        <div className="card-body">
                            <form onSubmit={handleSubmit}>
                                <div className="row mb-3">
                                    <div className="col-md-8">
                                        <label htmlFor="name" className="form-label">
                                            Category Name <span className="text-danger">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            className={`form-control ${errors.name ? 'is-invalid' : ''}`}
                                            id="name"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            onBlur={generateCode}
                                            required
                                        />
                                        {errors.name && <div className="invalid-feedback">{errors.name}</div>}
                                    </div>
                                    <div className="col-md-4">
                                        <label htmlFor="code" className="form-label">
                                            Code <span className="text-danger">*</span>
                                        </label>
                                        <div className="input-group">
                                            <input
                                                type="text"
                                                className={`form-control ${errors.code ? 'is-invalid' : ''}`}
                                                id="code"
                                                value={data.code}
                                                onChange={(e) => setData('code', e.target.value)}
                                                required
                                            />
                                            <button
                                                type="button"
                                                className="btn btn-outline-secondary"
                                                onClick={generateCode}
                                                title="Generate code from name"
                                            >
                                                <i className="fas fa-magic"></i>
                                            </button>
                                        </div>
                                        {errors.code && <div className="invalid-feedback">{errors.code}</div>}
                                    </div>
                                </div>

                                <div className="row mb-3">
                                    <div className="col-md-6">
                                        <label htmlFor="parent_id" className="form-label">Parent Category</label>
                                        <select
                                            className={`form-select ${errors.parent_id ? 'is-invalid' : ''}`}
                                            id="parent_id"
                                            value={data.parent_id}
                                            onChange={(e) => setData('parent_id', e.target.value)}
                                        >
                                            <option value="">None (Top Level Category)</option>
                                            {parentCategories.map((parent) => (
                                                <option key={parent.id} value={parent.id}>
                                                    {parent.name}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.parent_id && <div className="invalid-feedback">{errors.parent_id}</div>}
                                        <div className="form-text">
                                            Leave empty to create a top-level category
                                        </div>
                                    </div>
                                    <div className="col-md-6">
                                        <label htmlFor="icon" className="form-label">Icon (FontAwesome)</label>
                                        <div className="input-group">
                                            <span className="input-group-text">
                                                <i className={data.icon || 'fas fa-tag'}></i>
                                            </span>
                                            <input
                                                type="text"
                                                className={`form-control ${errors.icon ? 'is-invalid' : ''}`}
                                                id="icon"
                                                value={data.icon}
                                                onChange={(e) => setData('icon', e.target.value)}
                                                placeholder="fas fa-tag"
                                            />
                                        </div>
                                        {errors.icon && <div className="invalid-feedback">{errors.icon}</div>}
                                        <div className="form-text">
                                            Use FontAwesome classes (e.g., fas fa-home, fas fa-car)
                                        </div>
                                    </div>
                                </div>

                                <div className="mb-3">
                                    <label htmlFor="description" className="form-label">Description</label>
                                    <textarea
                                        className={`form-control ${errors.description ? 'is-invalid' : ''}`}
                                        id="description"
                                        rows="3"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        placeholder="Optional description for this category"
                                    ></textarea>
                                    {errors.description && <div className="invalid-feedback">{errors.description}</div>}
                                </div>

                                <div className="row mb-3">
                                    <div className="col-md-6">
                                        <label className="form-label">Color</label>
                                        <div className="d-flex align-items-center">
                                            <input
                                                type="color"
                                                className="form-control form-control-color me-2"
                                                value={data.color}
                                                onChange={(e) => setData('color', e.target.value)}
                                                style={{ width: '60px' }}
                                            />
                                            <button
                                                type="button"
                                                className="btn btn-outline-secondary btn-sm"
                                                onClick={() => setShowColorPicker(!showColorPicker)}
                                            >
                                                Presets
                                            </button>
                                        </div>
                                        {showColorPicker && (
                                            <div className="mt-2 p-2 border rounded">
                                                <div className="row g-1">
                                                    {colorPresets.map((color) => (
                                                        <div key={color} className="col-auto">
                                                            <button
                                                                type="button"
                                                                className="btn p-1"
                                                                style={{
                                                                    backgroundColor: color,
                                                                    width: '30px',
                                                                    height: '30px',
                                                                    border: data.color === color ? '2px solid #000' : '1px solid #ddd'
                                                                }}
                                                                onClick={() => {
                                                                    setData('color', color);
                                                                    setShowColorPicker(false);
                                                                }}
                                                            ></button>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                        {errors.color && <div className="invalid-feedback">{errors.color}</div>}
                                    </div>
                                    <div className="col-md-6">
                                        <label className="form-label">Status</label>
                                        <div className="form-check form-switch">
                                            <input
                                                className="form-check-input"
                                                type="checkbox"
                                                id="is_active"
                                                checked={data.is_active}
                                                onChange={(e) => setData('is_active', e.target.checked)}
                                            />
                                            <label className="form-check-label" htmlFor="is_active">
                                                {data.is_active ? 'Active' : 'Inactive'}
                                            </label>
                                        </div>
                                        <div className="form-text">
                                            Inactive categories won't appear in transaction forms
                                        </div>
                                    </div>
                                </div>

                                <div className="row mt-4">
                                    <div className="col-12 text-end">
                                        <button type="button" className="btn btn-outline-secondary me-2" 
                                                onClick={() => window.history.back()}>
                                            <i className="fas fa-times me-2"></i>Cancel
                                        </button>
                                        <button type="submit" className="btn btn-warning" disabled={processing}>
                                            <i className="fas fa-save me-2"></i>
                                            {processing ? 'Updating...' : 'Update Category'}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {/* Preview Card */}
            <div className="row mt-4">
                <div className="col-lg-8 offset-lg-2">
                    <div className="card">
                        <div className="card-header">
                            <h6 className="mb-0">
                                <i className="fas fa-eye me-2"></i>Preview
                            </h6>
                        </div>
                        <div className="card-body">
                            <div className="d-flex align-items-center">
                                <div 
                                    className="me-3 d-flex align-items-center justify-content-center"
                                    style={{
                                        backgroundColor: data.color,
                                        color: 'white',
                                        width: '40px',
                                        height: '40px',
                                        borderRadius: '8px'
                                    }}
                                >
                                    <i className={data.icon || 'fas fa-tag'}></i>
                                </div>
                                <div>
                                    <h6 className="mb-0">{data.name || 'Category Name'}</h6>
                                    <small className="text-muted">
                                        Code: {data.code || 'AUTO'} | 
                                        Status: <span className={`badge ${data.is_active ? 'bg-success' : 'bg-danger'}`}>
                                            {data.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </small>
                                    {data.description && (
                                        <p className="text-muted small mt-1 mb-0">{data.description}</p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </BootstrapLayout>
    );
}