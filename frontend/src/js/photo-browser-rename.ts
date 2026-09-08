type SortOrder = 'selection' | 'name' | 'date' | 'taken';

export type BrowserGpsCoordinates = {
  latitude: number;
  longitude: number;
};

type CommuneState = 'pending' | 'detected' | 'missing_gps' | 'lookup_failed';

export type BrowserRenameInput = {
  name: string;
  lastModified: number;
  size?: number;
  type?: string;
  file?: File;
  takenAt?: number;
  gps?: BrowserGpsCoordinates;
  detectedCommune?: string;
  communeState?: CommuneState;
};

export type BrowserRenameOptions = {
  communeName: string;
  startNumber: number;
  counterDigits: number;
  separator: string;
  sortOrder: SortOrder;
};

export type BrowserRenameOperation = {
  index: number;
  originalName: string;
  newName: string;
  status: 'ready' | 'conflict';
  issues: string[];
  resolvedCommune: string;
  communeSource: 'gps' | 'fallback' | 'missing';
  communeState?: CommuneState;
  gps?: BrowserGpsCoordinates;
  lastModified: number;
  takenAt: number;
  size: number;
  type: string;
  file?: File;
};

const ALLOWED_EXTENSIONS = new Set(['jpg', 'jpeg', 'png', 'webp', 'heic']);
const RESERVED_WINDOWS_NAMES = new Set([
  'con',
  'prn',
  'aux',
  'nul',
  'com1',
  'com2',
  'com3',
  'com4',
  'com5',
  'com6',
  'com7',
  'com8',
  'com9',
  'lpt1',
  'lpt2',
  'lpt3',
  'lpt4',
  'lpt5',
  'lpt6',
  'lpt7',
  'lpt8',
  'lpt9'
]);

const textEncoder = new TextEncoder();

const separator = (candidate: string): string => (['_', '-', ' '].includes(candidate) ? candidate : '-');

const extensionOf = (name: string): string => {
  const basename = name.split(/[\\/]/).pop() ?? '';
  const dot = basename.lastIndexOf('.');

  return dot > 0 && dot < basename.length - 1 ? basename.slice(dot + 1) : '';
};

