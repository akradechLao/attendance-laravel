/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.vue',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                navy: '#0f172a',
                blue: '#3b82f6',
                cream: '#f0f4ff',
            }
        },
    },
    plugins: [],
}
