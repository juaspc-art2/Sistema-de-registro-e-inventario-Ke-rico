import { useState } from "react";

const productosMock = [
  { id: 1, nombre: "Notebook Lenovo", categoria: "Electrónica", stock: 12, minimo: 5, proveedor: "TechDistrib S.A." },
  { id: 2, nombre: "Mouse Inalámbrico", categoria: "Periféricos", stock: 3, minimo: 10, proveedor: "PerifPlus" },
  { id: 3, nombre: "Teclado Mecánico", categoria: "Periféricos", stock: 8, minimo: 8, proveedor: "PerifPlus" },
  { id: 4, nombre: "Monitor 24\"", categoria: "Electrónica", stock: 2, minimo: 4, proveedor: "TechDistrib S.A." },
  { id: 5, nombre: "Auriculares BT", categoria: "Audio", stock: 20, minimo: 6, proveedor: "AudioWorld" },
  { id: 6, nombre: "Webcam HD", categoria: "Periféricos", stock: 1, minimo: 5, proveedor: "PerifPlus" },
  { id: 7, nombre: "Hub USB-C", categoria: "Accesorios", stock: 15, minimo: 8, proveedor: "AccesorioCorp" },
  { id: 8, nombre: "Cable HDMI 2m", categoria: "Accesorios", stock: 0, minimo: 10, proveedor: "AccesorioCorp" },
];

const getEstado = (stock, minimo) => {
  if (stock === 0) return "sin-stock";
  if (stock <= minimo * 0.5) return "critico";
  if (stock <= minimo) return "bajo";
  return "ok";
};

const estadoConfig = {
  "sin-stock": { label: "Sin Stock", color: "text-red-400", bg: "bg-red-900/40", border: "border-red-800", dot: "bg-red-500", badge: "bg-red-900/60 text-red-300" },
  critico: { label: "Crítico", color: "text-orange-400", bg: "bg-orange-900/30", border: "border-orange-800", dot: "bg-orange-500", badge: "bg-orange-900/60 text-orange-300" },
  bajo: { label: "Stock Bajo", color: "text-yellow-400", bg: "bg-yellow-900/20", border: "border-yellow-800", dot: "bg-yellow-500", badge: "bg-yellow-900/60 text-yellow-300" },
  ok: { label: "Normal", color: "text-emerald-400", bg: "", border: "border-slate-700", dot: "bg-emerald-500", badge: "bg-emerald-900/60 text-emerald-300" },
};

