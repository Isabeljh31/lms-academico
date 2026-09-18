import { useState } from "react";

const NAV_ITEMS = [
  { id: "panel", label: "Panel", icon: <GridIcon /> },
  { id: "cursos", label: "Cursos", icon: <BookIcon /> },
  { id: "calendario", label: "Calendario", icon: <CalendarIcon /> },
  { id: "reportes", label: "Reportes", icon: <ChartIcon /> },
  { id: "bot", label: "Propuestas del bot", icon: <BotIcon /> },
];

const COURSES = [
  { id: 1, title: "Matemática I", code: "MAT", progress: 20, nextDelivery: "14/09", color: "#1768AC", bg: "#E8F1FA" },
  { id: 2, title: "Historia del Perú", code: "HIS", progress: 25, nextDelivery: "24/09", color: "#2541B2", bg: "#EBF0FB" },
  { id: 3, title: "Introducción a la Programación", code: "PRG", progress: 25, nextDelivery: "24/09", color: "#06BEE1", bg: "#E0F9FD" },
];

const DELIVERIES = [
  { id: 1, title: "Ejercicios de repaso", course: "Matemática I", status: "Atrasado", statusColor: "#EF4444", statusBg: "#FEE2E2", icon: "📋" },
  { id: 2, title: "Práctica calificada 2", course: "Matemática I", status: "Pendiente", statusColor: "#F59E0B", statusBg: "#FEF3C7", icon: "📝" },
  { id: 3, title: "Práctica calificada 2", course: "Historia del Perú", status: "Pendiente", statusColor: "#F59E0B", statusBg: "#FEF3C7", icon: "📝" },
  { id: 4, title: "Práctica calificada 2", course: "Introducción a la Programación", status: "Pendiente", statusColor: "#F59E0B", statusBg: "#FEF3C7", icon: "📝" },
];

const CALENDAR_EVENTS: Record<number, string> = { 17: "today", 14: "event", 24: "event" };

function getDaysInMonth(year: number, month: number) {
  return new Date(year, month + 1, 0).getDate();
}
function getFirstDayOfMonth(year: number, month: number) {
  return new Date(year, month, 1).getDay();
}