const normalizePart = (value: string, separatorValue = '-'): string => {
  const sep = separator(separatorValue);
  let normalized = value
    .trim()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[\x00-\x1F<>:"/\\|?*]+/g, sep)
    .replace(/\s+/g, sep);

  const escapedSeparator = sep.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  normalized = normalized
    .replace(new RegExp(`${escapedSeparator}{2,}`, 'g'), sep)
    .replace(new RegExp(`^[ .${escapedSeparator}]+|[ .${escapedSeparator}]+$`, 'g'), '');

  if (normalized === '') {
    normalized = 'photo';
  }

  if (RESERVED_WINDOWS_NAMES.has(normalized.toLowerCase())) {
    normalized += `${sep}file`;
  }

  return normalized;
};

const normalizeFilename = (baseName: string, extension: string, separatorValue: string): string => {
  const normalizedBaseName = normalizePart(baseName, separatorValue);
  const extensionPart = extension !== '' ? `.${extension}` : '';
  const maxBaseLength = Math.max(20, 180 - extensionPart.length);
  const trimmedBaseName =
    normalizedBaseName.length > maxBaseLength
      ? normalizedBaseName.slice(0, maxBaseLength).replace(/[ ._-]+$/g, '')
      : normalizedBaseName;

  return `${trimmedBaseName}${extensionPart}`;
};

const coordinateDistanceKm = (fromLatitude: number, fromLongitude: number, toLatitude: number, toLongitude: number): number => {
  const earthRadiusKm = 6371;
  const latitudeDelta = ((toLatitude - fromLatitude) * Math.PI) / 180;
  const longitudeDelta = ((toLongitude - fromLongitude) * Math.PI) / 180;
  const fromLatitudeRad = (fromLatitude * Math.PI) / 180;
  const toLatitudeRad = (toLatitude * Math.PI) / 180;
  const a =
    Math.sin(latitudeDelta / 2) ** 2 +
    Math.cos(fromLatitudeRad) * Math.cos(toLatitudeRad) * Math.sin(longitudeDelta / 2) ** 2;

  return earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
};

export const knownCommuneFromGps = (gps: BrowserGpsCoordinates): string | null => {
  if (gps.latitude < 43.12 || gps.latitude > 43.38 || gps.longitude < 6.42 || gps.longitude > 6.68) {
    return null;
  }

  const communes = [
    { name: 'Saint-Tropez', latitude: 43.2677, longitude: 6.6407 },
    { name: 'Cogolin', latitude: 43.2528, longitude: 6.5306 },
    { name: 'Gassin', latitude: 43.2285, longitude: 6.585 },
    { name: 'Grimaud', latitude: 43.273, longitude: 6.523 },
    { name: 'Sainte-Maxime', latitude: 43.3083, longitude: 6.6386 },
    { name: 'Ramatuelle', latitude: 43.215, longitude: 6.612 },
    { name: 'La Croix-Valmer', latitude: 43.2071, longitude: 6.567 },
    { name: 'Cavalaire-sur-Mer', latitude: 43.1727, longitude: 6.5294 },
    { name: 'La Mole', latitude: 43.2096, longitude: 6.4669 },
    { name: 'Le Plan-de-la-Tour', latitude: 43.3392, longitude: 6.5467 },
    { name: 'La Garde-Freinet', latitude: 43.3176, longitude: 6.4697 },
    { name: 'Le Rayol-Canadel-sur-Mer', latitude: 43.1593, longitude: 6.4801 }
  ];

  let nearest: string | null = null;
  let nearestDistance = Number.POSITIVE_INFINITY;
  for (const commune of communes) {
    const distance = coordinateDistanceKm(gps.latitude, gps.longitude, commune.latitude, commune.longitude);
    if (distance < nearestDistance) {
      nearestDistance = distance;
      nearest = commune.name;
    }
  }

  return nearestDistance <= 18 ? nearest : null;
};

const readAscii = (view: DataView, offset: number, length: number): string => {
  if (offset < 0 || length < 0 || offset + length > view.byteLength) {
    return '';
  }

  let value = '';
  for (let index = 0; index < length; index++) {
    const code = view.getUint8(offset + index);
    if (code === 0) {
      break;
    }
    value += String.fromCharCode(code);
  }

  return value;
};

const readUint16 = (view: DataView, offset: number, littleEndian: boolean): number | null => {
  return offset >= 0 && offset + 2 <= view.byteLength ? view.getUint16(offset, littleEndian) : null;
};

const readUint32 = (view: DataView, offset: number, littleEndian: boolean): number | null => {
  return offset >= 0 && offset + 4 <= view.byteLength ? view.getUint32(offset, littleEndian) : null;
};

const ifdEntryOffset = (view: DataView, tiffStart: number, ifdOffset: number, tag: number, littleEndian: boolean): number | null => {
  const absoluteOffset = tiffStart + ifdOffset;
  const count = readUint16(view, absoluteOffset, littleEndian);
  if (count === null) {
    return null;
  }

  for (let index = 0; index < count; index++) {
    const entryOffset = absoluteOffset + 2 + index * 12;
    if (entryOffset + 12 > view.byteLength) {
      return null;
    }

    if (readUint16(view, entryOffset, littleEndian) === tag) {
      return entryOffset;
    }
  }

  return null;
};

const rationalTriplet = (view: DataView, tiffStart: number, entryOffset: number, littleEndian: boolean): number[] | null => {
  const type = readUint16(view, entryOffset + 2, littleEndian);
  const count = readUint32(view, entryOffset + 4, littleEndian);
  const valueOffset = readUint32(view, entryOffset + 8, littleEndian);
  if (type !== 5 || count !== 3 || valueOffset === null) {
    return null;
  }

  const absoluteOffset = tiffStart + valueOffset;
  if (absoluteOffset + 24 > view.byteLength) {
    return null;
  }

  const values: number[] = [];
  for (let index = 0; index < 3; index++) {
    const numerator = readUint32(view, absoluteOffset + index * 8, littleEndian);
    const denominator = readUint32(view, absoluteOffset + index * 8 + 4, littleEndian);
    if (numerator === null || denominator === null || denominator === 0) {
      return null;
    }
    values.push(numerator / denominator);
  }

  return values;
};

const gpsRef = (view: DataView, entryOffset: number, littleEndian: boolean): string | null => {
  const type = readUint16(view, entryOffset + 2, littleEndian);
  const count = readUint32(view, entryOffset + 4, littleEndian);
  if (type !== 2 || count === null || count < 1) {
    return null;
  }

  return readAscii(view, entryOffset + 8, 1).toUpperCase();
};

const asciiEntryValue = (view: DataView, tiffStart: number, entryOffset: number, littleEndian: boolean): string | null => {
  const type = readUint16(view, entryOffset + 2, littleEndian);
  const count = readUint32(view, entryOffset + 4, littleEndian);
  if (type !== 2 || count === null || count < 1) {
    return null;
  }

  const inline = count <= 4;
  const valueOffset = inline ? entryOffset + 8 : tiffStart + (readUint32(view, entryOffset + 8, littleEndian) ?? -1);
  if (valueOffset < 0 || valueOffset + count > view.byteLength) {
    return null;
  }

  return readAscii(view, valueOffset, Math.min(count, 64)).trim();
};

const parseExifTimestamp = (value: string | null): number | null => {
  if (value === null) {
    return null;
  }

  const match = value.match(/^(\d{4}):(\d{2}):(\d{2})[ T](\d{2}):(\d{2}):(\d{2})$/);
  if (match === null) {
    return null;
  }

  const [, year, month, day, hours, minutes, seconds] = match;
  const timestamp = new Date(
    Number(year),
    Number(month) - 1,
    Number(day),
    Number(hours),
    Number(minutes),
    Number(seconds)
  ).getTime();

  return Number.isFinite(timestamp) ? timestamp : null;
};

const dmsToDecimal = (parts: number[], ref: string | null): number | null => {
  if (parts.length !== 3 || ref === null || !['N', 'S', 'E', 'W'].includes(ref)) {
    return null;
  }

  const decimal = parts[0] + parts[1] / 60 + parts[2] / 3600;
  return ref === 'S' || ref === 'W' ? -decimal : decimal;
};

export const parseJpegMetadataFromBuffer = (
  buffer: ArrayBuffer
): { gps: BrowserGpsCoordinates | null; takenAt: number | null } => {
  const view = new DataView(buffer);
  if (view.byteLength < 4 || view.getUint16(0, false) !== 0xffd8) {
    return { gps: null, takenAt: null };
  }

  let offset = 2;
  while (offset + 4 <= view.byteLength) {
    if (view.getUint8(offset) !== 0xff) {
      return { gps: null, takenAt: null };
    }

    const marker = view.getUint8(offset + 1);
    offset += 2;
    if (marker === 0xda || marker === 0xd9) {
      return { gps: null, takenAt: null };
    }

    if (marker >= 0xd0 && marker <= 0xd7) {
      continue;
    }

    const segmentLength = view.getUint16(offset, false);
    const segmentStart = offset + 2;
    const segmentEnd = offset + segmentLength;
    if (segmentLength < 2 || segmentEnd > view.byteLength) {
      return { gps: null, takenAt: null };
    }

    if (marker === 0xe1 && readAscii(view, segmentStart, 6) === 'Exif') {
      const tiffStart = segmentStart + 6;
      const byteOrder = readAscii(view, tiffStart, 2);
      const littleEndian = byteOrder === 'II';
      if (!littleEndian && byteOrder !== 'MM') {
        return { gps: null, takenAt: null };
      }

      if (readUint16(view, tiffStart + 2, littleEndian) !== 42) {
        return { gps: null, takenAt: null };
      }

      const firstIfdOffset = readUint32(view, tiffStart + 4, littleEndian);
      if (firstIfdOffset === null) {
        return { gps: null, takenAt: null };
      }

      let takenAt = parseExifTimestamp(
        (() => {
          const dateEntry = ifdEntryOffset(view, tiffStart, firstIfdOffset, 0x0132, littleEndian);
          return dateEntry === null ? null : asciiEntryValue(view, tiffStart, dateEntry, littleEndian);
        })()
      );
      const exifPointerEntry = ifdEntryOffset(view, tiffStart, firstIfdOffset, 0x8769, littleEndian);
      const exifIfdOffset = exifPointerEntry === null ? null : readUint32(view, exifPointerEntry + 8, littleEndian);
      if (exifIfdOffset !== null) {
        const originalEntry = ifdEntryOffset(view, tiffStart, exifIfdOffset, 0x9003, littleEndian);
        takenAt = parseExifTimestamp(
          originalEntry === null ? null : asciiEntryValue(view, tiffStart, originalEntry, littleEndian)
        ) ?? takenAt;
      }

      const gpsPointerEntry = ifdEntryOffset(view, tiffStart, firstIfdOffset, 0x8825, littleEndian);
      if (gpsPointerEntry === null) {
        return { gps: null, takenAt };
      }

      const gpsIfdOffset = readUint32(view, gpsPointerEntry + 8, littleEndian);
      if (gpsIfdOffset === null) {
        return { gps: null, takenAt };
      }

      const latitudeRefEntry = ifdEntryOffset(view, tiffStart, gpsIfdOffset, 0x0001, littleEndian);
      const latitudeEntry = ifdEntryOffset(view, tiffStart, gpsIfdOffset, 0x0002, littleEndian);
      const longitudeRefEntry = ifdEntryOffset(view, tiffStart, gpsIfdOffset, 0x0003, littleEndian);
      const longitudeEntry = ifdEntryOffset(view, tiffStart, gpsIfdOffset, 0x0004, littleEndian);
      if (
        latitudeRefEntry === null ||
        latitudeEntry === null ||
        longitudeRefEntry === null ||
        longitudeEntry === null
      ) {
        return { gps: null, takenAt };
      }

      const latitude = dmsToDecimal(
        rationalTriplet(view, tiffStart, latitudeEntry, littleEndian) ?? [],
        gpsRef(view, latitudeRefEntry, littleEndian)
      );
      const longitude = dmsToDecimal(
        rationalTriplet(view, tiffStart, longitudeEntry, littleEndian) ?? [],
        gpsRef(view, longitudeRefEntry, littleEndian)
      );
      if (latitude === null || longitude === null) {
        return { gps: null, takenAt };
      }

      return { gps: { latitude, longitude }, takenAt };
    }

    offset = segmentEnd;
  }

  return { gps: null, takenAt: null };
};

export const parseGpsFromJpegBuffer = (buffer: ArrayBuffer): BrowserGpsCoordinates | null => {
  return parseJpegMetadataFromBuffer(buffer).gps;
};

export const extractGpsFromJpeg = async (file: File): Promise<BrowserGpsCoordinates | null> => {
  const extension = extensionOf(file.name).toLowerCase();
  if (extension !== 'jpg' && extension !== 'jpeg') {
    return null;
  }

  return parseJpegMetadataFromBuffer(await file.slice(0, 1024 * 1024).arrayBuffer()).gps;
};

const extractJpegMetadata = async (file: File): Promise<{ gps: BrowserGpsCoordinates | null; takenAt: number | null }> => {
  const extension = extensionOf(file.name).toLowerCase();
  if (extension !== 'jpg' && extension !== 'jpeg') {
    return { gps: null, takenAt: null };
  }

  return parseJpegMetadataFromBuffer(await file.slice(0, 1024 * 1024).arrayBuffer());
};

const sortedInputs = (files: BrowserRenameInput[], sortOrder: SortOrder): BrowserRenameInput[] => {
  return files
    .map((file, index) => ({ file, index }))
    .sort((left, right) => {
      if (sortOrder === 'selection') {
        return left.index - right.index;
      }

      if (sortOrder === 'name') {
        return left.file.name.localeCompare(right.file.name, 'fr') || left.index - right.index;
      }

      if (sortOrder === 'taken') {
        return (
          (left.file.takenAt || left.file.lastModified || Number.MAX_SAFE_INTEGER) -
            (right.file.takenAt || right.file.lastModified || Number.MAX_SAFE_INTEGER) ||
          left.file.name.localeCompare(right.file.name, 'fr') ||
          left.index - right.index
        );
      }

      return (
        (left.file.lastModified || Number.MAX_SAFE_INTEGER) -
          (right.file.lastModified || Number.MAX_SAFE_INTEGER) ||
        left.file.name.localeCompare(right.file.name, 'fr') ||
        left.index - right.index
      );
    })
    .map((entry) => entry.file);
};

export const buildBrowserRenamePlan = (
  files: BrowserRenameInput[],
  options: BrowserRenameOptions
): {
  ok: boolean;
  operations: BrowserRenameOperation[];
  summary: { selected: number; ready: number; conflicts: number };
} => {
  const fallbackCommune = normalizePart(options.communeName, options.separator);
  const startNumber = Number.isFinite(options.startNumber) ? Math.max(1, Math.floor(options.startNumber)) : 1;
  const digits = Number.isFinite(options.counterDigits)
    ? Math.max(2, Math.min(8, Math.floor(options.counterDigits)))
    : 2;
  const targets = new Set<string>();

  const operations = sortedInputs(files, options.sortOrder).map((file, index): BrowserRenameOperation => {
    const issues: string[] = [];
    const extension = extensionOf(file.name);
    if (!ALLOWED_EXTENSIONS.has(extension.toLowerCase())) {
      issues.push('extension_non_supportee');
    }

    if (file.communeState === 'pending' && (file.detectedCommune ?? '').trim() === '') {
      issues.push('geocodage_en_cours');
    }

    const detectedCommune = (file.detectedCommune ?? '').trim();
    const hasDetectedCommune = detectedCommune !== '';
    const commune = hasDetectedCommune ? normalizePart(detectedCommune, options.separator) : fallbackCommune;
    const communeSource = hasDetectedCommune ? 'gps' : commune !== '' && options.communeName.trim() !== '' ? 'fallback' : 'missing';

    if (communeSource === 'missing' && !issues.includes('geocodage_en_cours')) {
      if (file.communeState === 'missing_gps') {
        issues.push('gps_absent');
      } else if (file.communeState === 'lookup_failed') {
        issues.push('commune_introuvable');
      } else {
        issues.push('commune_requise');
      }
    }

    const counter = String(startNumber + index).padStart(digits, '0');
    const newName = normalizeFilename(`${commune}${separator(options.separator)}${counter}`, extension, options.separator);
    if (targets.has(newName)) {
      issues.push('doublon_destination');
    }
    targets.add(newName);

    return {
      index,
      originalName: file.name,
      newName,
      status: issues.length === 0 ? 'ready' : 'conflict',
      issues,
      resolvedCommune: commune,
      communeSource,
      communeState: file.communeState,
      gps: file.gps,
      lastModified: file.lastModified || 0,
      takenAt: file.takenAt || 0,
      size: file.size ?? 0,
      type: file.type ?? '',
      file: file.file
    };
  });

  const conflicts = operations.filter((operation) => operation.status === 'conflict').length;

  return {
    ok: conflicts === 0,
    operations,
    summary: {
      selected: operations.length,
      ready: operations.length - conflicts,
      conflicts
    }
  };
};

type ZipEntry = {
  name: string;
  data: Uint8Array;
  lastModified?: number;
};

const crcTable = (() => {
  const table = new Uint32Array(256);
  for (let index = 0; index < 256; index++) {
    let value = index;
    for (let bit = 0; bit < 8; bit++) {
      value = value & 1 ? 0xedb88320 ^ (value >>> 1) : value >>> 1;
    }
    table[index] = value >>> 0;
  }

  return table;
})();

const crc32 = (data: Uint8Array): number => {
  let crc = 0xffffffff;
  for (const byte of data) {
    crc = crcTable[(crc ^ byte) & 0xff] ^ (crc >>> 8);
  }

  return (crc ^ 0xffffffff) >>> 0;
};

const dosDateTime = (timestamp = Date.now()): { date: number; time: number } => {
  const date = new Date(timestamp || Date.now());
  const year = Math.max(1980, date.getFullYear());

  return {
    date: ((year - 1980) << 9) | ((date.getMonth() + 1) << 5) | date.getDate(),
    time: (date.getHours() << 11) | (date.getMinutes() << 5) | Math.floor(date.getSeconds() / 2)
  };
};

const header = (size: number): DataView => new DataView(new ArrayBuffer(size));

const viewBuffer = (view: DataView): ArrayBuffer => {
  return view.buffer.slice(view.byteOffset, view.byteOffset + view.byteLength) as ArrayBuffer;
};

const bytesBuffer = (bytes: Uint8Array): ArrayBuffer => {
  return bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength) as ArrayBuffer;
};

