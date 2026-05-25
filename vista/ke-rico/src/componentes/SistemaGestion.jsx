import { useState } from "react";

// ─── Datos de muestra ───────────────────────────────────────────────────────

const movimientos = [
  { fecha: "2026-05-11 09:14", sku: "SKU-0041", producto: "Silla ergonómica Pro",  tipo: "Entrada", qty: "+50", usuario: "M. López",   motivo: "Compra PO-2241" },
  { fecha: "2026-05-11 10:02", sku: "SKU-0017", producto: 'Monitor 27" 4K',        tipo: "Salida",  qty: "-12", usuario: "C. Ramírez", motivo: "Venta ORD-8821" },
  { fecha: "2026-05-11 10:45", sku: "SKU-0033", producto: "Teclado mecánico TKL",  tipo: "Ajuste",  qty: "±3",  usuario: "Admin",       motivo: "Corrección de conteo" },
  { fecha: "2026-05-11 11:30", sku: "SKU-0058", producto: "Webcam 1080p",           tipo: "Entrada", qty: "+25", usuario: "M. López",   motivo: "Compra PO-2245" },
  { fecha: "2026-05-11 12:10", sku: "SKU-0009", producto: "Auriculares BT 700",    tipo: "Salida",  qty: "-8",  usuario: "P. Torres",  motivo: "Venta ORD-8834" },
];

const proveedores = [
  { nombre: "TechSupply SAS",       nit: "900.112.344-1", categoria: "Tecnología", contacto: "juan@techsupply.co",  estado: "Activo",     rating: 4.8, vencimiento: "2027-01-15" },
  { nombre: "MobiCorp Ltda.",       nit: "830.504.111-9", categoria: "Mobiliario", contacto: "ventas@mobicorp.com", estado: "Activo",     rating: 4.1, vencimiento: "2026-08-01" },
  { nombre: "LogiRápido Express",   nit: "860.500.330-2", categoria: "Logística",  contacto: "ops@lograpido.co",   estado: "En revisión",rating: 3.5, vencimiento: "2026-06-30" },
  { nombre: "GlobalParts Inc.",     nit: "900.721.002-5", categoria: "Tecnología", contacto: "co@globalparts.io",  estado: "Inactivo",   rating: 2.9, vencimiento: "Vencido" },
];

const rentabilidadCategorias = [
  { label: "Tecnología", pct: 78, color: "#312235" },
  { label: "Mobiliario", pct: 52, color: "#1D9E75" },
  { label: "Accesorios", pct: 65, color: "#378ADD" },
  { label: "Logística",  pct: 31, color: "#EF9F27" },
  { label: "Servicios",  pct: 88, color: "#D4537E" },
];

const topProductos = [
  { nombre: "Silla ergonómica Pro", margen: "81%", ingreso: "$48M", nivel: "green" },
  { nombre: 'Monitor 27" 4K',       margen: "74%", ingreso: "$39M", nivel: "green" },
  { nombre: "Webcam 1080p",          margen: "68%", ingreso: "$22M", nivel: "green" },
  { nombre: "Auriculares BT 700",   margen: "55%", ingreso: "$18M", nivel: "amber" },
  { nombre: "Teclado mecánico TKL", margen: "47%", ingreso: "$14M", nivel: "amber" },
];

const logs = [
  { icono: "🔐", msg: "Usuario admin@empresa.co inició sesión correctamente",              modulo: "Auth",         detalle: "IP: 190.24.71.12", hora: "09:01:44", nivel: "Info",        nivColor: "blue" },
  { icono: "📦", msg: "Entrada de 50 unidades de SKU-0041 registrada por M. López",       modulo: "Inventario",   detalle: "Referencia: PO-2241", hora: "09:14:22", nivel: "Info",     nivColor: "green" },
  { icono: "⚠️", msg: "Stock de Auriculares BT 700 por debajo del umbral mínimo (8 uds.)", modulo: "Inventario",  detalle: "SKU-0009", hora: "10:52:17", nivel: "Advertencia",          nivColor: "amber" },
  { icono: "❌", msg: "Error al procesar importación de inventario_mayo.xlsx — columna 'costo' inválida", modulo: "Inventario", detalle: "Usuario: C. Ramírez", hora: "11:08:55", nivel: "Error", nivColor: "red" },
  { icono: "🚛", msg: "Contrato de LogiRápido Express próximo a vencer (50 días)",        modulo: "Proveedores",  detalle: "NIT: 860.500.330-2", hora: "12:00:00", nivel: "Sistema",   nivColor: "purple" },
  { icono: "📊", msg: "Reporte de rentabilidad Mayo 2026 generado y exportado",           modulo: "Rentabilidad", detalle: "Usuario: admin@empresa.co", hora: "13:41:09", nivel: "Info", nivColor: "green" },
];

