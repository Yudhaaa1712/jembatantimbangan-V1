/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./renderer/pages/**/*.html",
    "./renderer/js/**/*.js"
  ],
  theme: {
    extend: {
      colors: {
        neoBg: '#f4f4f0',
        neoYellow: '#ffe600',
        neoPink: '#ff007f',
        neoBlue: '#0066ff',
        neoGreen: '#00ff66',
        neoOrange: '#ff6600',
      },
      boxShadow: {
        neo: '4px 4px 0px 0px rgba(0,0,0,1)',
        neoLg: '8px 8px 0px 0px rgba(0,0,0,1)',
      }
    },
  },
  plugins: [],
}
