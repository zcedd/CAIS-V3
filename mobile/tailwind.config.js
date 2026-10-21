/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ['./app/**/*.{js,jsx,ts,tsx}', './src/**/*.{js,jsx,ts,tsx}'],
    presets: [require('nativewind/preset')],
    theme: {
        extend: {
            colors: {
                paper: '#EFEDE8',
                ink: '#1C1917',
                brand: '#4338CA',
            },
        },
    },
    plugins: [],
};
