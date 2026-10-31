import assert from 'node:assert/strict';
import http from 'node:http';

const webdriver = (port, path, method, body) => new Promise((resolve, reject) => {
  const req = http.request({ hostname: '127.0.0.1', port, path, method, headers: { 'content-type': 'application/json' } }, res => {
    let data = '';
    res.on('data', chunk => { data += chunk; });
    res.on('end', () => resolve({ status: res.statusCode, body: data }));
  });
  req.on('error', reject);
  if (body) req.write(JSON.stringify(body));
  req.end();
});

const port = Number(process.env.WEBDRIVER_PORT ?? 4444);
const base = `http://127.0.0.1:${process.env.APP_PORT ?? 8765}`;
const session = await webdriver(port, '/session', 'POST', { capabilities: { alwaysMatch: { browserName: 'safari' } } });
assert.equal(session.status, 200, `Safari WebDriver session failed: ${session.body}`);
const sid = JSON.parse(session.body).value.sessionId;
const command = (path, method, body) => webdriver(port, `/session/${sid}${path}`, method, body);
try {
  await command('/url', 'POST', { url: `${base}/csrf-cookie` });
  const result = await command('/execute/sync', 'POST', { script: `async () => {
    const cookie = document.cookie;
    const withoutToken = await fetch('${base}/login', { method: 'POST', credentials: 'include', headers: {'content-type':'application/json'}, body: JSON.stringify({email:'not-an-email', password:'x'}) });
    const token = decodeURIComponent((cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
    const withToken = await fetch('${base}/login', { method: 'POST', credentials: 'include', headers: {'content-type':'application/json', 'X-XSRF-TOKEN': token}, body: JSON.stringify({email:'not-an-email', password:'x'}) });
    return { cookie, withoutToken: withoutToken.status, withToken: withToken.status };
  }`, args: [] });
  assert.equal(result.status, 200, result.body);
  const value = JSON.parse(result.body).value;
  assert.match(value.cookie, /XSRF-TOKEN=/, `CSRF cookie missing: ${value.cookie}`);
  assert.equal(value.withoutToken, 419, `missing-token request was not rejected: ${JSON.stringify(value)}`);
  assert.equal(value.withToken, 422, `token was not accepted by CSRF middleware: ${JSON.stringify(value)}`);
  console.log('PASS browser runtime CSRF: missing token=419, valid token reaches login=422');
} finally {
  await command('', 'DELETE').catch(() => {});
}
