import { beforeEach, describe, expect, it } from 'vitest';
import { initDiscussionForms } from '../discussion-form.ts';

describe('discussion-form', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <form class="blog-discussion-form" data-discussion-form>
        <p class="blog-discussion-notice blog-discussion-notice-info" data-discussion-submit-feedback hidden role="status" aria-live="polite">
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
  });

  it('shows the pending message and locks the submit button on submit', () => {
    initDiscussionForms();

    const form = document.querySelector<HTMLFormElement>('[data-discussion-form]');
    const button = document.querySelector<HTMLButtonElement>('[data-discussion-submit-button]');
    const feedback = document.querySelector<HTMLElement>('[data-discussion-submit-feedback]');

    form?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    expect(button?.disabled).toBe(true);
    expect(button?.textContent).toBe('Envoi en cours...');
    expect(button?.getAttribute('aria-disabled')).toBe('true');
    expect(feedback?.hidden).toBe(false);
  });
});
