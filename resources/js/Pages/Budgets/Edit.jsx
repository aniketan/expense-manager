import React from 'react';
import Form from './Form';

export default function Edit({ budget, categories, periodTypes }) {
    return (
        <Form
            budget={budget}
            categories={categories}
            periodTypes={periodTypes}
            isEdit={true}
        />
    );
}
