/**
 * The Custom Themes table's Preview, on Cast's settings screen.
 *
 * Pressing a row's Light or Dark Preview button shows that theme on the page, in that
 * mode, before anything is saved: the page switches mode too, so either half of a theme
 * can be checked from either. Editing its colour or tint updates it as you go. The theme
 * comes from the server, generated as it would be once saved, so the preview is the real
 * thing. One button is on at a time, across every row; pressing it again, leaving the
 * screen or saving puts the page back.
 *
 * The buttons are Craft's multiple-choice button group, the only kind that can be
 * clicked off, so pressing one leaves any other on: this switches those off by clicking
 * them, which keeps Craft's own state in step with what's shown.
 *
 * Published through the plugin's `$scripts`, so it loads on every CP page: Craft moves
 * between screens without reloading. It listens on the document and does nothing until
 * a Preview button is pressed. Rows are found by Craft's table markup (`tr[data-row-id]`,
 * cells `td[data-column]`). `tests/js/custom-themes.test.mjs` runs it against a stand-in.
 */
(function () {
  var dataset = document.documentElement.dataset;

  // The row being previewed, and what the page showed before.
  var previewing = null;
  var saved = null;
  var timer = null;

  // Set while this switches buttons off, so those clicks aren't taken as the user's.
  var syncing = false;

  function cell(row, column) {
    return row.querySelector('td[data-column="' + column + '"]');
  }

  // A cell's value. Craft wraps a select cell's native `<select>` in a `<craft-select>`,
  // whose own value isn't the chosen option, so the native one is read first. A colour
  // cell's input is inside `<craft-input-color>`'s shadow root, so that carries it.
  function value(td) {
    var control = td && (td.querySelector('select') || td.querySelector('craft-input-color') || td.querySelector('input:not([type="hidden"])'));

    return control && typeof control.value === 'string' ? control.value : '';
  }

  // The settings screen is `<cp>/settings/plugins/cast`, and the preview `<cp>/cast/preview.css`.
  function previewUrl() {
    var match = window.location.pathname.match(/^(.*)\/settings\/plugins\/cast\/?$/);

    return match ? new URL(match[1] + '/cast/preview.css', window.location.href) : null;
  }

  function isThemeRow(row) {
    return !!(row && cell(row, 'preview') && cell(row, 'color') && cell(row, 'tint'));
  }

  function buttons(row) {
    var td = cell(row, 'preview');

    return td ? Array.prototype.slice.call(td.querySelectorAll('craft-button')) : [];
  }

  function isOn(button) {
    return !!button.active || button.hasAttribute('active');
  }

  function buttonValue(button) {
    return button.value || button.getAttribute('value') || '';
  }

  // The mode a row's Preview is on, or '' for none.
  function mode(row) {
    var on = buttons(row).filter(isOn).map(buttonValue);

    return on.indexOf('dark') !== -1 ? 'dark' : on.indexOf('light') !== -1 ? 'light' : '';
  }

  function switchOff(button) {
    syncing = true;

    try {
      button.click();
    } finally {
      syncing = false;
    }
  }

  function link() {
    var el = document.getElementById('cast-preview');

    if (!el) {
      el = document.createElement('link');
      el.id = 'cast-preview';
      el.rel = 'stylesheet';
      document.head.appendChild(el);
    }

    return el;
  }

  // `pressed` is the mode just pressed, which Craft may not have marked as the only one on yet.
  function show(row, pressed) {
    var previewMode = pressed || mode(row);
    var color = value(cell(row, 'color')).replace(/^#/, '').toLowerCase();
    var tint = value(cell(row, 'tint')) || 'subtle';
    var url = previewUrl();

    if (!previewMode || !/^[0-9a-f]{6}$/.test(color) || !url) {
      return;
    }

    if (!saved) {
      saved = {light: dataset.castLight, dark: dataset.castDark, theme: dataset.theme};
    }

    previewing = row;

    url.searchParams.set('color', color);
    url.searchParams.set('tint', tint);
    link().href = url.toString();

    // Craft's mode is its `data-theme`, which it sets for dark and removes for light.
    // The generated theme dresses both.
    if (previewMode === 'dark') {
      dataset.theme = 'dark';
    } else {
      delete dataset.theme;
    }

    dataset.castLight = 'cast-preview';
    dataset.castDark = 'cast-preview';
  }

  function restore(key, value) {
    if (value === undefined) {
      delete dataset[key];
    } else {
      dataset[key] = value;
    }
  }

  function clear() {
    previewing = null;

    var el = document.getElementById('cast-preview');

    if (el) {
      el.remove();
    }

    if (saved) {
      restore('castLight', saved.light);
      restore('castDark', saved.dark);
      restore('theme', saved.theme);
      saved = null;
    }
  }

  // One button on at a time: every other one, in this row and the rest, goes off.
  function onlyThis(row, pressed) {
    document.querySelectorAll('tr[data-row-id]').forEach(function (other) {
      if (!isThemeRow(other)) {
        return;
      }

      buttons(other).forEach(function (button) {
        if (isOn(button) && !(other === row && buttonValue(button) === pressed)) {
          switchOff(button);
        }
      });
    });
  }

  // Another row with a button on takes over when the previewed one goes, or nothing does.
  function next() {
    var rows = Array.prototype.slice.call(document.querySelectorAll('tr[data-row-id]')).filter(function (row) {
      return isThemeRow(row) && mode(row) !== '';
    });

    if (rows.length) {
      show(rows[rows.length - 1]);
    } else {
      clear();
    }
  }

  // The previewed row's colour or tint being edited. Typing a colour fires on every key,
  // so wait for a pause.
  function onEdit(event) {
    var target = event.target;
    var td = target && target.closest ? target.closest('td[data-column="color"], td[data-column="tint"]') : null;
    var row = td ? td.closest('tr[data-row-id]') : null;

    if (row && row === previewing) {
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        if (row === previewing && row.isConnected) {
          show(row);
        }
      }, 150);
    }
  }

  document.addEventListener('change', onEdit);
  document.addEventListener('input', onEdit);

  // A Preview button pressed, read once Craft has updated which are on.
  function onPress(button) {
    var td = button.closest('td[data-column="preview"]');
    var row = td ? td.closest('tr[data-row-id]') : null;

    if (!isThemeRow(row)) {
      return;
    }

    var pressed = buttonValue(button);

    if (isOn(button)) {
      onlyThis(row, pressed);
      show(row, pressed);
    } else if (row === previewing) {
      next();
    }
  }

  document.addEventListener('click', function (event) {
    var button = !syncing && event.target && event.target.closest ? event.target.closest('craft-button') : null;

    window.setTimeout(function () {
      if (button) {
        onPress(button);
      }

      // A row deleted while previewed.
      if (previewing && !previewing.isConnected) {
        next();
      }
    }, 0);
  });

  // Leaving the screen, or saving, which reloads it with every button off.
  ['inertia:navigate', 'inertia:success'].forEach(function (name) {
    document.addEventListener(name, clear);
  });
})();
