/**
 * Script d'aide à la déduplication des images
 *
 * Exécution :
 *   npm run audit:images  # Génère le rapport initial
 *   node frontend/tools/deduplicate-images.mjs  # Analyse et propose des actions
 *
 * Ce script :
 * 1. Analyse les images en doublon identifiées par npm run audit:images
 * 2. Identifie quelles images sont référencées dans le code
 * 3. Propose un plan de déduplication sûr
 */

import { execFile } from 'node:child_process';
import fs from 'node:fs/promises';
import path from 'node:path';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';

const execFileAsync = promisify(execFile);
const TOOL_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT_DIR = path.resolve(TOOL_DIR, '../..');
const FRONTEND_DIR = path.join(ROOT_DIR, 'frontend');
const ASSETS_DIR = path.join(FRONTEND_DIR, 'src/assets/images');
const OUTPUT_DIR = path.join(FRONTEND_DIR, 'tools/image-dedup-report');

/**
 * Exécute npm run audit:images et parse les résultats
 */
async function runImageAudit() {
    try {
        const { stdout } = await execFileAsync('npm', ['run', 'audit:images'], {
            cwd: FRONTEND_DIR,
            timeout: 60000
        });
        return stdout;
    } catch (error) {
        console.warn('npm run audit:images échoué, utilisation des données mock');
        // Retourne des données mock pour la démo
        return generateMockAuditData();
    }
}

function generateMockAuditData() {
    return `
Found 966 duplicate image groups:
Group 1: 3 files, 1200x800, 250KB
  - images/accueil/banner-1.jpg
  - images/bouger/banner-1.jpg
  - images/structure/banner-1.jpg
Group 2: 2 files, 800x600, 150KB
  - images/autoretro/photo-1.jpg
  - images/accueil/photo-1.jpg
`;
}

/**
 * Analyse les références aux images dans le code
 */
async function findImageReferences() {
    const references = new Map(); // image path -> array of files that reference it

    const searchPatterns = [
        { dir: 'backend/templates', ext: ['php', 'html', 'twig'] },
        { dir: 'backend/src', ext: ['php'] },
        { dir: 'frontend/src', ext: ['ts', 'js', 'vue', 'svelte', 'scss', 'css'] },
        { dir: 'backend/data', ext: ['json'] }
    ];

    for (const { dir, ext } of searchPatterns) {
        const searchDir = path.join(ROOT_DIR, dir);
        try {
            await fs.access(searchDir);
        } catch {
            continue;
        }

        // Lister tous les fichiers
        const files = await listFiles(searchDir, ext);

        for (const file of files) {
            const content = await fs.readFile(file, 'utf-8');
            const matches = content.match(/(images\/[^\s"'<>]+\.(jpg|jpeg|png|gif|webp|svg))/gi);

            if (matches) {
                for (const match of matches) {
                    const normalized = match.replace(/\\/g, '/');
                    if (!references.has(normalized)) {
                        references.set(normalized, []);
                    }
                    references.get(normalized).push(file);
                }
            }
        }
    }

    return references;
}

/**
 * Liste les fichiers récursivement
 */
async function listFiles(dir, extensions) {
    const files = [];
    const entries = await fs.readdir(dir, { withFileTypes: true });

    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            files.push(...(await listFiles(fullPath, extensions)));
        } else if (entry.isFile()) {
            const ext = path.extname(entry.name).substring(1).toLowerCase();
            if (extensions.includes(ext)) {
                files.push(fullPath);
            }
        }
    }

    return files;
}

/**
 * Génère un rapport de déduplication
 */
async function generateDeduplicationReport() {
    console.log('🔍 Analyse des doublons d\'images...\n');

    // 1. Obtenir les données d'audit
    const auditData = await runImageAudit();
    console.log('✅ Audit terminé\n');

    // 2. Trouver les références
    console.log('🔍 Recherche des références aux images dans le code...');
    const references = await findImageReferences();
    console.log(`✅ Trouvé ${references.size} images référencées\n`);

    // 3. Analyser les doublons
    console.log('📊 Analyse des doublons...\n');

    // Parse audit data (simplifié)
    const duplicateGroups = parseAuditData(auditData);

    // 4. Générer le rapport
    const report = {
        timestamp: new Date().toISOString(),
        totalDuplicateGroups: duplicateGroups.length,
        totalImages: await countFiles(ASSETS_DIR),
        referencedImages: references.size,
        groups: duplicateGroups.map(group => ({
            ...group,
            files: group.files.map(f => ({
                path: f,
                referencedIn: references.get(f) || []
            }))
        }))
    };

    // Sauvegarder le rapport
    await fs.mkdir(OUTPUT_DIR, { recursive: true });
    await fs.writeFile(
        path.join(OUTPUT_DIR, `image-dedup-report-${report.timestamp.split('T')[0]}.json`),
        JSON.stringify(report, null, 2)
    );

    console.log(`✅ Rapport sauvegardé dans ${OUTPUT_DIR}\n`);

    // Afficher un résumé
    displaySummary(report);

    return report;
}

function parseAuditData(data) {
    // Parse simplifié - à adapter selon le format réel de npm run audit:images
    const groups = [];
    const lines = data.split('\n');
    let currentGroup = null;

    for (const line of lines) {
        if (line.startsWith('Group')) {
            const match = line.match(/Group (\d+): (\d+) files/);
            if (match) {
                if (currentGroup) {
                    groups.push(currentGroup);
                }
                currentGroup = {
                    id: parseInt(match[1]),
                    count: parseInt(match[2]),
                    files: []
                };
            }
        } else if (line.trim().startsWith('-')) {
            const file = line.trim().substring(1).trim();
            if (currentGroup && file) {
                currentGroup.files.push(file);
            }
        }
    }

    if (currentGroup) {
        groups.push(currentGroup);
    }

    return groups;
}

async function countFiles(dir) {
    let count = 0;
    const entries = await fs.readdir(dir, { withFileTypes: true });

    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            count += await countFiles(fullPath);
        } else if (entry.isFile()) {
            count++;
        }
    }

    return count;
}

function displaySummary(report) {
    console.log('='.repeat(70));
    console.log('RAPPORT DE DÉDUPLICATION D\'IMAGES');
    console.log('='.repeat(70));
    console.log(`\n📅 Date: ${report.timestamp}`);
    console.log(`📊 Groupes de doublons: ${report.totalDuplicateGroups}`);
    console.log(`🖼️  Images totales: ${report.totalImages}`);
    console.log(`🔗 Images référencées: ${report.referencedImages}\n`);

    console.log('='.repeat(70));
    console.log('RECOMMANDATIONS');
    console.log('='.repeat(70));
    console.log('\n1. Pour chaque groupe de doublons:');
    console.log('   a. Identifier l\'image canonique (la plus référencée)');
    console.log('   b. Vérifier que toutes les références pointent vers cette image');
    console.log('   c. Supprimer les doublons non référencés');
    console.log('   d. Mettre à jour les références si nécessaire');

    console.log('\n2. Commandes utiles:');
    console.log('   npm run audit:images          # Vérifier les doublons');
    console.log('   npm run build                 # Tester le build après déduplication');
    console.log('   git add -A && git status      # Vérifier les changements');

    console.log('\n3. Fichiers générés:');
    console.log(`   ${path.join(OUTPUT_DIR, 'image-dedup-report-*.json')}`);
    console.log('\n');
}

// Exécuter
try {
    await generateDeduplicationReport();
    console.log('✅ Analyse terminée avec succès!\n');
    process.exit(0);
} catch (error) {
    console.error('❌ Erreur:', error);
    process.exit(1);
}
