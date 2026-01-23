import React from 'react';
import Form from './Form';

export default function Create({ categories, periodTypes }) {
    return (
        <Form
            categories={categories}
            periodTypes={periodTypes}
            isEdit={false}
        />
    );
}
