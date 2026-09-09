import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  outputDir: './test-results/static',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 30_000,
  reporter: 'line',
  use: { baseURL: 'http://127.0.0.1:4201' },
  webServer: [
    {
      command: 'node e2e/mock-api.mjs',
      url: 'http://127.0.0.1:4310/admin/api/v2/bootstrap',
      reuseExistingServer: false,
      timeout: 30_000
    },
    {
      command: 'npm run build:admin:e2e && PSFS_UI_STATIC_ROOT=dist node e2e/static-server.mjs',
      url: 'http://127.0.0.1:4201/admin-v2/',
      reuseExistingServer: false,
      timeout: 60_000
    }
  ]
});
