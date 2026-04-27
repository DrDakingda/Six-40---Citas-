# Six40 Booking System

Plugin de WordPress para gestión de citas de **Sixcuarenta 640 Barbería** — locales de Málaga y Torremolinos.

## Características

- Formulario de reservas con shortcode `[six40_booking_form]`
- Slots de 30 minutos de 9:00 a 19:00
- Soporte para 2 locales y 8 barberos (4 por local)
- Sincronización con Google Calendar
- Emails de confirmación y cancelación vía Resend
- Panel de administración con dashboard, tabla de citas y calendario visual
- Gestión de disponibilidad de barberos (Disponible / Vacaciones / Baja)
- Base de datos en Supabase

## Requisitos

- WordPress 6.0+
- PHP 8.1+
- Cuenta en [Supabase](https://supabase.com) (gratuita)
- Cuenta en [Resend](https://resend.com) (gratuita hasta 3.000 emails/mes)
- Proyecto en [Google Cloud Console](https://console.cloud.google.com) con Calendar API activada

## Instalación rápida

1. Comprime la carpeta `six40-booking-system/` en un ZIP.
2. En WordPress → **Plugins → Añadir nuevo → Subir plugin**.
3. Selecciona el ZIP y pulsa **Instalar ahora → Activar**.
4. Importa `supabase-schema.sql` en tu proyecto de Supabase (SQL Editor).
5. Configura las credenciales en **Six40 Booking → Configuración**.
6. Crea una página con el shortcode `[six40_booking_form]`.

Consulta [INSTALL.md](INSTALL.md) para la guía completa paso a paso.

## Estructura del plugin

```
six40-booking-system/
├── six40-booking-system.php   # Archivo principal del plugin
├── supabase-schema.sql        # Esquema de base de datos
├── assets/
│   └── shortcode.php          # Shortcode [six40_booking_form]
├── includes/
│   ├── class-booking-api.php  # Lógica de citas (Supabase)
│   ├── class-google-calendar.php  # Integración Google Calendar
│   ├── class-email.php        # Emails transaccionales (Resend)
│   └── class-admin-panel.php  # Panel de administración
├── public/
│   ├── booking-form.php       # Template del formulario
│   ├── css/booking.css
│   └── js/booking.js
└── admin/
    ├── dashboard.php
    ├── css/admin.css
    └── js/admin.js
```

## Configuración

### Supabase
En **Six40 Booking → Configuración** rellena:
- **Project URL** — `https://xxxxx.supabase.co`
- **Service Role Key** — clave `service_role` (no la anon key)

### Google Calendar
- **Client ID** y **Client Secret** de Google Cloud Console
- **Calendar ID** para Málaga y Torremolinos
- URI de redirección OAuth: `https://six40.katibu.es/wp-admin/admin.php?page=six40-settings&oauth=google`

### Resend (email)
- **API Key** de Resend
- Remitente: `noreply@six40.katibu.es`

### Variables de entorno (opcional)
Para mayor seguridad, define las claves en `wp-config.php`:

```php
define('SIX40_SUPABASE_URL', 'https://xxxxx.supabase.co');
define('SIX40_SUPABASE_KEY', 'tu-service-role-key');
define('SIX40_RESEND_KEY',   'tu-resend-api-key');
```

## Uso del panel de administración

Accede desde WordPress → **Six40 Booking**.

| Sección | Descripción |
|---|---|
| Dashboard | Resumen del día, tabla de citas y calendario |
| Citas | Listado completo con filtros por local, fecha y estado |
| Barberos | Gestión de disponibilidad por local |
| Configuración | Credenciales de Supabase, Google y Resend |

### Estados de cita

| Estado | Significado |
|---|---|
| Confirmada | Cita activa |
| Completada | Cliente atendido |
| Cancelada | Cancelada (el cliente recibe email) |
| No presentó | Cliente no apareció |

### Disponibilidad de barberos

| Estado | Efecto |
|---|---|
| Disponible | Aparece en el formulario de reservas |
| Vacaciones | No aparece; citas existentes no se cancelan |
| Baja | No aparece; citas existentes no se cancelan |

## Licencia

GPL-2.0+
