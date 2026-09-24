import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import FormErrorSummary from '../Components/FormErrorSummary';

describe('FormErrorSummary', () => {
    it('renders every error with its label', () => {
        render(
            <FormErrorSummary
                errorEntries={[['category', 'Pick a category'], ['amount', 'Amount is required']]}
                errorLabels={{ category: 'Category', amount: 'Amount' }}
            />,
        );

        expect(screen.getByText('Please fix the highlighted fields before saving.')).toBeInTheDocument();
        expect(screen.getByText('Category:')).toBeInTheDocument();
        expect(screen.getByText('Pick a category')).toBeInTheDocument();
        expect(screen.getByText('Amount:')).toBeInTheDocument();
        expect(screen.getByText('Amount is required')).toBeInTheDocument();
    });

    it('falls back to the field name when no label is provided', () => {
        render(<FormErrorSummary errorEntries={[['payee_payer', 'Who was this for?']]} errorLabels={{}} />);
        expect(screen.getByText('payee_payer:')).toBeInTheDocument();
    });

    it('renders nothing when there are no errors', () => {
        const { container } = render(<FormErrorSummary errorEntries={[]} errorLabels={{}} />);
        expect(container.firstChild).toBeNull();
    });
});
