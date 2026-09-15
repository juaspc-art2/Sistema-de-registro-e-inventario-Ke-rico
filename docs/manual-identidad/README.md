# Manual de Identidad Corporativa · Ke-Rico!

Documento normativo de la marca **Ke-Rico!**, versión 1.0 (2026).
Formato A4 apaisado (297 × 210 mm), 45 páginas.

## Contenido de la carpeta

| Archivo | Descripción |
|---|---|
| `Manual-Identidad-Corporativa-Ke-Rico.pdf` | **Entregable.** Manual completo en PDF. |
| `manual.html` | Fuente del manual. Todo el documento es HTML + CSS; el PDF se genera imprimiéndolo. |
| `assets/isotipo.svg` | Isotipo a color (versión principal). |
| `assets/isotipo-badge.svg` | Isotipo en recuadro Naranja — avatares e iconos de app. |
| `assets/isotipo-blanco.svg` | Isotipo monocromo blanco (contorno) para fondos oscuros. |
| `assets/isotipo-negro.svg` | Isotipo monocromo Carbón para blanco y negro en positivo. |
| `assets/isotipo-una-tinta.svg` | Isotipo a una tinta Naranja — Pantone 1585 C. |
| `assets/chispa.svg` | Chispa suelta, recurso gráfico secundario (B.11). |
| `assets/web-preview.png` | Captura del sitio para la página C.11. |

## Regenerar el PDF

Requiere Google Chrome y conexión a internet la primera vez (las tipografías se cargan de
Google Fonts y quedan incrustadas en el PDF).

```bash
"C:/Program Files/Google/Chrome/Application/chrome.exe" --headless --disable-gpu --no-pdf-header-footer --force-color-profile=srgb --virtual-time-budget=45000 --print-to-pdf="Manual-Identidad-Corporativa-Ke-Rico.pdf" "file:///C:/Users/Esteban/Desktop/sistema-de-registro-e-inventario-ke-rico/manual-identidad/manual.html"
```

## Estructura

- **A · La marca** (A.01–A.10): filosofía, logotipo, versiones, construcción modular,
  área de protección, tamaños mínimos, monocromía, fondos, usos permitidos e incorrectos.
- **B · Sistema visual** (B.01–B.14): color corporativo, neutros, colores funcionales,
  proporción y contraste verificado, tipografía, retícula, formas y elevación, texturas,
  fotografía, iconografía y tono de voz.
- **C · Aplicaciones** (C.01–C.13): papelería, factura y tirilla POS, menú, empaques,
  uniformes, señalética, promocionales, firma de correo, redes, sitio web,
  Sistema de Registro e Inventario y pendón.

## Piezas gobernadas por este manual

- Landing: `vista/ke-rico/stitch_sabor_dorado_fritos_web/code.html` — sus tokens de diseño
  están en `DESIGN.md`, sincronizados con la sección B del manual.
- Aplicación React: `vista/ke-rico/src` — **todavía no aplica la identidad** (usa una paleta
  azul/violeta genérica). Ver C.12 para las reglas que debe cumplir.

## Fuentes tipográficas

Las tres son Google Fonts con licencia SIL Open Font License 1.1:

- **Baloo 2** ExtraBold — exclusiva del logotipo.
- **Plus Jakarta Sans** 400–800 — titulares, etiquetas, botones, cifras.
- **Work Sans** 300–600 — cuerpo de texto.

## Datos pendientes

Estos campos aparecen en las maquetas de la sección C con **valores de plantilla**, no reales.
Reemplazarlos antes de producir cualquier pieza:

| Dato | Estado actual en el documento |
|---|---|
| NIT | `NIT 000.000.000-0` |
| Resolución DIAN de facturación | `Resolución DIAN N.º 0000000000000` |
| Correo electrónico | **omitido** — el campo existe en el orden de la papelería (C.02), pero no se muestra ningún valor |

**Dominio web y redes sociales:** no existen. Se retiraron por completo del documento —
maquetas y especificaciones— en vez de dejarlos como marcador. Si la marca abre sitio o perfiles,
hay que volver a introducirlos en C.02 (respaldo de la tarjeta), C.05 (caja), C.09 (firma de correo),
C.11 (barra del navegador) y C.13 (pendón).

Datos confirmados: dirección `Cra. 59 # 132A - 7, Suba, Bogotá D.C.` · teléfono `(+57) 317 796 5569`.
