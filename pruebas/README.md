# Pruebas automatizadas

Dos suites que verifican el sistema completo contra la API real y la base de datos real.
No usan datos simulados: registran ventas, mueven inventario, generan PDF y consultan
MySQL para comprobar lo que quedó guardado.

| Archivo | Qué comprueba | Casos |
|---|---|---|
| `requerimientos.mjs` | Los nueve requerimientos funcionales y los quince no funcionales | 138 |
| `revision.mjs` | La lista de verificación de la revisión de la aplicación | 176 |

## Antes de ejecutar

1. Apache y MySQL encendidos.
2. Base de datos recién cargada desde `database/kerico.sql`.
3. Backend publicado y respondiendo.

Las suites modifican datos. Vuelva a importar `database/kerico.sql` antes de cada corrida
para partir siempre del mismo estado.

## Ejecutar

```bash
node pruebas/requerimientos.mjs
node pruebas/revision.mjs
```

## Ajustes por entorno

Las rutas por defecto asumen XAMPP. Si su instalación es distinta, defina las variables
antes de ejecutar:

| Variable | Valor por defecto |
|---|---|
| `KERICO_API` | `http://localhost/kerico-api` |
| `KERICO_MYSQL` | `C:/xampp/mysql/bin/mysql.exe` |
| `KERICO_DB_USER` | `root` |
| `KERICO_DB_PORT` | `3306` |

Ejemplo en modo desarrollo, con el servidor de PHP en el puerto 8199:

```bash
set KERICO_API=http://127.0.0.1:8199
node pruebas/requerimientos.mjs
```

## Qué cubre `revision.mjs`

1. **Autenticación, roles y sesiones** — cifrado bcrypt de las claves, acceso denegado a
   rutas fuera del rol, caducidad a los 15 minutos y destrucción del token al salir.
2. **Dashboard** — cada indicador se compara contra una consulta SQL directa, y se
   comprueba que las gráficas traigan datos agregados.
3. **CRUD** — validación en el servidor, paginación, filtros de texto, ordenamiento
   ascendente y descendente, precarga del formulario de edición y borrado lógico que
   conserva la fila en la base de datos.
4. **Informes** — filtro por rango de fechas, PDF con encabezado, tabla y numeración de
   páginas, y CSV delimitado con BOM UTF-8 que respeta las tildes.
5. **Seguridad** — seis cargas de inyección SQL, inyección de etiquetas `<script>`,
   rechazo de peticiones sin token CSRF, estructura MVC, diseño adaptable y tiempos de
   respuesta por debajo de dos segundos.

## Resultado esperado

```
PASADAS: 138   FALLIDAS: 0
PASADAS: 176   FALLIDAS: 0
```
