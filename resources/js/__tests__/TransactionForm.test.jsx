import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useState } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import TransactionForm from '../Pages/Transactions/Form';
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

const accounts = [
    { id: 1, name: 'Cash' },
    { id: 2, name: 'Bank' },
];

const categories = [
    { id: 1, name: 'Food', code: 'FOOD', parent_id: null },
    { id: 2, name: 'Groceries', code: 'GROC', parent_id: 1 },
    { id: 3, name: 'Income', code: 'INCOME', parent_id: null },
    { id: 4, name: 'Salary', code: 'SAL', parent_id: 3 },
];

const fillExpenseBasics = (container) => {
    fireEvent.change(container.querySelector('#amount'), { target: { value: '250' } });
    fireEvent.change(container.querySelector('#account'), { target: { value: '1' } });
    fireEvent.change(container.querySelector('#payment_method'), { target: { value: 'Cash' } });
    fireEvent.change(container.querySelector('#category'), { target: { value: '1' } });
    fireEvent.change(container.querySelector('#subcategory'), { target: { value: '2' } });
};

describe('TransactionForm', () => {
    it('Create: submitting a valid expense calls post("/transactions") with the entered values and defaults status to Pending', () => {
        const { container } = render(
            <TransactionForm categories={categories} accounts={accounts} isEdit={false} />,
        );

        // The default status is Pending in Create mode.
        expect(container.querySelector('#status').value).toBe('Pending');

        fillExpenseBasics(container);
        fireEvent.click(screen.getByRole('button', { name: /add transaction/i }));

        expect(post).toHaveBeenCalledTimes(1);
        expect(post).toHaveBeenCalledWith('/transactions');
        expect(put).not.toHaveBeenCalled();
        expect(__formStore.data).toMatchObject({
            amount: '250',
            account_id: '1',
            payment_method: 'Cash',
            category_id: '2',
            status: 'Pending',
            transaction_type: 'expense',
        });
    });

    it('Edit: a transaction on a child category pre-selects the parent category and the subcategory, and submits via put', () => {
        const transaction = {
            id: 7,
            transaction_type: 'expense',
            transaction_date: '2026-09-10',
            transaction_time: '14:30',
            amount: '99.50',
            account_id: 1,
            payment_method: 'UPI',
            category_id: 2,
            description: '',
            payee_payer: 'Store',
            reference_number: '',
            tax: '0',
            status: 'Cleared',
            tags: '',
            notes: '',
        };
        const { container } = render(
            <TransactionForm transaction={transaction} categories={categories} accounts={accounts} isEdit={true} />,
        );

        expect(container.querySelector('#category').value).toBe('1');
        expect(container.querySelector('#subcategory').value).toBe('2');

        fireEvent.click(screen.getByRole('button', { name: /update transaction/i }));

        expect(put).toHaveBeenCalledTimes(1);
        expect(put).toHaveBeenCalledWith('/transactions/7');
        expect(post).not.toHaveBeenCalled();
    });

    it('Transfer in Create: hides category and payment method, and requires a destination account', () => {
        const { container } = render(
            <TransactionForm categories={categories} accounts={accounts} isEdit={false} />,
        );

        fireEvent.click(container.querySelector('#type_transfer'));

        expect(container.querySelector('#payment_method')).toBeNull();
        expect(container.querySelector('#category')).toBeNull();
        expect(container.querySelector('#subcategory')).toBeNull();
        expect(container.querySelector('#transfer_to_account')).not.toBeNull();

        fireEvent.change(container.querySelector('#amount'), { target: { value: '500' } });
        fireEvent.change(container.querySelector('#account'), { target: { value: '1' } });
        fireEvent.click(screen.getByRole('button', { name: /add transaction/i }));

        // The error shows both per field and in the FormErrorSummary.
        expect(screen.getAllByText('Destination account is required')).toHaveLength(2);
        expect(post).not.toHaveBeenCalled();

        fireEvent.change(container.querySelector('#transfer_to_account'), { target: { value: '2' } });
        fireEvent.click(screen.getByRole('button', { name: /add transaction/i }));

        expect(post).toHaveBeenCalledTimes(1);
        expect(post).toHaveBeenCalledWith('/transactions');
        expect(__formStore.data).toMatchObject({
            transaction_type: 'transfer',
            account_id: '1',
            transfer_to_account_id: '2',
        });
    });

    it('Edit keeps the payee on a transfer instead of blanking it', () => {
        const transaction = {
            id: 9,
            transaction_type: 'transfer',
            transaction_date: '2026-09-10',
            transaction_time: '09:00',
            amount: '500',
            account_id: 1,
            transfer_to_account_id: 2,
            payee_payer: 'John',
            description: '',
            reference_number: '',
            tax: '0',
            status: 'Cleared',
            tags: '',
            notes: '',
        };
        render(
            <TransactionForm transaction={transaction} categories={categories} accounts={accounts} isEdit={true} />,
        );

        fireEvent.click(screen.getByRole('button', { name: /update transaction/i }));

        expect(put).toHaveBeenCalledTimes(1);
        expect(put).toHaveBeenCalledWith('/transactions/9');
        expect(__formStore.data.payee_payer).toBe('John');
    });

    it('Edit type locking: Transfer is disabled for an expense row, and every type is disabled for a transfer row', () => {
        const expense = {
            id: 7,
            transaction_type: 'expense',
            transaction_date: '2026-09-10',
            transaction_time: '14:30',
            amount: '99.50',
            account_id: 1,
            payment_method: 'UPI',
            category_id: 2,
            status: 'Cleared',
        };
        const { container, unmount } = render(
            <TransactionForm transaction={expense} categories={categories} accounts={accounts} isEdit={true} />,
        );

        expect(container.querySelector('#type_income').disabled).toBe(false);
        expect(container.querySelector('#type_expense').disabled).toBe(false);
        expect(container.querySelector('#type_transfer').disabled).toBe(true);
        unmount();

        const transfer = {
            id: 9,
            transaction_type: 'transfer',
            transaction_date: '2026-09-10',
            transaction_time: '09:00',
            amount: '500',
            account_id: 1,
            transfer_to_account_id: 2,
            status: 'Cleared',
        };
        const second = render(
            <TransactionForm transaction={transfer} categories={categories} accounts={accounts} isEdit={true} />,
        );

        expect(second.container.querySelector('#type_income').disabled).toBe(true);
        expect(second.container.querySelector('#type_expense').disabled).toBe(true);
        expect(second.container.querySelector('#type_transfer').disabled).toBe(true);
    });

    it('Create leaves all three transaction types selectable', () => {
        const { container } = render(
            <TransactionForm categories={categories} accounts={accounts} isEdit={false} />,
        );

        expect(container.querySelector('#type_income').disabled).toBe(false);
        expect(container.querySelector('#type_expense').disabled).toBe(false);
        expect(container.querySelector('#type_transfer').disabled).toBe(false);
    });

    it('Client validation: an empty amount shows an error and does not submit', () => {
        const { container } = render(
            <TransactionForm categories={categories} accounts={accounts} isEdit={false} />,
        );

        fillExpenseBasics(container);
        fireEvent.change(container.querySelector('#amount'), { target: { value: '' } });
        fireEvent.click(screen.getByRole('button', { name: /add transaction/i }));

        // The error shows both per field and in the FormErrorSummary.
        expect(screen.getAllByText('Amount is required')).toHaveLength(2);
        expect(post).not.toHaveBeenCalled();
        expect(put).not.toHaveBeenCalled();
    });
});
