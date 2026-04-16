import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const distDir = path.resolve(__dirname, '..', 'dist');
const distAssetsDir = path.join(distDir, 'assets');
const distTarteaucitronDir = path.join(distDir, 'tarteaucitron');
const distManifestPath = path.join(distDir, '.vite', 'manifest.json');

const backendPublicDir = path.resolve(__dirname, '..', '..', 'backend', 'public');
const backendAssetsDir = path.join(backendPublicDir, 'assets');
const backendImagesDir = path.join(backendAssetsDir, 'images');
const backendTarteaucitronDir = path.join(backendPublicDir, 'tarteaucitron');
const backendViteDir = path.join(backendPublicDir, '.vite');
const backendManifestPath = path.join(backendViteDir, 'manifest.json');
const distImagesDir = path.join(distAssetsDir, 'images');

const PRESERVED_ROOT_FILES = new Set(['index.php', 'rss.php']);

async function main() {
  const manifest = await readManifest(distManifestPath);
  const referencedAssetPaths = collectPublishedAssetPaths(manifest);

  await fs.mkdir(backendAssetsDir, { recursive: true });
  await fs.mkdir(backendViteDir, { recursive: true });

  await syncPublishedImages();
  await copyDistAssets();
  await copyStaticDirectory(distTarteaucitronDir, backendTarteaucitronDir);
  await fs.copyFile(distManifestPath, backendManifestPath);

  const removedRootAssets = await purgeObsoleteRootAssets(referencedAssetPaths);

  console.log(
    [
      '[publish-build] publication terminée',
      `- manifest : ${path.relative(process.cwd(), backendManifestPath)}`,
      `- assets référencés : ${referencedAssetPaths.size}`,
      `- images publiées : ${path.relative(process.cwd(), backendImagesDir)}`,
      `- anciens bundles supprimés : ${removedRootAssets.length}`,
    ].join('\n')
  );
}

async function readManifest(manifestPath) {
  const rawManifest = await fs.readFile(manifestPath, 'utf8');
  const manifest = JSON.parse(rawManifest);

  if (manifest === null || typeof manifest !== 'object' || Array.isArray(manifest)) {
    throw new Error(`Manifest Vite invalide: ${manifestPath}`);
  }

  return manifest;
}

function collectPublishedAssetPaths(manifest) {
  const assetPaths = new Set();

  for (const manifestEntry of Object.values(manifest)) {
    if (manifestEntry === null || typeof manifestEntry !== 'object' || Array.isArray(manifestEntry)) {
      continue;
    }

    for (const key of ['file', 'css', 'assets']) {
      const value = manifestEntry[key];

      if (typeof value === 'string') {
        assetPaths.add(normalizePublishedAssetPath(value));
        continue;
      }

      if (Array.isArray(value)) {
        for (const item of value) {
          if (typeof item === 'string') {
            assetPaths.add(normalizePublishedAssetPath(item));
          }
        }
      }
    }
  }

  return assetPaths;
}

function normalizePublishedAssetPath(assetPath) {
  const normalized = assetPath.replaceAll('\\', '/').replace(/^\/+/, '');

  if (normalized.startsWith('assets/')) {
    return normalized.slice('assets/'.length);
  }

  return normalized;
}

async function copyDistAssets() {
  await copyStaticDirectory(distAssetsDir, backendAssetsDir);
}

async function syncPublishedImages() {
  await fs.rm(backendImagesDir, {
    force: true,
    recursive: true,
  });

  await copyStaticDirectory(distImagesDir, backendImagesDir);
}

async function copyStaticDirectory(sourceDir, targetDir) {
  try {
    await fs.access(sourceDir);
  } catch {
    return;
  }

  await fs.cp(sourceDir, targetDir, {
    recursive: true,
    force: true,
  });
}

async function purgeObsoleteRootAssets(referencedAssetPaths) {
  const removed = [];
  const entries = await fs.readdir(backendAssetsDir, { withFileTypes: true });

  for (const entry of entries) {
    if (!entry.isFile()) {
      continue;
    }

    if (PRESERVED_ROOT_FILES.has(entry.name)) {
      continue;
    }

    if (!looksLikeHashedRootAsset(entry.name)) {
      continue;
    }

    if (referencedAssetPaths.has(entry.name)) {
      continue;
    }

    await fs.unlink(path.join(backendAssetsDir, entry.name));
    removed.push(entry.name);
  }

  return removed;
}

function looksLikeHashedRootAsset(filename) {
  return /^[^/]+\.[A-Za-z0-9_-]{8,}\.[^.]+$/.test(filename);
}

main().catch((error) => {
  console.error(`[publish-build] ${error instanceof Error ? error.message : String(error)}`);
  process.exitCode = 1;
});
