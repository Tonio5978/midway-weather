import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (p) => JSON.parse(fs.readFileSync(path.join(root, p)));
const sha = (p) => createHash('sha256').update(fs.readFileSync(p)).digest('hex');
const cache = path.join(root, '.contracts-cache');
fs.mkdirSync(cache, { recursive: true });
for (const name of ['contracts-core', 'contracts-weather']) {
  const lock = read(`contracts/${name}.lock.json`);
  assert.equal(lock.package, name);
  assert.equal(lock.tag, `${name}/v${lock.version}`);
  const archive = path.join(root, 'contracts/vendor', lock.archive);
  assert.equal(sha(archive), lock.archive_sha256);
  assert.equal(fs.readFileSync(`${archive}.sha256`, 'utf8').trim(), `${lock.archive_sha256}  ${lock.archive}`);
  const prefix = `${name}-v${lock.version}/`;
  const names = execFileSync('tar', ['-tzf', archive], { encoding: 'utf8' }).trim().split('\n');
  assert.ok(names.every((n) => n.startsWith(prefix) && !n.split('/').includes('..')));
  const destination = path.join(cache, name);
  fs.rmSync(destination, { recursive: true, force: true });
  fs.mkdirSync(destination);
  execFileSync('tar', ['-xzf', archive, '--strip-components=1', '-C', destination]);
  const manifest = read(`.contracts-cache/${name}/contracts/${name}/package.json`);
  assert.equal(manifest.name, name);
  assert.equal(manifest.version, lock.version);
  assert.equal(manifest.tag, lock.tag);
  if (name === 'contracts-weather') {
    assert.deepEqual(read('.contracts-cache/contracts-weather/contracts/contracts-weather/contracts-core.lock.json'), read('contracts/contracts-core.lock.json'));
    assert.equal(sha(path.join(root, lock.semantic_validator.vendored_path)), lock.semantic_validator.sha256);
  }
  console.log(`Verified ${lock.tag} at ${lock.source_commit}: ${lock.archive_sha256}`);
}