export default function AlertasStock() {
  const [productos, setProductos] = useState(productosMock);
  const [tab, setTab] = useState("alertas");
  const [filtro, setFiltro] = useState("todos");
  const [editandoId, setEditandoId] = useState(null);
  const [stockEditado, setStockEditado] = useState({});
  const [notificaciones, setNotificaciones] = useState([
    { id: 1, mensaje: "Mouse Inalámbrico bajó del mínimo (3/10)", hora: "10:32", leida: false },
    { id: 2, mensaje: "Cable HDMI 2m llegó a cero unidades", hora: "09:15", leida: false },
    { id: 3, mensaje: "Webcam HD en estado crítico (1/5)", hora: "08:50", leida: true },
  ]);

  const alertas = productos.filter((p) => getEstado(p.stock, p.minimo) !== "ok");
  const productosFiltrados = filtro === "todos"
    ? productos
    : productos.filter((p) => getEstado(p.stock, p.minimo) === filtro);

  const guardarStock = (id) => {
    if (stockEditado[id] === undefined) return;
    setProductos(productos.map((p) => p.id === id ? { ...p, stock: parseInt(stockEditado[id]) } : p));
    setEditandoId(null);
  };

  const marcarLeidas = () =>
    setNotificaciones(notificaciones.map((n) => ({ ...n, leida: true })));

  const noLeidas = notificaciones.filter((n) => !n.leida).length;

  const resumen = {
    "sin-stock": productos.filter((p) => getEstado(p.stock, p.minimo) === "sin-stock").length,
    critico: productos.filter((p) => getEstado(p.stock, p.minimo) === "critico").length,
    bajo: productos.filter((p) => getEstado(p.stock, p.minimo) === "bajo").length,
    ok: productos.filter((p) => getEstado(p.stock, p.minimo) === "ok").length,
  };

  return (
    <div className="min-h-screen bg-slate-900 text-slate-100 p-6 font-sans">
      <div className="max-w-6xl mx-auto">
        {/* Header */}
        <div className="flex justify-between items-start mb-8">
          <div>
            <h1 className="text-3xl font-bold text-white tracking-tight">🔔 Alertas de Stock</h1>
            <p className="text-slate-400 mt-1">Monitoreo automático de niveles mínimos</p>
          </div>
          <div className="relative">
            <button
              onClick={() => setTab("notificaciones")}
              className="bg-slate-800 border border-slate-700 p-3 rounded-xl hover:border-slate-500 transition-colors"
            >
              🔔
            </button>
            {noLeidas > 0 && (
              <span className="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
                {noLeidas}
              </span>
            )}
          </div>
        </div>

        {/* KPIs */}
        <div className="grid grid-cols-4 gap-4 mb-8">
          {[
            { key: "sin-stock", label: "Sin Stock", icon: "🚫" },
            { key: "critico", label: "Crítico", icon: "🔴" },
            { key: "bajo", label: "Stock Bajo", icon: "🟡" },
            { key: "ok", label: "Normal", icon: "✅" },
          ].map(({ key, label, icon }) => {
            const cfg = estadoConfig[key];
            return (
              <button
                key={key}
                onClick={() => { setFiltro(key); setTab("inventario"); }}
                className={`bg-slate-800 border rounded-2xl p-4 text-left hover:scale-105 transition-transform ${cfg.border}`}
              >
                <p className="text-2xl mb-1">{icon}</p>
                <p className={`text-2xl font-bold ${cfg.color}`}>{resumen[key]}</p>
                <p className="text-slate-400 text-xs mt-1">{label}</p>
              </button>
            );
          })}
        </div>

        {/* Tabs */}
        <div className="flex gap-2 mb-6">
          {[
            { key: "alertas", label: "⚠️ Alertas Activas" },
            { key: "inventario", label: "📦 Inventario" },
            { key: "notificaciones", label: `🔔 Notificaciones${noLeidas > 0 ? ` (${noLeidas})` : ""}` },
          ].map((t) => (
            <button
              key={t.key}
              onClick={() => setTab(t.key)}
              className={`px-5 py-2 rounded-full text-sm font-medium transition-all ${
                tab === t.key
                  ? "bg-sky-500 text-white shadow-lg shadow-sky-500/30"
                  : "bg-slate-800 text-slate-400 hover:text-white"
              }`}
            >
              {t.label}
            </button>
          ))}
        </div>

        {/* Tab: Alertas */}
        {tab === "alertas" && (
          <div className="space-y-3">
            {alertas.length === 0 ? (
              <div className="text-center py-16 text-slate-400">
                <p className="text-5xl mb-3">✅</p>
                <p>Todos los productos están sobre el stock mínimo</p>
              </div>
            ) : (
              alertas.map((p) => {
                const estado = getEstado(p.stock, p.minimo);
                const cfg = estadoConfig[estado];
                const porcentaje = p.minimo > 0 ? Math.min((p.stock / p.minimo) * 100, 100) : 0;
                return (
                  <div key={p.id} className={`border rounded-2xl px-5 py-4 ${cfg.bg} ${cfg.border}`}>
                    <div className="flex justify-between items-start">
                      <div className="flex items-center gap-3">
                        <span className={`w-2.5 h-2.5 rounded-full animate-pulse ${cfg.dot}`} />
                        <div>
                          <p className="font-semibold">{p.nombre}</p>
                          <p className="text-slate-400 text-xs">{p.categoria} · Proveedor: {p.proveedor}</p>
                        </div>
                      </div>
                      <span className={`px-2 py-1 rounded-full text-xs font-semibold ${cfg.badge}`}>
                        {cfg.label}
                      </span>
                    </div>
                    <div className="mt-3">
                      <div className="flex justify-between text-xs text-slate-400 mb-1">
                        <span>Stock actual: <span className={`font-bold ${cfg.color}`}>{p.stock} u.</span></span>
                        <span>Mínimo requerido: {p.minimo} u.</span>
                      </div>
                      <div className="w-full bg-slate-700 rounded-full h-2">
                        <div
                          className={`h-2 rounded-full transition-all ${
                            estado === "sin-stock" ? "bg-red-500" :
                            estado === "critico" ? "bg-orange-500" : "bg-yellow-500"
                          }`}
                          style={{ width: `${porcentaje}%` }}
                        />
                      </div>
                    </div>
                    <div className="mt-3 flex justify-end">
                      <button
                        onClick={() => { setEditandoId(p.id); setTab("inventario"); }}
                        className="text-xs bg-slate-700 hover:bg-slate-600 px-3 py-1.5 rounded-lg transition-colors"
                      >
                        Actualizar stock →
                      </button>
                    </div>
                  </div>
                );
              })
            )}
          </div>
        )}

        {/* Tab: Inventario */}
        {tab === "inventario" && (
          <div>
            <div className="flex gap-2 mb-4 flex-wrap">
              {["todos", "sin-stock", "critico", "bajo", "ok"].map((f) => (
                <button
                  key={f}
                  onClick={() => setFiltro(f)}
                  className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                    filtro === f ? "bg-sky-600 text-white" : "bg-slate-800 text-slate-400 hover:text-white"
                  }`}
                >
                  {f === "todos" ? "Todos" : estadoConfig[f].label}
                </button>
              ))}
            </div>
            <div className="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-slate-900 text-slate-400 uppercase text-xs">
                  <tr>
                    {["Producto", "Categoría", "Stock", "Mínimo", "Estado", "Acciones"].map((h) => (
                      <th key={h} className="px-5 py-4 text-left">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {productosFiltrados.map((p) => {
                    const estado = getEstado(p.stock, p.minimo);
                    const cfg = estadoConfig[estado];
                    return (
                      <tr key={p.id} className="border-t border-slate-700 hover:bg-slate-700/30 transition-colors">
                        <td className="px-5 py-4 font-medium">{p.nombre}</td>
                        <td className="px-5 py-4 text-slate-400">{p.categoria}</td>
                        <td className="px-5 py-4">
                          {editandoId === p.id ? (
                            <div className="flex gap-2 items-center">
                              <input
                                type="number"
                                min="0"
                                defaultValue={p.stock}
                                onChange={(e) => setStockEditado({ ...stockEditado, [p.id]: e.target.value })}
                                className="w-20 bg-slate-900 border border-sky-500 rounded-lg px-2 py-1 text-sm focus:outline-none"
                              />
                              <button onClick={() => guardarStock(p.id)} className="text-emerald-400 text-xs font-semibold">✓</button>
                              <button onClick={() => setEditandoId(null)} className="text-slate-400 text-xs">✕</button>
                            </div>
                          ) : (
                            <span className={`font-bold ${cfg.color}`}>{p.stock} u.</span>
                          )}
                        </td>
                        <td className="px-5 py-4 text-slate-400">{p.minimo} u.</td>
                        <td className="px-5 py-4">
                          <span className={`flex items-center gap-1.5 text-xs font-medium`}>
                            <span className={`w-2 h-2 rounded-full ${cfg.dot}`} />
                            <span className={cfg.color}>{cfg.label}</span>
                          </span>
                        </td>
                        <td className="px-5 py-4">
                          <button
                            onClick={() => setEditandoId(p.id)}
                            className="text-xs text-slate-400 hover:text-white transition-colors"
                          >
                            ✏️ Editar
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* Tab: Notificaciones */}
        {tab === "notificaciones" && (
          <div>
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-sm font-semibold text-slate-400 uppercase tracking-wide">
                {noLeidas > 0 ? `${noLeidas} sin leer` : "Todo leído"}
              </h2>
              {noLeidas > 0 && (
                <button
                  onClick={marcarLeidas}
                  className="text-xs text-sky-400 hover:text-sky-300 transition-colors"
                >
                  Marcar todas como leídas
                </button>
              )}
            </div>
            <div className="space-y-3">
              {notificaciones.map((n) => (
                <div
                  key={n.id}
                  className={`border rounded-2xl px-5 py-4 flex justify-between items-center transition-all ${
                    n.leida ? "bg-slate-800/40 border-slate-700 opacity-60" : "bg-slate-800 border-slate-600"
                  }`}
                >
                  <div className="flex items-center gap-3">
                    {!n.leida && <span className="w-2 h-2 rounded-full bg-sky-500 shrink-0" />}
                    <p className="text-sm">{n.mensaje}</p>
                  </div>
                  <span className="text-slate-500 text-xs shrink-0 ml-4">{n.hora}</span>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
