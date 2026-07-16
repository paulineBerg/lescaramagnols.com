type GrecaptchaApi = {
  ready: (callback: () => void) => void;
  execute: (siteKey: string, options: { action: string }) => Promise<string>;
};

type DiscussionLogLevel = 'info' | 'warning' | 'error';
type DiscussionFeedbackTone = 'success' | 'info' | 'error';

type DiscussionSubmitResponse = {
  ok?: boolean;
  message?: string;
  form?: {
    csrf_token?: string;
    form_nonce?: string;
  };
};

declare global {
  interface Window {
    grecaptcha?: GrecaptchaApi;
  }
}

const RECAPTCHA_MODE_V3_SCORE = 'v3_score';
const DEFAULT_RECAPTCHA_ACTION = 'blog_discussion';
const RECAPTCHA_READY_TIMEOUT_MS = 8000;
const RECAPTCHA_EXECUTE_TIMEOUT_MS = 12000;

const emitClientLog = (
  form: HTMLFormElement,
  stage: string,
  level: DiscussionLogLevel,
  details: Record<string, unknown> = {}
) => {
  const endpoint = (form.dataset.discussionLogEndpoint || '').trim();
  const articleSlug = form.querySelector<HTMLInputElement>('input[name="article_slug"]')?.value?.trim() || '';
  const articleLang = form.querySelector<HTMLInputElement>('input[name="article_lang"]')?.value?.trim() || '';
  const payload = {
    article_slug: articleSlug,
    article_lang: articleLang,
    stage,
    level,
    mode: (form.dataset.recaptchaMode || '').trim(),
    page: `${window.location.pathname}${window.location.search}${window.location.hash}`,
    details
  };

  if (endpoint === '') {
    return;
  }

  const body = JSON.stringify(payload);

  try {
    if (typeof navigator.sendBeacon === 'function') {
      const blob = new Blob([body], { type: 'application/json' });
      const accepted = navigator.sendBeacon(endpoint, blob);
      if (accepted) {
        return;
      }
    }
  } catch {
    // noop
  }

  void fetch(endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body,
    keepalive: true,
    credentials: 'same-origin'
  }).catch(() => {});
};

const setFeedbackState = (feedback: HTMLElement | null, tone: DiscussionFeedbackTone, message: string) => {
  if (!(feedback instanceof HTMLElement)) {
    return;
  }

  feedback.hidden = false;
  feedback.textContent = message;
  feedback.classList.remove(
    'blog-discussion-notice-info',
    'blog-discussion-notice-error',
    'blog-discussion-notice-success'
  );
  feedback.classList.add(`blog-discussion-notice-${tone}`);
};

const setSubmitButtonState = (submitButton: HTMLButtonElement | null, state: 'idle' | 'pending') => {
  if (!(submitButton instanceof HTMLButtonElement)) {
    return;
  }

  if (state === 'pending') {
    const pendingLabel = submitButton.getAttribute('data-submit-label-pending') || submitButton.textContent || '';
    submitButton.disabled = true;
    submitButton.textContent = pendingLabel;
    submitButton.setAttribute('aria-disabled', 'true');

    return;
  }

  const idleLabel = submitButton.getAttribute('data-submit-label-idle') || submitButton.textContent || '';
  submitButton.disabled = false;
  submitButton.textContent = idleLabel;
  submitButton.removeAttribute('aria-disabled');
};

const setPendingState = (submitButton: HTMLButtonElement | null, feedback: HTMLElement | null) => {
  setSubmitButtonState(submitButton, 'pending');

  const pendingMessage = feedback?.getAttribute('data-feedback-pending-message') || feedback?.textContent || '';
  setFeedbackState(feedback, 'info', pendingMessage);
};

const setErrorState = (submitButton: HTMLButtonElement | null, feedback: HTMLElement | null, errorMessage: string) => {
  setSubmitButtonState(submitButton, 'idle');
  setFeedbackState(feedback, 'error', errorMessage);
};

const setSuccessState = (submitButton: HTMLButtonElement | null, feedback: HTMLElement | null, message: string) => {
  setSubmitButtonState(submitButton, 'idle');
  setFeedbackState(feedback, 'success', message);
};

