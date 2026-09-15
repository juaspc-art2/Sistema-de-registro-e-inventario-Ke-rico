# Guía de instalación

## 1. Qué necesita

| Programa | Versión mínima | Para qué |
|---|---|---|
| XAMPP (Apache + MySQL/MariaDB + PHP) | PHP 8.0 | Servir la API y alojar la base de datos |
| Node.js | 20 | Solo si va a compilar el frontend usted mismo |

XAMPP trae PHP, Apache y MariaDB juntos; con eso basta para poner el sistema a funcionar.
Las extensiones que usa el backend (`pdo_mysql`, `mbstring`, `zip`, `json`) vienen
activadas por defecto en XAMPP.

---

## 2. Crear la base de datos

1. Inicie **XAMPP Control Panel** y pulse **Start** en **Apache** y en **MySQL**.
2. Abra <http://localhost/phpmyadmin>.
3. Vaya a la pestaña **Importar**.
4. Pulse **Seleccionar archivo** y elija `database/kerico.sql`.
5. Pulse **Continuar**.

El script borra la base `kerico` si existe, la vuelve a crear y carga:

- 20 tablas y 4 vistas de consulta
- 4 roles y 22 permisos
- 4 usuarios de prueba
- 6 categorías, 12 proveedores y 23 productos del menú real
- 8 compras, 22 ventas con su detalle y sus comprobantes
- 94 movimientos de inventario, 7 alertas y la bitácora inicial

Si prefiere la consola:

```bash
mysql -u root < database/kerico.sql
```

### Volver a sembrar la base

`database/semilla.sql` borra todos los registros y vuelve a insertar los datos de
arranque, sin tocar el esquema ni las vistas. Sirve para dejar el sistema como recién
instalado después de hacer pruebas. Se puede ejecutar las veces que haga falta.

```bash
mysql -u root < database/semilla.sql
```

Requiere que la base `kerico` ya exista; si no, ejecute antes `kerico.sql`.

---

## 3. Publicar el backend

Copie la carpeta `backend/` a `C:\xampp\htdocs\` y renómbrela `kerico-api`.

Debe quedar así:

```
C:\xampp\htdocs\kerico-api\index.php
C:\xampp\htdocs\kerico-api\config\
C:\xampp\htdocs\kerico-api\modelo\
...
```

Compruebe abriendo <http://localhost/kerico-api/>. Debe responder:

```json
{"ok":true,"datos":{"nombre":"API del Sistema de Registro e Inventario Ke-Rico!","version":"1.0.0","estado":"en línea"}}
```

### Si su MySQL tiene contraseña

Por defecto el sistema se conecta como `root` sin contraseña, que es la configuración de
fábrica de XAMPP. Si su servidor usa otros datos, defina estas variables de entorno antes
de iniciar Apache:

| Variable | Valor por defecto |
|---|---|
| `KERICO_DB_HOST` | `127.0.0.1` |
| `KERICO_DB_PORT` | `3306` |
| `KERICO_DB_NAME` | `kerico` |
| `KERICO_DB_USER` | `root` |
| `KERICO_DB_PASS` | *(vacío)* |

En XAMPP se agregan en `C:\xampp\apache\conf\httpd.conf`:

```apache
SetEnv KERICO_DB_USER root
SetEnv KERICO_DB_PASS su_contrasena
```

Reinicie Apache después de guardar.

---

## 4. Publicar el frontend

La carpeta `frontend/dist/` ya viene compilada. Copie **su contenido** a
`C:\xampp\htdocs\kerico\`:

```
C:\xampp\htdocs\kerico\index.html
C:\xampp\htdocs\kerico\config.js
C:\xampp\htdocs\kerico\assets\
C:\xampp\htdocs\kerico\marca\
```

Abra `C:\xampp\htdocs\kerico\config.js` y deje la ruta de la API:

```js
window.KERICO_API = '/kerico-api/api';
```

Esa es la única línea que cambia si usted renombra las carpetas.

Entre a <http://localhost/kerico/> e inicie sesión con `admin` / `123`.

### Volver a compilar el frontend

Solo si modifica el código de la vista:

```bash
cd frontend
npm install
npm run build
```

El resultado queda en `frontend/dist/`.

---

## 5. Modo desarrollo

Útil para trabajar sobre el código sin copiar archivos cada vez.

**Terminal 1 — API:**

```bash
cd backend
php -S 127.0.0.1:8199 servidor.php
```

**Terminal 2 — Vista:**

```bash
cd frontend
npm install
npm run dev
```

Abra <http://localhost:5173>. Vite redirige `/api` al servidor PHP automáticamente.

Para ver los errores completos del backend durante el desarrollo:

```bash
set KERICO_DEBUG=1
```

---

## 6. Respaldo automático cada 24 horas

El sistema genera respaldos desde `Administración → Respaldos`, y también por consola:

```bash
php backend/tareas/respaldo.php
```

El script solo actúa si han pasado más de 24 horas desde el último respaldo correcto.
Para forzarlo:

```bash
php backend/tareas/respaldo.php --forzar
```

### Programarlo en Windows

1. Abra el **Programador de tareas**.
2. **Crear tarea básica** → nombre `Respaldo Ke-Rico`.
3. Desencadenador: **Diariamente**, a la hora de cierre del local.
4. Acción: **Iniciar un programa**
   - Programa: `C:\xampp\php\php.exe`
   - Argumentos: `C:\xampp\htdocs\kerico-api\tareas\respaldo.php`
5. Finalizar.

Los archivos quedan en `backend/respaldos/` y se descargan desde la pantalla de
Administración. Esa carpeta está protegida con un `.htaccess` que impide leerla
directamente desde el navegador.

### Restaurar un respaldo

```bash
mysql -u root kerico < backend/respaldos/kerico-20260914-020000.sql
```

---

## 7. Problemas frecuentes

**«No hay conexión con el servidor»**
Apache o MySQL están apagados. Ábralos desde el XAMPP Control Panel.

**La página carga pero todo sale vacío**
La ruta de `config.js` no coincide con la carpeta del backend. Abra la consola del
navegador (F12); si ve errores 404 sobre `/api`, corrija `window.KERICO_API`.

**«Access denied for user 'root'»**
Su MySQL tiene contraseña. Siga el apartado *Si su MySQL tiene contraseña* del punto 3.
Si nunca le puso contraseña a XAMPP, lo más probable es que esté hablando con **otro**
servidor MySQL instalado aparte. Vea el punto siguiente.

**El MySQL de XAMPP no arranca, o arranca y la aplicación no conecta**

Otro servidor MySQL ya ocupa el puerto 3306. Es frecuente cuando la máquina tiene
instalado MySQL Server o MySQL Workbench por separado. Para comprobarlo, en PowerShell:

```powershell
Get-NetTCPConnection -LocalPort 3306 -State Listen | ForEach-Object { (Get-Process -Id $_.OwningProcess).Path }
```

Si la ruta no es `C:\xampp\mysql\bin\mysqld.exe`, hay conflicto. Tiene dos salidas:

*Opción A — dejar XAMPP en otro puerto (no toca el otro servidor):*

1. Abra `C:\xampp\mysql\bin\my.ini` y cambie las dos líneas `port=3306` por `port=3307`.
2. En `C:\xampp\phpMyAdmin\config.inc.php`, debajo de la línea del `host`, agregue:
   ```php
   $cfg['Servers'][$i]['port'] = '3307';
   ```
3. Al final de `C:\xampp\apache\conf\httpd.conf` agregue:
   ```apache
   SetEnv KERICO_DB_PORT 3307
   SetEnv KERICO_DB_HOST 127.0.0.1
   ```
4. Reinicie Apache y MySQL desde el panel de XAMPP.

*Opción B — liberar el 3306:* detenga el servicio del otro servidor desde
**Servicios** de Windows y póngalo en inicio manual. Así XAMPP toma el 3306 y no hace
falta ninguna de las líneas anteriores.

**PHP no carga ninguna extensión: «Call to undefined function mb_strlen» o «could not find driver»**

Ocurre cuando en la máquina hay **otra instalación de PHP** (por ejemplo vía scoop,
Laragon o Composer) que dejó la variable de entorno `PHP_INI_SCAN_DIR` apuntando a su
propia carpeta. El PHP de XAMPP carga primero su `php.ini` correcto y después el del otro
PHP, que sobrescribe `extension_dir` con una ruta relativa, y entonces ninguna extensión
carga.

Compruébelo abriendo `http://localhost/kerico-api/` y, si falla, creando un archivo
temporal `C:\xampp\htdocs\revisar.php`:

