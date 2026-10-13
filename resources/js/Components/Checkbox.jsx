import React from 'react';

export default function Checkbox({ name, value, handleChange }) {
    return (
        <input
            type="checkbox"
            name={name}
            value={value}
            className="h-6 w-6 border-2 border-ink text-brand rounded-none"
            onChange={(e) => handleChange(e)}
        />
    );
}
