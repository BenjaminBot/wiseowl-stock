const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const isbnContext = {};
vm.createContext(isbnContext);
vm.runInContext(fs.readFileSync('outputs/scanner-proof/isbn.js', 'utf8'), isbnContext);
const { canonicalIsbn } = isbnContext.WiseOwlIsbn;
for (const [input, expected] of [
  ['9780140328721', '9780140328721'], ['0-14-032872-6', '9780140328721'],
  ['080442957X', '9780804429573'], ['9791090636071', '9791090636071'],
  ['9780140328722', null], ['0140328727', null], ['4006381333931', null],
  ['hello', null], ['', null], ['978014032872', null], ['97801403287210', null],
]) assert.equal(canonicalIsbn(input), expected, input);
console.log('PASS: 11 ISBN validation/normalisation cases');
let linkCount = 0;
for (const file of ['README.md', ...walk('docs').filter(f => f.endsWith('.md'))]) {
  const body = fs.readFileSync(file, 'utf8');
  for (const match of body.matchAll(/\[[^\]]*\]\(([^)]+)\)/g)) {
    const ref = match[1];
    if (/^https?:/.test(ref)) continue;
    assert.ok(fs.existsSync(path.resolve(path.dirname(file), ref.split('#')[0])), `${file} -> ${ref}`);
    linkCount++;
  }
}
console.log(`PASS: ${linkCount} local documentation links`);
function walk(dir) { return fs.readdirSync(dir, { withFileTypes: true }).flatMap(e => e.isDirectory() ? walk(path.join(dir, e.name)) : [path.join(dir, e.name)]); }
const ids = ['start','stop','next','status','canonical','detail','result','isbn','empty','history','export','manual','example','photo'];
const elements = Object.fromEntries(ids.map(id => [id, { value: '', disabled: false, hidden: false, textContent: '', events: {}, rows: [], addEventListener(type, fn) { this.events[type] = fn; }, prepend(row) { this.rows.unshift(row); }, scrollIntoView() {}, focus() {} }]));
const document = { hidden: false, getElementById: id => elements[id], addEventListener() {}, createElement: () => ({ textContent: '' }) };
const context = { document, window: { isSecureContext: false, addEventListener() {} }, navigator: {}, WiseOwlIsbn: { canonicalIsbn }, Date, Blob, URL, setTimeout };
vm.createContext(context);
vm.runInContext(fs.readFileSync('outputs/scanner-proof/scanner.js','utf8'), context);
(async () => {
  await elements.start.events.click();
  assert.match(elements.status.textContent, /HTTPS/);
  elements.isbn.value = '9780140328721';
  elements.manual.events.submit({ preventDefault() {} });
  await new Promise(r => setImmediate(r));
  assert.equal(elements.canonical.textContent, '9780140328721');
  assert.equal(elements.history.rows.length, 1);
  elements.isbn.value = '0140328726';
  elements.manual.events.submit({ preventDefault() {} });
  await new Promise(r => setImmediate(r));
  assert.match(elements.detail.textContent, /Seen earlier/);
  assert.equal(elements.history.rows.length, 2);
  elements.isbn.value = '9780140328722';
  elements.manual.events.submit({ preventDefault() {} });
  await new Promise(r => setImmediate(r));
  assert.equal(elements.history.rows.length, 2);
  assert.match(elements.status.textContent, /Invalid/);
  console.log('PASS: manual entry, repeated ISBN, invalid input, insecure-context fallback');
})().catch(error => { console.error(error); process.exitCode = 1; });
