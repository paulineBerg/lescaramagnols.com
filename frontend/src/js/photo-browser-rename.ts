type SortOrder = 'selection' | 'name' | 'date';

export type BrowserRenameInput = {
  name: string;
  lastModified: number;
  size?: number;
  type?: string;
  file?: File;
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
  lastModified: number;
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
  const commune = normalizePart(options.communeName, options.separator);
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

    if (options.communeName.trim() === '') {
      issues.push('commune_requise');
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
      lastModified: file.lastModified || 0,
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
  parts.push(view.buffer, name, entry.data);

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
  parts.push(view.buffer, name);

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
  parts.push(...centralParts, end.buffer);

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
    file
  }));
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

  if (!fileInput || !communeInput || !startInput || !sortInput || !previewButton || !zipButton || !status || !table || !rows) {
    return;
  }

  const renderPlan = (): void => {
    const files = selectedFiles(fileInput);
    const plan = buildBrowserRenamePlan(files, {
      communeName: communeInput.value,
      startNumber: Number.parseInt(startInput.value, 10),
      counterDigits: 2,
      separator: '-',
      sortOrder: sortInput.value === 'name' || sortInput.value === 'date' ? sortInput.value : 'selection'
    });

    latestOperations = plan.operations;
    rows.replaceChildren();
    for (const operation of latestOperations) {
      const row = document.createElement('tr');
      row.innerHTML = '<td></td><td></td><td></td><td></td>';
      row.children[0].textContent = operation.originalName;
      row.children[1].textContent = formatBytes(operation.size);
      row.children[2].textContent = operation.newName;
      row.children[3].textContent = operation.issues.length > 0 ? operation.issues.join(', ') : 'pret';
      rows.append(row);
    }

    table.hidden = latestOperations.length === 0;
    zipButton.disabled = plan.summary.ready === 0 || plan.summary.conflicts > 0;
    status.textContent =
      plan.summary.selected === 0
        ? 'Aucun fichier selectionne.'
        : `${plan.summary.ready} copie(s) prete(s), ${plan.summary.conflicts} conflit(s).`;
  };

  previewButton.addEventListener('click', () => renderPlan());
  fileInput.addEventListener('change', () => renderPlan());
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
};

export const initPhotoBrowserRename = (): void => {
  document.querySelectorAll<HTMLElement>('[data-photo-browser-renamer]').forEach(initBrowserRenamer);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => initPhotoBrowserRename());
} else {
  initPhotoBrowserRename();
}