const pushLocalHeader = (parts: BlobPart[], entry: ZipEntry, crc: number): number => {
  const name = textEncoder.encode(entry.name);
  const { date, time } = dosDateTime(entry.lastModified);
  const view = header(30);
  view.setUint32(0, 0x04034b50, true);
  view.setUint16(4, 20, true);
  view.setUint16(6, 0, true);
  view.setUint16(8, 0, true);
  view.setUint16(10, time, true);
  view.setUint16(12, date, true);
  view.setUint32(14, crc, true);
  view.setUint32(18, entry.data.length, true);
  view.setUint32(22, entry.data.length, true);
  view.setUint16(26, name.length, true);
  view.setUint16(28, 0, true);
  parts.push(viewBuffer(view), bytesBuffer(name), bytesBuffer(entry.data));

  return view.byteLength + name.length + entry.data.length;
};

const pushCentralHeader = (parts: BlobPart[], entry: ZipEntry, crc: number, offset: number): number => {
  const name = textEncoder.encode(entry.name);
  const { date, time } = dosDateTime(entry.lastModified);
  const view = header(46);
  view.setUint32(0, 0x02014b50, true);
  view.setUint16(4, 20, true);
  view.setUint16(6, 20, true);
  view.setUint16(8, 0, true);
  view.setUint16(10, 0, true);
  view.setUint16(12, time, true);
  view.setUint16(14, date, true);
  view.setUint32(16, crc, true);
  view.setUint32(20, entry.data.length, true);
  view.setUint32(24, entry.data.length, true);
  view.setUint16(28, name.length, true);
  view.setUint16(30, 0, true);
  view.setUint16(32, 0, true);
  view.setUint16(34, 0, true);
  view.setUint16(36, 0, true);
  view.setUint32(38, 0, true);
  view.setUint32(42, offset, true);
  parts.push(viewBuffer(view), bytesBuffer(name));

  return view.byteLength + name.length;
};

