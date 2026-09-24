import Form from './Form';

export default function Edit({ account, accountTypes }) {
    return (
        <Form
            account={account}
            accountTypes={accountTypes}
            isEdit={true}
        />
    );
}
