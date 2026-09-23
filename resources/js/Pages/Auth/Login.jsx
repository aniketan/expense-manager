import React from 'react';
import { Head, useForm } from '@inertiajs/react';

export default function Login({ passwordConfigured }) {
    const { data, setData, post, processing, errors } = useForm({ password: '' });

    const submit = (e) => {
        e.preventDefault();
        post('/login', { onFinish: () => setData('password', '') });
    };

    return (
        <div className="min-vh-100 d-flex align-items-center justify-content-center bg-light">
            <Head title="Log in" />
            <div className="card shadow-sm" style={{ width: '100%', maxWidth: 380 }}>
                <div className="card-body p-4">
                    <h1 className="h4 mb-4 text-center">
                        <i className="fas fa-chart-line me-2 text-primary"></i>Expense Manager
                    </h1>

                    {!passwordConfigured ? (
                        <div className="alert alert-warning mb-0" role="alert">
                            No login password is set. Add <code>APP_LOGIN_PASSWORD=...</code> to <code>.env</code>,
                            then run <code>php artisan config:clear</code> and reload this page.
                        </div>
                    ) : (
                        <form onSubmit={submit}>
                            <label htmlFor="password" className="form-label">Password</label>
                            <input
                                id="password"
                                type="password"
                                className={`form-control ${errors.password ? 'is-invalid' : ''}`}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoComplete="current-password"
                                autoFocus
                                required
                            />
                            {errors.password && <div className="invalid-feedback">{errors.password}</div>}
                            <button type="submit" className="btn btn-primary w-100 mt-3" disabled={processing}>
                                <i className="fas fa-sign-in-alt me-1"></i>Log in
                            </button>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
}
