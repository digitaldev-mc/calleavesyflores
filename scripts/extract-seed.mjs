import fs from 'fs';

const s = fs.readFileSync(new URL('../setup.php', import.meta.url), 'utf8');

function extractPhpArray(afterMarker) {
  const start = s.indexOf(afterMarker);
  if (start < 0) throw new Error(`Marker not found: ${afterMarker}`);
  const arrStart = start + afterMarker.length;
  let depth = 0;
  let inStr = false;
  let strCh = '';
  let esc = false;
  for (let i = arrStart; i < s.length; i++) {
    const c = s[i];
    if (esc) {
      esc = false;
      continue;
    }
    if (inStr) {
      if (c === '\\') {
        esc = true;
        continue;
      }
      if (c === strCh) inStr = false;
      continue;
    }
    if (c === "'" || c === '"') {
      inStr = true;
      strCh = c;
      continue;
    }
    if (c === '[') depth++;
    if (c === ']') {
      depth--;
      if (depth === 0) return s.slice(arrStart, i + 1);
    }
  }
  throw new Error(`Unclosed array after ${afterMarker}`);
}

const condMatch = s.match(/\$cond = "([\s\S]*?)";\r?\n\s*cfg_set\('admin_hash'/);
if (!condMatch) throw new Error('cond not found');

const muralsInner = extractPhpArray('$murales = ');

const resenaMatches = [...s.matchAll(/\$r->execute\(\[([\s\S]*?)\]\);/g)];
const resenas = resenaMatches.slice(0, 3).map((m) => {
  const inner = m[1];
  const parts = [];
  let cur = '';
  let inStr = false;
  let esc = false;
  for (let i = 0; i < inner.length; i++) {
    const c = inner[i];
    if (esc) {
      cur += c;
      esc = false;
      continue;
    }
    if (c === '\\') {
      cur += c;
      esc = true;
      continue;
    }
    if (c === "'") {
      inStr = !inStr;
      cur += c;
      continue;
    }
    if (!inStr && c === ',') {
      parts.push(cur.trim());
      cur = '';
      continue;
    }
    cur += c;
  }
  if (cur.trim()) parts.push(cur.trim());
  return parts.map((p) => {
    p = p.trim();
    if (/^\d+$/.test(p)) return parseInt(p, 10);
    if (p.startsWith("'") && p.endsWith("'")) {
      return p.slice(1, -1).replace(/\\'/g, "'").replace(/\\\\/g, '\\');
    }
    return p;
  });
});

const seedContent = `<?php
if (!defined('AVES_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

return [
    'condiciones' => ${JSON.stringify(condMatch[1])},
    'murales' => ${muralsInner},
    'resenas' => ${JSON.stringify(resenas, null, 4)},
    'codigos' => [
        ['MZLSCOMPARTE', 10],
        ['AVESYFLORES', 5],
    ],
];
`;

fs.writeFileSync(new URL('../seed_data.php', import.meta.url), seedContent);
console.log('seed_data.php OK', seedContent.length, 'bytes, murals end check:', muralsInner.endsWith(']'));
