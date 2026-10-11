// Prueba el cliente SMTP del backend contra un servidor falso en este mismo equipo.
// No necesita Apache ni MySQL, y ningún correo sale a Internet.
import { execFile } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import net from 'node:net';
import os from 'node:os';
import path from 'node:path';

const PHP = process.env.KERICO_PHP || 'C:/xampp/php/php.exe';
const RAIZ = path.resolve(import.meta.dirname, '..');

let pasadas = 0, fallidas = 0;
function ok(nombre, cond, extra = '') {
  if (cond) { pasadas++; console.log('  PASS  ' + nombre); }
  else { fallidas++; console.log('  FALL  ' + nombre + (extra ? ' :: ' + extra : '')); }
}

// Servidor SMTP mínimo: anota las órdenes y el mensaje que recibe
function servidorFalso({ rechazarDestino = false } = {}) {
  const visto = { ordenes: [], mensaje: '' };
  const servidor = net.createServer((s) => {
    let enDatos = false;
    let bufer = '';
    s.write('220 falso ESMTP\r\n');
    // El cliente cuelga apenas se despide: ese corte no es un fallo de la prueba
    s.on('error', () => {});
    s.on('data', (trozo) => {
      bufer += trozo.toString('latin1');
      for (;;) {
        if (enDatos) {
          const fin = bufer.indexOf('\r\n.\r\n');
          if (fin < 0) return;
          visto.mensaje = bufer.slice(0, fin + 2);
          bufer = bufer.slice(fin + 5);
          enDatos = false;
          s.write('250 recibido\r\n');
          continue;
        }
        const salto = bufer.indexOf('\r\n');
        if (salto < 0) return;
        const linea = bufer.slice(0, salto);
        bufer = bufer.slice(salto + 2);
        visto.ordenes.push(linea);
        const orden = linea.slice(0, 4).toUpperCase();
        if (orden === 'EHLO') s.write('250-falso\r\n250 8BITMIME\r\n');
        else if (orden === 'RCPT' && rechazarDestino) s.write('550 buzon inexistente\r\n');
        else if (orden === 'DATA') { enDatos = true; s.write('354 adelante\r\n'); }
        else if (orden === 'QUIT') s.end('221 adios\r\n');
        else s.write('250 ok\r\n');
      }
    });
  });
  return new Promise((listo) => {
    servidor.listen(0, '127.0.0.1', () => listo({ servidor, visto, puerto: servidor.address().port }));
  });
}

// Ajustes consulta la base de datos: aquí se reemplaza por un doble para probar solo el correo
const guion = path.join(os.tmpdir(), 'kerico-correo-' + process.pid + '.php');
fs.writeFileSync(guion, `<?php
namespace Kerico\\Nucleo;
final class Ajustes {
    public static function emisor(): array {
        return ['nombre' => 'Ke-Rico!', 'direccion' => 'Cra. 59 # 132A - 7', 'telefono' => '317 796 5569'];
    }
}
require $argv[1] . '/backend/nucleo/ErrorHttp.php';
require $argv[1] . '/backend/nucleo/Correo.php';
$caso = json_decode($argv[2], true);
Correo::configurar($caso['config']);
try {
    Correo::enviar($caso['para'], $caso['asunto'], $caso['cuerpo'] ?? $caso['texto'], isset($caso['adjunto'])
        ? ['nombre' => 'reporte ventas.pdf', 'tipo' => 'application/pdf', 'contenido' => base64_decode($caso['adjunto'])]
        : null, $caso['firmante'] ?? null);
    echo 'OK';
} catch (\\Throwable $e) {
    echo 'ERROR ' . $e->getMessage();
}
`);

function enviar(caso) {
  return new Promise((listo) => {
    execFile(PHP, [guion, RAIZ, JSON.stringify(caso)], { encoding: 'utf8' }, (error, salida) => listo(error ? 'FALLO ' + error.message : salida));
  });
}

const config = (puerto, extra = {}) => ({ host: '127.0.0.1', puerto, usuario: '', password: '', remitente: 'ventas@kerico.test', ...extra });
const decodificar = (texto) => texto.replace(/=\?UTF-8\?B\?([^?]+)\?=/g, (_, b) => Buffer.from(b, 'base64').toString('utf8'));
const parte = (mensaje, marca) => {
  const cuerpo = mensaje.slice(mensaje.indexOf(marca));
  const inicio = cuerpo.indexOf('\r\n\r\n') + 4;
  const fin = cuerpo.indexOf('\r\n--', inicio);
  return Buffer.from(cuerpo.slice(inicio, fin < 0 ? undefined : fin).replace(/\r\n/g, ''), 'base64');
};

console.log('\n=== Envío con adjunto ===');
// Bytes al azar más líneas que empiezan por punto: el caso que rompe un cliente SMTP descuidado
const adjunto = Buffer.concat([crypto.randomBytes(5000), Buffer.from('\r\n.\r\n..\r\n.fin')]);
let s = await servidorFalso();
let salida = await enviar({
  config: config(s.puerto), para: 'cliente@correo.test, copia@correo.test',
  asunto: 'Tirilla POS N.º KR-00000012', texto: 'Gracias por su compra.\nAtención: ñ, á, ü.', adjunto: adjunto.toString('base64'),
});
s.servidor.close();
ok('el envío termina bien', salida === 'OK', salida);
ok('declara el remitente', s.visto.ordenes.includes('MAIL FROM:<ventas@kerico.test>'), s.visto.ordenes.join(' | '));
ok('declara cada destinatario', s.visto.ordenes.includes('RCPT TO:<cliente@correo.test>') && s.visto.ordenes.includes('RCPT TO:<copia@correo.test>'));
const asunto = decodificar((s.visto.mensaje.match(/^Subject: ((?:.|\r\n )*)/m) || [])[1] || '').replace(/\r\n /g, '');
ok('el asunto conserva tildes y lleva el nombre del negocio', asunto === 'Ke-Rico! · Tirilla POS N.º KR-00000012', asunto);
ok('el texto llega en UTF-8 con su pie', parte(s.visto.mensaje, 'Content-Type: text/plain').toString('utf8')
  .startsWith('Gracias por su compra.\nAtención: ñ, á, ü.\n\n-- \nKe-Rico!'));
