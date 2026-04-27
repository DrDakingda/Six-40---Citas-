# Six40 Booking System — Guía de uso para el equipo

## Acceder al panel de administración

1. Entra en WordPress: `https://six40.katibu.es/wp-admin`
2. En el menú lateral izquierdo verás **"Six40 Booking"**.

---

## Dashboard principal

Al entrar verás:
- **4 tarjetas** con el número de citas del día (Málaga, Torremolinos, total y barberos disponibles).
- **Tabla "Citas de hoy"** con todas las reservas del día actual.
- **Calendario** visual con todas las citas (puedes filtrar por local).

---

## Gestionar citas

### Ver todas las citas
Menú → **Citas**

Puedes filtrar por:
- **Local**: Málaga o Torremolinos
- **Desde**: fecha de inicio
- **Estado**: Confirmada, Completada, Cancelada, No presentó

### Cambiar el estado de una cita
En cualquier tabla de citas, en la columna **Acciones** verás un desplegable:

| Estado | Cuándo usarlo |
|---|---|
| **Confirmada** | Cita activa, cliente va a venir |
| **Completada** | Cliente pasó por la barbería |
| **Cancelada** | Cliente o barbería cancelaron |
| **No presentó** | Cliente no apareció |

Al cambiar el estado se guarda automáticamente. Si marcas **Cancelada**, el cliente recibe un email de aviso.

---

## Gestionar barberos

Menú → **Barberos**

Verás las dos secciones (Málaga y Torremolinos) con tarjetas para cada barbero.

### Cambiar disponibilidad
Cada tarjeta tiene 3 botones:

| Botón | Significado |
|---|---|
| ✅ **Disponible** | El barbero trabaja con normalidad |
| 🏖️ **Vacaciones** | De vacaciones, no aparece en las reservas |
| 🤒 **Baja** | Baja laboral, no aparece en las reservas |

El cambio se aplica **inmediatamente** — los clientes no podrán reservar con ese barbero hasta que vuelva a "Disponible".

---

## Calendario de citas

En el **Dashboard** puedes ver el calendario completo.

- **Vista mes**: visión general del mes.
- **Vista semana**: detalle hora a hora.
- **Vista día**: todas las citas del día.

**Colores:**
- 🟡 Amarillo: Confirmada
- 🟢 Verde: Completada
- 🔴 Rojo: Cancelada
- ⚫ Gris: No presentó

Haz clic en cualquier evento para ver los detalles (cliente, email, local).

---

## Preguntas frecuentes

**¿Qué pasa si un barbero coge vacaciones el día que ya tiene citas?**
Las citas existentes **no se cancelan** automáticamente. Deberás gestionarlas manualmente (cambiar estado a Cancelada o reasignar). Los nuevos clientes ya no podrán reservar con ese barbero.

**¿Puedo reservar yo mismo una cita desde el admin?**
Por ahora no. Las citas se crean siempre desde el formulario en `/pide-cita/`. En próximas versiones se añadirá esta opción.

**¿Qué horarios están activos?**
De 9:00 a 19:00, slots cada 30 minutos, para ambos locales.

**¿Cuántos barberos hay por local?**
4 barberos por local (8 en total). Se puede ampliar en una actualización futura.

**¿El cliente puede cancelar por su cuenta?**
Actualmente no, debe llamar o escribir. En próximas versiones se añadirá un enlace de cancelación en el email.