export default function App() {
  const [activeNav, setActiveNav] = useState("panel");

  const year = 2026;
  const month = 8; // September
  const monthName = "Septiembre 2026";
  const daysInMonth = getDaysInMonth(year, month);
  const firstDay = getFirstDayOfMonth(year, month); // 0=Sun
  // Convert to Mon-first: if firstDay is 0 (Sun), offset = 6; else offset = firstDay - 1
  const offset = firstDay === 0 ? 6 : firstDay - 1;

  return (
    <div style={{ background: "#FFFFFF", minHeight: "100vh", display: "flex", fontFamily: "'Inter', sans-serif" }}>
      {/* Sidebar */}
      <aside style={{
        width: 220, minHeight: "100vh", background: "#FFFFFF",
        borderRight: "1px solid #E8ECF2", display: "flex", flexDirection: "column",
        padding: "0", flexShrink: 0, position: "sticky", top: 0, height: "100vh"
      }}>
        {/* Logo */}
        <div style={{ padding: "24px 20px 20px", borderBottom: "1px solid #E8ECF2" }}>
          <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
            <div style={{
              width: 34, height: 34, borderRadius: 9, background: "linear-gradient(135deg, #03256C 0%, #1768AC 100%)",
              display: "flex", alignItems: "center", justifyContent: "center"
            }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
            </div>
            <span style={{ fontWeight: 700, fontSize: 15, color: "#03256C", letterSpacing: "-0.3px" }}>LMS Académico</span>
          </div>
        </div>

        {/* Nav */}
        <nav style={{ padding: "12px 12px", flex: 1 }}>
          {NAV_ITEMS.map(item => {
            const active = activeNav === item.id;
            return (
              <button
                key={item.id}
                onClick={() => setActiveNav(item.id)}
                style={{
                  display: "flex", alignItems: "center", gap: 10,
                  width: "100%", padding: "10px 12px", borderRadius: 10, marginBottom: 2,
                  background: active ? "#EBF0FB" : "transparent",
                  border: "none", cursor: "pointer",
                  color: active ? "#2541B2" : "#6B7280",
                  fontWeight: active ? 600 : 400, fontSize: 14,
                  transition: "all 0.15s ease",
                  textAlign: "left",
                }}
              >
                <span style={{ color: active ? "#2541B2" : "#9CA3AF", flexShrink: 0 }}>{item.icon}</span>
                {item.label}
              </button>
            );
          })}
        </nav>

        {/* User */}
        <div style={{ padding: "16px 20px", borderTop: "1px solid #E8ECF2" }}>
          <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
            <div style={{
              width: 34, height: 34, borderRadius: "50%", background: "linear-gradient(135deg, #2541B2, #06BEE1)",
              display: "flex", alignItems: "center", justifyContent: "center",
              color: "white", fontWeight: 700, fontSize: 13
            }}>ED</div>
            <div>
              <div style={{ fontSize: 13, fontWeight: 600, color: "#1F2937" }}>Estudiante Demo</div>
              <div style={{ fontSize: 11, color: "#9CA3AF" }}>Estudiante</div>
            </div>
          </div>
        </div>
      </aside>

      {/* Main content */}
      <div style={{ flex: 1, display: "flex", flexDirection: "column", minWidth: 0 }}>
        {/* Topbar */}
        <header style={{
          height: 60, background: "#FFFFFF", borderBottom: "1px solid #E8ECF2",
          display: "flex", alignItems: "center", justifyContent: "space-between",
          padding: "0 28px", position: "sticky", top: 0, zIndex: 10
        }}>
          <h1 style={{ fontSize: 17, fontWeight: 700, color: "#03256C", margin: 0 }}>Panel</h1>
          <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
            <button style={{
              width: 36, height: 36, borderRadius: "50%", border: "1px solid #E8ECF2",
              background: "white", cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center"
            }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6B7280" strokeWidth="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0" />
              </svg>
            </button>
            <div style={{
              width: 36, height: 36, borderRadius: "50%", background: "linear-gradient(135deg, #2541B2, #06BEE1)",
              display: "flex", alignItems: "center", justifyContent: "center",
              color: "white", fontWeight: 700, fontSize: 13, cursor: "pointer"
            }}>ED</div>
          </div>
        </header>

        {/* Page body */}
        <main style={{ flex: 1, padding: "24px 28px", background: "#F5F7FA", overflow: "auto" }}>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 300px", gap: 24, maxWidth: 1200 }}>

            {/* Left column */}
            <div style={{ display: "flex", flexDirection: "column", gap: 24 }}>

              {/* Hero banner */}
              <div style={{
                borderRadius: 16, padding: "28px 32px",
                background: "linear-gradient(135deg, #03256C 0%, #1768AC 60%, #06BEE1 100%)",
                display: "flex", alignItems: "center", justifyContent: "space-between",
                boxShadow: "0 4px 20px rgba(3, 37, 108, 0.15)"
              }}>
                <div>
                  <p style={{ color: "rgba(255,255,255,0.7)", fontSize: 13, margin: "0 0 6px", fontWeight: 400 }}>Bienvenido de vuelta</p>
                  <h2 style={{ color: "white", fontSize: 28, fontWeight: 700, margin: "0 0 8px", letterSpacing: "-0.5px" }}>Hola, Estudiante</h2>
                  <p style={{ color: "rgba(255,255,255,0.8)", fontSize: 14, margin: "0 0 20px" }}>Sigues avanzando en 3 cursos. ¡Vamos por más!</p>
                  <button style={{
                    background: "white", color: "#2541B2", border: "none",
                    borderRadius: 10, padding: "10px 20px", fontWeight: 600, fontSize: 14,
                    cursor: "pointer", display: "flex", alignItems: "center", gap: 8,
                    boxShadow: "0 2px 8px rgba(0,0,0,0.1)"
                  }}>
                    Ver catálogo de cursos
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M5 12h14M12 5l7 7-7 7" /></svg>
                  </button>
                </div>
                <div style={{ position: "relative", flexShrink: 0 }}>
                  <svg width="110" height="110" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="50" fill="none" stroke="rgba(255,255,255,0.15)" strokeWidth="10" />
                    <circle cx="60" cy="60" r="50" fill="none" stroke="white" strokeWidth="10"
                      strokeDasharray={`${2 * Math.PI * 50 * 0.23} ${2 * Math.PI * 50 * 0.77}`}
                      strokeDashoffset={2 * Math.PI * 50 * 0.25}
                      strokeLinecap="round" transform="rotate(-90 60 60)" />
                    <text x="60" y="56" textAnchor="middle" fill="white" fontSize="18" fontWeight="700">23%</text>
                    <text x="60" y="72" textAnchor="middle" fill="rgba(255,255,255,0.7)" fontSize="10">progreso</text>
                  </svg>
                </div>
              </div>

              {/* Stats row */}
              <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 14 }}>
                {[
                  { label: "Cursos activos", value: "3", icon: "📚", color: "#2541B2", bg: "#EBF0FB" },
                  { label: "Tareas pendientes", value: "4", icon: "📋", color: "#F59E0B", bg: "#FEF3C7" },
                  { label: "Próxima entrega", value: "14/09", icon: "📅", color: "#06BEE1", bg: "#E0F9FD" },
                ].map((s, i) => (
                  <div key={i} style={{
                    background: "white", borderRadius: 14, padding: "18px 20px",
                    border: "1px solid #E8ECF2", display: "flex", alignItems: "center", gap: 14
                  }}>
                    <div style={{
                      width: 44, height: 44, borderRadius: 12, background: s.bg,
                      display: "flex", alignItems: "center", justifyContent: "center", fontSize: 20, flexShrink: 0
                    }}>{s.icon}</div>
                    <div>
                      <div style={{ fontSize: 20, fontWeight: 700, color: "#1F2937" }}>{s.value}</div>
                      <div style={{ fontSize: 12, color: "#6B7280" }}>{s.label}</div>
                    </div>
                  </div>
                ))}
              </div>

              {/* Mis cursos */}
              <div>
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 14 }}>
                  <h3 style={{ fontSize: 16, fontWeight: 700, color: "#1F2937", margin: 0, display: "flex", alignItems: "center", gap: 8 }}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2541B2" strokeWidth="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /></svg>
                    Mis cursos
                  </h3>
                  <button style={{ fontSize: 13, color: "#2541B2", background: "none", border: "none", cursor: "pointer", fontWeight: 500, display: "flex", alignItems: "center", gap: 4 }}>
                    Ver todo <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M5 12h14M12 5l7 7-7 7" /></svg>
                  </button>
                </div>
                <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 14 }}>
                  {COURSES.map(c => (
                    <div key={c.id} style={{
                      background: "white", borderRadius: 14, border: "1px solid #E8ECF2",
                      overflow: "hidden", cursor: "pointer",
                      transition: "transform 0.15s ease, box-shadow 0.15s ease",
                    }}
                      onMouseEnter={e => { (e.currentTarget as HTMLElement).style.transform = "translateY(-2px)"; (e.currentTarget as HTMLElement).style.boxShadow = "0 8px 24px rgba(0,0,0,0.08)"; }}
                      onMouseLeave={e => { (e.currentTarget as HTMLElement).style.transform = ""; (e.currentTarget as HTMLElement).style.boxShadow = ""; }}
                    >
                      <div style={{
                        height: 90, background: c.bg,
                        display: "flex", alignItems: "center", justifyContent: "center",
                        fontSize: 36, fontWeight: 900, color: c.color, letterSpacing: "-2px", opacity: 0.6
                      }}>{c.code}</div>
                      <div style={{ padding: "14px 16px" }}>
                        <p style={{ fontSize: 14, fontWeight: 600, color: "#1F2937", margin: "0 0 12px", lineHeight: 1.3 }}>{c.title}</p>
                        <div style={{ marginBottom: 10 }}>
                          <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 5 }}>
                            <span style={{ fontSize: 11, color: "#6B7280" }}>Progreso</span>
                            <span style={{ fontSize: 11, fontWeight: 600, color: c.color }}>{c.progress}%</span>
                          </div>
                          <div style={{ height: 5, background: "#F0F2F5", borderRadius: 99, overflow: "hidden" }}>
                            <div style={{ height: "100%", width: `${c.progress}%`, background: c.color, borderRadius: 99 }} />
                          </div>
                        </div>
                        <div style={{ display: "flex", alignItems: "center", gap: 5 }}>
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" strokeWidth="2"><circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" /></svg>
                          <span style={{ fontSize: 11, color: "#9CA3AF" }}>Próxima entrega: {c.nextDelivery}</span>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Próximas entregas */}
              <div>
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 14 }}>
                  <h3 style={{ fontSize: 16, fontWeight: 700, color: "#1F2937", margin: 0, display: "flex", alignItems: "center", gap: 8 }}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2541B2" strokeWidth="2"><circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" /></svg>
                    Próximas entregas
                  </h3>
                </div>
                <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                  {DELIVERIES.map(d => (
                    <div key={d.id} style={{
                      background: "white", borderRadius: 12, border: "1px solid #E8ECF2",
                      padding: "14px 18px", display: "flex", alignItems: "center", justifyContent: "space-between",
                      gap: 12
                    }}>
                      <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
                        <div style={{
                          width: 38, height: 38, borderRadius: 10, background: d.statusBg,
                          display: "flex", alignItems: "center", justifyContent: "center", fontSize: 18, flexShrink: 0
                        }}>{d.icon}</div>
                        <div>
                          <p style={{ margin: 0, fontSize: 14, fontWeight: 600, color: "#1F2937" }}>{d.title}</p>
                          <p style={{ margin: 0, fontSize: 12, color: "#9CA3AF" }}>{d.course}</p>
                        </div>
                      </div>
                      <span style={{
                        fontSize: 11, fontWeight: 600, color: d.statusColor,
                        background: d.statusBg, borderRadius: 99, padding: "4px 10px", flexShrink: 0
                      }}>{d.status}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* Right column */}
            <div style={{ display: "flex", flexDirection: "column", gap: 20 }}>

              {/* Calendar */}
              <div style={{ background: "white", borderRadius: 14, border: "1px solid #E8ECF2", padding: "20px" }}>
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 16 }}>
                  <h3 style={{ fontSize: 14, fontWeight: 700, color: "#1F2937", margin: 0, display: "flex", alignItems: "center", gap: 8 }}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2541B2" strokeWidth="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /></svg>
                    {monthName}
                  </h3>
                  <button style={{ fontSize: 12, color: "#2541B2", background: "none", border: "none", cursor: "pointer", fontWeight: 500 }}>Ver todo</button>
                </div>
                <div style={{ display: "grid", gridTemplateColumns: "repeat(7, 1fr)", gap: 4, marginBottom: 8 }}>
                  {["L", "M", "X", "J", "V", "S", "D"].map(d => (
                    <div key={d} style={{ textAlign: "center", fontSize: 11, fontWeight: 600, color: "#9CA3AF", padding: "2px 0" }}>{d}</div>
                  ))}
                </div>
                <div style={{ display: "grid", gridTemplateColumns: "repeat(7, 1fr)", gap: 4 }}>
                  {Array.from({ length: offset }).map((_, i) => <div key={`e${i}`} />)}
                  {Array.from({ length: daysInMonth }).map((_, i) => {
                    const day = i + 1;
                    const isToday = CALENDAR_EVENTS[day] === "today";
                    const hasEvent = CALENDAR_EVENTS[day] === "event";
                    return (
                      <div key={day} style={{
                        textAlign: "center", fontSize: 12, padding: "6px 2px", borderRadius: 8, cursor: "pointer",
                        fontWeight: isToday ? 700 : 400,
                        background: isToday ? "#2541B2" : hasEvent ? "#EBF0FB" : "transparent",
                        color: isToday ? "white" : hasEvent ? "#2541B2" : "#374151",
                        position: "relative"
                      }}>
                        {day}
                        {hasEvent && !isToday && <div style={{ position: "absolute", bottom: 2, left: "50%", transform: "translateX(-50%)", width: 4, height: 4, borderRadius: "50%", background: "#06BEE1" }} />}
                      </div>
                    );
                  })}
                </div>
              </div>

              {/* Upcoming events */}
              <div style={{ background: "white", borderRadius: 14, border: "1px solid #E8ECF2", padding: "20px" }}>
                <h3 style={{ fontSize: 14, fontWeight: 700, color: "#1F2937", margin: "0 0 14px" }}>Próximos eventos</h3>
                <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                  {[
                    { date: "24 SEP", title: "Práctica calificada 2", sub: "Matemática I" },
                    { date: "24 SEP", title: "Práctica calificada 2", sub: "Historia del Perú" },
                    { date: "24 SEP", title: "Práctica calificada 2", sub: "Introducción a la Prog." },
                  ].map((ev, i) => (
                    <div key={i} style={{ display: "flex", alignItems: "center", gap: 12 }}>
                      <div style={{
                        width: 40, height: 40, borderRadius: 10, background: "#EBF0FB",
                        display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center",
                        flexShrink: 0
                      }}>
                        <span style={{ fontSize: 14, fontWeight: 700, color: "#2541B2", lineHeight: 1 }}>{ev.date.split(" ")[0]}</span>
                        <span style={{ fontSize: 9, fontWeight: 600, color: "#6B7280", letterSpacing: "0.5px" }}>{ev.date.split(" ")[1]}</span>
                      </div>
                      <div>
                        <p style={{ margin: 0, fontSize: 13, fontWeight: 600, color: "#1F2937" }}>{ev.title}</p>
                        <p style={{ margin: 0, fontSize: 11, color: "#9CA3AF" }}>{ev.sub}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Accesos rápidos */}
              <div style={{ background: "white", borderRadius: 14, border: "1px solid #E8ECF2", padding: "20px" }}>
                <h3 style={{ fontSize: 14, fontWeight: 700, color: "#1F2937", margin: "0 0 14px" }}>Accesos rápidos</h3>
                <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 10 }}>
                  {[
                    { label: "Calendario", icon: <CalendarIcon />, color: "#2541B2", bg: "#EBF0FB" },
                    { label: "Asistente", icon: <BotIcon />, color: "#06BEE1", bg: "#E0F9FD" },
                    { label: "Mis cursos", icon: <BookIcon />, color: "#1768AC", bg: "#E8F1FA" },
                  ].map((q, i) => (
                    <button key={i} style={{
                      display: "flex", flexDirection: "column", alignItems: "center", gap: 8,
                      padding: "14px 8px", borderRadius: 12, border: "1px solid #E8ECF2",
                      background: "white", cursor: "pointer", transition: "background 0.15s"
                    }}
                      onMouseEnter={e => (e.currentTarget as HTMLElement).style.background = "#F5F7FA"}
                      onMouseLeave={e => (e.currentTarget as HTMLElement).style.background = "white"}
                    >
                      <div style={{
                        width: 38, height: 38, borderRadius: 10, background: q.bg,
                        display: "flex", alignItems: "center", justifyContent: "center",
                        color: q.color
                      }}>{q.icon}</div>
                      <span style={{ fontSize: 11, fontWeight: 500, color: "#374151" }}>{q.label}</span>
                    </button>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>
  );
}

// Icon components
function GridIcon() {
  return <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>;
}
function BookIcon() {
  return <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>;
}
function CalendarIcon() {
  return <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>;
}
function ChartIcon() {
  return <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>;
}
function BotIcon() {
  return <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" y1="16" x2="8" y2="16"/><line x1="16" y1="16" x2="16" y2="16"/></svg>;
}
