import React from 'react';

export default function Button({ type = 'submit', className = '', processing, children }) {
    return (
        <button
            type={type}
            className={`gov-button ${processing ? 'opacity-60' : ''} ${className}`}
            disabled={processing}
        >
            {children}
        </button>
    );
}
