import fs from 'node:fs';
import path from 'node:path';

const raiz = path.resolve(path.dirname(new URL(import.meta.url).pathname.slice(1)), '..');
const destino = path.join(raiz, 'publicar');
const origenFront = path.join(raiz, 'frontend', 'dist');
const origenApi = path.join(raiz, 'backend');

const EXCLUIDOS = new Set(['respaldos', '.bak', 'node_modules', '.git']);

function copiar(desde, hacia, filtro) {
  fs.mkdirSync(hacia, { recursive: true });
  for (const entrada of fs.readdirSync(desde, { withFileTypes: true })) {
    if (EXCLUIDOS.has(entrada.name)) {
      continue;
    }
    if (filtro && !filtro(entrada.name)) {
      continue;
    }
    const a = path.join(desde, entrada.name);
    const b = path.join(hacia, entrada.name);
    if (entrada.isDirectory()) {
      copiar(a, b, null);
    } else {
      fs.copyFileSync(a, b);
    }
  }
}

function listar(dir, base = dir) {
  const salida = [];
  for (const entrada of fs.readdirSync(dir, { withFileTypes: true })) {
    const completo = path.join(dir, entrada.name);
    if (entrada.isDirectory()) {
      salida.push(...listar(completo, base));
    } else {
      salida.push(path.relative(base, completo).split(path.sep).join('/'));
    }
  }
  return salida.sort();
}

if (!fs.existsSync(origenFront)) {
  console.error('Falta frontend/dist. Ejecute "npm run build" dentro de frontend/.');
  process.exit(1);
}

const verificar = process.argv.includes('--verificar');

if (verificar) {
  if (!fs.existsSync(destino)) {
    console.error('No existe publicar/. Ejecute: node herramientas/publicar.mjs');
    process.exit(1);
  }
  const temporal = destino + '-verificacion';
  fs.rmSync(temporal, { recursive: true, force: true });
  copiar(origenFront, temporal, null);
  copiar(origenApi, path.join(temporal, 'api'), null);
  fs.mkdirSync(path.join(temporal, 'api', 'respaldos'), { recursive: true });
  fs.writeFileSync(path.join(temporal, 'api', 'respaldos', '.gitkeep'), '');

  const actuales = listar(destino);
  const esperados = listar(temporal);
  const problemas = [];

  for (const archivo of esperados) {
    if (!actuales.includes(archivo)) {
      problemas.push('falta en publicar/: ' + archivo);
      continue;
    }
    const uno = fs.readFileSync(path.join(temporal, archivo));
    const otro = fs.readFileSync(path.join(destino, archivo));
    if (!uno.equals(otro)) {
      problemas.push('desactualizado: ' + archivo);
    }
  }
  for (const archivo of actuales) {
    if (!esperados.includes(archivo)) {
      problemas.push('sobra en publicar/: ' + archivo);
    }
  }

  fs.rmSync(temporal, { recursive: true, force: true });

  if (problemas.length > 0) {
    console.error('publicar/ no coincide con el codigo fuente:');
    for (const problema of problemas) {
      console.error('  ' + problema);
    }
    console.error('Regenere con: node herramientas/publicar.mjs');
    process.exit(1);
  }
  console.log('publicar/ esta al dia (' + actuales.length + ' archivos).');
  process.exit(0);
}

fs.rmSync(destino, { recursive: true, force: true });
copiar(origenFront, destino, null);
copiar(origenApi, path.join(destino, 'api'), null);
fs.mkdirSync(path.join(destino, 'api', 'respaldos'), { recursive: true });
fs.writeFileSync(path.join(destino, 'api', 'respaldos', '.gitkeep'), '');

const archivos = listar(destino);
console.log('Carpeta publicar/ generada con ' + archivos.length + ' archivos.');
console.log('Copie publicar/ dentro de htdocs con el nombre que prefiera.');