// ─── Utilidades ─────────────────────────────────────────────────────────────

const badgeStyles = {
  green:  { background: "#EAF3DE", color: "#27500A" },
  amber:  { background: "#FAEEDA", color: "#633806" },
  red:    { background: "#FCEBEB", color: "#791F1F" },
  blue:   { background: "#E6F1FB", color: "#0C447C" },
  purple: { background: "#EEEDFE", color: "#3C3489" },
  gray:   { background: "#F1EFE8", color: "#444441" },
};

const tipoBadge = { Entrada: "green", Salida: "red", Ajuste: "amber" };
const estadoBadge = { Activo: "green", Inactivo: "red", "En revisión": "amber" };

function Badge({ label, color = "gray" }) {
  const s = badgeStyles[color] || badgeStyles.gray;
  return (
    <span style={{ ...s, display: "inline-flex", alignItems: "center", padding: "3px 9px", borderRadius: 99, fontSize: 11, fontWeight: 500 }}>
      {label}
    </span>
  );
}

function MetricCard({ label, value, delta, deltaUp }) {
  return (
    <div style={{ background: "var(--metric-bg, #f5f5f3)", borderRadius: 8, padding: "1rem" }}>
      <div style={{ fontSize: 12, color: "#888", marginBottom: 6 }}>{label}</div>
      <div style={{ fontSize: 22, fontWeight: 500 }}>{value}</div>
      {delta && (
        <div style={{ fontSize: 11, marginTop: 4, color: deltaUp ? "#27500A" : "#A32D2D" }}>
          {delta}
        </div>
      )}
    </div>
  );
}

// ─── Páginas ─────────────────────────────────────────────────────────────────

