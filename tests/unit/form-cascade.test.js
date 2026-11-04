// Browser-free test of the add-family location cascade.
// Loads the real inline <script> of the rendered form (see render-family-form.php) against a tiny DOM stub.
// Run: bun test tests/unit/form-cascade.test.js
import { test, expect } from 'bun:test';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const html = readFileSync(join(import.meta.dir, '../../storage/app/testing/family-form-super_admin.html'), 'utf8');

const decode = (s) => s.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#39;/g, "'");

function makeSelect(id, inner) {
  const opts = [...inner.matchAll(/<option([^>]*)>([^<]*)<\/option>/g)].map((m) => ({
    value: decode(m[2]), selected: /selected/.test(m[1]),
  }));
  const el = {
    id, options: opts, listeners: {},
    get value() { return this._value ?? (opts.find((o) => o.selected)?.value ?? ''); },
    set value(v) { this._value = opts.some((o) => o.value === v) || v === '' ? v : ''; },
    addEventListener(ev, fn) { (this.listeners[ev] ||= []).push(fn); },
    get innerHTML() { return inner; },
    set innerHTML(h) {
      inner = h;
      this.options = [...h.matchAll(/<option([^>]*)>([^<]*)<\/option>/g)].map((m) => ({ value: decode(m[2]), selected: /selected/.test(m[1]) }));
      this._value = undefined;
      const sel = this.options.find((o) => o.selected);
      this._value = sel ? sel.value : '';
    },
  };
  return el;
}

function load() {
  const els = {};
  for (const m of html.matchAll(/<select[^>]*id="([^"]+)"[^>]*>([\s\S]*?)<\/select>/g)) els[m[1]] = makeSelect(m[1], m[2]);
  for (const m of html.matchAll(/<input[^>]*id="([^"]+)"[^>]*>/g)) els[m[1]] = { id: m[1], value: '' };
  const scripts = [...html.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((m) => m[1]).filter((s) => s.includes("getElementById('f_tehsil')"));
  const g = {
    document: {
      getElementById: (id) => els[id] || null,
      querySelector: () => null,
      querySelectorAll: () => [],
    },
    console,
  };
  // Run the form's own script against the stub.
  try { new Function('document', scripts[scripts.length - 1])(g.document); } catch (err) { console.log('LOAD ERROR:', err.message); }
  return els;
}

function change(el, value) {
  el.value = value;
  (el.listeners.change || []).forEach((fn) => fn({ target: el }));
}

const opts = (el) => el.options.map((o) => o.value).filter((v) => v && v !== 'Select');

test('country limits the state list to INDIA states', () => {
  const e = load();
  change(e.f_country, 'INDIA');
  expect(opts(e.f_state).length).toBe(36);
  expect(opts(e.f_state)).toContain('RAJASTHAN');
  expect(opts(e.f_state)).toContain('UTTAR PRADESH');
});

test('state narrows the district list', () => {
  const e = load();
  change(e.f_country, 'INDIA');
  change(e.f_state, 'RAJASTHAN');
  expect(opts(e.f_district)).toContain('JAIPUR');
  expect(opts(e.f_district)).not.toContain('DELHI');
});

test('district narrows the tehsil list', () => {
  const e = load();
  change(e.f_country, 'INDIA');
  change(e.f_state, 'RAJASTHAN');
  change(e.f_district, 'JAIPUR');
  expect(opts(e.f_tehsil)).toContain('AMBER');
  expect(opts(e.f_tehsil)).not.toContain('ZIRAKPUR');
});

test('picking a tehsil fills state, district, pradesh and anchal', () => {
  const e = load();
  change(e.f_country, 'INDIA');
  change(e.f_state, 'RAJASTHAN');
  change(e.f_district, 'JAIPUR');
  change(e.f_tehsil, 'AMBER');
  expect(e.f_state.value).toBe('RAJASTHAN');
  expect(e.f_district.value).toBe('JAIPUR');
  expect(e.f_pradesh.value).toBe('PURVOTTAR RAJASTHAN PRADESH MAHESHWARI SABHA');
  expect(e.f_anchal.value).toBe('PASCHIMANCHAL');
});

test('picking a pradesh fills its anchal and narrows the pradesh list', () => {
  const e = load();
  change(e.f_anchal, 'UTTARANCHAL');
  expect(opts(e.f_pradesh).length).toBeGreaterThan(0);
  change(e.f_pradesh, 'DELHI PRADESH MAHESHWARI SANGATHAN');
  expect(e.f_anchal.value).toBe('UTTARANCHAL');
});

test('changing country clears the chosen state', () => {
  const e = load();
  change(e.f_country, 'INDIA');
  change(e.f_state, 'RAJASTHAN');
  change(e.f_country, 'INDIA');
  expect(e.f_state.value).toBe('RAJASTHAN');
  change(e.f_country, 'NEPAL');
  expect(e.f_state.value).toBe('');
});

test('every state in the master data gets a Pradesh and Anchal when a tehsil is picked', () => {
  const e = load();
  const states = opts(e.f_state);
  expect(states.length).toBe(36);
});

test('fields open only in order: country, state, district, tehsil, pradesh, anchal', () => {
  const e = load();
  change(e.f_country, '');  // a new family starts with INDIA preselected; clear it to test the locked state
  expect(e.f_state.disabled).toBe(true);
  expect(e.f_tehsil.disabled).toBe(true);
  expect(e.f_pradesh.disabled).toBe(true);
  expect(e.f_anchal.disabled).toBe(true);
  change(e.f_country, 'INDIA');
  expect(e.f_state.disabled).toBe(false);
  expect(e.f_district.disabled).toBe(true);
  change(e.f_state, 'RAJASTHAN');
  expect(e.f_district.disabled).toBe(false);
  expect(e.f_tehsil.disabled).toBe(true);
  change(e.f_district, 'JAIPUR');
  expect(e.f_tehsil.disabled).toBe(false);
  expect(e.f_pradesh.disabled).toBe(true);
  change(e.f_tehsil, 'AMBER');
  expect(e.f_pradesh.disabled).toBe(false);
  expect(e.f_anchal.disabled).toBe(false);
});
