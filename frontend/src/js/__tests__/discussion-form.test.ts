import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initDiscussionForms } from '../discussion-form.ts';

describe('discussion-form', () => {
  const fetchMock = vi.fn();

  beforeEach(() => {
    vi.useRealTimers();
    vi.stubGlobal('fetch', fetchMock);
    fetchMock.mockReset();
    window.grecaptcha = undefined;
    document.body.innerHTML = `
      <form class="blog-discussion-form" data-discussion-form action="/core/blog/submit_discussion.php">
        <p class="blog-discussion-empty" data-discussion-empty-state>Aucun message validé pour le moment.</p>
        <input type="hidden" name="article_slug" value="bonjour" />
        <input type="hidden" name="article_lang" value="fr" />
        <input type="hidden" name="csrf_token" value="csrf-old" />
        <input type="hidden" name="form_nonce" value="nonce-old" />
        <p class="blog-discussion-notice blog-discussion-notice-info" data-discussion-submit-feedback data-feedback-pending-message="Votre message est en cours d'envoi." data-feedback-error-message="Le service reCAPTCHA n'est pas pret." data-feedback-request-error-message="Impossible de finaliser l'envoi." hidden role="status" aria-live="polite">
          Votre message est en cours d'envoi.
        </p>
        <div class="field">
          <input type="text" name="author" value="Pauline" />
          <input type="email" name="email" value="pauline@example.com" />
          <textarea name="content">Bonjour</textarea>
        </div>
        <div class="actions-inline">
          <button
            type="submit"
            data-discussion-submit-button
            data-submit-label-idle="Envoyer le message"
            data-submit-label-pending="Envoi en cours..."
          >Envoyer le message</button>
        </div>
      </form>
    `;
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('shows the pending message and locks the submit button while the request is in flight', () => {
    fetchMock.mockReturnValue(new Promise(() => {}));
    initDiscussionForms();

    const form = document.querySelector<HTMLFormElement>('[data-discussion-form]');
    const button = document.querySelector<HTMLButtonElement>('[data-discussion-submit-button]');
    const feedback = document.querySelector<HTMLElement>('[data-discussion-submit-feedback]');

    form?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    expect(button?.disabled).toBe(true);
    expect(button?.textContent).toBe('Envoi en cours...');
    expect(button?.getAttribute('aria-disabled')).toBe('true');
    expect(feedback?.hidden).toBe(false);
    expect(feedback?.textContent).toContain("Votre message est en cours d'envoi.");
  });

  it('executes recaptcha v3 and shows a success message without reloading the page', async () => {
    const ready = vi.fn((callback: () => void) => callback());
    const execute = vi.fn().mockResolvedValue('token-123');

    window.grecaptcha = {
      ready,
      execute
    };

    document.body.innerHTML = `
      <form
        class="blog-discussion-form"
        data-discussion-form
        action="/core/blog/submit_discussion.php"
        data-recaptcha-enabled="1"
        data-recaptcha-mode="v3_score"
        data-recaptcha-site-key="site-key-123"
        data-recaptcha-action="blog_discussion"
        data-recaptcha-error-not-ready="Le service reCAPTCHA n'est pas pret."
      >
        <p class="blog-discussion-empty" data-discussion-empty-state>Aucun message validé pour le moment.</p>
        <input type="hidden" name="article_slug" value="bonjour" />
        <input type="hidden" name="article_lang" value="fr" />
        <input type="hidden" name="csrf_token" value="csrf-old" />
        <input type="hidden" name="form_nonce" value="nonce-old" />
        <input type="hidden" name="g-recaptcha-response" value="" />
        <input type="text" name="author" value="Pauline" />
        <input type="email" name="email" value="pauline@example.com" />
        <textarea name="content">Bonjour à tous</textarea>
        <p class="blog-discussion-notice blog-discussion-notice-info" data-discussion-submit-feedback data-feedback-pending-message="Votre message est en cours d'envoi." data-feedback-error-message="Le service reCAPTCHA n'est pas pret." data-feedback-request-error-message="Impossible de finaliser l'envoi." hidden role="status" aria-live="polite">
          Votre message est en cours d'envoi.
        </p>
        <div class="actions-inline">
          <button
            type="submit"
            data-discussion-submit-button
            data-submit-label-idle="Envoyer le message"
            data-submit-label-pending="Envoi en cours..."
          >Envoyer le message</button>
        </div>
      </form>
    `;

    fetchMock.mockResolvedValue(
      new Response(
        JSON.stringify({
          ok: true,
          message: 'Merci. Votre message est enregistré.',
          form: {
            csrf_token: 'csrf-new',
            form_nonce: 'nonce-new'
          }
        }),
        {
          status: 201,
          headers: {
            'Content-Type': 'application/json; charset=utf-8'
          }
        }
      )
    );

    initDiscussionForms();

    const form = document.querySelector<HTMLFormElement>('[data-discussion-form]');
    const button = document.querySelector<HTMLButtonElement>('[data-discussion-submit-button]');
    const feedback = document.querySelector<HTMLElement>('[data-discussion-submit-feedback]');
    const csrfInput = document.querySelector<HTMLInputElement>('input[name="csrf_token"]');
    const nonceInput = document.querySelector<HTMLInputElement>('input[name="form_nonce"]');
    const tokenInput = document.querySelector<HTMLInputElement>('input[name="g-recaptcha-response"]');
    const authorInput = document.querySelector<HTMLInputElement>('input[name="author"]');
    const emailInput = document.querySelector<HTMLInputElement>('input[name="email"]');
    const contentInput = document.querySelector<HTMLTextAreaElement>('textarea[name="content"]');
    const emptyState = document.querySelector<HTMLElement>('[data-discussion-empty-state]');

    form?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    await vi.waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    expect(ready).toHaveBeenCalledTimes(1);
    expect(execute).toHaveBeenCalledWith('site-key-123', { action: 'blog_discussion' });
    expect(tokenInput?.value).toBe('');
    expect(button?.disabled).toBe(false);
    expect(button?.textContent).toBe('Envoyer le message');
    expect(feedback?.textContent).toContain('Merci. Votre message est enregistré.');
    expect(feedback?.className).toContain('blog-discussion-notice-success');
    expect(emptyState?.hidden).toBe(true);
    expect(csrfInput?.value).toBe('csrf-new');
    expect(nonceInput?.value).toBe('nonce-new');
    expect(authorInput?.value).toBe('');
    expect(emailInput?.value).toBe('');
    expect(contentInput?.value).toBe('');
  });

  it('shows the server error message inline and refreshes form tokens', async () => {
    fetchMock.mockResolvedValue(
      new Response(
        JSON.stringify({
          ok: false,
          message: 'Le formulaire a expiré. Merci de réessayer.',
          form: {
            csrf_token: 'csrf-refreshed',
            form_nonce: 'nonce-refreshed'
          }
        }),
        {
          status: 422,
          headers: {
            'Content-Type': 'application/json; charset=utf-8'
          }
        }
      )
    );

    initDiscussionForms();

    const form = document.querySelector<HTMLFormElement>('[data-discussion-form]');
    const button = document.querySelector<HTMLButtonElement>('[data-discussion-submit-button]');
    const feedback = document.querySelector<HTMLElement>('[data-discussion-submit-feedback]');
    const csrfInput = document.querySelector<HTMLInputElement>('input[name="csrf_token"]');
    const nonceInput = document.querySelector<HTMLInputElement>('input[name="form_nonce"]');
    const authorInput = document.querySelector<HTMLInputElement>('input[name="author"]');
    const emptyState = document.querySelector<HTMLElement>('[data-discussion-empty-state]');

    form?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    await vi.waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    await vi.waitFor(() => {
      expect(button?.disabled).toBe(false);
      expect(button?.textContent).toBe('Envoyer le message');
      expect(feedback?.textContent).toContain('Le formulaire a expiré. Merci de réessayer.');
      expect(feedback?.className).toContain('blog-discussion-notice-error');
      expect(csrfInput?.value).toBe('csrf-refreshed');
      expect(nonceInput?.value).toBe('nonce-refreshed');
    });
    expect(authorInput?.value).toBe('Pauline');
    expect(emptyState?.hidden).toBe(false);
  });
});
