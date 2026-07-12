import qrcode from 'qrcode-generator';

const TOTP_SECRET_PATTERN = /^[A-Z2-7]{16,}$/;
const DEFAULT_ISSUER = 'Les Caramagnols';

export function normalizeTotpSecret(value: string): string {
  return value.toUpperCase().replace(/[^A-Z2-7]/g, '');
}

export function isValidTotpSecret(value: string): boolean {
  return TOTP_SECRET_PATTERN.test(normalizeTotpSecret(value));
}

export function buildTotpUri(secret: string, account: string, issuer = DEFAULT_ISSUER): string {
  const normalizedSecret = normalizeTotpSecret(secret);
  const normalizedIssuer = issuer.trim() || DEFAULT_ISSUER;
  const normalizedAccount = account.trim() || 'admin';
  const label = `${encodeURIComponent(normalizedIssuer)}:${encodeURIComponent(normalizedAccount)}`;
  const params = new URLSearchParams({
    secret: normalizedSecret,
    issuer: normalizedIssuer,
    algorithm: 'SHA1',
    digits: '6',
    period: '30',
  });

  return `otpauth://totp/${label}?${params.toString()}`;
}

export function renderTotpQrSvg(uri: string): string {
  const qr = qrcode(0, 'M');
  qr.addData(uri);
  qr.make();

  return qr.createSvgTag({ cellSize: 8, margin: 4, scalable: true });
}

function closeDialog(dialog: HTMLDialogElement): void {
  if (dialog.open && typeof dialog.close === 'function') {
    dialog.close();
    return;
  }

  dialog.removeAttribute('open');
}

function openDialog(dialog: HTMLDialogElement): void {
  if (typeof dialog.showModal === 'function') {
    dialog.showModal();
    return;
  }

  dialog.setAttribute('open', 'open');
}

function textFromInput(inputId: string): string {
  const input = document.getElementById(inputId);
  return input instanceof HTMLInputElement ? input.value : '';
}

function setQrDialogError(dialog: HTMLDialogElement, message: string): void {
  const error = dialog.querySelector<HTMLElement>('[data-admin-totp-qr-error]');
  const output = dialog.querySelector<HTMLElement>('[data-admin-totp-qr-output]');
  const secretNode = dialog.querySelector<HTMLElement>('[data-admin-totp-qr-secret]');

  if (output instanceof HTMLElement) {
    output.replaceChildren();
  }
  if (secretNode instanceof HTMLElement) {
    secretNode.textContent = '';
  }
  if (error instanceof HTMLElement) {
    error.textContent = message;
    error.hidden = false;
  }
}

function setQrDialogContent(dialog: HTMLDialogElement, secret: string, uri: string): void {
  const error = dialog.querySelector<HTMLElement>('[data-admin-totp-qr-error]');
  const output = dialog.querySelector<HTMLElement>('[data-admin-totp-qr-output]');
  const secretNode = dialog.querySelector<HTMLElement>('[data-admin-totp-qr-secret]');

  if (error instanceof HTMLElement) {
    error.textContent = '';
    error.hidden = true;
  }
  if (output instanceof HTMLElement) {
    output.innerHTML = renderTotpQrSvg(uri);
  }
  if (secretNode instanceof HTMLElement) {
    secretNode.textContent = secret;
  }
}

function openTotpQrDialog(trigger: HTMLElement): void {
  const dialog = document.getElementById('admin-totp-qr-dialog');
  const secretInputId = trigger.getAttribute('aria-controls') || trigger.dataset.adminTotpSecretInput || '';
  const accountInputId = trigger.dataset.adminTotpAccountInput || 'admin_identifier';
  const issuer = trigger.dataset.adminTotpIssuer || DEFAULT_ISSUER;
  const invalidMessage = trigger.dataset.adminTotpQrInvalid || 'Renseigne ou genere un secret TOTP Base32 valide avant d afficher le QR code.';

  if (!(dialog instanceof HTMLDialogElement) || secretInputId === '') {
    return;
  }

  const secret = normalizeTotpSecret(textFromInput(secretInputId));
  if (!isValidTotpSecret(secret)) {
    setQrDialogError(dialog, invalidMessage);
    openDialog(dialog);
    return;
  }

  const account = textFromInput(accountInputId);
  setQrDialogContent(dialog, secret, buildTotpUri(secret, account, issuer));
  openDialog(dialog);
}

export function initAdminTotpQr(root: ParentNode = document): void {
  root.querySelectorAll<HTMLButtonElement>('[data-admin-totp-qr-close]').forEach((button) => {
    button.addEventListener('click', () => {
      const dialog = button.closest('dialog');
      if (dialog instanceof HTMLDialogElement) {
        closeDialog(dialog);
      }
    });
  });

  document.addEventListener('click', (event) => {
    const trigger = event.target instanceof Element
      ? event.target.closest('[data-admin-totp-qr], [data-admin-totp-generate][data-admin-totp-open-qr="true"]')
      : null;
    if (!(trigger instanceof HTMLElement)) {
      return;
    }

    event.preventDefault();
    openTotpQrDialog(trigger);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => initAdminTotpQr(), { once: true });
} else {
  initAdminTotpQr();
}
