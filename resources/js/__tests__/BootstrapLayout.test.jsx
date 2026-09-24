import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import BootstrapLayout from '../Layouts/BootstrapLayout';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, method, as, children, ...rest }) =>
        as === 'button'
            ? <button type="button" data-method={method} data-href={href} {...rest}>{children}</button>
            : <a href={href} {...rest}>{children}</a>,
    router: { get: vi.fn(), post: vi.fn() },
    useForm: () => ({}),
    usePage: () => ({ props: {}, url: '/' }),
}));

vi.mock('../Components/ChatBot/ChatWidget', () => ({
    default: () => <div data-testid="chat-widget" />,
}));

describe('BootstrapLayout', () => {
    it('renders the navbar links', () => {
        render(<BootstrapLayout><div>page content</div></BootstrapLayout>);

        expect(screen.getByRole('link', { name: /expense manager/i })).toHaveAttribute('href', '/');
        expect(screen.getByRole('link', { name: /import/i })).toHaveAttribute('href', '/statements/upload');
        expect(screen.getByRole('link', { name: /transactions/i })).toHaveAttribute('href', '/transactions');
        expect(screen.getByRole('link', { name: /add transaction/i })).toHaveAttribute('href', '/transactions/create');
        expect(screen.getByRole('link', { name: /budgets/i })).toHaveAttribute('href', '/budgets');
        expect(screen.getByRole('link', { name: /categories/i })).toHaveAttribute('href', '/categories');
        expect(screen.getByRole('link', { name: /accounts/i })).toHaveAttribute('href', '/accounts');
    });

    it('renders a Log out control that posts to /logout and mounts the ChatWidget', () => {
        render(<BootstrapLayout><div>page content</div></BootstrapLayout>);

        const logout = screen.getByRole('button', { name: /log out/i });
        expect(logout).toHaveAttribute('data-method', 'post');
        expect(logout).toHaveAttribute('data-href', '/logout');
        expect(screen.getByTestId('chat-widget')).toBeInTheDocument();
    });
});
