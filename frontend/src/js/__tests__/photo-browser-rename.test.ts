import { describe, expect, it } from 'vitest';
import { buildBrowserRenamePlan, createZipBlob } from '../photo-browser-rename.ts';

const bytes = (value: string): Uint8Array => new TextEncoder().encode(value);

const readBlob = (blob: Blob): Promise<ArrayBuffer> => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.addEventListener('load', () => {
      if (reader.result instanceof ArrayBuffer) {
        resolve(reader.result);
        return;
      }

      reject(new Error('Blob non binaire'));
    });
    reader.addEventListener('error', () => reject(reader.error ?? new Error('Lecture impossible')));
    reader.readAsArrayBuffer(blob);
  });
};

describe('photo browser rename', () => {
  it('planifie des copies renommees compatibles avec le format commune compteur', () => {
    const plan = buildBrowserRenamePlan(
      [
        { name: 'IMG_0002.JPG', lastModified: Date.UTC(2026, 8, 8, 10, 2), size: 12 },
        { name: 'IMG_0001.JPG', lastModified: Date.UTC(2026, 8, 8, 10, 1), size: 10 }
      ],
      {
        communeName: 'La Mole',
        startNumber: 7,
        counterDigits: 2,
        separator: '-',
        sortOrder: 'date'
      }
    );

    expect(plan.ok).toBe(true);
    expect(plan.operations.map((operation) => operation.newName)).toEqual(['La-Mole-07.JPG', 'La-Mole-08.JPG']);
  });

  it('signale les extensions non supportees et la commune manquante', () => {
    const plan = buildBrowserRenamePlan(
      [{ name: 'notes.txt', lastModified: 0, size: 4 }],
      {
        communeName: '',
        startNumber: 1,
        counterDigits: 2,
        separator: '-',
        sortOrder: 'selection'
      }
    );

    expect(plan.ok).toBe(false);
    expect(plan.operations[0].issues).toContain('extension_non_supportee');
    expect(plan.operations[0].issues).toContain('commune_requise');
  });

  it('cree une archive zip stock avec repertoire central', async () => {
    const zip = createZipBlob([
      { name: 'Cogolin-01.jpg', data: bytes('photo-a'), lastModified: Date.UTC(2026, 8, 8, 10, 1) },
      { name: 'Cogolin-02.jpg', data: bytes('photo-b'), lastModified: Date.UTC(2026, 8, 8, 10, 2) }
    ]);
    const buffer = await readBlob(zip);
    const view = new DataView(buffer);

    expect(zip.type).toBe('application/zip');
    expect(view.getUint32(0, true)).toBe(0x04034b50);
    expect(view.getUint32(buffer.byteLength - 22, true)).toBe(0x06054b50);
    expect(view.getUint16(buffer.byteLength - 12, true)).toBe(2);
  });
});
