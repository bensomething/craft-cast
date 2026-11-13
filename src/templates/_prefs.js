/**
 * Wires up the fields `_prefs.twig` renders on Craft's preferences page.
 *
 * Kept out of the template so `tests/js/prefs.test.mjs` can run it against a stand-in
 * page. The template includes it with `source()` and calls it with what only the server
 * knows.
 *
 * @param {object} config
 * @param {string} config.url The action that saves a change.
 * @param {string} config.token The CSRF token for it.
 * @param {{light: string, dark: string}} config.defaults The site default appearance
 *   for each mode, which "Site default" applies. Empty is Craft's own.
 * @param {string} config.craftTheme Craft's saved Theme, for when its radios can't be
 *   read: light, dark or system.
 */
function CastPreferences(config) {
  // Craft re-renders this fragment as the page updates, and runs this again each time,
  // so it has to be safe to repeat. Bind to the newest copy, once.
  var roots = document.querySelectorAll('#cast-prefs');
  var root = roots[roots.length - 1];

  if (!root || root.hasAttribute('data-cast-ready')) {
    return;
  }

  root.setAttribute('data-cast-ready', '');

  var dataset = document.documentElement.dataset;

  function save(body, what) {
    window
      .fetch(config.url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': config.token,
        },
        body: JSON.stringify(body),
      })
      .then(function (response) {
        if (!response.ok) {
          throw new Error(response.statusText);
        }
      })
      .catch(function () {
        console.error('Cast couldn’t save the ' + what + '.');
      });
  }

  // Every theme's stylesheet is already on the page, so each change repaints at once.
  var keys = {light: 'castLight', dark: 'castDark'};

  // Fields moved out of the fragment are beyond Craft's cleanup of an old render, so
  // whatever an earlier render moved goes before this one moves its own.
  document.querySelectorAll('[data-cast-moved]').forEach(function (old) {
    old.remove();
  });

  function move(field, after) {
    field.setAttribute('data-cast-moved', '');
    after.parentNode.insertBefore(field, after.nextSibling);
  }

  // Held from here on: once moved, they're no longer inside `root`.
  var fields = Array.prototype.slice.call(root.querySelectorAll('[data-cast-field]'));
  var contrastField = root.querySelector('#cast-contrast-field');
  var contrast = root.querySelector('#cast-contrast');
  var widths = ['width-25', 'width-33', 'width-50', 'width-66', 'width-75'];

  // The appearances share a row with Craft's Theme field, in its own group. Craft gives
  // plugins no hook there; this places them after it. If Craft's markup changes, they
  // stay in Cast's group below, costing the layout and not the fields.
  var themeField = document.querySelector('craft-field-group > craft-radio-group[name="theme"]');

  if (themeField) {
    fields
      .slice()
      .reverse()
      .forEach(function (field) {
        move(field, themeField);
      });
  }

  // Shows the appearances Theme's value can use, all in thirds of Theme's row: both for
  // System, one for Light or Dark. The group stacks them again when narrow.
  function layout(theme) {
    var shown = fields.filter(function (field) {
      var scheme = field.getAttribute('data-cast-field');
      var visible = theme === 'system' || theme === scheme;

      field.style.display = visible ? '' : 'none';

      return visible;
    });

    // Without Craft's field to share a row with, each keeps a full one.
    if (!themeField) {
      return;
    }

    // Always thirds, so Theme holds its width as the appearances come and go.
    [themeField].concat(shown).forEach(function (el) {
      el.classList.remove.apply(el.classList, widths);
      el.classList.add('width-33');
    });
  }

  var checked = document.querySelector('input[name="theme"][type="radio"]:checked');
  layout(checked ? checked.value : config.craftTheme);

  // Follow Craft's Theme field as it changes, before it's saved. Delegated from the
  // document, as Vue owns those radios and may replace them. One listener for the page,
  // swapped for this render's, so re-renders don't stack them.
  if (window.CastThemeListener) {
    document.removeEventListener('change', window.CastThemeListener);
  }

  window.CastThemeListener = function (event) {
    var input = event.target;

    if (input && input.name === 'theme' && input.type === 'radio' && input.checked) {
      layout(input.value);
    }
  };

  document.addEventListener('change', window.CastThemeListener);

  fields.forEach(function (field) {
    var select = field.querySelector('select[data-cast-scheme]');

    select.addEventListener('change', function () {
      var scheme = select.getAttribute('data-cast-scheme');
      var handle = select.value === 'inherit' ? config.defaults[scheme] : select.value;

      if (handle) {
        dataset[keys[scheme]] = handle;
      } else {
        delete dataset[keys[scheme]];
      }

      var body = {};
      body[scheme] = select.value;
      save(body, scheme + ' appearance');
    });
  });

  if (contrast) {
    contrast.addEventListener('change', function () {
      if (contrast.checked) {
        dataset.castContrast = 'more';
      } else {
        delete dataset.castContrast;
      }

      save({highContrast: contrast.checked}, 'contrast setting');
    });
  }

  // Craft gives plugins no hook on the Accessibility tab, so the switch moves there,
  // straight after Display Settings. It's placed beside Craft's own fields rather than
  // inside one, which Vue re-renders. If Craft's markup changes, it stays put under
  // Appearance, costing the position and not the setting.
  var display = document.querySelector(
    '#preferences-accessibility craft-field-group > craft-checkbox-select'
  );

  if (display && contrastField) {
    move(contrastField, display);
  }

  // With everything placed in Craft's groups, Cast's own is empty; don't let it leave a
  // gap at the end of the section.
  if (!root.children.length) {
    root.style.display = 'none';
  }
}
