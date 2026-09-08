/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          navy: '#0C2A4A',
          offwhite: '#FAF9F6',
          emerald: '#10B981',
          cobalt: '#3B82F6',
          rose: '#F43F5E',
        }
      }
    },
  },
  plugins: [],
}
