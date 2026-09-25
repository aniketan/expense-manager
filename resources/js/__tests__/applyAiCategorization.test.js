import { describe, it, expect } from 'vitest';
import { applyAiCategorizationToRow } from '../Pages/Statements/applyAiCategorization';

describe('applyAiCategorizationToRow', () => {
    it('fills the expense row category when fallback is false', () => {
        const row = { _id: 0, type: 'expense', category_id: '', subcategory_id: '', aiLoading: true };
        const data = { category_id: 3, subcategory_id: 7, confidence: 'high', reason: 'Grocery store', fallback: false };

        const next = applyAiCategorizationToRow(row, data);

        expect(next.category_id).toBe('3');
        expect(next.subcategory_id).toBe('7');
        expect(next.aiDone).toBe(true);
        expect(next.aiLoading).toBe(false);
        expect(next.aiConfidence).toBe('high');
        expect(next.aiReason).toBe('Grocery store');
    });

    it('fills only the subcategory for income rows when fallback is false', () => {
        const row = { _id: 1, type: 'income', category_id: '', subcategory_id: '', aiLoading: true };
        const data = { category_id: 9, subcategory_id: 11, confidence: 'medium', reason: 'Payroll', fallback: false };

        const next = applyAiCategorizationToRow(row, data);

        expect(next.category_id).toBe('');
        expect(next.subcategory_id).toBe('11');
        expect(next.aiConfidence).toBe('medium');
    });

    it('leaves the row selection unchanged when fallback is true', () => {
        const row = { _id: 2, type: 'expense', category_id: '5', subcategory_id: '6', aiLoading: true };
        const data = {
            category_id: 3,
            subcategory_id: 7,
            confidence: 'low',
            reason: 'AI could not determine a category; showing a default suggestion for review.',
            fallback: true,
        };

        const next = applyAiCategorizationToRow(row, data);

        expect(next.category_id).toBe('5');
        expect(next.subcategory_id).toBe('6');
        expect(next.aiDone).toBe(true);
        expect(next.aiConfidence).toBe('low');
        expect(next.aiReason).toBe(data.reason);
    });

    it('leaves an empty selection empty when fallback is true', () => {
        const row = { _id: 3, type: 'expense', category_id: '', subcategory_id: '' };
        const data = { category_id: 3, subcategory_id: 7, confidence: 'low', reason: 'Default', fallback: true };

        const next = applyAiCategorizationToRow(row, data);

        expect(next.category_id).toBe('');
        expect(next.subcategory_id).toBe('');
        expect(next.aiConfidence).toBe('low');
    });
});
