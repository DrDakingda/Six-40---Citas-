# Six40 Booking System

Plugin de WordPress con el sistema de citas de **Sixcuarenta 640 Barbería** (Málaga y Torremolinos).

**Características:**
- Formulario de reserva estilo Typeform (6 pasos)
- Panel de administración con gestión de citas y barberos
- Cambios de horario temporales (múltiples franjas horarias por semana)
- Sincronización bidireccional con Google Calendar
- Gestión de citas por cliente (cancelar/modificar por enlace seguro)
- Correos automáticos de confirmación y cancelación

**Tech Stack:**
- Base de datos: Supabase (PostgreSQL vía REST)
- Calendarios: Google Calendar (OAuth2, por barbero + por local)
- Versión: 1.20.0

## Estructura

```
six40-booking-system.php      Bootstrap: hooks, AJAX, assets, cron
includes/
  class-booking-api.php       Lógica de disponibilidad, citas (Supabase)
  class-google-calendar.php   Sincronización Google Calendar + OAuth2
  class-email.php             Correos automáticos
  class-admin-panel.php       Panel admin: Citas, Barberos, Configuración
  class-manage.php            Gestión de cita por cliente (token-based)
admin/                        UI y assets del panel de administración
public/                       Formulario de reserva (shortcode) + assets
assets/shortcode.php          Registro del shortcode [six40_booking]
```

## Instalación

### 1. Supabase (Base de datos)
- Crear proyecto en Supabase
- Ejecutar `supabase-schema.sql` en SQL Editor
- ✅ Incluye todas las tablas y esquema final (no requiere migraciones individuales)

### 2. WordPress
- Subir el plugin (carpeta o ZIP)
- Activar en Plugins

### 3. Configuración
Ir a **Six40 Booking → Configuración** y rellenar:
- **Supabase**: Project URL + Service Role Key (nunca anon)
- **Google Calendar**: Client ID + Secret (OAuth2)
- Conectar con Google y asignar Calendar ID por local y barbero
- Email remitente y días festivos (opcional)

### 4. Publicar
Insertar el shortcode `[six40_booking]` en la página de reservas.

## Cambios de horario temporales

**Función**: Agregar franjas horarias extras para barberos en semanas específicas.

**Ejemplo**: Adrián normalmente trabaja 10:00-15:00, pero la próxima semana quieres que esté disponible también 16:00-20:00.

**Desde el admin:**
1. Ir a **Six40 Booking → Barberos**
2. En la tarjeta del barbero, sección "CAMBIOS DE HORARIO TEMPORALES"
3. Agregar: Desde/Hasta + Hora inicio/fin
4. ✅ Las franjas se suman (no reemplazan)

## Notas de desarrollo

- Configuración local sensible: `six40-config.php` (gitignorado)
- Reglas de duración: duplicadas en `class-booking-api.php` (backend) y `public/js/booking.js` (frontend) — sincronizar si cambias
- Barberos hardcodeados: en `public/js/booking.js` (`barbersByLocation`, avatares) y tabla `barbers` en Supabase
- Hardcodes actuales: Adrián (ID 8) disponible 3-7 agosto 16:00-20:00 en Torremolinos

## Migraciones

Si trabajas con una **instalación existente** (no una nueva), ver [MIGRATIONS.md](MIGRATIONS.md).
