import { describe, it, expect, vi } from 'vitest';
import {
    sanitizeText,
    validateAmount,
    validateDate,
    validateCode,
    validateIFSC,
    validateTags,
    handleAmountInput,
    getErrorEntries,
    validateTransactionForm,
} from '../utils/inputValidation';

describe('sanitizeText', () => {
    it('strips control characters but keeps tabs and line breaks', () => {
        // NUL is replaced with U+FFFD by the HTML entity-decoding step, so it isn't used here.
        expect(sanitizeText('a\u0001b\u0007c\u001Fd\u007Fe')).toBe('abcde');
        expect(sanitizeText('line1\nline2\tend')).toBe('line1\nline2\tend');
    });

    it('removes HTML tags and scripts, decodes entities, trims, and caps length', () => {
        expect(sanitizeText('<b>Hi</b><script>alert(1)</script> &amp; bye ')).toBe('Hi & bye');
        expect(sanitizeText('abcdef', 3)).toBe('abc');
        expect(sanitizeText(null)).toBe('');
    });
});

const isoDate = (date) => date.toISOString().slice(0, 10);

const daysFromNow = (days) => {
    const d = new Date();
    d.setDate(d.getDate() + days);
    d.setHours(12, 0, 0, 0);
    return d;
};

describe('validateAmount', () => {
    it('rejects empty, null, and undefined amounts', () => {
        for (const input of ['', null, undefined]) {
            expect(validateAmount(input)).toEqual({ isValid: false, value: null, error: 'Amount is required' });
        }
    });

    it('rejects zero and non-numeric input', () => {
        expect(validateAmount(0).error).toBe('Amount must be greater than zero');
        expect(validateAmount('0.00').error).toBe('Amount must be greater than zero');
        expect(validateAmount('abc').error).toBe('Amount must be a valid number');
    });

    it('rejects negatives unless allowNegative is set', () => {
        expect(validateAmount(-5).error).toBe('Amount cannot be negative');
        const allowed = validateAmount(-5, true);
        expect(allowed.isValid).toBe(true);
        expect(allowed.value).toBe(-5);
    });

    it('rejects amounts above the max and rounds valid decimals to two places', () => {
        const tooBig = validateAmount('1000000000');
        expect(tooBig.isValid).toBe(false);
        expect(tooBig.error).toMatch(/cannot exceed/);

        expect(validateAmount('12.34').value).toBe(12.34);
        expect(validateAmount('1234.5678').value).toBe(1234.57);
        expect(validateAmount(10).value).toBe(10);
    });
});

describe('validateDate', () => {
    it('rejects future dates unless allowFuture is set', () => {
        const future = isoDate(daysFromNow(10));
        expect(validateDate(future).error).toBe('Future dates are not allowed');
        expect(validateDate(future, true)).toEqual({ isValid: true, value: future, error: null });
    });

    it('rejects dates older than the allowed past window', () => {
        const old = new Date();
        old.setFullYear(old.getFullYear() - 11);
        old.setHours(12, 0, 0, 0);
        const result = validateDate(isoDate(old));
        expect(result.isValid).toBe(false);
        expect(result.error).toMatch(/more than 10 years/);
    });

    it('rejects missing or malformed dates', () => {
        expect(validateDate('').error).toBe('Date is required');
        expect(validateDate('2024/01/15').error).toBe('Invalid date format');
    });

    it('accepts a valid recent date', () => {
        const past = isoDate(daysFromNow(-1));
        expect(validateDate(past)).toEqual({ isValid: true, value: past, error: null });
    });
});

describe('validateCode', () => {
    it('uppercases and strips disallowed characters', () => {
        expect(validateCode(' groceries ').value).toBe('GROCERIES');
        expect(validateCode('a_b-1').value).toBe('A_B-1');
    });

    it('rejects empty, symbol-only, and too-long codes', () => {
        expect(validateCode('').error).toBe('Code is required');
        expect(validateCode('!!!').error).toBe('Code must contain alphanumeric characters');
        expect(validateCode('a'.repeat(25)).error).toBe('Code cannot exceed 20 characters');
    });
});

