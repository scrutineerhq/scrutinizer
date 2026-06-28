#!/usr/bin/env node
/**
 * Validate that the share payload fixture matches the share-format schema.
 *
 * Run: node tests/validate-share-format.js
 *
 * This catches field-name renames (the recurring bug class) at CI time:
 * if the share builder renames a field, the fixture must be updated to
 * match, and this test will fail if the fixture violates the schema.
 *
 * Uses a lightweight schema check (no dependencies) that validates
 * required fields, types, and banned field names.
 */

const fs = require('fs');
const path = require('path');

const schema = JSON.parse(fs.readFileSync(path.join(__dirname, 'share-format.json'), 'utf8'));
const fixture = JSON.parse(fs.readFileSync(path.join(__dirname, 'share-format-fixture.json'), 'utf8'));

let errors = 0;

function fail(msg) {
  console.error('FAIL: ' + msg);
  errors++;
}

// Check required top-level fields
for (const key of schema.required || []) {
  if (!(key in fixture)) fail('Missing required field: ' + key);
}

// Check no extra top-level fields
for (const key of Object.keys(fixture)) {
  if (!(key in schema.properties)) fail('Unknown top-level field: ' + key);
}

// Banned field names — these are the old renamed fields that caused bugs.
// If any appear, the share builder is renaming again.
const BANNED = {
  sources: ['source'],           // was renamed from 'slug'
  timeline: ['source_type', 'offset_ms', 'duration_ms'],  // was renamed from 'type', 'offset_ns', 'wall_ns'
  phase_markers: ['offset_ms'],  // was renamed from 'offset_ns'
  trace: ['callback', 'phase', 'exclusive_ms', 'inclusive_ms'],  // was decomposed from 'id', renamed from '_ns'
  queries: ['offset_ms'],        // was renamed from 'offset_ns'
  http_calls: ['offset_ms']      // was renamed from 'offset_ns'
};

for (const [section, bannedFields] of Object.entries(BANNED)) {
  if (Array.isArray(fixture[section])) {
    for (const item of fixture[section]) {
      for (const field of bannedFields) {
        if (field in item) {
          fail(section + '[]: contains banned renamed field "' + field +
               '" — use the native profiler field name instead');
        }
      }
    }
  }
}

// Validate sources have 'slug' not 'source'
if (fixture.sources) {
  for (const src of fixture.sources) {
    if (!src.slug) fail('sources[]: missing required "slug" field');
    if ('exclusive_ms' in src) fail('sources[]: has "exclusive_ms" — use "exclusive_ns"');
    if ('inclusive_ms' in src) fail('sources[]: has "inclusive_ms" — use "inclusive_ns"');
  }
}

// Validate trace has composite 'id' not decomposed 'callback'/'phase'
if (fixture.trace) {
  for (const t of fixture.trace) {
    if (!t.id) fail('trace[]: missing required composite "id" field');
    if (!t.id.includes('@')) fail('trace[]: "id" should be composite format callback@hook:priority, got: ' + t.id);
    if ('exclusive_ms' in t) fail('trace[]: has "exclusive_ms" — use "exclusive_ns"');
  }
}

// Validate timeline uses native field names
if (fixture.timeline) {
  for (const t of fixture.timeline) {
    if ('offset_ms' in t) fail('timeline[]: has "offset_ms" — use "offset_ns"');
    if ('duration_ms' in t) fail('timeline[]: has "duration_ms" — use "wall_ns" or "excl_ns"');
    if ('source_type' in t) fail('timeline[]: has "source_type" — use "type"');
  }
}

// Validate phase_markers use native field names
if (fixture.phase_markers) {
  for (const m of fixture.phase_markers) {
    if ('offset_ms' in m) fail('phase_markers[]: has "offset_ms" — use "offset_ns"');
  }
}

if (errors === 0) {
  console.log('OK: Share format fixture validates against schema (' +
    Object.keys(fixture).length + ' sections, 0 banned field names)');
  process.exit(0);
} else {
  console.error(errors + ' error(s) found');
  process.exit(1);
}
