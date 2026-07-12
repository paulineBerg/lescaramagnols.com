import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const renderDiscussionComposer = () => {
  document.body.innerHTML = `
    <section
      data-private-discussion-app
      data-conversation-id="42"
      data-current-user-id="1"
      data-api-messages-url="/private/discussions/api/conversations/42/messages"
      data-api-events-url=""
      data-api-client-events-url=""
      data-api-read-url="/private/discussions/api/conversations/42/read"
      data-api-keys-url="/private/discussions/api/conversations/42/keys"
      data-api-crypto-devices-url="/private/discussions/api/crypto/devices"
      data-csrf-token="csrf"
    >
      <p data-discussion-crypto-status></p>
      <div data-discussion-messages></div>
      <form data-discussion-message-form>
        <input type="hidden" name="client_message_id" value="" />
        <input type="hidden" name="encryption_mode" value="" />
        <input type="hidden" name="encrypted_payload" value="" />
        <input type="hidden" name="encryption_metadata" value="" />
        <button type="button" data-discussion-emoji-toggle aria-expanded="false" aria-controls="discussion-emoji-picker">Emojis</button>
        <textarea id="discussion-message-body" name="body" maxlength="4000" data-discussion-draft></textarea>
        <div id="discussion-emoji-picker" data-discussion-emoji-picker hidden></div>
        <p data-discussion-submit-status></p>
        <button type="submit" data-discussion-submit-button>Envoyer</button>
      </form>
    </section>
  `;
};

const loadDiscussionModule = async () => {
  await import('../private-discussion.ts');
  document.dispatchEvent(new Event('DOMContentLoaded'));
};

describe('private discussion composer', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.resetModules();
    window.sessionStorage.clear();
    renderDiscussionComposer();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
  });

  it('insere un emoji dans le message a la position du curseur', async () => {
    await loadDiscussionModule();

    const toggle = document.querySelector<HTMLButtonElement>('[data-discussion-emoji-toggle]');
    const picker = document.querySelector<HTMLElement>('[data-discussion-emoji-picker]');
    const textarea = document.querySelector<HTMLTextAreaElement>('[data-discussion-draft]');

    expect(toggle).not.toBeNull();
    expect(picker).not.toBeNull();
    expect(textarea).not.toBeNull();

    textarea!.value = 'Salut ';
    textarea!.setSelectionRange(6, 6);
    toggle!.click();

    expect(toggle!.getAttribute('aria-expanded')).toBe('true');
    expect(picker!.hidden).toBe(false);

    picker!.querySelector<HTMLButtonElement>('.private-discussion-emoji-choice')!.click();

    expect(textarea!.value).toBe('Salut 😀');
    expect(window.sessionStorage.getItem('caramagnols.private.discussion.draft.42')).toBe('Salut 😀');
  });
});
