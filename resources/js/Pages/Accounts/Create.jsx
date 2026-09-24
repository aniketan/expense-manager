import Form from './Form';

export default function Create({ accountTypes }) {
    return (
        <Form
            accountTypes={accountTypes}
            isEdit={false}
        />
    );
}
