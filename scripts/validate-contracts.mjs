import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import Ajv2020 from 'ajv/dist/2020.js';
import addFormats from 'ajv-formats';
import { weatherSemanticErrors } from '../contracts/vendor/weather-semantics.mjs';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (p) => JSON.parse(fs.readFileSync(p));
const ajv = new Ajv2020({ strict: true, allErrors: true });
addFormats(ajv);
const schemaFiles = {
  metadata: 'contracts-core/contracts/common/v1/metadata.schema.json',
  success: 'contracts-core/contracts/common/v1/success-envelope.schema.json',
  error: 'contracts-core/contracts/common/v1/error-envelope.schema.json',
  health: 'contracts-core/contracts/health/v1/health.schema.json',
  version: 'contracts-core/contracts/version/v1/version.schema.json',
  manifest: 'contracts-core/contracts/release-manifest/v1/release-manifest.schema.json',
  weather: 'contracts-weather/contracts/weather/v2/summary-current.schema.json',
  weatherError: 'contracts-weather/contracts/weather/v2/error.schema.json',
};
const schemas = Object.fromEntries(Object.entries(schemaFiles).map(([name, file]) => [name, read(path.join(root, '.contracts-cache', file))]));
for (const schema of Object.values(schemas)) ajv.addSchema(schema);
function validate(kind, payload, status) {
  const schema = kind === 'weather' && payload.error ? schemas.weatherError : schemas[kind];
  const validator = ajv.getSchema(schema.$id);
  assert.ok(validator(payload), ajv.errorsText(validator.errors, { separator: '\n' }));
  if (kind === 'weather') assert.deepEqual(weatherSemanticErrors(payload, Number(status)), []);
}
const [kind, file, status = '200'] = process.argv.slice(2);
if (kind === 'batch') {
  for (const entry of read(file)) validate(entry.kind, entry.payload, entry.status);
} else if (kind) {
  assert.ok(schemas[kind], 'Unknown response kind');
  validate(kind, read(file), status);
} else {
  const examples = path.join(root, '.contracts-cache/contracts-weather/contracts/weather/v2/examples');
  for (const name of fs.readdirSync(path.join(examples, 'valid'))) {
    validate('weather', read(path.join(examples, 'valid', name)), name === 'unavailable.json' ? 503 : name === 'internal-error.json' ? 500 : 200);
  }
  for (const name of fs.readdirSync(path.join(examples, 'invalid'))) {
    assert.throws(() => validate('weather', read(path.join(examples, 'invalid', name)), 200), name);
  }
}
console.log('Pinned contract schema and semantic conformance passed.');
