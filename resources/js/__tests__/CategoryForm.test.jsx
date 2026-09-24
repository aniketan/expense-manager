import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useState } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import CategoryForm from '../Pages/Categories/Form';
import { post, put, __formStore } from '@inertiajs/react';

vi.mock('@inertiajs/react', () => {
    const postMock = vi.fn();
    const putMock = vi.fn();
    const store = { data: null };

    function useForm(initialData) {
        const [data, setDataState] = useState(initialData);
        store.data = data;
        const setData = (keyOrObject, value) => {
            if (typeof keyOrObject === 'object' && keyOrObject !== null) {
                setDataState((current) => ({ ...current, ...keyOrObject }));
            } else {
                setDataState((current) => ({ ...current, [keyOrObject]: value }));
            }
        };
        return {
            data,
            setData,
            post: postMock,
            put: putMock,
            processing: false,
            errors: {},
        };
    }

    return {
        Head: () => null,
        Link: ({ children }) => <a>{children}</a>,
        router: { get: vi.fn(), post: postMock },
        useForm,
        usePage: () => ({ props: {}, url: '/' }),
        post: postMock,
        put: putMock,
        __formStore: store,
    };
});

vi.mock('../Layouts/BootstrapLayout', () => ({
    default: ({ children }) => <>{children}</>,
}));

beforeEach(() => {
    post.mockClear();
    put.mockClear();
});

const parentCategories = [
    { id: 1, name: 'Food' },
    { id: 3, name: 'Income' },
];

describe('CategoryForm', () => {
    it('Create: posts a new category to /categories', () => {
        render(
            <CategoryForm parentCategories={parentCategories} isEdit={false} />,
        );

        fireEvent.change(screen.getByLabelText(/category name/i), { target: { value: 'Dining' } });
        fireEvent.change(screen.getByLabelText(/^code/i), { target: { value: 'DINING' } });
        fireEvent.click(screen.getByRole('button', { name: /create category/i }));

        expect(post).toHaveBeenCalledTimes(1);
        expect(post).toHaveBeenCalledWith('/categories');
        expect(put).not.toHaveBeenCalled();
        expect(__formStore.data).toMatchObject({
            name: 'Dining',
            code: 'DINING',
        });
    });

    it('Edit: pre-fills the category, excludes it from the parent list, and puts to /categories/<id>', () => {
        const category = {
            id: 2,
            name: 'Groceries',
            code: 'GROC',
            parent_id: 1,
            description: '',
            icon: '',
            color: '#3B82F6',
            is_active: true,
        };
        // The backend excludes the category itself from the parent list.
        const parentsWithoutSelf = [
            { id: 1, name: 'Food' },
            { id: 3, name: 'Income' },
        ];
        const { container } = render(
            <CategoryForm category={category} parentCategories={parentsWithoutSelf} isEdit={true} />,
        );

        expect(screen.getByLabelText(/category name/i).value).toBe('Groceries');
        expect(container.querySelector('#parent_id').value).toBe('1');
        const parentOptions = Array.from(container.querySelectorAll('#parent_id option')).map((o) => o.textContent);
        expect(parentOptions).not.toContain('Groceries');

        fireEvent.click(screen.getByRole('button', { name: /update category/i }));

        expect(put).toHaveBeenCalledTimes(1);
        expect(put).toHaveBeenCalledWith('/categories/2');
        expect(post).not.toHaveBeenCalled();
    });
});
