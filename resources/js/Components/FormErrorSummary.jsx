import React from 'react';

export default function FormErrorSummary({ errorEntries, errorLabels }) {
    if (!errorEntries || errorEntries.length === 0) {
        return null;
    }

    return (
        <div className="alert alert-danger" role="alert" aria-live="polite">
            <div className="fw-semibold mb-2">
                Please fix the highlighted fields before saving.
            </div>
            <ul className="mb-0 ps-3">
                {errorEntries.map(([field, message]) => (
                    <li key={field}>
                        <strong>{errorLabels[field] || field}:</strong> {message}
                    </li>
                ))}
            </ul>
        </div>
    );
}
