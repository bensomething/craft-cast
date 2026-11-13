// Runs `src/templates/_prefs.js` against a stand-in for Craft 6's preferences page.
//
// The page is the parts of `resources/js/pages/users/Preferences.vue` the script reaches
// for: the General field group holding the Theme radios, the hook's container under it,
// and the Accessibility field group led by Display Settings. The fragment mirrors what
// `_prefs.twig` renders. If either drifts, update it here alongside.

import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {JSDOM} from 'jsdom';

const script = readFileSync(new URL('../../src/templates/_prefs.js', import.meta.url), 'utf8');

function fragment({light = 'inherit', dark = 'inherit', contrast = false} = {}) {
  const select = (scheme, value, options) => `
    <craft-select data-cast-field="${scheme}">
      <label slot="label" for="cast-${scheme}">${scheme}</label>
      <select slot="input" id="cast-${scheme}" data-cast-scheme="${scheme}">
        ${options.map((o) => `<option value="${o}" ${o === value ? 'selected' : ''}>${o}</option>`).join('')}
      </select>
    </craft-select>`;

  return `
    <craft-field-group id="cast-prefs">
      ${select('light', light, ['inherit', '', 'stone'])}
      ${select('dark', dark, ['inherit', '', 'stone', 'dim'])}
      <craft-select-color data-cast-field="button" id="cast-button"></craft-select-color>
      <craft-checkbox-group id="cast-contrast-field">
        <craft-checkbox>
          <input slot="input" type="checkbox" id="cast-contrast" ${contrast ? 'checked' : ''}>
        </craft-checkbox>
      </craft-checkbox-group>
    </craft-field-group>`;
}

function page({theme = 'system', withTheme = true, withDisplay = true, buttonDefault = ''} = {}) {
  const radios = ['light', 'dark', 'system']
    .map((v) => `<input type="radio" name="theme" value="${v}" ${v === theme ? 'checked' : ''}>`)
    .join('');

  const dom = new JSDOM(
    `<!doctype html><html><body>
      <section id="preferences-general">
        <craft-field-group id="general">
          <craft-select id="time-zone"></craft-select>
          ${withTheme ? `<craft-radio-group name="theme">${radios}</craft-radio-group>` : ''}
        </craft-field-group>
        <div id="hook">${fragment()}</div>
      </section>
      <section id="preferences-accessibility">
        <craft-field-group id="accessibility">
          ${
            // The script places the switch after the group's first checkbox select, which
            // is Display Settings. Without it, Craft's markup has changed, so neither is here.
            withDisplay
              ? '<craft-checkbox-select id="display"></craft-checkbox-select><craft-checkbox-select id="general-settings"></craft-checkbox-select>'
              : '<craft-select id="notification-duration"></craft-select>'
          }
        </craft-field-group>
      </section>
    </body></html>`,
    {runScripts: 'outside-only'}
  );

  const {window} = dom;
  const saves = [];

  window.fetch = (url, options) => {
    saves.push({url, headers: options.headers, body: JSON.parse(options.body)});
    return Promise.resolve({ok: true});
  };

  window.eval(script);

  const run = () =>
    window.CastPreferences({
      url: '/actions/cast/preferences',
      token: 'csrf',
      defaults: {light: '', dark: 'dim'},
      craftTheme: theme,
      buttonDefault,
      buttonHandles: {'#dc2626': 'red'},
    });

  // Craft re-rendering the fragment: a fresh copy in the hook's container, then the
  // script runs again.
  const rerender = (state) => {
    window.document.getElementById('hook').innerHTML = fragment(state);
    run();
  };

  run();

  return {window, document: window.document, html: window.document.documentElement, saves, rerender};
}

function change(window, el, value) {
  if (el.type === 'checkbox' || el.type === 'radio') {
    el.checked = value;
  } else {
    el.value = value;
  }

  el.dispatchEvent(new window.Event('change', {bubbles: true}));
}

const ids = (nodes) => Array.from(nodes, (n) => n.id || n.getAttribute('data-cast-field') || n.localName);

test('places the appearances after Theme and the switch after Display Settings', () => {
  const {document} = page();

  assert.deepEqual(ids(document.getElementById('general').children), ['time-zone', 'craft-radio-group', 'light', 'dark', 'cast-button']);
  assert.deepEqual(ids(document.getElementById('accessibility').children), [
    'display',
    'cast-contrast-field',
    'general-settings',
  ]);
  assert.equal(document.getElementById('cast-prefs').style.display, 'none');
});

test('shows both appearances under System, all in quarters', () => {
  const {document} = page({theme: 'system'});
  const theme = document.querySelector('craft-radio-group');

  for (const el of [theme, ...document.querySelectorAll('[data-cast-field]')]) {
    assert.equal(el.style.display, '');
    assert.ok(el.classList.contains('width-25'));
  }
});

test('shows only the appearance Theme can use, keeping quarters', () => {
  const {document} = page({theme: 'dark'});

  assert.equal(document.querySelector('[data-cast-field="light"]').style.display, 'none');
  assert.equal(document.querySelector('[data-cast-field="dark"]').style.display, '');
  assert.ok(document.querySelector('craft-radio-group').classList.contains('width-25'));
  assert.equal(document.getElementById('cast-button').style.display, '', 'the button colour applies in either mode');
});

test('follows Theme as it changes, before saving', () => {
  const {window, document, saves} = page({theme: 'dark'});

  change(window, document.querySelector('input[name="theme"][value="light"]'), true);

  assert.equal(document.querySelector('[data-cast-field="light"]').style.display, '');
  assert.equal(document.querySelector('[data-cast-field="dark"]').style.display, 'none');
  assert.equal(saves.length, 0, "Theme is Craft's to save");
});

