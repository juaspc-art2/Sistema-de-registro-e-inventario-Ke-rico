import { useState } from "react";

const clientesMock = [
  { id: 1, nombre: "Empresa Alpha S.A.", cuit: "30-12345678-9", email: "admin@alpha.com" },
  { id: 2, nombre: "Juan Pérez", cuit: "20-87654321-0", email: "juan@email.com" },
  { id: 3, nombre: "Tech Solutions SRL", cuit: "30-99887766-1", email: "info@techsol.com" },
];

const comprobantesMock = [
  { id: "FC-0001", tipo: "Factura A", cliente: "Empresa Alpha S.A.", total: 1700000, fecha: "2025-05-01", estado: "Emitida" },
  { id: "FC-0002", tipo: "Factura B", cliente: "Juan Pérez", total: 175000, fecha: "2025-05-03", estado: "Emitida" },
  { id: "NC-0001", tipo: "Nota de Crédito", cliente: "Tech Solutions SRL", total: 50000, fecha: "2025-05-07", estado: "Anulada" },
];

const tiposComprobante = ["Factura A", "Factura B", "Nota de Crédito", "Nota de Débito", "Ticket"];

export default function EmisionComprobantes() {
  const [comprobantes, setComprobantes] = useState(comprobantesMock);
  const [tab, setTab] = useState("listado");
  const [vistaPrevia, setVistaPrevia] = useState(null);
  const [form, setForm] = useState({
    tipo: "Factura B",
    clienteId: "",
    descripcion: "",
    cantidad: 1,
    precioUnit: "",
  });

  const clienteSeleccionado = clientesMock.find((c) => c.id === parseInt(form.clienteId));
  const subtotal = form.cantidad * parseFloat(form.precioUnit || 0);
  const iva = subtotal * 0.21;
  const total = subtotal + iva;

  const generarComprobante = () => {
    if (!form.clienteId || !form.descripcion || !form.precioUnit) return;
    const nuevo = {
      id: `FC-${String(comprobantes.length + 1).padStart(4, "0")}`,
      tipo: form.tipo,
      cliente: clienteSeleccionado.nombre,
      total,
      fecha: new Date().toISOString().split("T")[0],
      estado: "Emitida",
    };
    setComprobantes([nuevo, ...comprobantes]);
    setVistaPrevia({ ...nuevo, clienteSeleccionado, form, subtotal, iva });
    setTab("preview");
  };

  return (
    <div className="min-h-screen bg-zinc-950 text-zinc-100 p-6 font-sans">
      <div className="max-w-5xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-white tracking-tight">🧾 Comprobantes Digitales</h1>
          <p className="text-zinc-400 mt-1">Emisión y gestión de facturas y notas</p>
        </div>

        {/* Tabs */}
        <div className="flex gap-2 mb-6">
          {[
            { key: "listado", label: "📋 Listado" },
            { key: "nuevo", label: "➕ Nuevo Comprobante" },
            { key: "preview", label: "👁️ Vista Previa" },
          ].map((t) => (
            <button
              key={t.key}
              onClick={() => setTab(t.key)}
              className={`px-5 py-2 rounded-full text-sm font-medium transition-all ${
                tab === t.key
                  ? "bg-amber-500 text-zinc-950 font-bold shadow-lg shadow-amber-500/30"
                  : "bg-zinc-800 text-zinc-400 hover:text-white"
              }`}
            >
              {t.label}
            </button>
          ))}
        </div>

        {/* Tab: Listado */}
        {tab === "listado" && (
          <div className="bg-zinc-800 rounded-2xl border border-zinc-700 overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-zinc-900 text-zinc-400 uppercase text-xs">
                <tr>
                  {["Número", "Tipo", "Cliente", "Total", "Fecha", "Estado"].map((h) => (
                    <th key={h} className="px-5 py-4 text-left">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {comprobantes.map((c) => (
                  <tr key={c.id} className="border-t border-zinc-700 hover:bg-zinc-700/40 transition-colors">
                    <td className="px-5 py-4 font-mono text-amber-400">{c.id}</td>
                    <td className="px-5 py-4">{c.tipo}</td>
                    <td className="px-5 py-4 text-zinc-300">{c.cliente}</td>
                    <td className="px-5 py-4 font-semibold text-white">
                      ${c.total.toLocaleString("es-AR")}
                    </td>
                    <td className="px-5 py-4 text-zinc-400">{c.fecha}</td>
                    <td className="px-5 py-4">
                      <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                        c.estado === "Emitida"
                          ? "bg-emerald-900/60 text-emerald-300"
                          : "bg-red-900/60 text-red-300"
                      }`}>
                        {c.estado}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Tab: Nuevo Comprobante */}
        {tab === "nuevo" && (
          <div className="grid grid-cols-2 gap-6">
            <div className="bg-zinc-800 rounded-2xl border border-zinc-700 p-6 space-y-4">
              <h2 className="font-semibold text-lg">Datos del Comprobante</h2>

              <div>
                <label className="text-xs text-zinc-400 mb-1 block uppercase tracking-wide">Tipo</label>
                <select
                  value={form.tipo}
                  onChange={(e) => setForm({ ...form, tipo: e.target.value })}
                  className="w-full bg-zinc-900 border border-zinc-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-amber-500"
                >
                  {tiposComprobante.map((t) => <option key={t}>{t}</option>)}
                </select>
              </div>

              <div>
                <label className="text-xs text-zinc-400 mb-1 block uppercase tracking-wide">Cliente</label>
                <select
                  value={form.clienteId}
                  onChange={(e) => setForm({ ...form, clienteId: e.target.value })}
                  className="w-full bg-zinc-900 border border-zinc-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-amber-500"
                >
                  <option value="">Seleccionar cliente...</option>
                  {clientesMock.map((c) => (
                    <option key={c.id} value={c.id}>{c.nombre}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="text-xs text-zinc-400 mb-1 block uppercase tracking-wide">Descripción</label>
                <input
                  type="text"
                  placeholder="Descripción del ítem"
                  value={form.descripcion}
                  onChange={(e) => setForm({ ...form, descripcion: e.target.value })}
                  className="w-full bg-zinc-900 border border-zinc-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-amber-500"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs text-zinc-400 mb-1 block uppercase tracking-wide">Cantidad</label>
                  <input
                    type="number"
                    min="1"
                    value={form.cantidad}
                    onChange={(e) => setForm({ ...form, cantidad: parseInt(e.target.value) })}
                    className="w-full bg-zinc-900 border border-zinc-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-amber-500"
                  />
                </div>
                <div>
                  <label className="text-xs text-zinc-400 mb-1 block uppercase tracking-wide">Precio Unit.</label>
                  <input
                    type="number"
                    placeholder="0"
                    value={form.precioUnit}
                    onChange={(e) => setForm({ ...form, precioUnit: e.target.value })}
                    className="w-full bg-zinc-900 border border-zinc-700 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-amber-500"
                  />
                </div>
              </div>

              <button
                onClick={generarComprobante}
                disabled={!form.clienteId || !form.descripcion || !form.precioUnit}
                className="w-full bg-amber-500 hover:bg-amber-400 disabled:opacity-40 disabled:cursor-not-allowed text-zinc-950 font-bold py-3 rounded-xl transition-colors mt-2"
              >
                Emitir Comprobante
              </button>
            </div>

            {/* Resumen en tiempo real */}
            <div className="bg-zinc-800 rounded-2xl border border-zinc-700 p-6">
              <h2 className="font-semibold text-lg mb-4">Resumen</h2>
              <div className="space-y-3 text-sm">
                <div className="flex justify-between border-b border-zinc-700 pb-3">
                  <span className="text-zinc-400">Tipo</span>
                  <span>{form.tipo}</span>
                </div>
                <div className="flex justify-between border-b border-zinc-700 pb-3">
                  <span className="text-zinc-400">Cliente</span>
                  <span>{clienteSeleccionado?.nombre || "—"}</span>
                </div>
                <div className="flex justify-between border-b border-zinc-700 pb-3">
                  <span className="text-zinc-400">CUIT</span>
                  <span className="font-mono">{clienteSeleccionado?.cuit || "—"}</span>
                </div>
                <div className="flex justify-between border-b border-zinc-700 pb-3">
                  <span className="text-zinc-400">Subtotal</span>
                  <span>${subtotal.toLocaleString("es-AR")}</span>
                </div>
                <div className="flex justify-between border-b border-zinc-700 pb-3">
                  <span className="text-zinc-400">IVA 21%</span>
                  <span>${iva.toLocaleString("es-AR")}</span>
                </div>
                <div className="flex justify-between text-lg font-bold text-amber-400">
                  <span>Total</span>
                  <span>${total.toLocaleString("es-AR")}</span>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Tab: Vista Previa */}
        {tab === "preview" && vistaPrevia ? (
          <div className="bg-white text-zinc-900 rounded-2xl p-8 max-w-xl mx-auto shadow-2xl">
            <div className="flex justify-between items-start mb-6">
              <div>
                <h2 className="text-2xl font-bold text-zinc-900">{vistaPrevia.tipo}</h2>
                <p className="text-zinc-500 text-sm">N° {vistaPrevia.id}</p>
              </div>
              <span className="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-xs font-semibold">
                {vistaPrevia.estado}
              </span>
            </div>
            <div className="border-t border-zinc-200 pt-4 mb-4 space-y-1 text-sm">
              <p><span className="font-semibold">Cliente:</span> {vistaPrevia.clienteSeleccionado?.nombre}</p>
              <p><span className="font-semibold">CUIT:</span> {vistaPrevia.clienteSeleccionado?.cuit}</p>
              <p><span className="font-semibold">Email:</span> {vistaPrevia.clienteSeleccionado?.email}</p>
              <p><span className="font-semibold">Fecha:</span> {vistaPrevia.fecha}</p>
            </div>
            <table className="w-full text-sm border-t border-zinc-200 mt-4 mb-4">
              <thead>
                <tr className="text-zinc-500 text-xs uppercase">
                  <th className="py-2 text-left">Descripción</th>
                  <th className="py-2 text-right">Cant.</th>
                  <th className="py-2 text-right">P. Unit</th>
                  <th className="py-2 text-right">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr className="border-t border-zinc-100">
                  <td className="py-2">{vistaPrevia.form.descripcion}</td>
                  <td className="py-2 text-right">{vistaPrevia.form.cantidad}</td>
                  <td className="py-2 text-right">${parseFloat(vistaPrevia.form.precioUnit).toLocaleString("es-AR")}</td>
                  <td className="py-2 text-right">${vistaPrevia.subtotal.toLocaleString("es-AR")}</td>
                </tr>
              </tbody>
            </table>
            <div className="border-t border-zinc-200 pt-4 space-y-1 text-sm">
              <div className="flex justify-between text-zinc-500"><span>IVA 21%</span><span>${vistaPrevia.iva.toLocaleString("es-AR")}</span></div>
              <div className="flex justify-between font-bold text-lg"><span>Total</span><span>${vistaPrevia.total.toLocaleString("es-AR")}</span></div>
            </div>
            <button
              onClick={() => window.print()}
              className="mt-6 w-full bg-zinc-900 text-white font-semibold py-3 rounded-xl hover:bg-zinc-700 transition-colors"
            >
              🖨️ Imprimir / Descargar PDF
            </button>
          </div>
        ) : tab === "preview" ? (
          <div className="text-center text-zinc-500 py-16">
            <p className="text-5xl mb-4">🧾</p>
            <p>Emite un comprobante primero desde "Nuevo Comprobante"</p>
          </div>
        ) : null}
      </div>
    </div>
  );
}
