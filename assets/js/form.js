(function () {
  'use strict';

  const form    = document.getElementById('sbf-form');
  const status  = document.getElementById('sbf-status');
  const btn     = document.getElementById('sbf-submit');

  if ( ! form ) return;

  // ── Client-side validation ─────────────────────────────────────────────────
  function validate() {
    let valid = true;

    form.querySelectorAll('[required]').forEach(field => {
      field.classList.remove('sbf-error');

      if ( ! field.value.trim() ) {
        field.classList.add('sbf-error');
        valid = false;
        return;
      }

      if ( field.type === 'email' && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( field.value.trim() ) ) {
        field.classList.add('sbf-error');
        valid = false;
      }
    });

    return valid;
  }

  // ── Clear errors on input ──────────────────────────────────────────────────
  form.querySelectorAll('input, select, textarea').forEach(field => {
    field.addEventListener('input', () => field.classList.remove('sbf-error'));
    field.addEventListener('change', () => field.classList.remove('sbf-error'));
  });

  // ── Set status message ─────────────────────────────────────────────────────
  function setStatus( message, type ) {
    status.textContent  = message;
    status.className    = 'sbf-status sbf-status--' + type;
    status.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function clearStatus() {
    status.textContent = '';
    status.className   = 'sbf-status';
  }

  // ── Submit ─────────────────────────────────────────────────────────────────
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearStatus();

    if ( ! validate() ) {
      setStatus( SBF.strings.required, 'error' );
      return;
    }

    // Disable button
    btn.disabled    = true;
    btn.textContent = SBF.strings.sending;

    const body = new FormData( form );

    fetch( SBF.ajax_url, {
      method:      'POST',
      credentials: 'same-origin',
      body,
    })
    .then( res => {
      if ( ! res.ok ) throw new Error( 'Network error' );
      return res.json();
    })
    .then( data => {
      if ( data.success ) {
        setStatus( data.data?.message || SBF.strings.success, 'success' );
        form.reset();
      } else {
        setStatus( data.data?.message || SBF.strings.error, 'error' );
        btn.disabled    = false;
        btn.textContent = SBF.strings.button_text || 'Send enquiry';
      }
    })
    .catch( () => {
      setStatus( SBF.strings.error, 'error' );
      btn.disabled    = false;
      btn.textContent = 'Send enquiry';
    });
  });

})();