import React, { useState, useRef } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import BootstrapLayout from '../../Layouts/BootstrapLayout';

export default function Upload() {
    const { errors } = usePage().props;
    const [file, setFile] = useState(null);
    const [dragging, setDragging] = useState(false);
    const [loading, setLoading] = useState(false);
    const fileRef = useRef();

    const handleFile = (f) => {
        if (!f) return;
        const ok =
            f.type === 'application/pdf' ||
            f.type === 'text/csv' ||
            f.type === 'application/vnd.ms-excel' ||
            f.name?.toLowerCase().endsWith('.csv') ||
            f.name?.toLowerCase().endsWith('.pdf');
        if (ok) {
            setFile(f);
        } else {
            alert('Please upload a PDF or CSV file.');
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setDragging(false);
        handleFile(e.dataTransfer.files[0]);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!file) return;
        setLoading(true);
        router.post('/statements/process', { statement: file }, {
            forceFormData: true,
            onFinish: () => setLoading(false),
        });
    };

    return (
        <BootstrapLayout>
            <Head title="Import Bank Statement" />
            <div className="row justify-content-center">
                <div className="col-lg-7">
                    <div className="card shadow-sm">
                        <div className="card-header" style={{ background: 'linear-gradient(135deg,#667eea,#764ba2)', color: '#fff' }}>
                            <h4 className="mb-0">
                                <i className="fas fa-file-import me-2"></i>Import Bank Statement
                            </h4>
                            <small style={{ opacity: 0.85 }}>PDF or CSV — AI will extract transactions automatically</small>
                        </div>
                        <div className="card-body p-4">
                            <form onSubmit={handleSubmit}>
                                <div
                                    onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                                    onDragLeave={() => setDragging(false)}
                                    onDrop={handleDrop}
                                    onClick={() => fileRef.current?.click()}
                                    style={{
                                        border: `2px dashed ${dragging ? '#667eea' : '#dee2e6'}`,
                                        borderRadius: 12,
                                        padding: '48px 24px',
                                        textAlign: 'center',
                                        cursor: 'pointer',
                                        background: dragging ? '#f0f4ff' : '#f8f9fa',
                                        transition: 'all 0.2s',
                                    }}
                                >
                                    <input
                                        ref={fileRef}
                                        type="file"
                                        accept=".pdf,.csv"
                                        style={{ display: 'none' }}
                                        onChange={(e) => handleFile(e.target.files[0])}
                                    />
                                    {file ? (
                                        <>
                                            <i className="fas fa-file-check fa-3x mb-3" style={{ color: '#667eea' }}></i>
                                            <h5 style={{ color: '#374151' }}>{file.name}</h5>
                                            <p className="text-muted mb-0">{(file.size / 1024).toFixed(1)} KB</p>
                                        </>
                                    ) : (
                                        <>
                                            <i className="fas fa-cloud-upload-alt fa-3x mb-3 text-muted"></i>
                                            <h5 className="text-muted">Drop your statement here</h5>
                                            <p className="text-muted small mb-0">or click to browse · PDF or CSV · Max 10MB</p>
                                        </>
                                    )}
                                </div>

                                <div className="mt-4 p-3 rounded" style={{ background: '#f0f4ff', border: '1px solid #c7d2fe' }}>
                                    <h6 style={{ color: '#667eea' }}>
                                        <i className="fas fa-magic me-2"></i>AI will automatically extract:
                                    </h6>
                                    <div className="row mt-2">
                                        {['Bank Name', 'Account Number', 'Account Holder', 'IFSC Code', 'All Transactions', 'Income/Expense Type'].map((item) => (
                                            <div key={item} className="col-6">
                                                <small><i className="fas fa-check text-success me-1"></i>{item}</small>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {(errors?.statement || errors?.message) && (
                                    <div className="alert alert-danger mt-3 mb-0" role="alert">
                                        {[errors.statement, errors.message].flat().filter(Boolean).join(' ')}
                                    </div>
                                )}

                                <button
                                    type="submit"
                                    disabled={!file || loading}
                                    className="btn w-100 mt-3"
                                    style={{
                                        background: 'linear-gradient(135deg,#667eea,#764ba2)',
                                        color: '#fff',
                                        padding: '12px',
                                        borderRadius: 10,
                                        fontWeight: 600,
                                        fontSize: 15,
                                    }}
                                >
                                    {loading ? (
                                        <>
                                            <span className="spinner-border spinner-border-sm me-2"></span>
                                            AI is reading your statement...
                                        </>
                                    ) : (
                                        <>
                                            <i className="fas fa-robot me-2"></i>Extract with AI
                                        </>
                                    )}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </BootstrapLayout>
    );
}
