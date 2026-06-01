import { afterEach, describe, expect, it } from 'vitest';
import { buildTotpUri, isValidTotpSecret, normalizeTotpSecret, renderTotpQrSvg } from '../admin.ts';

describe('admin TOTP helpers', () => {
  it('normalise les secrets Base32 et rejette les valeurs de login a 6 chiffres', () => {
    expect(normalizeTotpSecret(' dhww6rez slvffo2yezrn ')).toBe('DHWW6REZSLVFFO2YEZRN');
    expect(isValidTotpSecret('DHWW6REZSLVFFO2YEZRN')).toBe(true);
    expect(isValidTotpSecret('123456')).toBe(false);
    expect(isValidTotpSecret('**********')).toBe(false);
  });

  it('construit une URI otpauth compatible avec une application TOTP', () => {
    const uri = buildTotpUri('DHWW6REZSLVFFO2YEZRN', 'pauline@lescaramagnols.com', 'Les Caramagnols');

    expect(uri).toContain('otpauth://totp/Les%20Caramagnols:pauline%40lescaramagnols.com?');
    expect(uri).toContain('secret=DHWW6REZSLVFFO2YEZRN');
    expect(uri).toContain('issuer=Les+Caramagnols');
    expect(uri).toContain('digits=6');
    expect(uri).toContain('period=30');
  });

  it('genere un SVG QR local sans fuite du secret en clair dans le balisage', () => {
    const svg = renderTotpQrSvg(buildTotpUri('DHWW6REZSLVFFO2YEZRN', 'admin'));

    expect(svg).toContain('<svg');
    expect(svg).toContain('<path');
    expect(svg).not.toContain('DHWW6REZSLVFFO2YEZRN');
  });

  it('ouvre la popup QR depuis le formulaire admin avec le secret courant', () => {
    HTMLDialogElement.prototype.showModal = function showModal() {
      this.setAttribute('open', 'open');
    };
    document.body.innerHTML = `
      <input id="admin_identifier" value="pauline@lescaramagnols.com" />
      <input id="admin_totp_secret" value="DHWW6REZSLVFFO2YEZRN" />
      <button
        type="button"
        data-admin-totp-qr
        data-admin-totp-account-input="admin_identifier"
        data-admin-totp-issuer="Les Caramagnols"
        aria-controls="admin_totp_secret"
      >
        QR
      </button>
      <dialog id="admin-totp-qr-dialog">
        <p data-admin-totp-qr-error hidden></p>
        <div data-admin-totp-qr-output></div>
        <code data-admin-totp-qr-secret></code>
      </dialog>
    `;

    document.querySelector<HTMLButtonElement>('[data-admin-totp-qr]')!.click();

    expect(document.querySelector<HTMLDialogElement>('#admin-totp-qr-dialog')!.hasAttribute('open')).toBe(true);
    expect(document.querySelector<HTMLElement>('[data-admin-totp-qr-output]')!.innerHTML).toContain('<svg');
    expect(document.querySelector<HTMLElement>('[data-admin-totp-qr-secret]')!.textContent).toBe('DHWW6REZSLVFFO2YEZRN');
  });
});

afterEach(() => {
  document.body.innerHTML = '';
});