test('an appearance change repaints and saves', () => {
  const {window, document, html, saves} = page();

  change(window, document.getElementById('cast-dark'), 'stone');

  assert.equal(html.dataset.castDark, 'stone');
  assert.deepEqual(saves.at(-1).body, {dark: 'stone'});
  assert.equal(saves.at(-1).url, '/actions/cast/preferences');
  assert.equal(saves.at(-1).headers['X-CSRF-TOKEN'], 'csrf');
});

test("Site default applies the site's appearance, and Craft default clears it", () => {
  const {window, document, html, saves} = page();
  const dark = document.getElementById('cast-dark');

  change(window, dark, 'inherit');
  assert.equal(html.dataset.castDark, 'dim');
  assert.deepEqual(saves.at(-1).body, {dark: 'inherit'});

  change(window, dark, '');
  assert.equal(html.dataset.castDark, undefined);
  assert.deepEqual(saves.at(-1).body, {dark: ''});
});

test('the contrast switch repaints and saves', () => {
  const {window, document, html, saves} = page();
  const contrast = document.getElementById('cast-contrast');

  change(window, contrast, true);
  assert.equal(html.dataset.castContrast, 'more');
  assert.deepEqual(saves.at(-1).body, {highContrast: true});

  change(window, contrast, false);
  assert.equal(html.dataset.castContrast, undefined);
  assert.deepEqual(saves.at(-1).body, {highContrast: false});
});

test('a re-render replaces what the last one moved rather than adding to it', () => {
  const {document, rerender} = page();

  rerender();
  rerender();

  assert.equal(document.querySelectorAll('[data-cast-field="dark"]').length, 1);
  assert.equal(document.querySelectorAll('#cast-contrast-field').length, 1);
  assert.deepEqual(ids(document.getElementById('general').children), ['time-zone', 'craft-radio-group', 'light', 'dark', 'cast-button']);
});

test("a re-render's fields are the ones that respond", () => {
  const {window, document, html, saves, rerender} = page({theme: 'system'});

  rerender({dark: 'stone'});

  change(window, document.getElementById('cast-dark'), 'dim');
  assert.equal(html.dataset.castDark, 'dim');
  assert.deepEqual(saves.at(-1).body, {dark: 'dim'});

  // One Theme listener after three runs, acting on the newest fields.
  change(window, document.querySelector('input[name="theme"][value="light"]'), true);
  assert.equal(document.querySelector('[data-cast-field="dark"]').style.display, 'none');
});

test('running twice on the same render binds once', () => {
  const {window, document, saves} = page();

  window.CastPreferences({url: '/x', token: 't', defaults: {light: '', dark: ''}, craftTheme: 'system'});
  change(window, document.getElementById('cast-dark'), 'stone');

  assert.equal(saves.length, 1);
});

test("without Craft's fields to join, everything stays in Cast's group at full width", () => {
  const {document} = page({withTheme: false, withDisplay: false});
  const group = document.getElementById('cast-prefs');

  assert.deepEqual(ids(group.children), ['light', 'dark', 'cast-button', 'cast-contrast-field']);
  assert.equal(group.style.display, '');

  for (const field of group.querySelectorAll('[data-cast-field]')) {
    assert.equal(field.style.display, '');
    assert.equal(field.className, '');
  }
});

test('a button colour repaints and saves', () => {
  const {window, document, html, saves} = page();

  change(window, document.getElementById('cast-button'), 'red');

  assert.equal(html.dataset.castButton, 'red');
  assert.deepEqual(saves.at(-1).body, {buttonColor: 'red'});
});

test("Craft's blue follows the site default while that's Craft's own", () => {
  const {window, document, html, saves} = page();

  change(window, document.getElementById('cast-button'), 'red');
  change(window, document.getElementById('cast-button'), 'blue');

  assert.equal(html.dataset.castButton, undefined);
  assert.deepEqual(saves.at(-1).body, {buttonColor: 'inherit'});
});

test("with a site default colour, it follows the site and blue pins Craft's", () => {
  const {window, document, html, saves} = page({buttonDefault: 'teal'});
  const button = document.getElementById('cast-button');

  change(window, button, 'teal');
  assert.equal(html.dataset.castButton, 'teal');
  assert.deepEqual(saves.at(-1).body, {buttonColor: 'inherit'});

  change(window, button, 'blue');
  assert.equal(html.dataset.castButton, undefined);
  assert.deepEqual(saves.at(-1).body, {buttonColor: ''});
});

test("a re-render's button colour is the one that responds", () => {
  const {window, document, html, saves, rerender} = page();

  rerender();
  change(window, document.getElementById('cast-button'), 'pink');

  assert.equal(document.querySelectorAll('#cast-button').length, 1);
  assert.equal(html.dataset.castButton, 'pink');
  assert.deepEqual(saves.at(-1).body, {buttonColor: 'pink'});
});

test('Craft Red, picked by its hex, is stored and stamped as red', () => {
  const {window, document, html, saves} = page();

  change(window, document.getElementById('cast-button'), '#dc2626');

  assert.equal(html.dataset.castButton, 'red');
  assert.deepEqual(saves.at(-1).body, {buttonColor: 'red'});
});

test('Craft Red as the site default is followed, not pinned', () => {
  const {window, document, saves} = page({buttonDefault: 'red'});

  change(window, document.getElementById('cast-button'), '#dc2626');

  assert.deepEqual(saves.at(-1).body, {buttonColor: 'inherit'});
});