const setFieldAriaInvalid = (form: HTMLFormElement, isInvalid: boolean) => {
  form
    .querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('input[name="author"], input[name="email"], textarea[name="content"]')
    .forEach((field) => {
      if (isInvalid) {
        field.setAttribute('aria-invalid', 'true');
      } else {
        field.removeAttribute('aria-invalid');
      }
    });
};

const updateHiddenInput = (form: HTMLFormElement, name: string, value: string) => {
  const input = form.querySelector<HTMLInputElement>(`input[name="${name}"]`);
  if (input instanceof HTMLInputElement) {
    input.value = value;
  }
};

const refreshFormState = (form: HTMLFormElement, payload?: DiscussionSubmitResponse['form']) => {
  if (!payload) {
    return;
  }

  if (typeof payload.csrf_token === 'string' && payload.csrf_token !== '') {
    updateHiddenInput(form, 'csrf_token', payload.csrf_token);
  }

  if (typeof payload.form_nonce === 'string' && payload.form_nonce !== '') {
    updateHiddenInput(form, 'form_nonce', payload.form_nonce);
  }
};

const setEmptyStateVisibility = (form: HTMLFormElement, visible: boolean) => {
  const section = form.closest<HTMLElement>('.blog-discussions');
  const emptyState = section?.querySelector<HTMLElement>('[data-discussion-empty-state]')
    || form.parentElement?.querySelector<HTMLElement>('[data-discussion-empty-state]')
    || form.querySelector<HTMLElement>('[data-discussion-empty-state]');
  if (!(emptyState instanceof HTMLElement)) {
    return;
  }

  emptyState.hidden = !visible;
};

const clearDiscussionFields = (form: HTMLFormElement) => {
  form.querySelectorAll<HTMLInputElement>('input[name="author"], input[name="email"], input[name="g-recaptcha-response"]').forEach((input) => {
    input.value = '';
  });

  form.querySelectorAll<HTMLTextAreaElement>('textarea[name="content"]').forEach((textarea) => {
    textarea.value = '';
  });
};

const requestExpectsJson = async (response: globalThis.Response): Promise<DiscussionSubmitResponse | null> => {
  const contentType = (response.headers.get('content-type') || '').toLowerCase();
  if (!contentType.includes('application/json')) {
    return null;
  }

  const payload = await response.json().catch(() => null);

  return payload && typeof payload === 'object' ? (payload as DiscussionSubmitResponse) : null;
};

const submitFormRequest = async (
  form: HTMLFormElement,
  requestErrorMessage: string
): Promise<DiscussionSubmitResponse> => {
  const formData = new FormData(form);
  const response = await fetch(form.action, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  });

  const payload = await requestExpectsJson(response);
  if (payload === null) {
    emitClientLog(form, 'submit_non_json_response', 'error', {
      status: response.status,
      content_type: response.headers.get('content-type') || ''
    });

    throw new Error(requestErrorMessage);
  }

  refreshFormState(form, payload.form);

  if (!response.ok || payload.ok !== true) {
    emitClientLog(form, 'submit_server_error', 'warning', {
      status: response.status,
      message: payload.message || ''
    });

    throw new Error(payload.message || requestErrorMessage);
  }

  return payload;
};

const resolveV3Token = async (
  form: HTMLFormElement,
  tokenInput: HTMLInputElement,
  siteKey: string,
  action: string,
  api: GrecaptchaApi,
  errorMessage: string
): Promise<void> => {
  const readyStartedAt = Date.now();
  const readyPromise = new Promise<void>((resolve, reject) => {
    const readyTimeoutId = window.setTimeout(() => {
      reject(new Error('recaptcha-ready-timeout'));
    }, RECAPTCHA_READY_TIMEOUT_MS);

    api.ready(() => {
      window.clearTimeout(readyTimeoutId);
      resolve();
    });
  });

  await readyPromise;
  emitClientLog(form, 'recaptcha_ready', 'info', {
    elapsed_ms: Date.now() - readyStartedAt
  });

  const executeStartedAt = Date.now();
  const token = await Promise.race<string>([
    api.execute(siteKey, { action }),
    new Promise<string>((_, reject) => {
      window.setTimeout(() => {
        reject(new Error('recaptcha-execute-timeout'));
      }, RECAPTCHA_EXECUTE_TIMEOUT_MS);
    })
  ]);

  tokenInput.value = token.trim();
  if (tokenInput.value === '') {
    emitClientLog(form, 'recaptcha_empty_token', 'error', {
      elapsed_ms: Date.now() - executeStartedAt
    });
    throw new Error(errorMessage);
  }

  emitClientLog(form, 'recaptcha_token_received', 'info', {
    elapsed_ms: Date.now() - executeStartedAt,
    token_length: tokenInput.value.length
  });
};

