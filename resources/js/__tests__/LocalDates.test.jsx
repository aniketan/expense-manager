import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { useState } from 'react';
import { render, fireEvent } from '@testing-library/react';
import BudgetForm from '../Pages/Budgets/Form';
import TransactionForm from '../Pages/Transactions/Form';
import { toLocalDateString, getMaxDate, validateDate } from '../utils/inputValidation';

vi.mock('@inertiajs/react', () => {
    function useForm(initialData) {
        const [data, setDataState] = useState(initialData);
        const setData = (keyOrObject, value) => {
            if (typeof keyOrObject === 'object' && keyOrObject !== null) {
                setDataState((current) => ({ ...current, ...keyOrObject }));
            } else {
                setDataState((current) => ({ ...current, [keyOrObject]: value }));
            }
        };
        return { data, setData, post: vi.fn(), put: vi.fn(), processing: false, errors: {} };
    }

    return {
        Head: () => null,
        Link: ({ children }) => <a>{children}</a>,
        router: { get: vi.fn(), post: vi.fn() },
        useForm,
        usePage: () => ({ props: { errors: {} }, url: '/' }),
    };
});

vi.mock('../Layouts/BootstrapLayout', () => ({ default: ({ children }) => <div>{children}</div> }));

// 01:30 on 24 Sep 2026 in India is still 23 Sep in UTC: the window where
// toISOString()-based dates came out a day early. vitest.config.js sets TZ=Asia/Kolkata.
const EARLY_MORNING_IST = new Date(2026, 8, 24, 1, 30);

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(EARLY_MORNING_IST);
});

afterEach(() => {
    vi.useRealTimers();
});

describe('local calendar dates (UTC+05:30)', () => {
    it('runs in the India time zone', () => {
        expect(new Date().getTimezoneOffset()).toBe(-330);
    });

    it('toLocalDateString and getMaxDate return the local day, not the UTC day', () => {
        expect(toLocalDateString()).toBe('2026-09-24');
        expect(toLocalDateString(new Date(2026, 8, 1))).toBe('2026-09-01');
        expect(getMaxDate()).toBe('2026-09-24');
    });

    it('validateDate accepts today without a time before 05:30', () => {
        expect(validateDate('2026-09-24', false).isValid).toBe(true);
        expect(validateDate('2026-09-25', false).error).toBe('Future dates are not allowed');
        expect(validateDate('2026-02-30', true).error).toBe('Invalid date (impossible date)');
    });

    it('new transaction defaults to the local date and the picker allows today', () => {
        const { container } = render(<TransactionForm categories={[]} accounts={[]} isEdit={false} />);
        const date = container.querySelector('#transaction_date');

        expect(date.value).toBe('2026-09-24');
        expect(date.getAttribute('max')).toBe('2026-09-24');
    });

    it('budget Monthly/Yearly presets cover the exact local month and year', () => {
        const { container } = render(<BudgetForm categories={[]} isEdit={false} />);
        const period = container.querySelector('#period_type');

        fireEvent.change(period, { target: { value: 'yearly' } });
        expect(container.querySelector('#start_date').value).toBe('2026-01-01');
        expect(container.querySelector('#end_date').value).toBe('2026-12-31');

        fireEvent.change(period, { target: { value: 'monthly' } });
        expect(container.querySelector('#start_date').value).toBe('2026-09-01');
        expect(container.querySelector('#end_date').value).toBe('2026-09-30');
    });
});
