/**
 * Vitest configuration for the unit tests of the browser scripts in assets/js.
 *
 * Run with `npm run test:js`. Each test loads the script it exercises with
 * `vi.resetModules()` + `await import()`, so the script runs the way the page
 * runs it and the coverage report sees it.
 */
import { defineConfig } from 'vitest/config';

export default defineConfig( {
	test: {
		environment: 'jsdom',
		include: [ 'tests/js/**/*.test.js' ],
		// Hasta que llegue el primer guion de assets/js no hay nada que probar.
		passWithNoTests: true,
		globals: false,
		clearMocks: true,
		mockReset: false,
		restoreMocks: true,
		coverage: {
			enabled: true,
			provider: 'v8',
			include: [ 'assets/js/*.js' ],
			reportsDirectory: 'artifacts/coverage-js',
			reporter: [ 'lcov', 'text-summary' ],
		},
	},
} );
