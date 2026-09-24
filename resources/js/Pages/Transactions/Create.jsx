import Form from './Form';

export default function Create({ categories, accounts }) {
    return (
        <Form
            categories={categories}
            accounts={accounts}
            isEdit={false}
        />
    );
}
