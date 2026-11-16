/**
 * Runs Cast's actions in Craft's command palette: switching an appearance, and the
 * contrast switch.
 *
 * `CommandPaletteActions` gives each one an `event` action, which the palette runs by
 * dispatching `cast:set-preference` on the window with the action's detail: the change,
 * plus the URL and CSRF token to save it with. The page repaints at once, as every
 * theme's stylesheet is already on it, and the change saves behind it. If the save
 * fails, the page goes back and Craft shows the error.
 *
 * Published through the plugin's `$scripts`, so it loads on every CP page, where the
 * palette can be opened. `tests/js/command-palette.test.mjs` runs it in jsdom.
 */
(function () {
  var dataset = document.documentElement.dataset;
  var keys = {light: 'castLight', dark: 'castDark'};

  function set(key, value) {
    if (value) {
      dataset[key] = value;
    } else {
      delete dataset[key];
    }
  }

  // On the preferences screen, its own fields follow, so they don't show the old choice.
  function syncFields(detail) {
    if (detail.scheme in keys) {
      var select = document.getElementById('cast-' + detail.scheme);

      if (select) {
        var match = Array.prototype.some.call(select.options, function (option) {
          return option.value === detail.handle;
        });

        // Craft's own is the "inherit" row while the site default is Craft's own too.
        select.value = match ? detail.handle : 'inherit';
      }
    } else {
      var contrast = document.getElementById('cast-contrast');

      if (contrast) {
        contrast.checked = detail.highContrast;
      }
    }
  }

  function showError(message) {
    window.dispatchEvent(new CustomEvent('craft-message', {detail: {type: 'error', message: message}}));
  }

  window.addEventListener('cast:set-preference', function (event) {
    var detail = event.detail || {};
    var body = {};
    var key;

    if (detail.scheme in keys && typeof detail.handle === 'string') {
      key = keys[detail.scheme];
      body[detail.scheme] = detail.handle;
    } else if (typeof detail.highContrast === 'boolean') {
      key = 'castContrast';
      body.highContrast = detail.highContrast;
    } else {
      return;
    }

    var before = dataset[key];

    set(key, key === 'castContrast' ? (detail.highContrast ? 'more' : '') : detail.handle);
    syncFields(detail);

    window
      .fetch(detail.url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': detail.token,
        },
        body: JSON.stringify(body),
      })
      .then(function (response) {
        if (response.ok) {
          return;
        }

        return response
          .json()
          .catch(function () {
            return {};
          })
          .then(function (data) {
            throw new Error(data.message || response.statusText);
          });
      })
      .catch(function (error) {
        set(key, before);
        showError(error && error.message ? error.message : 'Cast couldn’t save that.');
      });
  });
})();