ok('el adjunto llega byte a byte', parte(s.visto.mensaje, 'Content-Disposition: attachment').equals(adjunto));
ok('el nombre del adjunto queda saneado', s.visto.mensaje.includes('filename="reporte_ventas.pdf"'));
ok('ninguna línea supera el límite de SMTP', s.visto.mensaje.split('\r\n').every((l) => l.length <= 998));

console.log('\n=== Versión HTML con la identidad de la marca ===');
s = await servidorFalso();
salida = await enviar({
  config: config(s.puerto), para: 'a@b.test', asunto: 'Prueba <b>', firmante: { nombre: 'Ana Ruiz', cargo: 'Cajera' },
  cuerpo: ['Hola <script>alert(1)</script>', { datos: { Total: '$62.050' } }, { codigo: 'abc123' }],
});
s.servidor.close();
const html = parte(s.visto.mensaje, 'Content-Type: text/html').toString('utf8');
ok('el mensaje trae HTML y texto plano como alternativas', salida === 'OK' && html.startsWith('<!doctype html>')
  && s.visto.mensaje.includes('multipart/alternative'), salida);
ok('el HTML escapa lo que venga en los datos', !html.includes('<script>') && html.includes('&lt;script&gt;') && html.includes('Prueba &lt;b&gt;'));
ok('usa los colores del manual y firma quien envía', html.includes('#eb5e15') && html.includes('#edca34')
  && html.includes('Ana Ruiz') && html.includes('Cajera'));
ok('el logotipo viaja dentro del mensaje', html.includes('cid:logo-kerico')
  && parte(s.visto.mensaje, 'Content-ID: <logo-kerico>').subarray(1, 4).toString() === 'PNG');
ok('el texto plano conserva datos, código y firma', parte(s.visto.mensaje, 'Content-Type: text/plain').toString('utf8')
  === 'Hola <script>alert(1)</script>\n\nTotal: $62.050\n\nabc123\n\n-- \nAna Ruiz\nCajera\n317 796 5569\nCra. 59 # 132A - 7');

console.log('\n=== Redacción de las alertas ===');
const guionUnidad = path.join(os.tmpdir(), 'kerico-unidad-' + process.pid + '.php');
fs.writeFileSync(guionUnidad, `<?php
require $argv[1] . '/backend/modelo/Inventario.php';
echo json_encode(array_map(fn (array $c): string => \\Kerico\\Modelo\\Inventario::conUnidad((float) $c[0], $c[1]), json_decode($argv[2], true)));
`);
const plurales = await new Promise((listo) => {
  execFile(PHP, [guionUnidad, RAIZ, JSON.stringify([[49, 'unidad'], [1, 'unidad'], [8, 'kg'], [17, 'litro'], [4, 'porción']])],
    { encoding: 'utf8' }, (error, salidaPhp) => listo(error ? error.message : salidaPhp));
});
fs.unlinkSync(guionUnidad);
ok('las cantidades llevan la unidad en plural, salvo las abreviaturas',
  plurales === JSON.stringify(['49 unidades', '1 unidad', '8 kg', '17 litros', '4 porciones']), plurales);

console.log('\n=== Fallos ===');
s = await servidorFalso();
salida = await enviar({ config: config(s.puerto, { usuario: 'ventas@kerico.test', password: 'secreta' }), para: 'a@b.test', asunto: 'x', texto: 'x' });
s.servidor.close();
ok('sin TLS no entrega las credenciales', salida.startsWith('ERROR') && salida.includes('TLS') && !s.visto.ordenes.some((o) => o.startsWith('AUTH')), salida);
ok('y tampoco envía el mensaje', s.visto.mensaje === '');

s = await servidorFalso({ rechazarDestino: true });
salida = await enviar({ config: config(s.puerto), para: 'nadie@correo.test', asunto: 'x', texto: 'x' });
s.servidor.close();
ok('informa el rechazo del servidor', salida.includes('550 buzon inexistente'), salida);

salida = await enviar({ config: config(1), para: 'no-es-un-correo', asunto: 'x', texto: 'x' });
ok('rechaza una dirección mal escrita antes de conectar', salida.startsWith('ERROR') && salida.includes('formato'), salida);

salida = await enviar({ config: config(1), para: 'a@b.test', asunto: 'x', texto: 'x' });
ok('avisa cuando el servidor no responde', salida.startsWith('ERROR No fue posible conectar'), salida);

salida = await enviar({ config: { ...config(1), host: '' }, para: 'a@b.test', asunto: 'x', texto: 'x' });
ok('avisa cuando el correo no está configurado', salida.includes('no está configurado'), salida);

fs.unlinkSync(guion);
console.log('\n================================');
console.log('PASADAS: ' + pasadas + '   FALLIDAS: ' + fallidas);
console.log('================================');
process.exit(fallidas > 0 ? 1 : 0);
