import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useState } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import AccountForm from '../Pages/Accounts/Form';
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

const accountTypes = {
    savings: 'Savings',
    cash: 'Cash',
    credit_card: 'Credit Card',
};

describe('AccountForm', () => {
    it('Create: has no current-balance input and posts to /accounts', () => {
        const { container } = render(
            <AccountForm accountTypes={accountTypes} isEdit={false} />,
        );

        expect(screen.queryByText('Current Balance')).not.toBeInTheDocument();

        fireEvent.change(screen.getByPlaceholderText('e.g., SBI01, CASH'), { target: { value: 'SBI01' } });
        fireEvent.change(screen.getByPlaceholderText('e.g., SBI Savings Account, Cash Wallet'), { target: { value: 'SBI Savings' } });
        fireEvent.change(container.querySelector('input[placeholder="0.00"]'), { target: { value: '1000' } });
        fireEvent.click(screen.getByRole('button', { name: /create account/i }));

        expect(post).toHaveBeenCalledTimes(1);
        expect(post).toHaveBeenCalledWith('/accounts');
        expect(put).not.toHaveBeenCalled();
        expect(__formStore.data).toMatchObject({
            code: 'SBI01',
            name: 'SBI Savings',
            opening_balance: '1000',
        });
        expect(__formStore.data).not.toHaveProperty('current_balance');
    });

    it('Edit: shows a read-only current balance and puts to /accounts/<id>', () => {
        const account = {
            id: 5,
            code: 'SBI01',
            name: 'SBI Savings',
            type: 'savings',
            bank_name: '',
            account_number: '',
            ifsc_code: '',
            opening_balance: '1000.00',
            current_balance: '2500.00',
            credit_limit: '0.00',
            is_active: true,
        };
        const { container } = render(
            <AccountForm account={account} accountTypes={accountTypes} isEdit={true} />,
        );

        const currentBalance = container.querySelector('input[readonly]');
        expect(currentBalance).not.toBeNull();
        expect(currentBalance.value).toBe('2500.00');

        fireEvent.click(screen.getByRole('button', { name: /update account/i }));

        expect(put).toHaveBeenCalledTimes(1);
        expect(put).toHaveBeenCalledWith('/accounts/5');
        expect(post).not.toHaveBeenCalled();
    });
});
