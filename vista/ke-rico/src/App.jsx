import { useState } from 'react'
import GestionVentas from './componentes/GestionVentas.jsx'
import AlertasStock from './componentes/AlertasStock.jsx'
import Descuentos from './componentes/Descuentos.jsx'
import EmisionComprobantes from './componentes/EmisionComprobantes.jsx'
import SistemaGestion from './componentes/SistemaGestion.jsx'

const modulos = [
  { id: 'ventas', label: '📦 Ventas', componente: <GestionVentas /> },
  { id: 'alertas', label: '🔔 Alertas de Stock', componente: <AlertasStock /> },
  { id: 'descuentos', label: '🏷️ Descuentos', componente: <Descuentos /> },
  { id: 'comprobantes', label: '🧾 Comprobantes', componente: <EmisionComprobantes /> },
  { id: 'gestion', label: '📋 Gestión (RF5-RF8)', componente: <SistemaGestion /> },
]

function App() {
  const [activo, setActivo] = useState('ventas')
  const moduloActivo = modulos.find((m) => m.id === activo) ?? modulos[0]

  return (
    <div style={{ minHeight: '100vh', background: '#0f172a' }}>
      <nav
        style={{
          display: 'flex',
          flexWrap: 'wrap',
          gap: 8,
          padding: '12px 16px',
          background: '#020617',
          borderBottom: '1px solid #1e293b',
          position: 'sticky',
          top: 0,
          zIndex: 10,
        }}
      >
        {modulos.map((m) => (
          <button
            key={m.id}
            type="button"
            onClick={() => setActivo(m.id)}
            style={{
              padding: '8px 16px',
              borderRadius: 9999,
              fontSize: 14,
              fontWeight: 500,
              cursor: 'pointer',
              border: '1px solid ' + (activo === m.id ? '#38bdf8' : '#1e293b'),
              background: activo === m.id ? '#0ea5e9' : '#0f172a',
              color: activo === m.id ? '#fff' : '#94a3b8',
            }}
          >
            {m.label}
          </button>
        ))}
      </nav>
      {moduloActivo.componente}
    </div>
  )
}

export default App
