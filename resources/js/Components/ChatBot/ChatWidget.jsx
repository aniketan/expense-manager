import React, { useEffect, useMemo, useRef, useState } from 'react';

const SESSION_KEY = 'chatbot_session_id';

function getOrCreateSessionId() {
    const existing = window.localStorage.getItem(SESSION_KEY);
    if (existing) {
        return existing;
    }

    const sessionId = `sess_${crypto.randomUUID()}`;
    window.localStorage.setItem(SESSION_KEY, sessionId);
    return sessionId;
}

function parseSseChunk(rawChunk) {
    const lines = rawChunk.split('\n');
    let eventName = null;
    const dataLines = [];

    lines.forEach((line) => {
        if (line.startsWith('event:')) {
            eventName = line.slice(6).trim();
        } else if (line.startsWith('data:')) {
            dataLines.push(line.slice(5).trim());
        }
    });

    if (!eventName) {
        return null;
    }

    let payload = {};
    const dataText = dataLines.join('\n');
    if (dataText) {
        try {
            payload = JSON.parse(dataText);
        } catch (_err) {
            payload = {};
        }
    }

    return { eventName, payload };
}

export default function ChatWidget() {
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [streaming, setStreaming] = useState(false);
    const [toolStatus, setToolStatus] = useState('');
    const [clearChatError, setClearChatError] = useState('');
    const bottomRef = useRef(null);
    const sessionId = useMemo(() => getOrCreateSessionId(), []);

    useEffect(() => {
        if (!open) {
            return;
        }

        const loadHistory = async () => {
            const res = await fetch(`/chat/history?session_id=${encodeURIComponent(sessionId)}`);
            const data = await res.json();
            if (Array.isArray(data.messages)) {
                setMessages(data.messages.map((msg, i) => ({
                    id: `hist_${i}`,
                    role: msg.role,
                    content: msg.content,
                })));
            }
        };

        loadHistory();
    }, [open, sessionId]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, toolStatus]);

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const clearChat = async () => {
        if (streaming) {
            return;
        }
        if (!window.confirm('Clear this conversation? This cannot be undone.')) {
            return;
        }
        setClearChatError('');
        try {
            const res = await fetch('/chat/reset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                },
                body: JSON.stringify({ session_id: sessionId }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                setMessages([]);
                setToolStatus('');
                setInput('');
                return;
            }
            if (res.ok && data.success === false && data.message === 'Session not found') {
                setMessages([]);
                setToolStatus('');
                setInput('');
                return;
            }
            setClearChatError(data.message || 'Could not clear chat. Try again.');
        } catch (_err) {
            setClearChatError('Could not clear chat. Check your connection.');
        }
    };

    const sendMessage = async () => {
        const value = input.trim();
        if (!value || streaming) {
            return;
        }

        setInput('');
        setStreaming(true);
        setToolStatus('');
        setMessages((prev) => [...prev, { id: crypto.randomUUID(), role: 'user', content: value }]);
        const assistantId = crypto.randomUUID();
        setMessages((prev) => [...prev, { id: assistantId, role: 'assistant', content: '', thinking: '', thinkingDone: false }]);

        const response = await fetch('/chat/stream', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'X-CSRF-TOKEN': csrf || '',
            },
            body: JSON.stringify({ message: value, session_id: sessionId }),
        });

        if (!response.ok || !response.body) {
            setStreaming(false);
            setToolStatus('');
            return;
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value: chunk } = await reader.read();
            if (done) {
                break;
            }

            buffer += decoder.decode(chunk, { stream: true });
            const parts = buffer.split('\n\n');
            buffer = parts.pop() || '';

            parts.forEach((part) => {
                const parsed = parseSseChunk(part);
                if (!parsed) {
                    return;
                }

                // Prism uses underscores in event names: text_delta, tool_call, stream_end
                if (parsed.eventName === 'thinking_delta' && parsed.payload.delta) {
                    setMessages((prev) => prev.map((msg) => (
                        msg.id === assistantId
                            ? { ...msg, thinking: msg.thinking + parsed.payload.delta }
                            : msg
                    )));
                } else if (parsed.eventName === 'thinking_complete') {
                    setMessages((prev) => prev.map((msg) => (
                        msg.id === assistantId ? { ...msg, thinkingDone: true } : msg
                    )));
                } else if (parsed.eventName === 'text_delta' && parsed.payload.delta) {
                    setMessages((prev) => prev.map((msg) => (
                        msg.id === assistantId
                            ? { ...msg, content: msg.content + parsed.payload.delta }
                            : msg
                    )));
                } else if (parsed.eventName === 'tool_call') {
                    const toolName = parsed.payload?.tool_call?.name || parsed.payload?.name || 'processing';
                    setToolStatus(`Running tool: ${toolName}`);
                } else if (parsed.eventName === 'tool_result') {
                    setToolStatus('');
                } else if (parsed.eventName === 'error') {
                    setMessages((prev) => prev.map((msg) => (
                        msg.id === assistantId
                            ? { ...msg, content: parsed.payload?.message || parsed.payload?.error || 'Something went wrong.' }
                            : msg
                    )));
                } else if (parsed.eventName === 'stream_end') {
                    setStreaming(false);
                    setToolStatus('');
                }
            });
        }

        setStreaming(false);
        setToolStatus('');
    };

    const onKeyDown = (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen((prev) => !prev)}
                style={{
                    position: 'fixed',
                    right: '24px',
                    bottom: '24px',
                    width: '58px',
                    height: '58px',
                    borderRadius: '999px',
                    border: 'none',
                    zIndex: 1200,
                    color: '#fff',
                    background: 'linear-gradient(135deg,#6366f1,#8b5cf6)',
                    boxShadow: '0 8px 24px rgba(79,70,229,0.35)',
                }}
            >
                <i className={`fas ${open ? 'fa-times' : 'fa-robot'}`}></i>
            </button>

            {open && (
                <div
                    style={{
                        position: 'fixed',
                        right: '24px',
                        bottom: '92px',
                        width: '360px',
                        height: '520px',
                        zIndex: 1199,
                        borderRadius: '14px',
                        border: '1px solid #e5e7eb',
                        background: '#fff',
                        boxShadow: '0 16px 50px rgba(0,0,0,0.18)',
                        display: 'flex',
                        flexDirection: 'column',
                        overflow: 'hidden',
                    }}
                >
                    <div
                        style={{
                            padding: '14px 16px',
                            background: 'linear-gradient(135deg,#6366f1,#8b5cf6)',
                            color: '#fff',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            gap: '8px',
                        }}
                    >
                        <div>
                            <strong>Munshi G</strong>
                            <div style={{ fontSize: '12px', opacity: 0.9 }}>
                                {streaming ? 'Typing...' : 'Online'}
                            </div>
                        </div>
                        <button
                            type="button"
                            title="Clear conversation"
                            disabled={streaming}
                            onClick={clearChat}
                            style={{
                                flexShrink: 0,
                                border: '1px solid rgba(255,255,255,0.5)',
                                background: 'rgba(255,255,255,0.15)',
                                color: '#fff',
                                borderRadius: '8px',
                                padding: '6px 10px',
                                fontSize: '12px',
                                cursor: streaming ? 'not-allowed' : 'pointer',
                                opacity: streaming ? 0.5 : 1,
                            }}
                        >
                            <i className="fas fa-trash-alt me-1" aria-hidden="true"></i>
                            Clear
                        </button>
                    </div>
                    {clearChatError && (
                        <div
                            style={{
                                padding: '8px 12px',
                                fontSize: '12px',
                                color: '#b91c1c',
                                background: '#fef2f2',
                                borderBottom: '1px solid #fecaca',
                            }}
                        >
                            {clearChatError}
                        </div>
                    )}

                    <div style={{ flex: 1, overflowY: 'auto', padding: '12px', background: '#f8fafc' }}>
                        {messages.map((msg) => (
                            <div
                                key={msg.id}
                                style={{
                                    marginBottom: '10px',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: msg.role === 'user' ? 'flex-end' : 'flex-start',
                                }}
                            >
                                {msg.role === 'assistant' && msg.thinking && (
                                    <details
                                        open={!msg.thinkingDone}
                                        style={{ fontSize: 11, color: '#6b7280', marginBottom: 4, maxWidth: '80%' }}
                                    >
                                        <summary style={{ cursor: 'pointer', userSelect: 'none' }}>
                                            {msg.thinkingDone ? 'Thought process' : 'Thinking...'}
                                        </summary>
                                        <div style={{
                                            marginTop: 4, padding: '6px 10px', background: '#f1f5f9',
                                            borderRadius: 8, whiteSpace: 'pre-wrap', lineHeight: 1.4,
                                            maxHeight: 160, overflowY: 'auto',
                                        }}>
                                            {msg.thinking}
                                        </div>
                                    </details>
                                )}
                                <div
                                    style={{
                                        maxWidth: '80%',
                                        padding: '8px 12px',
                                        borderRadius: '10px',
                                        whiteSpace: 'pre-wrap',
                                        background: msg.role === 'user' ? '#4f46e5' : '#fff',
                                        color: msg.role === 'user' ? '#fff' : '#111827',
                                        border: msg.role === 'user' ? 'none' : '1px solid #e5e7eb',
                                        fontSize: '13px',
                                    }}
                                >
                                    {msg.content || (msg.role === 'assistant' && streaming ? '...' : '')}
                                </div>
                            </div>
                        ))}

                        {toolStatus && (
                            <div style={{ fontSize: '12px', color: '#6b7280', marginBottom: '8px' }}>
                                {toolStatus}
                            </div>
                        )}
                        <div ref={bottomRef}></div>
                    </div>

                    <div style={{ borderTop: '1px solid #e5e7eb', padding: '10px' }}>
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <textarea
                                rows={1}
                                value={input}
                                onChange={(event) => setInput(event.target.value)}
                                onKeyDown={onKeyDown}
                                disabled={streaming}
                                placeholder="Try: I spent 200 on food today"
                                style={{
                                    flex: 1,
                                    resize: 'none',
                                    border: '1px solid #d1d5db',
                                    borderRadius: '8px',
                                    padding: '8px 10px',
                                    fontSize: '13px',
                                }}
                            />
                            <button
                                type="button"
                                disabled={streaming || !input.trim()}
                                onClick={sendMessage}
                                className="btn btn-primary"
                            >
                                <i className="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
