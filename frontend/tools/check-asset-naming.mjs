import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..', '..');
const assetImagesRoot = path.join(projectRoot, 'backend', 'public', 'assets', 'images');

const ALLOWED_RELATIVE_PATH = /^[A-Za-z0-9._@/-]+$/u;

async function main() {
  const files = await collectFiles(assetImagesRoot);
  const invalid = [];

  for (const absoluteFilePath of files) {
    const relativePath = path
      .relative(assetImagesRoot, absoluteFilePath)
      .replaceAll(path.sep, '/');

    if (!ALLOWED_RELATIVE_PATH.test(relativePath)) {
      invalid.push(relativePath);
      continue;
    }

    if (relativePath.includes('..')) {
      invalid.push(relativePath);
    }
  }

  if (invalid.length > 0) {
    console.error('[asset-naming] Invalid filenames detected in backend/public/assets/images.');
    for (const item of invalid.sort()) {
      console.error(`- ${item}`);
    }
    console.error('[asset-naming] Allowed characters: A-Z a-z 0-9 . _ - @ and /.');
    process.exitCode = 1;
    return;
  }

  console.log(`[asset-naming] OK - ${files.length} files checked.`);
}

async function collectFiles(directory) {
  const collected = [];
  await walk(directory, collected);
  return collected;
}

async function walk(directory, collected) {
  let entries = [];
  try {
    entries = await fs.readdir(directory, { withFileTypes: true });
  } catch {
    return;
  }

  for (const entry of entries) {
    const absoluteEntryPath = path.join(directory, entry.name);
    if (entry.isDirectory()) {
      await walk(absoluteEntryPath, collected);
      continue;
    }

    if (entry.isFile()) {
      collected.push(absoluteEntryPath);
    }
  }
}

main().catch((error) => {
  console.error(`[asset-naming] ${error instanceof Error ? error.message : String(error)}`);
  process.exitCode = 1;
});
