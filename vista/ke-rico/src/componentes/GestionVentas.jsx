import { useState } from "react";

const productosIniciales = [
  { id: 1, nombre: "Notebook Lenovo", categoria: "Electrónica", precio: 850000, stock: 12 },
  { id: 2, nombre: "Mouse Inalámbrico", categoria: "Periféricos", precio: 35000, stock: 45 },
  { id: 3, nombre: "Teclado Mecánico", categoria: "Periféricos", precio: 120000, stock: 8 },
  { id: 4, nombre: "Monitor 24\"", categoria: "Electrónica", precio: 420000, stock: 5 },
  { id: 5, nombre: "Auriculares BT", categoria: "Audio", precio: 95000, stock: 20 },
];

const ventasIniciales = [
  { id: 1, producto: "Notebook Lenovo", cantidad: 2, total: 1700000, fecha: "2025-05-01", estado: "Completada" },
  { id: 2, producto: "Mouse Inalámbrico", cantidad: 5, total: 175000, fecha: "2025-05-03", estado: "Completada" },
  { id: 3, producto: "Monitor 24\"", cantidad: 1, total: 420000, fecha: "2025-05-08", estado: "Pendiente" },
];

export default function GestionVentas() {
  const [productos, setProductos] = useState(productosIniciales);
  const [ventas, setVentas] = useState(ventasIniciales);
  const [tab, setTab] = useState("ventas");
  const [busqueda, setBusqueda] = useState("");
  const [nuevaVenta, setNuevaVenta] = useState({ productoId: "", cantidad: 1 });
  const [mensajeExito, setMensajeExito] = useState("");

  const productosFiltrados = productos.filter((p) =>
    p.nombre.toLowerCase().includes(busqueda.toLowerCase())
  );

  const registrarVenta = () => {
    const prod = productos.find((p) => p.id === parseInt(nuevaVenta.productoId));
    if (!prod) return;
    if (nuevaVenta.cantidad > prod.stock) {
      alert("Stock insuficiente");
      return;
    }
    const venta = {
      id: ventas.length + 1,
      producto: prod.nombre,
      cantidad: parseInt(nuevaVenta.cantidad),
      total: prod.precio * parseInt(nuevaVenta.cantidad),
      fecha: new Date().toISOString().split("T")[0],
      estado: "Completada",
    };
    setVentas([venta, ...ventas]);
    setProductos(productos.map((p) =>
      p.id === prod.id ? { ...p, stock: p.stock - parseInt(nuevaVenta.cantidad) } : p
    ));
    setNuevaVenta({ productoId: "", cantidad: 1 });
    setMensajeExito(`Venta de "${prod.nombre}" registrada con éxito.`);
    setTimeout(() => setMensajeExito(""), 3000);
  };

  const totalVentas = ventas.reduce((acc, v) => acc + v.total, 0);

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 font-sans p-6">
      <div className="max-w-6xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-white tracking-tight">
            📦 Gestión de Ventas & Stock
          </h1>
          <p className="text-slate-400 mt-1">Control de inventario y registro de ventas</p>
        </div>

        {/* KPIs */}
        <div className="grid grid-cols-3 gap-4 mb-8">
          <div className="bg-slate-800 rounded-2xl p-5 border border-slate-700">
            <p className="text-slate-400 text-sm">Total Ventas</p>
            <p className="text-2xl font-bold text-emerald-400 mt-1">
              ${totalVentas.toLocaleString("es-AR")}
            </p>
          </div>
          <div className="bg-slate-800 rounded-2xl p-5 border border-slate-700">
            <p className="text-slate-400 text-sm">Transacciones</p>
            <p className="text-2xl font-bold text-sky-400 mt-1">{ventas.length}</p>
          </div>
          <div className="bg-slate-800 rounded-2xl p-5 border border-slate-700">
            <p className="text-slate-400 text-sm">Productos en Stock</p>
            <p className="text-2xl font-bold text-violet-400 mt-1">{productos.length}</p>
          </div>
        </div>

        {/* Tabs */}
        <div className="flex gap-2 mb-6">
          {["ventas", "stock", "nueva-venta"].map((t) => (
            <button
              key={t}
              onClick={() => setTab(t)}
              className={`px-5 py-2 rounded-full text-sm font-medium transition-all ${
                tab === t
                  ? "bg-emerald-500 text-white shadow-lg shadow-emerald-500/30"
                  : "bg-slate-800 text-slate-400 hover:text-white"
              }`}
            >
              {t === "ventas" ? "📋 Ventas" : t === "stock" ? "🗃️ Stock" : "➕ Nueva Venta"}
            </button>
          ))}
        </div>

        {/* Mensaje éxito */}
        {mensajeExito && (
          <div className="mb-4 bg-emerald-900/50 border border-emerald-500 text-emerald-300 rounded-xl px-4 py-3 text-sm">
            ✅ {mensajeExito}
          </div>
        )}

        {/* Tab: Ventas */}
        {tab === "ventas" && (
          <div className="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-slate-900 text-slate-400 uppercase text-xs">
                <tr>
                  {["#", "Producto", "Cantidad", "Total", "Fecha", "Estado"].map((h) => (
                    <th key={h} className="px-5 py-4 text-left">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {ventas.map((v) => (
                  <tr key={v.id} className="border-t border-slate-700 hover:bg-slate-700/40 transition-colors">
                    <td className="px-5 py-4 text-slate-500">{v.id}</td>
                    <td className="px-5 py-4 font-medium">{v.producto}</td>
                    <td className="px-5 py-4">{v.cantidad}</td>
                    <td className="px-5 py-4 text-emerald-400 font-semibold">
                      ${v.total.toLocaleString("es-AR")}
                    </td>
                    <td className="px-5 py-4 text-slate-400">{v.fecha}</td>
                    <td className="px-5 py-4">
                      <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                        v.estado === "Completada"
                          ? "bg-emerald-900/60 text-emerald-300"
                          : "bg-yellow-900/60 text-yellow-300"
                      }`}>
                        {v.estado}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Tab: Stock */}
        {tab === "stock" && (
          <div>
            <input
              type="text"
              placeholder="Buscar producto..."
              value={busqueda}
              onChange={(e) => setBusqueda(e.target.value)}
              className="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-sm mb-4 focus:outline-none focus:border-emerald-500"
            />
            <div className="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-slate-900 text-slate-400 uppercase text-xs">
                  <tr>
                    {["Producto", "Categoría", "Precio", "Stock"].map((h) => (
                      <th key={h} className="px-5 py-4 text-left">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {productosFiltrados.map((p) => (
                    <tr key={p.id} className="border-t border-slate-700 hover:bg-slate-700/40 transition-colors">
                      <td className="px-5 py-4 font-medium">{p.nombre}</td>
                      <td className="px-5 py-4 text-slate-400">{p.categoria}</td>
                      <td className="px-5 py-4 text-sky-400">${p.precio.toLocaleString("es-AR")}</td>
                      <td className="px-5 py-4">
                        <span className={`font-bold ${p.stock <= 5 ? "text-red-400" : "text-emerald-400"}`}>
                          {p.stock} u.
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* Tab: Nueva Venta */}
        {tab === "nueva-venta" && (
          <div className="bg-slate-800 rounded-2xl border border-slate-700 p-6 max-w-md">
            <h2 className="text-lg font-semibold mb-4">Registrar Nueva Venta</h2>
            <div className="space-y-4">
              <div>
                <label className="text-sm text-slate-400 mb-1 block">Producto</label>
                <select
                  value={nuevaVenta.productoId}
                  onChange={(e) => setNuevaVenta({ ...nuevaVenta, productoId: e.target.value })}
                  className="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-emerald-500"
                >
                  <option value="">Seleccionar producto...</option>
                  {productos.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.nombre} — Stock: {p.stock}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="text-sm text-slate-400 mb-1 block">Cantidad</label>
                <input
                  type="number"
                  min="1"
                  value={nuevaVenta.cantidad}
                  onChange={(e) => setNuevaVenta({ ...nuevaVenta, cantidad: e.target.value })}
                  className="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-emerald-500"
                />
              </div>
              {nuevaVenta.productoId && (
                <div className="bg-slate-900 rounded-xl p-4 text-sm">
                  <span className="text-slate-400">Total estimado: </span>
                  <span className="text-emerald-400 font-bold text-lg">
                    ${(
                      (productos.find((p) => p.id === parseInt(nuevaVenta.productoId))?.precio || 0) *
                      nuevaVenta.cantidad
                    ).toLocaleString("es-AR")}
                  </span>
                </div>
              )}
              <button
                onClick={registrarVenta}
                disabled={!nuevaVenta.productoId}
                className="w-full bg-emerald-500 hover:bg-emerald-400 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold py-3 rounded-xl transition-colors"
              >
                Confirmar Venta
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
