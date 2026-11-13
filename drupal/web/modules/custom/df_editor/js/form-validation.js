/**
 * @file
 * Keeps Save from silently doing nothing.
 */
(function (Drupal, drupalSettings, once) {

  const dialogVisible = function (dialog) {
    if (!dialog) {
      return false;
    }
    const style = window.getComputedStyle(dialog);
    return style.display !== 'none' && style.visibility !== 'hidden';
  };

  const blockingDialog = function () {
    const dialogs = document.querySelectorAll('.ui-dialog, dialog[open]');
    for (let i = 0; i < dialogs.length; i++) {
      const dialog = dialogs[i];
      if (!dialogVisible(dialog)) {
        continue;
      }
      if (dialog.classList.contains('media-library-widget-modal') || dialog.querySelector('.media-library-add-form, .media-library-add-form-wrapper, form.media-image-form, input[name*="[alt]"]')) {
        return dialog;
      }
    }
    return null;
  };

  const altStillEmpty = function (dialog) {
    const fields = dialog.querySelectorAll('input[name*="[alt]"], textarea[name*="[alt]"]');
    for (let i = 0; i < fields.length; i++) {
      if (!fields[i].value || !fields[i].value.trim()) {
        return true;
      }
    }
    return false;
  };

  const editorForm = function (button) {
    const formId = button && button.getAttribute('form');
    if (formId) {
      const form = document.getElementById(formId);
      if (form) {
        return form;
      }
    }
    return document.querySelector('form[data-df-editor-validation]');
  };

  const showMediaBlock = function (form, dialog) {
    if (!form) {
      return;
    }
    let notice = form.querySelector('.df-editor-media-dialog-block');
    if (!notice) {
      notice = document.createElement('div');
      notice.className = 'df-editor-media-dialog-block messages messages--error';
      notice.setAttribute('role', 'alert');
      form.prepend(notice);
    }
    if (dialog && altStillEmpty(dialog)) {
      notice.textContent = Drupal.t('The image upload is still open. Enter alt text and save the image, or close the window, then save the page.');
    }
    else {
      notice.textContent = Drupal.t('The media library is still open. Click Insert selected to add the image, or close the dialog, then save.');
    }
    notice.hidden = false;
    if (dialog) {
      dialog.prepend(notice);
    }
    else {
      form.prepend(notice);
    }
  };

  const stickySaveAt = function (x, y) {
    const sticky = document.querySelector('#gin-sticky-edit-submit, #gin-sticky-edit-preview, [data-drupal-selector="edit-submit"]');
    if (!sticky) {
      return false;
    }
    const box = sticky.getBoundingClientRect();
    return x >= box.left && x <= box.right && y >= box.top && y <= box.bottom;
  };

  const isSaveControl = function (button) {
    if (!button || button.closest('.ui-dialog')) {
      return false;
    }
    const selector = button.getAttribute('data-drupal-selector') || '';
    if (selector === 'edit-submit' || selector === 'edit-preview' || selector === 'gin-sticky-edit-submit' || selector === 'gin-sticky-edit-preview') {
      return true;
    }
    const label = (button.value || button.textContent || '').trim();
    return label === 'Save' || label === 'Preview';
  };

  Drupal.behaviors.dfEditorFormValidation = {
    attach: function (context) {
      once('dfEditorMediaSaveGuard', 'body', context).forEach(function () {
        document.addEventListener('click', function (event) {
          const dialog = blockingDialog();
          if (!dialog) {
            return;
          }
          const button = event.target.closest('button, input[type="submit"]');
          if (!isSaveControl(button) && !stickySaveAt(event.clientX, event.clientY)) {
            return;
          }
          event.preventDefault();
          event.stopPropagation();
          event.stopImmediatePropagation();
          showMediaBlock(editorForm(button), dialog);
        }, true);
      });

      once('dfEditorDraftConfirm', 'form[data-df-published-live="1"]', context).forEach(function (form) {
        form.addEventListener('submit', function (event) {
          const dialog = blockingDialog();
          if (dialog) {
            event.preventDefault();
            event.stopPropagation();
            showMediaBlock(form, dialog);
            return;
          }
          const submitter = event.submitter;
          const selector = submitter ? (submitter.getAttribute('data-drupal-selector') || '') : '';
          if (selector !== 'edit-submit' && selector !== 'gin-sticky-edit-submit') {
            return;
          }
          const select = form.querySelector('[name="moderation_state[0][state]"]');
          if (!select || select.value === 'published') {
            return;
          }
          const message = (drupalSettings.dfEditor && drupalSettings.dfEditor.draftConfirm)
            ? drupalSettings.dfEditor.draftConfirm
            : Drupal.t('View shows the live published version. This draft is on Latest version. Save anyway?');
          if (!window.confirm(message)) {
            event.preventDefault();
            event.stopPropagation();
          }
        }, true);
      });

      once('dfEditorErrorScroll', '.df-editor-validation-errors', context).forEach(function (summary) {
        const form = summary.closest('form') || document;
        const error = form.querySelector('.df-editor-inline-error, .df-editor-paragraph-error');
        const item = error ? error.closest('.js-form-item, .form-item, .paragraphs-subform, fieldset') : null;
        const field = item ? item.querySelector('input, textarea, select') : null;
        const sticky = document.querySelector('.gin-sticky-form-actions, #gin-sticky-edit-submit');
        if (sticky && !document.querySelector('.df-editor-sticky-errors')) {
          const note = document.createElement('div');
          note.className = 'df-editor-sticky-errors messages messages--error';
          note.setAttribute('role', 'alert');
          note.textContent = summary.innerText.replace(/\s+/g, ' ').trim();
          const bar = sticky.classList.contains('gin-sticky-form-actions') ? sticky : sticky.parentElement;
          if (bar) {
            bar.prepend(note);
          }
        }
        const visibleBox = function (node) {
          if (!node) {
            return false;
          }
          const box = node.getBoundingClientRect();
          return box.height > 0 && box.width > 0;
        };
        const target = visibleBox(field) ? field : (visibleBox(error) ? error : summary);
        if (!target.hasAttribute('tabindex')) {
          target.setAttribute('tabindex', '-1');
        }
        if (window.history && 'scrollRestoration' in window.history) {
          window.history.scrollRestoration = 'manual';
        }
        window.requestAnimationFrame(function () {
          target.scrollIntoView({ block: 'center' });
          if (field && typeof field.focus === 'function') {
            field.focus({ preventScroll: true });
          }
        });
      });

      once('dfEditorImageAlt', 'body', context).forEach(function () {
        if (!Drupal.CKEditor5Instances || Drupal.CKEditor5Instances.__dfAltWrapped) {
          return;
        }
        const bind = function (editor) {
          if (!editor || editor.__dfAltBound || !editor.plugins || !editor.plugins.has('ImageUploadEditing')) {
            return;
          }
          editor.__dfAltBound = true;
          editor.plugins.get('ImageUploadEditing').on('uploadComplete', function (evt, data) {
            const image = data && data.imageElement;
            if (!image) {
              return;
            }
            const alt = window.prompt(Drupal.t('Alt text is required. Describe this image for screen readers.'));
            editor.model.change(function (writer) {
              if (!alt || !String(alt).trim()) {
                writer.remove(image);
                return;
              }
              writer.setAttribute('alt', String(alt).trim(), image);
            });
          });
        };
        Drupal.CKEditor5Instances.forEach(bind);
        const mapSet = Drupal.CKEditor5Instances.set.bind(Drupal.CKEditor5Instances);
        Drupal.CKEditor5Instances.set = function (id, editor) {
          const result = mapSet(id, editor);
          bind(editor);
          return result;
        };
        Drupal.CKEditor5Instances.__dfAltWrapped = true;
      });
    },
  };

})(Drupal, drupalSettings, once);
