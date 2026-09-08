import { describe, expect, it } from 'vitest';
import {
  buildBrowserRenamePlan,
  createZipBlob,
  parseGpsFromJpegBuffer,
  parseJpegMetadataFromBuffer
} from '../photo-browser-rename.ts';

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

const gpsJpegBuffer = (): ArrayBuffer => {
  const tiffStart = 6;
  const ifd0Offset = 8;
  const gpsIfdOffset = 50;
  const exifIfdOffset = 120;
  const dateStringOffset = 150;
  const latitudeOffset = 180;
  const longitudeOffset = 204;
  const exif = new Uint8Array(6 + 260);
  exif.set(bytes('Exif\0\0'), 0);
  exif.set(bytes('II'), tiffStart);
  const view = new DataView(exif.buffer);
  view.setUint16(tiffStart + 2, 42, true);
  view.setUint32(tiffStart + 4, ifd0Offset, true);
  view.setUint16(tiffStart + ifd0Offset, 2, true);
  view.setUint16(tiffStart + ifd0Offset + 2, 0x8825, true);
  view.setUint16(tiffStart + ifd0Offset + 4, 4, true);
  view.setUint32(tiffStart + ifd0Offset + 6, 1, true);
  view.setUint32(tiffStart + ifd0Offset + 10, gpsIfdOffset, true);
  view.setUint16(tiffStart + ifd0Offset + 14, 0x8769, true);
  view.setUint16(tiffStart + ifd0Offset + 16, 4, true);
  view.setUint32(tiffStart + ifd0Offset + 18, 1, true);
  view.setUint32(tiffStart + ifd0Offset + 22, exifIfdOffset, true);
  view.setUint16(tiffStart + gpsIfdOffset, 4, true);

  const gpsEntry = (index: number, tag: number, type: number, count: number, value: number): number => {
    const offset = tiffStart + gpsIfdOffset + 2 + index * 12;
    view.setUint16(offset, tag, true);
    view.setUint16(offset + 2, type, true);
    view.setUint32(offset + 4, count, true);
    view.setUint32(offset + 8, value, true);
    return offset;
  };

  const latitudeRef = gpsEntry(0, 0x0001, 2, 2, 0);
  exif[latitudeRef + 8] = 'N'.charCodeAt(0);
  const latitude = gpsEntry(1, 0x0002, 5, 3, latitudeOffset);
  const longitudeRef = gpsEntry(2, 0x0003, 2, 2, 0);
  exif[longitudeRef + 8] = 'E'.charCodeAt(0);
  const longitude = gpsEntry(3, 0x0004, 5, 3, longitudeOffset);
  expect(latitude).toBeGreaterThan(0);
  expect(longitude).toBeGreaterThan(0);

  const rational = (offset: number, values: Array<[number, number]>): void => {
    values.forEach(([numerator, denominator], index) => {
      view.setUint32(tiffStart + offset + index * 8, numerator, true);
      view.setUint32(tiffStart + offset + index * 8 + 4, denominator, true);
    });
  };
  rational(latitudeOffset, [
    [43, 1],
    [16, 1],
    [214, 10]
  ]);
  rational(longitudeOffset, [
    [6, 1],
    [37, 1],
    [5811, 100]
  ]);
  view.setUint16(tiffStart + exifIfdOffset, 1, true);
  view.setUint16(tiffStart + exifIfdOffset + 2, 0x9003, true);
  view.setUint16(tiffStart + exifIfdOffset + 4, 2, true);
  view.setUint32(tiffStart + exifIfdOffset + 6, 20, true);
  view.setUint32(tiffStart + exifIfdOffset + 10, dateStringOffset, true);
  exif.set(bytes('2025:02:04 14:31:00\0'), tiffStart + dateStringOffset);

  const jpeg = new Uint8Array(2 + 2 + 2 + exif.byteLength + 2);
  const jpegView = new DataView(jpeg.buffer);
  jpeg[0] = 0xff;
  jpeg[1] = 0xd8;
  jpeg[2] = 0xff;
  jpeg[3] = 0xe1;
  jpegView.setUint16(4, exif.byteLength + 2, false);
  jpeg.set(exif, 6);
  jpeg[jpeg.byteLength - 2] = 0xff;
  jpeg[jpeg.byteLength - 1] = 0xd9;

  return jpeg.buffer;
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

  it('utilise la commune detectee par GPS sans commune saisie', () => {
    const plan = buildBrowserRenamePlan(
      [{ name: 'IMG_7697.JPEG', lastModified: 0, size: 12, detectedCommune: 'Cogolin', communeState: 'detected' }],
      {
        communeName: '',
        startNumber: 1,
        counterDigits: 2,
        separator: '-',
        sortOrder: 'selection'
      }
    );

    expect(plan.ok).toBe(true);
    expect(plan.operations[0].newName).toBe('Cogolin-01.JPEG');
    expect(plan.operations[0].communeSource).toBe('gps');
    expect(plan.operations[0].issues).not.toContain('commune_requise');
  });

  it('lit les coordonnees GPS EXIF d un JPEG', () => {
    const gps = parseGpsFromJpegBuffer(gpsJpegBuffer());

    expect(gps?.latitude).toBeCloseTo(43.272611, 6);
    expect(gps?.longitude).toBeCloseTo(6.632808, 6);
  });

  it('lit la date de prise de vue EXIF d un JPEG', () => {
    const metadata = parseJpegMetadataFromBuffer(gpsJpegBuffer());

    expect(metadata.takenAt).toBe(new Date(2025, 1, 4, 14, 31, 0).getTime());
  });

  it('trie les copies par date de prise de vue quand elle est disponible', () => {
    const plan = buildBrowserRenamePlan(
      [
        { name: 'IMG_0002.JPG', lastModified: 20, size: 12, detectedCommune: 'Cogolin', takenAt: 200 },
        { name: 'IMG_0001.JPG', lastModified: 10, size: 10, detectedCommune: 'Cogolin', takenAt: 100 }
      ],
      {
        communeName: '',
        startNumber: 1,
        counterDigits: 2,
        separator: '-',
        sortOrder: 'taken'
      }
    );

    expect(plan.operations.map((operation) => operation.originalName)).toEqual(['IMG_0001.JPG', 'IMG_0002.JPG']);
    expect(plan.operations.map((operation) => operation.newName)).toEqual(['Cogolin-01.JPG', 'Cogolin-02.JPG']);
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
