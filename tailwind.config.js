/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './app/**/*.php',
    './resources/views/**/*.blade.php',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Outfit', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace'],
      },
      colors: {
        crm: {
          bg: '#ffffff',
          surface: '#f7f8fa',
          card: '#eef0f4',
          hover: '#e2e5ea',
          border: '#d1d5db',
          'border-h': '#b0b5be',
          t1: '#111111',
          t2: '#4b5563',
          t3: '#9ca3af',
        },
      },
    },
  },
  plugins: [],
};