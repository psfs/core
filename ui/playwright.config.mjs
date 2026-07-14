import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  outputDir: './test-results',
  fullyParallel: false,
  retries: 0,
  timeout: 30_000,
  reporter: 'line',
  use: {
    baseURL: process.env.PSFS_E2E_BASE_URL ?? 'http://php-swoole:8080',
    httpCredentials: {
      username: process.env.PSFS_E2E_ADMIN_USER ?? 'admin',
      password: process.env.PSFS_E2E_ADMIN_PASSWORD ?? 'admin'
    }
  }
});
