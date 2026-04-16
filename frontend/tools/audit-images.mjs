import { readdirSync, statSync, readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { extname, join, relative } from 'node:path';

const projectRoot = process.cwd();
const imageRoot = join(projectRoot, '..', 'backend', 'public', 'assets', 'images');

const rasterExtensions = new Set(['.jpg', '.jpeg', '.png', '.gif']);
const modernExtensions = new Set(['.webp', '.avif']);
const allowedNamePattern = /^[a-z0-9._/-]+$/;

/**
 * @param {string} directory
 * @param {string[]} files
 */
function walk(directory, files = []) {
  for (const entry of readdirSync(directory)) {
    const absolute = join(directory, entry);
    const stats = statSync(absolute);

    if (stats.isDirectory()) {
      walk(absolute, files);
      continue;
    }

    files.push(absolute);
  }

  return files;
}

const files = walk(imageRoot);

const summary = {
  total: 0,
  rasterLegacy: 0,
  modern: 0,
  duplicateGroups: 0,
  duplicateFiles: 0,
  invalidNames: 0,
  missingModernVariant: 0
};

/** @type {Map<string, string[]>} */
const byHash = new Map();
/** @type {Set<string>} */
const allWithoutExt = new Set();
/** @type {string[]} */
const invalidNames = [];

for (const absolutePath of files) {
  const extension = extname(absolutePath).toLowerCase();
  if (!rasterExtensions.has(extension) && !modernExtensions.has(extension) && extension !== '.svg') {
    continue;
  }

  summary.total += 1;

  if (rasterExtensions.has(extension)) {
    summary.rasterLegacy += 1;
  }
  if (modernExtensions.has(extension)) {
    summary.modern += 1;
  }

  const rel = relative(imageRoot, absolutePath).replaceAll('\\', '/');
  const noExt = rel.slice(0, Math.max(0, rel.length - extension.length));
  allWithoutExt.add(noExt.toLowerCase());

  const normalizedName = rel.toLowerCase();
  if (!allowedNamePattern.test(normalizedName) || normalizedName.includes(' ')) {
    invalidNames.push(rel);
  }

  const hash = createHash('sha1').update(readFileSync(absolutePath)).digest('hex');
  const existing = byHash.get(hash) ?? [];
  existing.push(rel);
  byHash.set(hash, existing);
}

const missingModernVariant = [];
for (const absolutePath of files) {
  const extension = extname(absolutePath).toLowerCase();
  if (!rasterExtensions.has(extension)) {
    continue;
  }

  const rel = relative(imageRoot, absolutePath).replaceAll('\\', '/');
  const noExt = rel.slice(0, Math.max(0, rel.length - extension.length)).toLowerCase();
  const hasModern = allWithoutExt.has(noExt + '.webp') || allWithoutExt.has(noExt + '.avif');

  if (!hasModern) {
    missingModernVariant.push(rel);
  }
}

const duplicateGroups = Array.from(byHash.values()).filter((group) => group.length > 1);
summary.duplicateGroups = duplicateGroups.length;
summary.duplicateFiles = duplicateGroups.reduce((count, group) => count + group.length, 0);
summary.invalidNames = invalidNames.length;
summary.missingModernVariant = missingModernVariant.length;

console.log('[images-audit] Résumé');
console.log(`- Total images suivies: ${summary.total}`);
console.log(`- Formats legacy (jpg/png/gif): ${summary.rasterLegacy}`);
console.log(`- Formats modernes (webp/avif): ${summary.modern}`);
console.log(`- Groupes de doublons exacts: ${summary.duplicateGroups}`);
console.log(`- Fichiers au nom non normalisé: ${summary.invalidNames}`);
console.log(`- Images legacy sans variante webp/avif: ${summary.missingModernVariant}`);

if (duplicateGroups.length > 0) {
  console.log('[images-audit] Exemple doublons (max 10 groupes)');
  for (const group of duplicateGroups.slice(0, 10)) {
    console.log(`  - ${group.join(' | ')}`);
  }
}

if (invalidNames.length > 0) {
  console.log('[images-audit] Exemple noms à corriger (max 20)');
  for (const name of invalidNames.slice(0, 20)) {
    console.log(`  - ${name}`);
  }
}

if (missingModernVariant.length > 0) {
  console.log('[images-audit] Exemple images legacy sans variante moderne (max 20)');
  for (const name of missingModernVariant.slice(0, 20)) {
    console.log(`  - ${name}`);
  }
}
