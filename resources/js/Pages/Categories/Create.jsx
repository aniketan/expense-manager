import Form from './Form';

export default function Create({ parentCategories }) {
    return (
        <Form
            parentCategories={parentCategories}
            isEdit={false}
        />
    );
}
