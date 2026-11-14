// Runs `resources/js/custom-themes.js` against a stand-in for the Custom Themes table, as
// Craft's TableControl.vue renders it: rows as `tr[data-row-id]`, cells as
// `td[data-column]` holding each cell's control, and Preview as Craft's multiple-choice
// button group.

import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {JSDOM} from 'jsdom';

const script = readFileSync(new URL('../../resources/js/custom-themes.js', import.meta.url), 'utf8');

function row(id, {color = '#0d9488', tint = 'subtle'} = {}) {
  return `
    <tr data-row-id="${id}">
      <td data-column="name"><input value="Theme ${id}"></td>
      <td data-column="color"><craft-input-color data-value="${color}"></craft-input-color></td>
      <td data-column="tint">
        <craft-select>
          <select slot="input">${['subtle', 'medium', 'strong'].map((t) => `<option ${t === tint ? 'selected' : ''}>${t}</option>`).join('')}</select>
        </craft-select>
      </td>
      <td data-column="preview">
        <craft-button-group>
          <craft-button value="light"></craft-button><craft-button value="dark"></craft-button>
        </craft-button-group>
      </td>
    </tr>`;
}

function page({rows = [row('a'), row('b', {color: '#7c3aed'})], url = 'https://example.test/admin/settings/plugins/cast'} = {}) {
  const dom = new JSDOM(
    `<!doctype html><html data-theme="dark" data-cast-dark="dim"><head></head><body><table><tbody>${rows.join('')}</tbody></table></body></html>`,
    {runScripts: 'outside-only', url}
  );
  const {window} = dom;
  const document = window.document;

  // Stand-ins for Craft's components: the colour's value lives on the element, as its
  // input is in a shadow root, and the select wrapper's own value isn't the chosen option.
  for (const el of document.querySelectorAll('craft-input-color')) {
    el.value = el.dataset.value;
  }
  for (const el of document.querySelectorAll('craft-select')) {
    el.value = 'stale';
  }

  // Craft's multiple button group: a click toggles the button, applied on Vue's next
  // tick (a microtask), as Craft's is.
  document.addEventListener('click', (event) => {
    const button = event.target.closest('craft-button');
    if (button) {
      window.queueMicrotask(() => button.toggleAttribute('active'));
    }
  }, true);

  window.eval(script);

  const html = document.documentElement;
  const cell = (id, column) => document.querySelector(`tr[data-row-id="${id}"] td[data-column="${column}"]`);
  const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));
  const press = async (id, mode) => {
    cell(id, 'preview').querySelector(`craft-button[value="${mode}"]`).click();
    await wait(10);
  };
  const on = (id) => [...cell(id, 'preview').querySelectorAll('craft-button[active]')].map((b) => b.getAttribute('value'));
  const edit = (id, column, value) => {
    const el = cell(id, column).querySelector('select, craft-input-color');
    el.value = value;
    el.dispatchEvent(new window.Event(el.tagName === 'SELECT' ? 'change' : 'input', {bubbles: true}));
  };
  const href = () => document.getElementById('cast-preview')?.getAttribute('href') ?? null;

  return {window, document, html, press, on, edit, href, wait};
}

test('pressing Light or Dark shows that row in that mode, from the server', async () => {
  const {html, press, href} = page();

  await press('a', 'light');

  assert.equal(href(), 'https://example.test/admin/cast/preview.css?color=0d9488&tint=subtle');
  assert.equal(html.dataset.theme, undefined, 'removed for light, as Craft does');
  assert.equal(html.dataset.castLight, 'cast-preview');
  assert.equal(html.dataset.castDark, 'cast-preview');

  await press('a', 'dark');
  assert.equal(html.dataset.theme, 'dark');
});

test('switching a row from Dark to Light leaves only Light on', async () => {
  const {html, press, on} = page();

  await press('a', 'dark');
  await press('a', 'light');

  assert.deepEqual(on('a'), ['light']);
  assert.equal(html.dataset.theme, undefined);
});

test("pressing a button in another row switches the first row's off", async () => {
  const {html, press, href, on} = page();

  await press('a', 'light');
  await press('b', 'dark');

  assert.deepEqual(on('a'), []);
  assert.deepEqual(on('b'), ['dark']);
  assert.match(href(), /color=7c3aed/);
  assert.equal(html.dataset.theme, 'dark');
});

test('pressing the button again puts back what the page showed, mode and all', async () => {
  const {html, press, href, on} = page();

  await press('a', 'light');
  await press('a', 'light');

  assert.deepEqual(on('a'), []);
  assert.equal(href(), null);
  assert.equal(html.dataset.theme, 'dark');
  assert.equal(html.dataset.castDark, 'dim');
  assert.equal(html.dataset.castLight, undefined);
});

test("a row's tint is read from the native select, not Craft's wrapper", async () => {
  const {press, href} = page({rows: [row('a', {tint: 'strong'})]});

  await press('a', 'dark');

  assert.match(href(), /tint=strong/);
});

test("editing the previewed row's colour and tint updates it after a pause", async () => {
  const {press, edit, href, wait} = page();

  await press('a', 'dark');
  edit('a', 'color', '#E11D48');
  edit('a', 'tint', 'strong');
  assert.match(href(), /color=0d9488/, 'not on every key');

  await wait(200);
  assert.equal(href(), 'https://example.test/admin/cast/preview.css?color=e11d48&tint=strong');
});

test("another row's edits leave the preview alone", async () => {
  const {press, edit, href, wait} = page();

  await press('a', 'dark');
  edit('b', 'color', '#ff0000');
  await wait(200);

  assert.match(href(), /color=0d9488/);
});

test('a half-typed colour leaves the page as it was', async () => {
  const {html, press, href} = page({rows: [row('a', {color: '#0d9'})]});

  await press('a', 'dark');

  assert.equal(href(), null);
  assert.equal(html.dataset.castDark, 'dim');
});

test('leaving the screen ends the preview', async () => {
  const {window, document, html, press, href} = page();

  await press('a', 'light');
  document.dispatchEvent(new window.CustomEvent('inertia:navigate'));

  assert.equal(href(), null);
  assert.equal(html.dataset.theme, 'dark');
  assert.equal(html.dataset.castDark, 'dim');
});

test("only on Cast's settings screen", async () => {
  const {press, href} = page({url: 'https://example.test/admin/settings/plugins/other'});

  await press('a', 'dark');

  assert.equal(href(), null);
});

test('buttons in other tables are left alone', async () => {
  const {document, href, wait} = page();
  document.body.insertAdjacentHTML(
    'beforeend',
    '<table><tr data-row-id="x"><td data-column="preview"><craft-button value="dark" id="other"></craft-button></td></tr></table>'
  );

  document.getElementById('other').click();
  await wait(10);

  assert.equal(href(), null);
});
