import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Pagination from '../Components/Pagination';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children }) => <a href={href}>{children}</a>,
}));

const paginationData = {
    current_page: 2,
    last_page: 10,
    per_page: 25,
    total: 237,
    from: 26,
    to: 50,
};

describe('Pagination', () => {
    it('shows the "from–to of total" text', () => {
        render(<Pagination paginationData={paginationData} />);
        expect(screen.getByText('Showing 26 to 50 of 237 results')).toBeInTheDocument();
    });

    it('offers the 15/25/50/100 per-page options', () => {
        render(<Pagination paginationData={paginationData} />);
        for (const value of ['15', '25', '50', '100']) {
            expect(screen.getByRole('option', { name: value })).toHaveAttribute('value', value);
        }
    });

    it('calls onPerPageChange when the page-size select changes', () => {
        const onPerPageChange = vi.fn();
        render(<Pagination paginationData={paginationData} onPerPageChange={onPerPageChange} />);

        const select = screen.getByRole('combobox');
        fireEvent.change(select, { target: { value: '50' } });

        expect(onPerPageChange).toHaveBeenCalledTimes(1);
        expect(onPerPageChange.mock.calls[0][0].target).toBe(select);
    });

    it('renders nothing when there is only one page', () => {
        const { container } = render(
            <Pagination paginationData={{ ...paginationData, last_page: 1 }} />,
        );
        expect(container.firstChild).toBeNull();
    });
});