function RF5Auditoria() {
  const [busqueda, setBusqueda] = useState("");
  const [tipo, setTipo]         = useState("Todos");
  const filtrados = movimientos.filter(m =>
    (tipo === "Todos" || m.tipo === tipo) &&
    (m.producto.toLowerCase().includes(busqueda.toLowerCase()) || m.sku.includes(busqueda))
  );
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "1.25rem" }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
        <div>
          <h2 style={styles.pageTitle}>Auditoría de movimientos</h2>
          <p style={styles.pageSub}>Trazabilidad completa de entradas, salidas y ajustes</p>
        </div>
        <button style={styles.btnPrimary}>⬇ Exportar</button>
      </div>
      <div style={styles.metricsGrid}>
        <MetricCard label="Movimientos hoy" value="142" delta="↑ 12% vs ayer"   deltaUp />
        <MetricCard label="Entradas"         value="89"  delta="↑ 8 unidades"    deltaUp />
        <MetricCard label="Salidas"          value="47"  delta="↓ 3 unidades"    deltaUp={false} />
        <MetricCard label="Ajustes"          value="6"   delta="Sin variación" />
      </div>
      <div style={styles.card}>
        <div style={{ display: "flex", gap: 8, flexWrap: "wrap", marginBottom: "1rem" }}>
          <input style={styles.input} placeholder="Buscar producto o SKU…" value={busqueda} onChange={e => setBusqueda(e.target.value)} />
          <select style={styles.select} value={tipo} onChange={e => setTipo(e.target.value)}>
            {["Todos", "Entrada", "Salida", "Ajuste"].map(t => <option key={t}>{t}</option>)}
          </select>
        </div>
        <div style={{ overflowX: "auto" }}>
          <table style={styles.table}>
            <thead>
              <tr>{["Fecha / Hora","SKU","Producto","Tipo","Qty","Usuario","Motivo"].map(h => (
                <th key={h} style={styles.th}>{h}</th>
              ))}</tr>
            </thead>
            <tbody>
              {filtrados.map((m, i) => (
                <tr key={i}>
                  <td style={{ ...styles.td, fontSize: 12, color: "#888" }}>{m.fecha}</td>
                  <td style={{ ...styles.td, fontFamily: "monospace", fontSize: 12 }}>{m.sku}</td>
                  <td style={styles.td}>{m.producto}</td>
                  <td style={styles.td}><Badge label={m.tipo} color={tipoBadge[m.tipo]} /></td>
                  <td style={{ ...styles.td, fontWeight: 500 }}>{m.qty}</td>
                  <td style={styles.td}>{m.usuario}</td>
                  <td style={{ ...styles.td, color: "#888" }}>{m.motivo}</td>
                </tr>
              ))}
              {filtrados.length === 0 && (
                <tr><td colSpan={7} style={{ ...styles.td, textAlign: "center", color: "#aaa", padding: "2rem 0" }}>Sin resultados</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

function RF6Proveedores() {
  const [busqueda, setBusqueda] = useState("");
  const [estado, setEstado]     = useState("Todos");
  const filtrados = proveedores.filter(p =>
    (estado === "Todos" || p.estado === estado) &&
    p.nombre.toLowerCase().includes(busqueda.toLowerCase())
  );
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "1.25rem" }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
        <div>
          <h2 style={styles.pageTitle}>Gestión de proveedores</h2>
          <p style={styles.pageSub}>Directorio, evaluación y estado de contratos</p>
        </div>
        <button style={styles.btnPrimary}>+ Nuevo proveedor</button>
      </div>
      <div style={styles.metricsGrid}>
        <MetricCard label="Proveedores activos" value="34" delta="↑ 2 este mes"   deltaUp />
        <MetricCard label="Contratos vigentes"   value="28" delta="5 por vencer" />
        <MetricCard label="OC pendientes"        value="11" delta="3 retrasadas"   deltaUp={false} />
        <MetricCard label="Rating promedio"      value="4.2" delta="↑ 0.3 puntos"  deltaUp />
      </div>
      <div style={styles.card}>
        <div style={{ display: "flex", gap: 8, flexWrap: "wrap", marginBottom: "1rem" }}>
          <input style={styles.input} placeholder="Buscar proveedor…" value={busqueda} onChange={e => setBusqueda(e.target.value)} />
          <select style={styles.select} value={estado} onChange={e => setEstado(e.target.value)}>
            {["Todos", "Activo", "Inactivo", "En revisión"].map(t => <option key={t}>{t}</option>)}
          </select>
        </div>
        <div style={{ overflowX: "auto" }}>
          <table style={styles.table}>
            <thead>
              <tr>{["Proveedor","Categoría","Contacto","Estado","Rating","Próx. vencimiento",""].map(h => (
                <th key={h} style={styles.th}>{h}</th>
              ))}</tr>
            </thead>
            <tbody>
              {filtrados.map((p, i) => (
                <tr key={i}>
                  <td style={styles.td}>
                    <strong style={{ fontSize: 13 }}>{p.nombre}</strong>
                    <br /><span style={{ fontSize: 11, color: "#888" }}>NIT {p.nit}</span>
                  </td>
                  <td style={styles.td}>{p.categoria}</td>
                  <td style={{ ...styles.td, fontSize: 12, color: "#888" }}>{p.contacto}</td>
                  <td style={styles.td}><Badge label={p.estado} color={estadoBadge[p.estado]} /></td>
                  <td style={styles.td}>⭐ {p.rating}</td>
                  <td style={{ ...styles.td, fontSize: 12 }}>{p.vencimiento}</td>
                  <td style={styles.td}>
                    <button style={styles.btnSm}>Ver</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

function RF7Rentabilidad() {
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "1.25rem" }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
        <div>
          <h2 style={styles.pageTitle}>Análisis de rentabilidad</h2>
          <p style={styles.pageSub}>Margen bruto, top productos y comparativo por categoría</p>
        </div>
        <div style={{ display: "flex", gap: 8 }}>
          <select style={styles.select}>
            <option>Mayo 2026</option><option>Abril 2026</option><option>Q1 2026</option>
          </select>
          <button style={styles.btnPrimary}>⬇ Reporte</button>
        </div>
      </div>
      <div style={styles.metricsGrid}>
        <MetricCard label="Ingresos totales" value="$284M" delta="↑ 18% vs mes ant." deltaUp />
        <MetricCard label="Costo de ventas"  value="$161M" delta="↑ 9% vs mes ant."  deltaUp={false} />
        <MetricCard label="Margen bruto"     value="43.3%" delta="↑ 4.1 pp"          deltaUp />
        <MetricCard label="EBITDA estimado"  value="$52M"  delta="↑ 22%"             deltaUp />
      </div>
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "1.25rem" }}>
        <div style={styles.card}>
          <div style={styles.cardTitle}>Margen por categoría</div>
          {rentabilidadCategorias.map((c, i) => (
            <div key={i} style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 12, fontSize: 13 }}>
              <span style={{ minWidth: 90, color: "#888" }}>{c.label}</span>
              <div style={{ flex: 1, background: "#f0f0ee", borderRadius: 4, height: 10, overflow: "hidden" }}>
                <div style={{ width: `${c.pct}%`, height: "100%", background: c.color, borderRadius: 4 }} />
              </div>
              <span style={{ minWidth: 36, textAlign: "right", fontWeight: 500 }}>{c.pct}%</span>
            </div>
          ))}
        </div>
        <div style={styles.card}>
          <div style={styles.cardTitle}>Top 5 productos más rentables</div>
          <table style={styles.table}>
            <thead><tr><th style={styles.th}>#</th><th style={styles.th}>Producto</th><th style={styles.th}>Margen</th><th style={styles.th}>Ingreso</th></tr></thead>
            <tbody>
              {topProductos.map((p, i) => (
                <tr key={i}>
                  <td style={{ ...styles.td, color: "#aaa" }}>{i + 1}</td>
                  <td style={{ ...styles.td, fontSize: 12 }}>{p.nombre}</td>
                  <td style={styles.td}><Badge label={p.margen} color={p.nivel} /></td>
                  <td style={{ ...styles.td, fontWeight: 500 }}>{p.ingreso}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

function RF8Logs() {
  const [busqueda, setBusqueda] = useState("");
  const [nivel, setNivel]       = useState("Todos");
  const filtrados = logs.filter(l =>
    (nivel === "Todos" || l.nivel === nivel) &&
    l.msg.toLowerCase().includes(busqueda.toLowerCase())
  );
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "1.25rem" }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
        <div>
          <h2 style={styles.pageTitle}>Registro de actividad</h2>
          <p style={styles.pageSub}>Historial de eventos, acciones y alertas del sistema</p>
        </div>
        <div style={{ display: "flex", gap: 8 }}>
          <button style={styles.btn}>↺ Actualizar</button>
          <button style={styles.btnPrimary}>⬇ Exportar</button>
        </div>
      </div>
      <div style={styles.metricsGrid}>
        <MetricCard label="Eventos hoy"      value="1,247" delta="↑ 340 vs ayer"  deltaUp />
        <MetricCard label="Errores"          value="3"     delta="↑ 1 crítico"     deltaUp={false} />
        <MetricCard label="Advertencias"     value="14"    delta="Sin cambio" />
        <MetricCard label="Usuarios activos" value="9"     delta="↑ 2 sesiones"    deltaUp />
      </div>
      <div style={styles.card}>
        <div style={{ display: "flex", gap: 8, flexWrap: "wrap", marginBottom: "1rem" }}>
          <input style={styles.input} placeholder="Filtrar eventos…" value={busqueda} onChange={e => setBusqueda(e.target.value)} />
          <select style={styles.select} value={nivel} onChange={e => setNivel(e.target.value)}>
            {["Todos","Info","Advertencia","Error","Sistema"].map(t => <option key={t}>{t}</option>)}
          </select>
        </div>
        <div>
          {filtrados.map((l, i) => (
            <div key={i} style={{ display: "flex", gap: 12, padding: "11px 0", borderBottom: i < filtrados.length - 1 ? "0.5px solid #e5e5e0" : "none", alignItems: "flex-start", fontSize: 13 }}>
              <span style={{ fontSize: 20, lineHeight: 1.4 }}>{l.icono}</span>
              <div style={{ flex: 1 }}>
                <div style={{ lineHeight: 1.5 }}>{l.msg}</div>
                <div style={{ fontSize: 11, color: "#888", marginTop: 3 }}>Módulo: {l.modulo} · {l.detalle}</div>
              </div>
              <div style={{ fontSize: 11, color: "#aaa", minWidth: 70, textAlign: "right", marginTop: 2, fontVariantNumeric: "tabular-nums" }}>{l.hora}</div>
              <Badge label={l.nivel} color={l.nivColor} />
            </div>
          ))}
          {filtrados.length === 0 && (
            <div style={{ textAlign: "center", color: "#aaa", padding: "2rem 0" }}>Sin registros</div>
          )}
        </div>
      </div>
    </div>
  );
}

// ─── Estilos compartidos ─────────────────────────────────────────────────────

const styles = {
  pageTitle:   { fontSize: 18, fontWeight: 500, margin: 0 },
  pageSub:     { fontSize: 13, color: "#888", marginTop: 3 },
  metricsGrid: { display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(140px,1fr))", gap: 12 },
  card:        { background: "#fff", border: "0.5px solid #e5e5e0", borderRadius: 12, padding: "1.25rem" },
  cardTitle:   { fontSize: 14, fontWeight: 500, marginBottom: "1rem" },
  table:       { width: "100%", borderCollapse: "collapse", fontSize: 13 },
  th:          { textAlign: "left", fontSize: 11, fontWeight: 500, color: "#aaa", textTransform: "uppercase", letterSpacing: "0.06em", padding: "0 12px 10px 0", borderBottom: "0.5px solid #e5e5e0" },
  td:          { padding: "11px 12px 11px 0", borderBottom: "0.5px solid #e5e5e0", verticalAlign: "middle" },
  input:       { height: 34, padding: "0 10px", border: "0.5px solid #ccc", borderRadius: 8, fontSize: 13, outline: "none", minWidth: 180 },
  select:      { height: 34, padding: "0 10px", border: "0.5px solid #ccc", borderRadius: 8, fontSize: 13, outline: "none", background: "#fff" },
  btn:         { display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 14px", fontSize: 13, fontWeight: 500, border: "0.5px solid #ccc", borderRadius: 8, cursor: "pointer", background: "#fff" },
  btnPrimary:  { display: "inline-flex", alignItems: "center", gap: 6, padding: "7px 14px", fontSize: 13, fontWeight: 500, border: "none", borderRadius: 8, cursor: "pointer", background: "#7F77DD", color: "#fff" },
  btnSm:       { padding: "4px 10px", fontSize: 12, fontWeight: 500, border: "0.5px solid #ccc", borderRadius: 8, cursor: "pointer", background: "#fff" },
};

// ─── App principal ───────────────────────────────────────────────────────────

const navItems = [
  { id: "rf5", label: "RF5 · Auditoría",    icon: "📋" },
  { id: "rf6", label: "RF6 · Proveedores",  icon: "🚛" },
  { id: "rf7", label: "RF7 · Rentabilidad", icon: "📊" },
  { id: "rf8", label: "RF8 · Logs",         icon: "🖥️" },
];

const pages = { rf5: <RF5Auditoria />, rf6: <RF6Proveedores />, rf7: <RF7Rentabilidad />, rf8: <RF8Logs /> };

export default function SistemaGestion() {
  const [activo, setActivo] = useState("rf5");
  return (
    <div style={{ display: "flex", height: "100vh", fontFamily: "system-ui, sans-serif" }}>
      {/* Sidebar */}
      <nav style={{ width: 220, background: "#fff", borderRight: "0.5px solid #e5e5e0", padding: "1.25rem 0", display: "flex", flexDirection: "column", gap: 4, flexShrink: 0 }}>
        <div style={{ fontSize: 11, fontWeight: 500, color: "#aaa", letterSpacing: "0.08em", textTransform: "uppercase", padding: "0 1rem 0.75rem" }}>Módulos</div>
        {navItems.map(n => (
          <div key={n.id} onClick={() => setActivo(n.id)}
            style={{
              display: "flex", alignItems: "center", gap: 10, padding: "8px 1rem",
              fontSize: 14, cursor: "pointer", transition: "background 0.15s",
              background: activo === n.id ? "#f5f5f3" : "transparent",
              color: activo === n.id ? "#1a1a18" : "#666",
              fontWeight: activo === n.id ? 500 : 400,
              borderLeft: activo === n.id ? "2px solid #7F77DD" : "2px solid transparent",
            }}>
            <span>{n.icon}</span>{n.label}
          </div>
        ))}
      </nav>
      {/* Contenido */}
      <main style={{ flex: 1, overflowY: "auto", padding: "1.5rem", background: "#fafaf8" }}>
        {pages[activo]}
      </main>
    </div>
  );
} 
