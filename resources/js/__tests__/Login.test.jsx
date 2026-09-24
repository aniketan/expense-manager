import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useState } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import Login from '../Pages/Auth/Login';
import { post } from '@inertiajs/react';

vi.mock('@inertiajs/react', () => {
    const postMock = vi.fn();

    function useForm(initialData) {
        const [data, setDataState] = useState(initialData);
        return {
            data,
            setData: (key, value) => setDataState((current) => ({ ...current, [key]: value })),
            post: postMock,
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
    };
});

beforeEach(() => {
    post.mockClear();
});

describe('Login', () => {
    it('shows the "no password set" message and no form when passwordConfigured is false', () => {
        render(<Login passwordConfigured={false} />);

        expect(screen.getByText(/no login password is set/i)).toBeInTheDocument();
        expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('renders a password field and submits through useForm().post("/login")', () => {
        render(<Login passwordConfigured />);

        const input = screen.getByLabelText('Password');
        expect(input).toHaveAttribute('type', 'password');
        expect(screen.queryByText(/no login password is set/i)).not.toBeInTheDocument();

        fireEvent.change(input, { target: { value: 'secret' } });
        fireEvent.click(screen.getByRole('button', { name: /log in/i }));

        expect(post).toHaveBeenCalledTimes(1);
        expect(post).toHaveBeenCalledWith('/login', expect.objectContaining({ onFinish: expect.any(Function) }));
    });
});
