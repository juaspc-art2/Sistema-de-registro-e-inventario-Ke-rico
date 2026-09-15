# Sistema de Registro e Inventario · Ke-Rico!

Sistema web para la gestión de ventas, inventario, comprobantes, proveedores y rentabilidad
del negocio de empanadas y fritos **Ke-Rico!** (Cra. 59 # 132A - 7, Suba, Bogotá D.C.).

Construido sobre el patrón **Modelo–Vista–Controlador**: una API REST en PHP 8 + MySQL/MariaDB
como modelo y controlador, y una aplicación React 19 como vista.

---

## Arranque rápido (XAMPP)

1. Instale [XAMPP](https://www.apachefriends.org/) e inicie **Apache** y **MySQL**.
2. Abra <http://localhost/phpmyadmin>, pestaña **Importar**, y cargue
   `database/kerico.sql`. El archivo crea la base `kerico`, todas las tablas, las vistas
   y los datos de arranque.
   Para devolver la base a su estado inicial más adelante, sin recrear el esquema,
   ejecute `database/semilla.sql`.
3. Copie la carpeta `backend/` dentro de `C:\xampp\htdocs\` y renómbrela `kerico-api`.
4. Copie el contenido de `frontend/dist/` dentro de `C:\xampp\htdocs\kerico\`.
5. Edite `C:\xampp\htdocs\kerico\config.js` y deje:
   ```js
   window.KERICO_API = '/kerico-api/api';
   ```
6. Entre a <http://localhost/kerico/>.

**Usuario:** `admin` · **Contraseña:** `123`

Los pasos detallados, el modo desarrollo y la solución de problemas están en
[INSTALACION.md](INSTALACION.md).

---

## Cuentas de prueba

Las cuatro cuentas existen para demostrar el control de acceso por rol. Todas usan la
contraseña `123` y su hash se guarda cifrado con bcrypt en la base de datos.

| Usuario | Rol | Qué puede hacer |
|---|---|---|
| `admin` | Administrador | Todo: reportes, precios, usuarios, respaldos, configuración |
| `gerente` | Gerente | Reportes, rentabilidad, proveedores, promociones, bitácora |
| `cajero` | Cajero | Registrar ventas, emitir comprobantes, aplicar códigos de descuento |
| `inventario` | Inventario | Productos, ajustes de stock, compras, alertas |

---

## Estructura del repositorio

```
├── database/
│   ├── kerico.sql              Script único: esquema + vistas + datos de arranque
│   └── semilla.sql             Solo datos: vacía las tablas y vuelve a sembrarlas
├── backend/                    API REST en PHP 8 (modelo y controlador)
│   ├── index.php               Controlador frontal y tabla de rutas
│   ├── config/config.php       Conexión y parámetros (leídos de variables de entorno)
│   ├── nucleo/                 Autenticación, enrutador, validación, exportadores, PDF
│   ├── modelo/                 Lógica de negocio y acceso a datos
│   ├── controlador/            Un controlador por módulo
│   ├── tareas/respaldo.php     Respaldo automático programable
│   └── respaldos/              Destino de los archivos .sql generados
├── frontend/                   Aplicación React 19 + Vite 8 (vista)
│   ├── src/componentes/        Una pantalla por módulo
│   ├── src/ui/Componentes.jsx  Biblioteca de componentes de interfaz
│   ├── src/contexto/Sesion.jsx Sesión, permisos y cierre por inactividad
│   ├── src/ganchos/            Listados con paginación, filtros y orden
│   └── src/api.js              Cliente HTTP con token y CSRF
├── docs/                       Requerimientos, matriz del proyecto y manual de identidad
└── diseno/                     Tablero de referencia y landing de la marca
```

---

## Requerimientos funcionales

| Código | Requerimiento | Dónde está |
|---|---|---|
| RF1 | Gestión de ventas y stock | `Ventas y stock` · descuenta existencias en la misma transacción |
| RF2 | Emisión de comprobantes digitales | `Comprobantes` · folio único, impuestos, almacenado y descargable |
| RF3 | Aplicación de descuentos y promociones | `Descuentos` · valida permiso, guarda motivo y valor antes/después |
| RF4 | Alertas automáticas de stock mínimo | `Alertas de stock` · punto de reorden configurable por producto |
| RF5 | Auditoría de movimientos de inventario | `Auditoría` · quién, qué, por qué, stock antes y después |
| RF6 | Gestión de proveedores | `Proveedores` · NIT, contacto, suministros y compras asociadas |
| RF7 | Análisis de rentabilidad | `Rentabilidad` · cruza costo de compra contra precio de venta |
| RF8 | Registro de actividad (logs) | `Registro de actividad` · bitácora completa con nivel e IP |
| RF9 | Inicio de sesión | Pantalla de acceso · bcrypt, bloqueo por intentos, recuperación |

## Requerimientos no funcionales

| Código | Atributo | Cómo se cumple |
|---|---|---|
| RNF1 | Rendimiento | Venta y actualización de stock en una transacción, muy por debajo de 3 s |
| RNF2 | Rendimiento | Reportes mensuales con índices y vistas, por debajo de 10 s |
| RNF3 | Rendimiento | Sesiones independientes por token; varios usuarios en paralelo |
| RNF4 | Seguridad | Permisos por rol verificados en el servidor en cada ruta |
| RNF5 | Seguridad | Contraseñas con bcrypt; el hash nunca sale en las respuestas |
| RNF6 | Seguridad | Cierre automático de sesión a los 15 minutos de inactividad |
| RNF7 | Seguridad | Respaldo completo en `.sql`, manual o programado cada 24 horas |
| RNF8 | Fiabilidad | Estado del sistema consultable; errores registrados en bitácora |
| RNF9 | Fiabilidad | Restauración desde cualquier respaldo generado |
| RNF10 | Fiabilidad | Sin ventas negativas ni stock inexistente: validado en PHP y en la base |
| RNF11 | Usabilidad | Punto de venta de un solo toque por producto |
| RNF12 | Usabilidad | Diseño adaptable a móvil, tableta y escritorio |
| RNF13 | Usabilidad | Paleta y tipografía del Manual de Identidad Corporativa |
| RNF14 | Portabilidad | Exportación en PDF, Excel y CSV |
| RNF15 | Portabilidad | Métodos de pago configurables desde el catálogo de la venta |

---

## Seguridad

- **Contraseñas:** bcrypt con coste 10 o superior; rehash automático al iniciar sesión.
- **Acceso por rol:** cada ruta declara el permiso que exige; el intento denegado queda
  en la bitácora con el usuario y la IP.
- **Sesiones:** token aleatorio de 256 bits, caducidad por inactividad, cierre inmediato
  al desactivar o eliminar la cuenta.
- **Inyección SQL:** todas las consultas usan sentencias preparadas; las columnas de
  ordenamiento se validan contra una lista blanca.
- **XSS:** la entrada se sanea en el servidor y React escapa toda la salida.
- **CSRF:** cada sesión recibe un token propio que el cliente envía en la cabecera
  `X-CSRF-Kerico`; toda petición `POST`, `PUT` o `DELETE` sin token válido se rechaza.
- **CSV:** las celdas que empiezan por `=`, `+`, `-` o `@` se neutralizan para evitar
  ejecución de fórmulas al abrir el archivo.

---

## Borrado lógico

Productos, proveedores, usuarios, clientes, categorías y promociones nunca se borran de
la base de datos: se marca la columna `deleted_at` y el registro desaparece de los
listados. Cada módulo tiene su **Papelera** para restaurarlos, y el historial de ventas,
compras y movimientos permanece intacto.

---

## Impuestos

Los precios del catálogo son **precios finales al público**: ya incluyen el impuesto.
El sistema separa la base gravable del impuesto en cada línea de venta y en cada
comprobante. Los alimentos preparados usan 8 % de impuesto al consumo y las bebidas
envasadas 19 % de IVA; ambos valores se editan en `Administración → Configuración`.

---

## Créditos

Proyecto formativo SENA · Ficha 3411795

| Integrante | Módulos |
|---|---|
| Juan Steban Peña Cárdenas | RF1 Ventas y stock · RF2 Comprobantes |
| Andrés Rodríguez | RF3 Descuentos y promociones |
| Esteban Torres | RF4 Alertas de stock · RF5 Auditoría de movimientos |
| Juan Esteban Ávila | RF6 Proveedores · RF7 Rentabilidad |
| Cristian David Molano Pérez | RF8 Registro de actividad · RF9 Inicio de sesión |