describe('validateIFSC', () => {
    it('accepts well-formed IFSC codes (uppercasing them) and rejects malformed ones', () => {
        expect(validateIFSC('sbin0001234')).toEqual({ isValid: true, value: 'SBIN0001234', error: null });
        expect(validateIFSC('SBIN1001234').error).toBe('Invalid IFSC code format (e.g., SBIN0001234)');
        expect(validateIFSC('SBIN000123').error).toBe('Invalid IFSC code format (e.g., SBIN0001234)');
    });

    it('treats empty as valid only when not required', () => {
        expect(validateIFSC('', true).error).toBe('IFSC code is required');
        expect(validateIFSC()).toEqual({ isValid: true, value: '', error: null });
    });
});

describe('validateTags', () => {
    it('trims each tag, joins with ", ", and accepts empty input', () => {
        expect(validateTags(' food ,travel, gym ').value).toBe('food, travel, gym');
        expect(validateTags('')).toEqual({ isValid: true, value: '', error: null });
    });

    it('rejects more than the maximum number of tags', () => {
        const tags = Array.from({ length: 11 }, (_, i) => `t${i}`).join(',');
        const result = validateTags(tags);
        expect(result.isValid).toBe(false);
        expect(result.error).toBe('Maximum 10 tags allowed');
    });
});

describe('handleAmountInput', () => {
    const fire = (value, setter, fieldKey) => handleAmountInput({ target: { value } }, setter, fieldKey);

    it('strips non-numeric characters, keeps a leading minus, and caps decimals at two places', () => {
        let captured;
        fire('12abc34.5678cd', (v) => { captured = v; });
        expect(captured).toBe('1234.56');

        captured = null;
        fire('-12.34', (v) => { captured = v; });
        expect(captured).toBe('-12.34');

        captured = null;
        fire('12-34', (v) => { captured = v; });
        expect(captured).toBe('1234');
    });

    it('supports the useForm.setData(fieldKey, value) form', () => {
        const setData = vi.fn();
        fire('9.99', setData, 'amount');
        expect(setData).toHaveBeenCalledWith('amount', '9.99');
    });
});

describe('getErrorEntries', () => {
    it('merges validation and server errors in field order', () => {
        const entries = getErrorEntries(
            { category: 'Pick a category' },
            { amount: 'Amount is required', account_id: 'Account is required' },
        );
        expect(entries).toEqual([
            ['amount', 'Amount is required'],
            ['account_id', 'Account is required'],
            ['category', 'Pick a category'],
        ]);
    });

    it('skips null entries and appends fields outside the known order', () => {
        const entries = getErrorEntries({ amount: null, tags: 'Too many tags' }, {}, ['amount', 'description']);
        expect(entries).toEqual([['tags', 'Too many tags']]);
    });
});

const baseData = () => ({
    amount: '100',
    transaction_date: isoDate(daysFromNow(-1)),
    transaction_time: '',
    account_id: '1',
    payment_method: 'Cash',
    category_id: '5',
    description: '',
    tags: '',
});

describe('validateTransactionForm', () => {
    it('flags a missing category for an expense', () => {
        const errors = validateTransactionForm(baseData(), { transactionType: 'expense' });
        expect(errors.category).toBe('Category is required');
        expect(errors.amount).toBeUndefined();
        expect(errors.account_id).toBeUndefined();
    });

    it('requires a destination account for transfers but not a category or payment method', () => {
        const data = { ...baseData(), category_id: '', transfer_to_account_id: '' };
        const errors = validateTransactionForm(data, { transactionType: 'transfer' });
        expect(errors.transfer_to_account_id).toBe('Destination account is required');
        expect(errors.category).toBeUndefined();
        expect(errors.category_id).toBeUndefined();
        expect(errors.payment_method).toBeUndefined();
    });

    it('passes a complete transfer', () => {
        const data = {
            ...baseData(),
            category_id: '',
            payment_method: '',
            transfer_to_account_id: '2',
        };
        expect(validateTransactionForm(data, { transactionType: 'transfer' })).toEqual({});
    });

    it('rejects an account transferring to itself and a missing income category', () => {
        const self = validateTransactionForm(
            { ...baseData(), category_id: '', transfer_to_account_id: '1' },
            { transactionType: 'transfer' },
        );
        expect(self.transfer).toBe('Source and destination accounts must be different');

        const income = validateTransactionForm(
            { ...baseData(), category_id: '' },
            { transactionType: 'income', selectedCategory: 'INC' },
        );
        expect(income.category_id).toBe('Income category is required');
        expect(income.category).toBeUndefined();
    });
});