export const createZipBlob = (entries: ZipEntry[]): Blob => {
  const parts: BlobPart[] = [];
  const centralParts: BlobPart[] = [];
  let offset = 0;
  let centralSize = 0;

  for (const entry of entries) {
    const crc = crc32(entry.data);
    centralSize += pushCentralHeader(centralParts, entry, crc, offset);
    offset += pushLocalHeader(parts, entry, crc);
  }

  const end = header(22);
  end.setUint32(0, 0x06054b50, true);
  end.setUint16(8, entries.length, true);
  end.setUint16(10, entries.length, true);
  end.setUint32(12, centralSize, true);
  end.setUint32(16, offset, true);
  parts.push(...centralParts, viewBuffer(end));

  return new Blob(parts, { type: 'application/zip' });
};

export const createBrowserRenameZip = async (operations: BrowserRenameOperation[]): Promise<Blob> => {
  const entries: ZipEntry[] = [];
  for (const operation of operations) {
    if (operation.status !== 'ready' || !(operation.file instanceof File)) {
      continue;
    }

    entries.push({
      name: operation.newName,
      data: new Uint8Array(await operation.file.arrayBuffer()),
      lastModified: operation.lastModified
    });
  }

  return createZipBlob(entries);
};

const formatBytes = (bytes: number): string => {
  if (bytes < 1024) {
    return `${bytes} o`;
  }

  if (bytes < 1024 * 1024) {
    return `${Math.round(bytes / 1024)} Ko`;
  }

  return `${(bytes / 1024 / 1024).toFixed(1)} Mo`;
};

