'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const scriptPath = path.join(__dirname, '..', 'media', 'js', 'splaskscore.js');
const source = fs.readFileSync(scriptPath, 'utf8');
const marker = '  if (window.matchMedia) {';

assert.ok(source.includes(marker), 'Unable to expose postModuleAjax for regression testing');

const instrumented = source.replace(
  marker,
  '  window.__splaskTest = { postModuleAjax };\n\n' + marker
);

const windowObject = {};
const context = {
  URLSearchParams,
  window: windowObject,
  document: {
    readyState: 'loading',
    addEventListener() {}
  }
};

vm.runInNewContext(instrumented, context, { filename: scriptPath });

const postModuleAjax = windowObject.__splaskTest.postModuleAjax;
const root = {
  dataset: {
    splaskAjaxUrl: '/ajax',
    splaskModuleId: '42',
    splaskCsrfToken: 'csrf-token'
  }
};

async function expectFailure(response, expectedMessage) {
  context.fetch = async () => response;

  await assert.rejects(
    () => postModuleAjax(root, 'refreshAnalytics', {}),
    (error) => error && error.message === expectedMessage
  );
}

(async () => {
  context.fetch = async () => ({
    ok: true,
    status: 200,
    statusText: 'OK',
    text: async () => '{"data":{"success":true}}'
  });

  const success = await postModuleAjax(root, 'refreshAnalytics', {});
  assert.equal(success.data.success, true);
  console.log('PASS: valid JSON response is parsed');

  let errorBodyRead = false;
  await expectFailure({
    ok: false,
    status: 503,
    statusText: 'Service Unavailable',
    text: async () => {
      errorBodyRead = true;
      return '<html>unavailable</html>';
    }
  }, 'System Server Error: 503 Service Unavailable');
  assert.equal(errorBodyRead, false, 'HTTP error bodies must not be parsed as JSON');
  console.log('PASS: HTTP 503 is reported before parsing its HTML body');

  await expectFailure({
    ok: true,
    status: 200,
    statusText: 'OK',
    text: async () => '<html>firewall interruption</html>'
  }, 'Non-JSON response received (possibly interrupted by Firewall or Server Error).');
  console.log('PASS: HTTP 200 HTML response becomes a readable non-JSON error');

  console.log('AJAX response handling regression checks passed');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
