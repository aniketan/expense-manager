import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

// Run every test in the app's primary time zone (UTC+05:30), so date bugs that
// only show up east of UTC fail in CI too. Set before workers start so they inherit it.
process.env.TZ = 'Asia/Kolkata';

export default defineConfig({
  plugins: [react()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./resources/js/__tests__/setup.js'],
  },
});