const selectedFiles = (input: HTMLInputElement): BrowserRenameInput[] => {
  return Array.from(input.files ?? []).map((file) => ({
    name: file.name,
    lastModified: file.lastModified,
    size: file.size,
    type: file.type,
    file,
    communeState: 'pending'
  }));
};

const sleep = (milliseconds: number): Promise<void> => new Promise((resolve) => window.setTimeout(resolve, milliseconds));

const coordinateKey = (gps: BrowserGpsCoordinates): string => {
  return `${gps.latitude.toFixed(4)},${gps.longitude.toFixed(4)}`;
};

const issueLabel = (issue: string): string => {
  return (
    {
      commune_requise: 'commune manquante',
      doublon_destination: 'doublon destination',
      extension_non_supportee: 'extension non supportee',
      geocodage_en_cours: 'geocodage en cours',
      gps_absent: 'coordonnees GPS absentes',
      commune_introuvable: 'commune GPS introuvable'
    }[issue] ?? issue
  );
};

const formatCoordinates = (gps: BrowserGpsCoordinates): string => {
  return `${gps.latitude.toFixed(6)}, ${gps.longitude.toFixed(6)}`;
};

const operationDetails = (operation: BrowserRenameOperation): string => {
  const details: string[] = [];
  if (operation.gps !== undefined) {
    details.push(`GPS lu: ${formatCoordinates(operation.gps)}`);
  }

  if (operation.communeSource === 'gps') {
    details.push('commune detectee automatiquement');
  } else if (operation.communeSource === 'fallback') {
    details.push('commune de secours utilisee');
  } else if (operation.issues.includes('geocodage_en_cours')) {
    details.push('recherche de commune en cours');
  } else if (operation.issues.includes('gps_absent')) {
    details.push('aucune coordonnee GPS EXIF lisible; saisir une commune de secours');
  } else if (operation.issues.includes('commune_introuvable')) {
    details.push('coordonnees GPS lues, mais aucune commune retournee; saisir une commune de secours');
  } else if (operation.issues.includes('commune_requise')) {
    details.push('saisir une commune de secours');
  }

  if (operation.issues.includes('doublon_destination')) {
    details.push('un autre fichier produit deja ce nom');
  }

  if (operation.issues.includes('extension_non_supportee')) {
    details.push('format accepte: JPG, PNG, WebP ou HEIC');
  }

  return details.join('. ');
};

