import Form from './Form';

export default function Edit({ category, parentCategories }) {
    return (
        <Form
            category={category}
            parentCategories={parentCategories}
            isEdit={true}
        />
    );
}
