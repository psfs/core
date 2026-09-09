import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  outputDir: './test-results/ui',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 30_000,
  reporter: 'line',
  use: { baseURL: 'http://127.0.0.1:4200' },
  webServer: [
    {
      command: 'node e2e/mock-api.mjs',
      url: 'http://127.0.0.1:4310/admin/api/v2/bootstrap',
      reuseExistingServer: false,
      timeout: 30_000
    },
    {
      command: 'npm run watch:admin:e2e',
      url: 'http://127.0.0.1:4200/admin-v2/',
      reuseExistingServer: false,
      timeout: 60_000
    }
  ]
});
