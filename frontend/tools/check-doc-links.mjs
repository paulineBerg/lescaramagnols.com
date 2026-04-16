import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..', '..');

const MARKDOWN_LINK_REGEX = /\[[^\]]+\]\(([^)]+)\)/g;
const EXTERNAL_PREFIXES = ['http://', 'https://', 'mailto:', 'tel:', 'javascript:'];

async function main() {
  const markdownFiles = await listMarkdownFiles();
  const brokenLinks = [];

  for (const relativeFilePath of markdownFiles) {
    const absoluteFilePath = path.join(projectRoot, relativeFilePath);
    const content = await fs.readFile(absoluteFilePath, 'utf8');

    let match = null;
    while ((match = MARKDOWN_LINK_REGEX.exec(content)) !== null) {
      const rawTarget = match[1];
      const href = normalizeLinkTarget(rawTarget);

      if (href === null || href === '' || href.startsWith('#') || isExternalLink(href)) {
        continue;
      }

      const targetPath = stripAnchorAndQuery(decodePath(href));
      if (targetPath === '') {
        continue;
      }

      const absoluteTarget = resolveLinkTarget(absoluteFilePath, targetPath);
      if (!(await exists(absoluteTarget))) {
        brokenLinks.push({
          file: relativeFilePath,
          href,
        });
      }
    }
  }

  if (brokenLinks.length > 0) {
    console.error('[docs-links] Broken markdown links detected.');
    for (const issue of brokenLinks) {
      console.error(`- ${issue.file} -> ${issue.href}`);
    }
    process.exitCode = 1;
    return;
  }

  console.log(`[docs-links] OK - ${markdownFiles.length} files checked, 0 broken links.`);
}

async function listMarkdownFiles() {
  const collected = [];
  await walkForMarkdown(projectRoot, collected);
  return collected.sort();
}

async function walkForMarkdown(directory, collected) {
  const relativeDirectory = path.relative(projectRoot, directory).replaceAll(path.sep, '/');
  if (shouldSkipDirectory(relativeDirectory)) {
    return;
  }

  const entries = await fs.readdir(directory, { withFileTypes: true });
  for (const entry of entries) {
    const absoluteEntryPath = path.join(directory, entry.name);
    const relativeEntryPath = path.relative(projectRoot, absoluteEntryPath).replaceAll(path.sep, '/');

    if (entry.isDirectory()) {
      await walkForMarkdown(absoluteEntryPath, collected);
      continue;
    }

    if (!entry.isFile()) {
      continue;
    }

    if (!relativeEntryPath.endsWith('.md')) {
      continue;
    }

    if (isTargetMarkdownFile(relativeEntryPath)) {
      collected.push(relativeEntryPath);
    }
  }
}

function shouldSkipDirectory(relativePath) {
  if (relativePath === '') {
    return false;
  }

  const normalized = `${relativePath}/`;
  const blockedPrefixes = [
    '.git/',
    'vendor/',
    'backend/vendor/',
    'frontend/node_modules/',
    'frontend/dist/',
    'backend/public/assets/',
    'backend/public/.vite/',
    'backups/',
  ];

  return blockedPrefixes.some((prefix) => normalized.startsWith(prefix));
}

function isTargetMarkdownFile(relativePath) {
  if (relativePath === 'README.md') {
    return true;
  }

  if (/^README.*\.md$/u.test(relativePath)) {
    return true;
  }

  if (/^backend\/README.*\.md$/u.test(relativePath)) {
    return true;
  }

  if (/^frontend\/README.*\.md$/u.test(relativePath)) {
    return true;
  }

  return relativePath.startsWith('docs/');
}

function normalizeLinkTarget(rawTarget) {
  const trimmed = rawTarget.trim();
  if (trimmed === '') {
    return null;
  }

  if (trimmed.startsWith('<') && trimmed.endsWith('>')) {
    return trimmed.slice(1, -1).trim();
  }

  const firstWhitespaceIndex = trimmed.search(/\s/u);
  if (firstWhitespaceIndex === -1) {
    return trimmed;
  }

  return trimmed.slice(0, firstWhitespaceIndex);
}

function isExternalLink(href) {
  const lowerHref = href.toLowerCase();
  return EXTERNAL_PREFIXES.some((prefix) => lowerHref.startsWith(prefix));
}

function stripAnchorAndQuery(href) {
  const withoutAnchor = href.split('#')[0] ?? '';
  return withoutAnchor.split('?')[0] ?? '';
}

function decodePath(href) {
  try {
    return decodeURI(href);
  } catch {
    return href;
  }
}

function resolveLinkTarget(sourceFilePath, hrefPath) {
  if (path.isAbsolute(hrefPath)) {
    return path.join(projectRoot, hrefPath.replace(/^\/+/u, ''));
  }

  return path.resolve(path.dirname(sourceFilePath), hrefPath);
}

async function exists(filePath) {
  try {
    await fs.access(filePath);
    return true;
  } catch {
    return false;
  }
}

main().catch((error) => {
  console.error(`[docs-links] ${error instanceof Error ? error.message : String(error)}`);
  process.exitCode = 1;
});