const communeLabel = (operation: BrowserRenameOperation): string => {
  if (operation.communeSource === 'gps') {
    return `${operation.resolvedCommune} (GPS)`;
  }

  if (operation.communeSource === 'fallback') {
    return `${operation.resolvedCommune} (secours)`;
  }

  return 'a renseigner';
};

const downloadBlob = (blob: Blob, filename: string): void => {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.rel = 'noopener';
  document.body.append(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 30000);
};

const initBrowserRenamer = (root: HTMLElement): void => {
  const geocodeUrl = root.dataset.photoBrowserGeocodeUrl ?? '';
  const csrfToken = root.dataset.photoBrowserCsrf ?? '';
  const fileInput = root.querySelector<HTMLInputElement>('[data-photo-browser-files]');
  const communeInput = root.querySelector<HTMLInputElement>('[data-photo-browser-commune]');
  const startInput = root.querySelector<HTMLInputElement>('[data-photo-browser-start]');
  const sortInput = root.querySelector<HTMLSelectElement>('[data-photo-browser-sort]');
  const previewButton = root.querySelector<HTMLButtonElement>('[data-photo-browser-preview]');
  const zipButton = root.querySelector<HTMLButtonElement>('[data-photo-browser-download]');
  const status = root.querySelector<HTMLElement>('[data-photo-browser-status]');
  const table = root.querySelector<HTMLTableElement>('[data-photo-browser-table]');
  const rows = root.querySelector<HTMLTableSectionElement>('[data-photo-browser-rows]');
  let latestOperations: BrowserRenameOperation[] = [];
  let currentFiles: BrowserRenameInput[] = [];
  let previewVisible = false;
  let analysisRun = 0;
  let latestGeocodeAt = 0;
  const communeCache = new Map<string, Promise<string | null>>();
  const previewUrls = new WeakMap<File, string>();
  let activePreviewUrls: string[] = [];

  if (!fileInput || !communeInput || !startInput || !sortInput || !previewButton || !zipButton || !status || !table || !rows) {
    return;
  }

  const reverseGeocode = async (gps: BrowserGpsCoordinates): Promise<string | null> => {
    const knownCommune = knownCommuneFromGps(gps);
    if (knownCommune !== null) {
      return knownCommune;
    }

    if (geocodeUrl === '' || csrfToken === '') {
      return null;
    }

    const key = coordinateKey(gps);
    if (communeCache.has(key)) {
      return communeCache.get(key) ?? null;
    }

    const request = (async (): Promise<string | null> => {
      const delay = 1100 - (Date.now() - latestGeocodeAt);
      if (delay > 0) {
        await sleep(delay);
      }
      latestGeocodeAt = Date.now();

      const body = new FormData();
      body.set('action', 'photo_reverse_geocode');
      body.set('csrf_token', csrfToken);
      body.set('latitude', gps.latitude.toFixed(6));
      body.set('longitude', gps.longitude.toFixed(6));

      const response = await fetch(geocodeUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        body
      });
      if (!response.ok) {
        return null;
      }

      const payload = (await response.json()) as { ok?: boolean; commune?: string };
      const commune = typeof payload.commune === 'string' ? payload.commune.trim() : '';
      return payload.ok === true && commune !== '' ? commune : null;
    })().catch(() => null);

    communeCache.set(key, request);
    return request;
  };

  const clearPreviewUrls = (): void => {
    activePreviewUrls.forEach((url) => URL.revokeObjectURL(url));
    activePreviewUrls = [];
  };

  const analyzeSelectedFiles = async (): Promise<void> => {
    const run = ++analysisRun;
    currentFiles = selectedFiles(fileInput);
    previewVisible = false;
    renderPlan();

    for (const input of currentFiles) {
      if (!(input.file instanceof File)) {
        input.communeState = 'missing_gps';
        continue;
      }

      const metadata = await extractJpegMetadata(input.file);
      if (run !== analysisRun) {
        return;
      }

      input.takenAt = metadata.takenAt ?? undefined;
      const gps = metadata.gps;
      if (gps === null) {
        input.communeState = 'missing_gps';
        renderPlan();
        continue;
      }

      input.gps = gps;
      const commune = await reverseGeocode(gps);
      if (run !== analysisRun) {
        return;
      }

      if (commune !== null) {
        input.detectedCommune = commune;
        input.communeState = 'detected';
      } else {
        input.communeState = 'lookup_failed';
      }
      renderPlan();
    }
  };

  const renderPlan = (): void => {
    const plan = buildBrowserRenamePlan(currentFiles, {
      communeName: communeInput.value,
      startNumber: Number.parseInt(startInput.value, 10),
      counterDigits: 2,
      separator: '-',
      sortOrder:
        sortInput.value === 'name' || sortInput.value === 'date' || sortInput.value === 'taken'
          ? sortInput.value
          : 'selection'
    });

    latestOperations = plan.operations;
    rows.replaceChildren();
    for (const operation of latestOperations) {
      const row = document.createElement('tr');
      row.innerHTML = '<td class="photo-browser-preview-cell"></td><td></td><td></td><td></td><td></td><td></td>';
      if (previewVisible && operation.file instanceof File) {
        const image = document.createElement('img');
        let previewUrl = previewUrls.get(operation.file);
        if (previewUrl === undefined) {
          previewUrl = URL.createObjectURL(operation.file);
          previewUrls.set(operation.file, previewUrl);
          activePreviewUrls.push(previewUrl);
        }
        image.src = previewUrl;
        image.alt = operation.originalName;
        image.loading = 'lazy';
        image.decoding = 'async';
        image.className = 'photo-browser-thumbnail';
        row.children[0].append(image);
      }
      row.children[1].textContent = operation.originalName;
      row.children[2].textContent = formatBytes(operation.size);
      row.children[3].textContent = communeLabel(operation);
      row.children[4].textContent = operation.newName;
      const state = document.createElement('span');
      state.textContent = operation.issues.length > 0 ? operation.issues.map(issueLabel).join(', ') : 'pret';
      row.children[5].append(state);
      const details = operationDetails(operation);
      if (details !== '') {
        const detail = document.createElement('small');
        detail.className = 'photo-browser-operation-details';
        detail.textContent = details;
        row.children[5].append(detail);
      }
      rows.append(row);
    }

    table.hidden = latestOperations.length === 0;
    zipButton.disabled = plan.summary.ready === 0;
    const pending = latestOperations.filter((operation) => operation.issues.includes('geocodage_en_cours')).length;
    status.textContent =
      plan.summary.selected === 0
        ? 'Aucun fichier selectionne.'
        : pending > 0
          ? `Lecture GPS et recherche commune en cours pour ${pending} photo(s).`
        : plan.summary.conflicts > 0
          ? `${plan.summary.ready} copie(s) prete(s), ${plan.summary.conflicts} conflit(s). Voir les details par ligne.`
          : `${plan.summary.ready} copie(s) prete(s), 0 conflit.`;
  };

  previewButton.addEventListener('click', () => {
    previewVisible = true;
    renderPlan();
  });
  fileInput.addEventListener('change', () => {
    clearPreviewUrls();
    void analyzeSelectedFiles();
  });
  communeInput.addEventListener('input', () => renderPlan());
  startInput.addEventListener('input', () => renderPlan());
  sortInput.addEventListener('change', () => renderPlan());

  zipButton.addEventListener('click', async () => {
    zipButton.disabled = true;
    status.textContent = 'Preparation de l archive...';
    try {
      const zip = await createBrowserRenameZip(latestOperations);
      downloadBlob(zip, `photo-rename-${Date.now()}.zip`);
      status.textContent = 'Archive prete.';
    } catch (_error) {
      status.textContent = 'Archive impossible a preparer.';
    } finally {
      renderPlan();
    }
  });

  renderPlan();
  window.addEventListener('pagehide', clearPreviewUrls, { once: true });
};

export const initPhotoBrowserRename = (): void => {
  document.querySelectorAll<HTMLElement>('[data-photo-browser-renamer]').forEach(initBrowserRenamer);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => initPhotoBrowserRename());
} else {
  initPhotoBrowserRename();
}
