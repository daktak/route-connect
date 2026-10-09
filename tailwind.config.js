/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#2563eb',
                    hover: '#1d4ed8',
                },
                success: '#16a34a',
                warning: '#f59e0b',
                danger: '#dc2626',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
};