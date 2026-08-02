/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './renderer/pages/**/*.html',
    './renderer/js/**/*.js',
    './renderer/assets/js/pages/**/*.js',
    './renderer/assets/js/main.js',
    '!./renderer/assets/js/**/*.min.js',
  ],
  theme: {
    extend: {
      colors: {
        // Palet "broken white" milik aplikasi (dulu dari bw-theme.css)
        bw: {
          50: '#f8f7f4',  // --bg-primary
          100: '#f0eeea', // --bg-secondary
          200: '#e8e6e1', // --bg-tertiary
          300: '#c0bdb5', // --border-light
        },
        ink: {
          DEFAULT: '#1a1a1a', // --text-primary / --accent-dark
          800: '#2a2a2a',     // --border-color
          700: '#333333',     // --hover-dark
          600: '#4a4a4a',     // --text-secondary
          500: '#6b6b6b',     // --text-muted
        },
      },
      fontFamily: {
        sans: ['Inter', 'Segoe UI', 'Tahoma', 'Geneva', 'Verdana', 'sans-serif'],
        mono: ['JetBrains Mono', 'Consolas', 'Courier New', 'monospace'],
      },
      spacing: {
        sidebar: '250px',
      },
      zIndex: {
        sidebar: '1000',
        modal: '1055',
      },
      keyframes: {
        pageLoad: {
          from: { opacity: '0.7', transform: 'translateY(5px)' },
          to: { opacity: '1', transform: 'translateY(0)' },
        },
      },
      animation: {
        pageLoad: 'pageLoad 0.2s ease-out',
      },
    },
  },
  plugins: [],
};
