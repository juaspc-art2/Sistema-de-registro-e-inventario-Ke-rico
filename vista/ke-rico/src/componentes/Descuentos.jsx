import { useState } from "react";

const productosMock = [
  { id: 1, nombre: "Notebook Lenovo", precio: 850000 },
  { id: 2, nombre: "Mouse Inalámbrico", precio: 35000 },
  { id: 3, nombre: "Teclado Mecánico", precio: 120000 },
  { id: 4, nombre: "Monitor 24\"", precio: 420000 },
  { id: 5, nombre: "Auriculares BT", precio: 95000 },
];

const promocionesMock = [
  { id: 1, codigo: "VERANO25", descripcion: "Descuento verano 25%", tipo: "porcentaje", valor: 25, activa: true, usos: 12 },
  { id: 2, codigo: "FIJO5000", descripcion: "Rebaja fija $5.000", tipo: "fijo", valor: 5000, activa: true, usos: 8 },
  { id: 3, codigo: "2X1AUDIO", descripcion: "2x1 en Audio", tipo: "porcentaje", valor: 50, activa: false, usos: 30 },
];

export default function Descuentos() {
  const [promociones, setPromociones] = useState(promocionesMock);
  const [tab, setTab] = useState("calculadora");
  const [carrito, setCarrito] = useState([]);
  const [codigoInput, setCodigoInput] = useState("");
  const [promoAplicada, setPromoAplicada] = useState(null);
  const [mensajePromo, setMensajePromo] = useState({ texto: "", tipo: "" });
  const [nuevaPromo, setNuevaPromo] = useState({ codigo: "", descripcion: "", tipo: "porcentaje", valor: "", activa: true });

  const agregarProducto = (prod) => {
    const existe = carrito.find((c) => c.id === prod.id);
    if (existe) {
      setCarrito(carrito.map((c) => c.id === prod.id ? { ...c, cantidad: c.cantidad + 1 } : c));
    } else {
      setCarrito([...carrito, { ...prod, cantidad: 1 }]);
    }
  };

  const quitarProducto = (id) => setCarrito(carrito.filter((c) => c.id !== id));

  const subtotal = carrito.reduce((acc, c) => acc + c.precio * c.cantidad, 0);

  const calcularDescuento = () => {
    if (!promoAplicada) return 0;
    if (promoAplicada.tipo === "porcentaje") return subtotal * (promoAplicada.valor / 100);
    return Math.min(promoAplicada.valor, subtotal);
  };

  const descuento = calcularDescuento();
  const total = subtotal - descuento;

  const aplicarCodigo = () => {
    const promo = promociones.find(
      (p) => p.codigo.toUpperCase() === codigoInput.toUpperCase() && p.activa
    );
    if (promo) {
      setPromoAplicada(promo);
      setMensajePromo({ texto: `✅ "${promo.descripcion}" aplicada correctamente.`, tipo: "ok" });
    } else {
      setPromoAplicada(null);
      setMensajePromo({ texto: "❌ Código inválido o inactivo.", tipo: "error" });
    }
    setTimeout(() => setMensajePromo({ texto: "", tipo: "" }), 3000);
  };

  const crearPromocion = () => {
    if (!nuevaPromo.codigo || !nuevaPromo.valor) return;
    setPromociones([...promociones, { ...nuevaPromo, id: promociones.length + 1, usos: 0, valor: parseFloat(nuevaPromo.valor) }]);
    setNuevaPromo({ codigo: "", descripcion: "", tipo: "porcentaje", valor: "", activa: true });
  };

  const togglePromo = (id) =>
    setPromociones(promociones.map((p) => p.id === id ? { ...p, activa: !p.activa } : p));

  return (
    <div className="min-h-screen bg-fuchsia-950 text-white p-6 font-sans">
      <div className="max-w-5xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold tracking-tight">🏷️ Descuentos & Promociones</h1>
          <p className="text-fuchsia-300 mt-1">Aplica cupones y gestiona campañas</p>
        </div>

        {/* Tabs */}
        <div className="flex gap-2 mb-6">
          {[
            { key: "calculadora", label: "🧮 Calculadora" },
            { key: "promociones", label: "🎟️ Promociones" },
            { key: "nueva", label: "➕ Nueva Promo" },
          ].map((t) => (
            <button
              key={t.key}
              onClick={() => setTab(t.key)}
              className={`px-5 py-2 rounded-full text-sm font-medium transition-all ${
                tab === t.key
                  ? "bg-fuchsia-500 text-white shadow-lg shadow-fuchsia-500/40"
                  : "bg-fuchsia-900/50 text-fuchsia-300 hover:text-white"
              }`}
            >
              {t.label}
            </button>
          ))}
        </div>

        {/* Tab: Calculadora */}
        {tab === "calculadora" && (
          <div className="grid grid-cols-2 gap-6">
            {/* Productos */}
            <div>
              <h2 className="text-sm font-semibold text-fuchsia-300 uppercase tracking-wide mb-3">Productos</h2>
              <div className="space-y-2">
                {productosMock.map((p) => (
                  <div
                    key={p.id}
                    className="bg-fuchsia-900/40 border border-fuchsia-800 rounded-xl px-4 py-3 flex justify-between items-center"
                  >
                    <div>
                      <p className="font-medium text-sm">{p.nombre}</p>
                      <p className="text-fuchsia-300 text-xs">${p.precio.toLocaleString("es-AR")}</p>
                    </div>
                    <button
                      onClick={() => agregarProducto(p)}
                      className="bg-fuchsia-600 hover:bg-fuchsia-500 text-white text-xs px-3 py-1.5 rounded-lg transition-colors"
                    >
                      + Agregar
                    </button>
                  </div>
                ))}
              </div>
            </div>

            {/* Carrito + Descuento */}
            <div className="space-y-4">
              <div className="bg-fuchsia-900/40 border border-fuchsia-800 rounded-2xl p-4">
                <h2 className="text-sm font-semibold text-fuchsia-300 uppercase tracking-wide mb-3">Carrito</h2>
                {carrito.length === 0 ? (
                  <p className="text-fuchsia-400 text-sm text-center py-4">Agrega productos</p>
                ) : (
                  <div className="space-y-2">
                    {carrito.map((c) => (
                      <div key={c.id} className="flex justify-between items-center text-sm">
                        <span>{c.nombre} x{c.cantidad}</span>
                        <div className="flex items-center gap-3">
                          <span className="text-fuchsia-300">${(c.precio * c.cantidad).toLocaleString("es-AR")}</span>
                          <button onClick={() => quitarProducto(c.id)} className="text-fuchsia-500 hover:text-red-400 transition-colors text-xs">✕</button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Código descuento */}
              <div className="bg-fuchsia-900/40 border border-fuchsia-800 rounded-2xl p-4 space-y-3">
                <h2 className="text-sm font-semibold text-fuchsia-300 uppercase tracking-wide">Código Descuento</h2>
                <div className="flex gap-2">
                  <input
                    type="text"
                    placeholder="Ej: VERANO25"
                    value={codigoInput}
                    onChange={(e) => setCodigoInput(e.target.value)}
                    className="flex-1 bg-fuchsia-950 border border-fuchsia-700 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-fuchsia-400"
                  />
                  <button
                    onClick={aplicarCodigo}
                    className="bg-fuchsia-600 hover:bg-fuchsia-500 px-4 py-2 rounded-xl text-sm font-medium transition-colors"
                  >
                    Aplicar
                  </button>
                </div>
                {mensajePromo.texto && (
                  <p className={`text-xs ${mensajePromo.tipo === "ok" ? "text-emerald-400" : "text-red-400"}`}>
                    {mensajePromo.texto}
                  </p>
                )}
              </div>

              {/* Totales */}
              <div className="bg-fuchsia-900/40 border border-fuchsia-800 rounded-2xl p-4 space-y-2 text-sm">
                <div className="flex justify-between text-fuchsia-300">
                  <span>Subtotal</span>
                  <span>${subtotal.toLocaleString("es-AR")}</span>
                </div>
                {promoAplicada && (
                  <div className="flex justify-between text-emerald-400">
                    <span>Descuento ({promoAplicada.codigo})</span>
                    <span>- ${descuento.toLocaleString("es-AR")}</span>
                  </div>
                )}
                <div className="flex justify-between text-xl font-bold text-white border-t border-fuchsia-800 pt-2">
                  <span>Total</span>
                  <span>${total.toLocaleString("es-AR")}</span>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Tab: Promociones */}
        {tab === "promociones" && (
          <div className="space-y-3">
            {promociones.map((p) => (
              <div
                key={p.id}
                className={`bg-fuchsia-900/40 border rounded-2xl px-5 py-4 flex justify-between items-center transition-all ${
                  p.activa ? "border-fuchsia-700" : "border-fuchsia-900 opacity-60"
                }`}
              >
                <div>
                  <div className="flex items-center gap-2">
                    <span className="font-mono font-bold text-fuchsia-300">{p.codigo}</span>
                    <span className={`text-xs px-2 py-0.5 rounded-full ${p.activa ? "bg-emerald-900/60 text-emerald-300" : "bg-zinc-800 text-zinc-400"}`}>
                      {p.activa ? "Activa" : "Inactiva"}
                    </span>
                  </div>
                  <p className="text-sm text-fuchsia-200 mt-0.5">{p.descripcion}</p>
                  <p className="text-xs text-fuchsia-400 mt-1">
                    {p.tipo === "porcentaje" ? `${p.valor}% de descuento` : `$${p.valor.toLocaleString("es-AR")} de descuento fijo`} · {p.usos} usos
                  </p>
                </div>
                <button
                  onClick={() => togglePromo(p.id)}
                  className={`text-xs px-4 py-2 rounded-lg font-medium transition-colors ${
                    p.activa
                      ? "bg-red-900/60 text-red-300 hover:bg-red-800"
                      : "bg-emerald-900/60 text-emerald-300 hover:bg-emerald-800"
                  }`}
                >
                  {p.activa ? "Desactivar" : "Activar"}
                </button>
              </div>
            ))}
          </div>
        )}

        {/* Tab: Nueva Promo */}
        {tab === "nueva" && (
          <div className="bg-fuchsia-900/40 border border-fuchsia-800 rounded-2xl p-6 max-w-md space-y-4">
            <h2 className="font-semibold text-lg">Crear Promoción</h2>
            <div>
              <label className="text-xs text-fuchsia-300 mb-1 block uppercase tracking-wide">Código</label>
              <input
                type="text"
                placeholder="Ej: PROMO2025"
                value={nuevaPromo.codigo}
                onChange={(e) => setNuevaPromo({ ...nuevaPromo, codigo: e.target.value.toUpperCase() })}
                className="w-full bg-fuchsia-950 border border-fuchsia-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-fuchsia-400"
              />
            </div>
            <div>
              <label className="text-xs text-fuchsia-300 mb-1 block uppercase tracking-wide">Descripción</label>
              <input
                type="text"
                placeholder="Descripción visible al cliente"
                value={nuevaPromo.descripcion}
                onChange={(e) => setNuevaPromo({ ...nuevaPromo, descripcion: e.target.value })}
                className="w-full bg-fuchsia-950 border border-fuchsia-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-fuchsia-400"
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="text-xs text-fuchsia-300 mb-1 block uppercase tracking-wide">Tipo</label>
                <select
                  value={nuevaPromo.tipo}
                  onChange={(e) => setNuevaPromo({ ...nuevaPromo, tipo: e.target.value })}
                  className="w-full bg-fuchsia-950 border border-fuchsia-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-fuchsia-400"
                >
                  <option value="porcentaje">Porcentaje (%)</option>
                  <option value="fijo">Monto fijo ($)</option>
                </select>
              </div>
              <div>
                <label className="text-xs text-fuchsia-300 mb-1 block uppercase tracking-wide">Valor</label>
                <input
                  type="number"
                  placeholder="Ej: 20"
                  value={nuevaPromo.valor}
                  onChange={(e) => setNuevaPromo({ ...nuevaPromo, valor: e.target.value })}
                  className="w-full bg-fuchsia-950 border border-fuchsia-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-fuchsia-400"
                />
              </div>
            </div>
            <button
              onClick={crearPromocion}
              disabled={!nuevaPromo.codigo || !nuevaPromo.valor}
              className="w-full bg-fuchsia-500 hover:bg-fuchsia-400 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold py-3 rounded-xl transition-colors"
            >
              Crear Promoción
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
