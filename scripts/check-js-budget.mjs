/**
 * Presupuesto de JavaScript de las páginas públicas: < 100 KB gzip (ARQUITECTURA.md §6.6).
 * Corre después de `vite build`; si se supera, el build falla (exit 1).
 *
 * Mide el peor caso: el entry público más TODO lo que puede cargar, incluidos los
 * chunks dinámicos de las islas, como si una página las montara todas a la vez.
 * Los assets de Filament (public/js/filament) no cuentan: son del backoffice.
 */
import { readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';

const BUDGET_BYTES = 100 * 1024;
const PUBLIC_ENTRY = 'resources/js/app.ts';

const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));

const seen = new Set();
function collect(key) {
    const chunk = manifest[key];
    if (!chunk || seen.has(key)) {
        return;
    }
    seen.add(key);
    for (const dep of [...(chunk.imports ?? []), ...(chunk.dynamicImports ?? [])]) {
        collect(dep);
    }
}
collect(PUBLIC_ENTRY);

const rows = [...seen]
    .map((key) => manifest[key].file)
    .filter((file) => file.endsWith('.js'))
    .map((file) => ({ file, gzip: gzipSync(readFileSync(`public/build/${file}`)).length }))
    .sort((a, b) => b.gzip - a.gzip);

const total = rows.reduce((sum, row) => sum + row.gzip, 0);
const kb = (bytes) => `${(bytes / 1024).toFixed(1)} KB`;

console.log('\nPresupuesto JS público (gzip, peor caso):');
for (const { file, gzip } of rows) {
    console.log(`  ${kb(gzip).padStart(9)}  ${file}`);
}
console.log(`  ${'-'.repeat(9)}\n  ${kb(total).padStart(9)}  total / límite ${kb(BUDGET_BYTES)}\n`);

if (total > BUDGET_BYTES) {
    console.error(`✗ Presupuesto de JS superado en ${kb(total - BUDGET_BYTES)}.`);
    process.exit(1);
}
console.log('✓ Dentro del presupuesto.');
