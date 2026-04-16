import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..', '..');

const ALLOWED_BACKEND_ASSET_ROOT = new Set([
  'backend/public/assets/index.php',
  'backend/public/assets/rss.php',
]);

async function main() {
  const issues = [];

  const trackedDist = listTrackedFiles('frontend/dist');
  if (trackedDist.length > 0) {
    issues.push({
      label: 'Tracked frontend build output (frontend/dist)',
      items: trackedDist,
    });
  }

  const trackedBackendVite = listTrackedFiles('backend/public/.vite');
  if (trackedBackendVite.length > 0) {
    issues.push({
      label: 'Tracked backend manifest output (backend/public/.vite)',
      items: trackedBackendVite,
    });
  }

  const trackedBackendTarteaucitron = listTrackedFiles('backend/public/tarteaucitron');
  if (trackedBackendTarteaucitron.length > 0) {
    issues.push({
      label: 'Tracked published tarteaucitron output (backend/public/tarteaucitron)',
      items: trackedBackendTarteaucitron,
    });
  }

  const trackedBackendAssets = listTrackedFiles('backend/public/assets');
  const trackedBackendAssetsRoot = trackedBackendAssets.filter((filePath) => {
    return path.posix.dirname(filePath) === 'backend/public/assets';
  });
  const trackedBackendAssetImages = trackedBackendAssets.filter((filePath) => {
    return filePath.startsWith('backend/public/assets/images/');
  });
  const forbiddenBackendAssetsRoot = trackedBackendAssetsRoot.filter((filePath) => {
    return !ALLOWED_BACKEND_ASSET_ROOT.has(filePath);
  });

  if (forbiddenBackendAssetsRoot.length > 0) {
    issues.push({
      label: 'Tracked generated files at backend/public/assets root',
      items: forbiddenBackendAssetsRoot,
    });
  }

  if (trackedBackendAssetImages.length > 0) {
    issues.push({
      label: 'Tracked published image output (backend/public/assets/images)',
      items: trackedBackendAssetImages,
    });
  }

  const allTracked = listTrackedFiles();
  const transientTracked = allTracked.filter((filePath) => {
    return /(\.tmp|\.bak|\.swp|:Zone\.Identifier)$/u.test(filePath);
  });
  if (transientTracked.length > 0) {
    issues.push({
      label: 'Tracked transient files (*.tmp, *.bak, *.swp, *:Zone.Identifier)',
      items: transientTracked,
    });
  }

  if (issues.length > 0) {
    console.error('[repo-artifacts] Repository artifact policy violations detected.');
    for (const issue of issues) {
      console.error(`- ${issue.label}`);
      for (const item of issue.items.slice(0, 200)) {
        console.error(`  - ${item}`);
      }
      if (issue.items.length > 200) {
        console.error(`  - ... (${issue.items.length - 200} more)`);
      }
    }
    process.exitCode = 1;
    return;
  }

  console.log('[repo-artifacts] OK - no tracked build artifacts or transient files.');
}

function listTrackedFiles(pathSpec = null) {
  const args = ['ls-files'];
  if (pathSpec !== null) {
    args.push(pathSpec);
  }

  const raw = execFileSync('git', args, {
    cwd: projectRoot,
    encoding: 'utf8',
  });

  return raw
    .split('\n')
    .map((item) => item.trim())
    .filter(Boolean);
}

main().catch((error) => {
  console.error(`[repo-artifacts] ${error instanceof Error ? error.message : String(error)}`);
  process.exitCode = 1;
});