export const initDiscussionForms = (): void => {
  const forms = document.querySelectorAll<HTMLFormElement>('[data-discussion-form]');

  forms.forEach((form) => {
    if (form.dataset.discussionFormBound === '1') {
      return;
    }

    const submitButton = form.querySelector<HTMLButtonElement>('[data-discussion-submit-button]');
    const feedback = form.querySelector<HTMLElement>('[data-discussion-submit-feedback]');

    form.dataset.discussionFormBound = '1';

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      setPendingState(submitButton, feedback);
      setFieldAriaInvalid(form, false);

      const recaptchaEnabled = form.dataset.recaptchaEnabled === '1';
      const recaptchaMode = (form.dataset.recaptchaMode || '').trim().toLowerCase();
      const tokenInput = form.querySelector<HTMLInputElement>('input[name="g-recaptcha-response"]');
      const siteKey = (form.dataset.recaptchaSiteKey || '').trim();
      const action = (form.dataset.recaptchaAction || DEFAULT_RECAPTCHA_ACTION).trim() || DEFAULT_RECAPTCHA_ACTION;
      const recaptchaErrorMessage = form.dataset.recaptchaErrorNotReady
        || feedback?.getAttribute('data-feedback-error-message')
        || 'reCAPTCHA unavailable';
      const requestErrorMessage = feedback?.getAttribute('data-feedback-request-error-message')
        || recaptchaErrorMessage;

      void (async () => {
        try {
          if (recaptchaEnabled && recaptchaMode === RECAPTCHA_MODE_V3_SCORE) {
            const api = window.grecaptcha;

            emitClientLog(form, 'submit_intercepted', 'info', {
              has_api: !!api,
              has_token_input: tokenInput instanceof HTMLInputElement,
              site_key_length: siteKey.length,
              action
            });

            if (!(tokenInput instanceof HTMLInputElement) || siteKey === '' || !api || typeof api.ready !== 'function' || typeof api.execute !== 'function') {
              emitClientLog(form, 'recaptcha_api_missing', 'error', {
                has_api: !!api,
                has_ready: !!api && typeof api.ready === 'function',
                has_execute: !!api && typeof api.execute === 'function',
                has_token_input: tokenInput instanceof HTMLInputElement,
                site_key_length: siteKey.length
              });
              throw new Error(recaptchaErrorMessage);
            }

            await resolveV3Token(form, tokenInput, siteKey, action, api, recaptchaErrorMessage);
          }

          emitClientLog(form, 'ajax_submit_start', 'info', {
            recaptcha_enabled: recaptchaEnabled,
            recaptcha_mode: recaptchaMode
          });

          const payload = await submitFormRequest(form, requestErrorMessage);

          clearDiscussionFields(form);
          setEmptyStateVisibility(form, false);
          setSuccessState(submitButton, feedback, payload.message || requestErrorMessage);
          setFieldAriaInvalid(form, false);
          emitClientLog(form, 'submit_success', 'info', {
            discussion_status: 'pending'
          });
        } catch (error: unknown) {
          const normalizedError = error instanceof Error ? error : new Error(requestErrorMessage);

          emitClientLog(form, 'submit_request_failed', 'error', {
            error_name: normalizedError.name,
            error_message: normalizedError.message
          });

          if (tokenInput instanceof HTMLInputElement) {
            tokenInput.value = '';
          }

          setEmptyStateVisibility(form, true);
          setErrorState(submitButton, feedback, normalizedError.message || requestErrorMessage);
          setFieldAriaInvalid(form, true);
        }
      })();
    });
  });
};
