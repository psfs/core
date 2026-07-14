import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const outputDirectory = resolve(import.meta.dirname, '../../src/public/ui');
const indexPath = resolve(outputDirectory, 'index.html');

if (!existsSync(indexPath)) {
  throw new Error(`Missing build entry point: ${indexPath}`);
}

const index = readFileSync(indexPath, 'utf8');
const scripts = [...index.matchAll(/<script[^>]+src="([^"]+\.js)"/g)].map((match) => match[1]);

if (scripts.length === 0) {
  throw new Error('index.html does not reference a JavaScript bundle.');
}

const outputFiles = new Set(readdirSync(outputDirectory));

for (const script of scripts) {
  const filename = script.replace(/^\.\//, '');

  if (!outputFiles.has(filename)) {
    throw new Error(`Referenced bundle is missing: ${filename}`);
  }
}

console.log(`Verified static UI build in ${outputDirectory}`);
