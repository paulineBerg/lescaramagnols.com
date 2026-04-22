#!/usr/bin/env node
/* eslint-disable no-console */

import { mkdirSync, statSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import fg from 'fast-glob';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const projectRoot = resolve(__dirname, '..');

const FORMATS = [
  { suffix: '', width: null, quality: 82 },
  { suffix: '@400w', width: 400, quality: 80 },
  { suffix: '@700w', width: 700, quality: 78 }
];

const SOURCE_GLOB = ['src/assets/**/*.{jpg,jpeg,png}'];

function buildDestPath(absSource, suffix = '') {
  return absSource.replace(/\.(jpg|jpeg|png)$/i, `${suffix}.webp`);
}

function isUpToDate(sourceStat, destPath) {
  try {
    const destStat = statSync(destPath);
    return destStat.mtimeMs >= sourceStat.mtimeMs;
  } catch {
    return false;
  }
}

async function convertVariant(absSource, relSource, sourceStat, metadata, format) {
  const destPath = buildDestPath(absSource, format.suffix);

  if (absSource.toLowerCase() === destPath.toLowerCase()) {
    return;
  }

  if (format.width && metadata.width && metadata.width <= format.width) {
    return;
  }

  if (isUpToDate(sourceStat, destPath)) {
    console.log(`Skip (up-to-date): ${relSource}${format.suffix}`);
    return;
  }

  mkdirSync(dirname(destPath), { recursive: true });

  try {
    let pipeline = sharp(absSource);
    if (format.width && metadata.width && metadata.width > format.width) {
      pipeline = pipeline.resize({
        width: format.width,
        withoutEnlargement: true
      });
    }

    await pipeline
      .webp({
        quality: format.quality,
        smartSubsample: true
      })
      .toFile(destPath);

    console.log(`Converted ${relSource} -> ${destPath}`);
  } catch (error) {
    console.error(`Failed to convert ${relSource} (${format.suffix || 'base'}):`, error);
    process.exitCode = 1;
  }
}

async function processFile(relPath) {
  const absSource = resolve(projectRoot, relPath);

  let sourceStat;
  try {
    sourceStat = statSync(absSource);
  } catch (error) {
    console.error(`Unable to stat ${relPath}:`, error.message);
    return;
  }

  let metadata;
  try {
    metadata = await sharp(absSource).metadata();
  } catch (error) {
    console.error(`Unable to read ${relPath}:`, error.message);
    return;
  }

  await Promise.all(
    FORMATS.map(format => convertVariant(absSource, relPath, sourceStat, metadata, format))
  );
}

async function main() {
  const files = await fg(SOURCE_GLOB, {
    cwd: projectRoot,
    onlyFiles: true,
    caseSensitiveMatch: false,
    dot: false
  });

  for (const file of files) {
    await processFile(file);
  }
}

main();