```php
<?php
echo php_ini_loaded_file(), PHP_EOL;
echo php_ini_scanned_files() ?: 'ninguno', PHP_EOL;
echo ini_get('extension_dir'), PHP_EOL;
```

Si la segunda línea muestra una ruta fuera de `C:\xampp`, esa es la causa. Para corregirlo:

```powershell
[Environment]::SetEnvironmentVariable('PHP_INI_SCAN_DIR', $null, 'User')
[Environment]::SetEnvironmentVariable('PHP_INI_SCAN_DIR', $null, 'Machine')
```

Cierre sesión en Windows o reinicie, y vuelva a iniciar Apache. La tercera línea debe
decir `C:\xampp\php\ext`. Borre el archivo `revisar.php` cuando termine.

**Al editar `php.ini` dejó de funcionar todo**

Si guardó el archivo con un editor que agrega BOM (la marca invisible `EF BB BF` al
inicio), PHP deja de leer la sección `[PHP]` y con ella todas las líneas `extension=`.
Guarde `php.ini` siempre como **UTF-8 sin BOM** o como ANSI. Lo mismo vale para
`config.js`.

**Las rutas de la API devuelven 404**
Falta el módulo de reescritura de Apache. Abra `httpd.conf`, quite el `#` de la línea
`LoadModule rewrite_module modules/mod_rewrite.so`, asegúrese de que el bloque
`<Directory "C:/xampp/htdocs">` tenga `AllowOverride All`, y reinicie Apache.

**«Token de seguridad inválido»**
La sesión se reinició en otra pestaña. Recargue la página y vuelva a entrar.

**Cuenta bloqueada**
Cinco intentos fallidos bloquean la cuenta 15 minutos. Otro administrador puede
desbloquearla desde `Administración → Usuarios`, o puede esperar a que venza.

**Se cerró la sesión sola**
Es el comportamiento esperado tras 15 minutos sin actividad. El tiempo se cambia en
`Administración → Configuración`, campo `sesion_minutos_inactividad`.
