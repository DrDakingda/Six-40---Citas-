# Six40 Booking System

Plugin de WordPress con el sistema de citas de **Sixcuarenta 640 Barbería** (Málaga y Torremolinos): formulario de reserva en 6 pasos estilo Typeform, panel de administración, sincronización con Google Calendar y gestión de cita por el cliente (cancelar/modificar por enlace con token).

- **Producción:** https://six40.katibu.es/reservar/
- **Base de datos:** Supabase (PostgreSQL vía REST)
- **Calendarios:** Google Calendar (OAuth2, calendario por barbero + por local)

## Estructura

```
six40-booking-system.php      Bootstrap: hooks, AJAX, assets, cron
includes/
  class-booking-api.php       Lógica de citas y disponibilidad (Supabase REST)
  class-google-calendar.php   Eventos, freeBusy y OAuth2 con Google
  class-email.php             Correos de confirmación/cancelación (wp_mail)
  class-admin-panel.php       Menú admin: Citas, Barberos, Configuración
  class-manage.php            Página pública de gestión de cita (?six40_manage=TOKEN)
admin/                        Vista y assets del panel de administración
public/                       Formulario de reserva (shortcode) + assets
assets/shortcode.php          Registro del shortcode [six40_booking]
supabase-schema.sql           Schema CONSOLIDADO (instalación desde cero)
supabase-migration-*.sql      Histórico de migraciones (instalaciones en marcha)
```

## Instalación

1. **Supabase**: crear proyecto y ejecutar `supabase-schema.sql` en SQL Editor (ya incluye todas las migraciones; no hace falta ejecutarlas en una instalación nueva).
2. **WordPress**: subir el plugin (zip de esta carpeta) y activarlo.
3. **Configuración** (menú Six40 Booking → Configuración):
   - Supabase: Project URL + **Service Role Key** (nunca la anon).
   - Google: Client ID + Secret (OAuth2 tipo web), conectar, y asignar Calendar ID por local y por barbero.
   - Email remitente y festivos.
4. Insertar el shortcode `[six40_booking]` en la página de reservas.

## Migraciones (solo instalaciones ya en marcha)

Orden cronológico de ejecución en Supabase → SQL Editor (todas son seguras de re-ejecutar):

| # | Archivo | Qué hace |
|---|---------|----------|
| 1 | `supabase-migration-precios.sql` | Precios, categorías y servicios por local (recarga `services`) |
| 2 | `supabase-migration-duraciones.sql` | Barbas a 20 min |
| 3 | `supabase-migration-quitar-depilacion.sql` | Elimina servicios de depilación |
| 4 | `supabase-migration-cancelaciones.sql` | Columnas de evento de Google en `appointments` |
| 5 | `supabase-migration-gestion.sql` | Token de gestión por cita + índice |
| 6 | `supabase-migration-sync-calendarios.sql` | Columna `google_events` (todos los calendarios) |
| 7 | `supabase-migration-graciela-solo-malaga.sql` | Desactiva a Graciela en Torremolinos |

## Desarrollo

- La configuración local sensible va en `six40-config.php` (gitignorado).
- Reglas de duración por combinación (corte+barba=40 min, corte+mechas=60…) viven duplicadas en `class-booking-api.php::calculate_service_duration()` y `public/js/booking.js::computeDuration()` — si cambias una, cambia la otra.
- Los barberos están hardcodeados en `public/js/booking.js` (`barbersByLocation`, avatares) además de en la tabla `barbers` de Supabase.
