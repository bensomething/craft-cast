// Runs `resources/js/command-palette.js` in jsdom, dispatching the event Craft's command
// palette dispatches for one of Cast's actions, with `fetch` stubbed.

import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {JSDOM} from 'jsdom';

const script = readFileSync(new URL('../../resources/js/command-palette.js', import.meta.url), 'utf8');

function page({body = '', respond = {ok: true, status: 200, json: {}}} = {}) {
  const dom = new JSDOM(
    `<!doctype html><html data-cast-dark="dim"><body>${body}</body></html>`,
    {runScripts: 'outside-only', url: 'https://example.test/admin/dashboard'}
  );
  const {window} = dom;
  const requests = [];
  const messages = [];

  window.fetch = (url, options) => {
    requests.push({url, options, body: JSON.parse(options.body)});

    return Promise.resolve({
      ok: respond.ok,
      status: respond.status,
      statusText: 'Nope',
      json: () => Promise.resolve(respond.json),
    });
  };
  window.addEventListener('craft-message', (event) => messages.push(event.detail));
  window.eval(script);

  const run = async (detail) => {
    window.dispatchEvent(
      new window.CustomEvent('cast:set-preference', {
        detail: {url: '/admin/actions/cast/preferences', token: 'tok', ...detail},
      })
    );
    await new Promise((resolve) => window.setTimeout(resolve, 0));
  };

  return {window, html: window.document.documentElement, requests, messages, run};
}

test('an appearance repaints at once and saves', async () => {
  const {html, requests, run} = page();

  await run({scheme: 'dark', handle: 'violet'});

  assert.equal(html.dataset.castDark, 'violet');
  assert.equal(requests.length, 1);
  assert.equal(requests[0].url, '/admin/actions/cast/preferences');
  assert.equal(requests[0].options.method, 'POST');
  assert.equal(requests[0].options.headers['X-CSRF-TOKEN'], 'tok');
  assert.deepEqual(requests[0].body, {dark: 'violet'});
});

test("Craft's own clears the appearance", async () => {
  const {html, requests, run} = page();

  await run({scheme: 'dark', handle: ''});

  assert.equal(html.dataset.castDark, undefined);
  assert.deepEqual(requests[0].body, {dark: ''});
});

test('the contrast switch turns on and off', async () => {
  const {html, requests, run} = page();

  await run({highContrast: true});
  assert.equal(html.dataset.castContrast, 'more');

  await run({highContrast: false});
  assert.equal(html.dataset.castContrast, undefined);
  assert.deepEqual(requests.map((r) => r.body), [{highContrast: true}, {highContrast: false}]);
});

test('a failed save puts the page back and shows the error', async () => {
  const {html, messages, run} = page({respond: {ok: false, status: 422, json: {message: 'That theme isn’t available.'}}});

  await run({scheme: 'dark', handle: 'violet'});

  assert.equal(html.dataset.castDark, 'dim');
  assert.deepEqual(JSON.parse(JSON.stringify(messages)), [{type: 'error', message: 'That theme isn’t available.'}]);
});

test("on the preferences screen, Cast's own fields follow", async () => {
  const {window, run} = page({
    body: `
      <select id="cast-dark"><option value="inherit"></option><option value="dim"></option><option value="violet"></option></select>
      <select id="cast-light"><option value="inherit" selected></option><option value="stone"></option></select>
      <input type="checkbox" id="cast-contrast">`,
  });
  const $ = (id) => window.document.getElementById(id);

  await run({scheme: 'dark', handle: 'violet'});
  await run({scheme: 'light', handle: ''});
  await run({highContrast: true});

  assert.equal($('cast-dark').value, 'violet');
  assert.equal($('cast-light').value, 'inherit', "Craft's own is the inherit row when there's no '' option");
  assert.equal($('cast-contrast').checked, true);
});

test('other details are ignored', async () => {
  const {html, requests, run} = page();

  await run({scheme: 'sepia', handle: 'x'});
  await run({});

  assert.equal(requests.length, 0);
  assert.equal(html.dataset.castDark, 'dim');
});
