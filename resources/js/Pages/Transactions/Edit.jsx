import Form from './Form';

export default function Edit({ transaction, categories, accounts }) {
    return (
        <Form
            transaction={transaction}
            categories={categories}
            accounts={accounts}
            isEdit={true}
        />
    );
}
