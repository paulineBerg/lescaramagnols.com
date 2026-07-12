import { statSync, readFileSync, existsSync } from 'node:fs';
import { extname, join } from 'node:path';

const DIST_DIR = join(process.cwd(), 'dist');
const MANIFEST_PATH = join(DIST_DIR, '.vite', 'manifest.json');

const budget = {
  jsEntryMaxBytes: 70 * 1024,
  cssEntryMaxBytes: 110 * 1024,
  initialTotalMaxBytes: 220 * 1024,
  largestImageMaxBytes: 220 * 1024
};

if (!existsSync(MANIFEST_PATH)) {
  console.error(`[budget] Manifest introuvable: ${MANIFEST_PATH}`);
  process.exit(1);
}

const manifest = JSON.parse(readFileSync(MANIFEST_PATH, 'utf8'));

const entries = Object.values(manifest).filter(
  (value) => value && typeof value === 'object' && value.isEntry === true
);

if (entries.length === 0) {
  console.error('[budget] Aucun entrypoint Vite trouvé dans le manifest.');
  process.exit(1);
}

const mainEntry = manifest['src/js/main.ts'] ?? entries.find((entry) => entry.src === 'src/js/main.ts') ?? entries[0];
const jsFiles = [mainEntry.file].filter((value) => typeof value === 'string');
const cssFiles = Array.isArray(mainEntry.css) ? mainEntry.css.filter((value) => typeof value === 'string') : [];

const imageExtensions = new Set(['.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg', '.avif']);
const imageFiles = new Set();

for (const value of Object.values(manifest)) {
  if (!value || typeof value !== 'object') {
    continue;
  }

  const files = [];
  if (typeof value.file === 'string') {
    files.push(value.file);
  }
  if (Array.isArray(value.assets)) {
    for (const asset of value.assets) {
      if (typeof asset === 'string') {
        files.push(asset);
      }
    }
  }

  for (const file of files) {
    if (imageExtensions.has(extname(file).toLowerCase())) {
      imageFiles.add(file);
    }
  }
}

const sizeOf = (relativeFile) => statSync(join(DIST_DIR, relativeFile)).size;
const sum = (values) => values.reduce((total, value) => total + value, 0);
const bytes = (value) => `${(value / 1024).toFixed(1)} KiB`;

const jsSize = sum(jsFiles.map(sizeOf));
const cssSize = sum(cssFiles.map(sizeOf));
const initialTotalSize = jsSize + cssSize;
const imageSizes = Array.from(imageFiles).map((file) => ({ file, size: sizeOf(file) }));
const largestImage = imageSizes.sort((left, right) => right.size - left.size)[0] ?? { file: '-', size: 0 };

const checks = [
  {
    label: 'JS entry',
    current: jsSize,
    max: budget.jsEntryMaxBytes
  },
  {
    label: 'CSS entry',
    current: cssSize,
    max: budget.cssEntryMaxBytes
  },
  {
    label: 'Initial JS+CSS',
    current: initialTotalSize,
    max: budget.initialTotalMaxBytes
  },
  {
    label: `Image max (${largestImage.file})`,
    current: largestImage.size,
    max: budget.largestImageMaxBytes
  }
];

let hasFailure = false;

console.log('[budget] Vérification des budgets frontend');
for (const check of checks) {
  const ok = check.current <= check.max;
  hasFailure = hasFailure || !ok;
  console.log(
    `- ${check.label}: ${bytes(check.current)} / ${bytes(check.max)} ${ok ? 'OK' : 'DEPASSE'}`
  );
}

if (hasFailure) {
  console.error('[budget] Build refusé: au moins un budget est dépassé.');
  process.exit(1);
}

console.log('[budget] Budgets respectés.');
