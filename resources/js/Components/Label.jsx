import React from 'react';

export default function Label({ forInput, value, className, children }) {
    return (
        <label htmlFor={forInput} className={`block font-bold text-ink mb-1 ${className ?? ''}`}>
            {value ? value : children}
        </label>
    );
}
