import forms from '@tailwindcss/forms'

/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,jsx}'],
  theme: {
    extend: {
      colors: {
        midnight: '#050c18',
        navy: {
          950: '#07111f',
          900: '#0b1628',
          800: '#10203a',
          700: '#173050',
          600: '#21456e',
        },
        gold: {
          50: '#fff9e7',
          100: '#fef2c6',
          200: '#f9df8c',
          300: '#f3ca57',
          400: '#e7b93b',
          500: '#d89c1a',
          600: '#b97812',
          700: '#87550f',
        },
        ink: '#1f2a37',
        mist: '#5f6b7a',
        panel: 'rgba(255, 255, 255, 0.92)',
      },
      fontFamily: {
        sans: ['Manrope', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        display: ['Cormorant Garamond', 'ui-serif', 'Georgia', 'serif'],
      },
      boxShadow: {
        glass: '0 16px 40px rgba(4, 10, 23, 0.34)',
        gold: '0 0 0 1px rgba(243, 202, 87, 0.18), 0 12px 30px rgba(215, 156, 26, 0.18)',
        'gold-soft': '0 0 22px rgba(243, 202, 87, 0.14)',
        'card-hover': '0 16px 28px rgba(2, 8, 20, 0.42)',
      },
      backgroundImage: {
        'gold-radial':
          'radial-gradient(circle at top, rgba(243, 202, 87, 0.26), transparent 42%)',
        'navy-panel': 'linear-gradient(160deg, rgba(255, 255, 255, 0.995), rgba(255, 255, 255, 0.988))',
      },
      animation: {
        shimmer: 'shimmer 3s linear infinite',
        float: 'float 7s ease-in-out infinite',
        fadeUp: 'fadeUp 0.8s ease forwards',
      },
      keyframes: {
        shimmer: {
          '0%': { backgroundPosition: '-200% center' },
          '100%': { backgroundPosition: '200% center' },
        },
        float: {
          '0%, 100%': { transform: 'translateY(0px)' },
          '50%': { transform: 'translateY(-10px)' },
        },
        fadeUp: {
          '0%': { opacity: '0', transform: 'translateY(18px)' },
          '100%': { opacity: '1', transform: 'translateY(0px)' },
        },
      },
      container: {
        center: true,
        padding: {
          DEFAULT: '0.875rem',
          sm: '1rem',
          lg: '1.25rem',
          xl: '1.5rem',
        },
      },
    },
  },
  plugins: [forms],
}
