import { test, expect } from 'bun:test';
import { readFileSync } from 'node:fs';
const html = readFileSync('/home/u966147115/domains/khobargarden.com/public_html/abmm/storage/app/testing/family-form-super_admin.html', 'utf8');
test('city list fills after a tehsil is chosen', async () => {
  const calls = [];
  globalThis.fetch = async (url) => { calls.push(String(url)); return { ok: true, json: async () => ({ villages: ['ACHROL', 'ANI'] }) }; };
  process.on('unhandledRejection', (e) => console.log('REJ', e));
  const els = {};
  const mk = (id, inner = '') => ({ id, listeners: {}, _v: '', innerHTML: inner, children: [],
    get value() { return this._v; }, set value(v) { this._v = v; },
    addEventListener(ev, fn) { (this.listeners[ev] ||= []).push(fn); },
    appendChild(o) { this.children.push(o); return o; } });
  for (const m of html.matchAll(/<select[^>]*id="([^"]+)"[^>]*>([\s\S]*?)<\/select>/g)) els[m[1]] = mk(m[1], m[2]);
  for (const m of html.matchAll(/<input[^>]*id="([^"]+)"[^>]*>/g)) els[m[1]] = mk(m[1]);
  for (const m of html.matchAll(/<datalist id="([^"]+)"[^>]*>/g)) els[m[1]] = mk(m[1]);
  const scripts = [...html.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((m) => m[1]).filter((s) => s.includes("getElementById('f_tehsil')"));
  const doc = { getElementById: (id) => els[id] || null, querySelector: () => null, querySelectorAll: () => [], createElement: (tag) => ({ tagName: tag.toUpperCase(), value: '' }) };
  try { new Function('document', 'fetch', scripts[scripts.length - 1])(doc, globalThis.fetch); } catch (e) { console.log('LOAD ERR', e.message); }
  console.log('script found:', scripts.length, 'tehsil listeners:', (els.f_tehsil.listeners.change || []).length, 'datalist el:', !!els.cityList);
  els.f_tehsil.value = 'AMBER';
  for (const f of (els.f_tehsil.listeners.change || [])) { try { f({ target: els.f_tehsil }); } catch (e) { console.log('LISTENER ERR', e.message); } }
  await new Promise((r) => setTimeout(r, 20));
  console.log('fetch calls:', calls, 'datalist options:', els.cityList.children.map((o) => o.value));
  expect(calls.length).toBe(1);
  expect(els.cityList.children.length).toBeGreaterThan(0);
});
