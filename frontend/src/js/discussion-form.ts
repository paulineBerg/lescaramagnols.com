export const initDiscussionForms = (): void => {
  const forms = document.querySelectorAll<HTMLFormElement>('[data-discussion-form]');

  forms.forEach((form) => {
    if (form.dataset.discussionFormBound === '1') {
      return;
    }

    const submitButton = form.querySelector<HTMLButtonElement>('[data-discussion-submit-button]');
    const feedback = form.querySelector<HTMLElement>('[data-discussion-submit-feedback]');

    form.dataset.discussionFormBound = '1';

    form.addEventListener('submit', () => {
      if (submitButton instanceof HTMLButtonElement) {
        const pendingLabel = submitButton.getAttribute('data-submit-label-pending') || submitButton.textContent || '';
        submitButton.disabled = true;
        submitButton.textContent = pendingLabel;
        submitButton.setAttribute('aria-disabled', 'true');
      }

      if (feedback instanceof HTMLElement) {
        feedback.hidden = false;
      }
    });
  });
};
