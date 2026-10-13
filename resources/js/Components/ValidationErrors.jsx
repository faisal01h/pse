import React from 'react';

export default function ValidationErrors({ errors }) {
    return (
        Object.keys(errors).length > 0 && (
            <div className="mb-6 border-l-8 border-brand bg-canvas px-4 py-3" role="alert">
                <p className="font-bold">Ada masalah pada isian ini.</p>
                <ul className="mt-2 list-disc pl-5">
                    {Object.keys(errors).map(function (key, index) {
                        return <li key={index}>{errors[key]}</li>;
                    })}
                </ul>
            </div>
        )
    );
}
