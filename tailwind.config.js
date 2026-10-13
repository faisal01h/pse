const defaultTheme = require('tailwindcss/defaultTheme');

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Public Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                ink: '#0b0c0c',
                brand: '#f43f5e',
                'brand-dark': '#e11d48',
                canvas: '#f3f2f1',
                muted: '#505a5f',
            },
        },
    },

    plugins: [require('@tailwindcss/forms')],
};
