# Six40 Booking System — Guía de Instalación

## Requisitos previos
- WordPress 6.0+
- PHP 8.1+
- Cuenta en Supabase (gratuita)
- Cuenta en Resend (gratuita hasta 3.000 emails/mes)
- Proyecto en Google Cloud Console (para Calendar)

---

## 1. Instalar el plugin

1. Comprime la carpeta `six40-booking-system/` en un ZIP.
2. En WordPress → **Plugins → Añadir nuevo → Subir plugin**.
3. Selecciona el ZIP y pulsa **Instalar ahora → Activar**.

---

## 2. Configurar Supabase

### Crear el proyecto
1. Ve a [supabase.com](https://supabase.com) y crea un proyecto nuevo (ej. `six40`).
2. En **Settings → API**, copia:
   - **Project URL** (ej. `https://xxxxx.supabase.co`)
   - **service_role** secret key *(no la anon key)*

### Crear las tablas
1. En tu proyecto Supabase, ve a **SQL Editor**.
2. Pega el contenido de `supabase-schema.sql` y ejecuta.
3. Comprueba que la tabla `appointments` aparece en **Table Editor**.

### Configurar en WordPress
1. Ve a **Six40 Booking → Configuración**.
2. Rellena *Project URL* y *Service Role Key*.
3. Guarda.

---

## 3. Configurar Resend (email)

1. Crea cuenta en [resend.com](https://resend.com).
2. Verifica tu dominio (`six40.katibu.es`) en **Domains**.
3. En **API Keys**, genera una nueva clave.
4. En WordPress: **Six40 Booking → Configuración → Email (Resend)**.
   - API Key: pega la clave.
   - Email remitente: `noreply@six40.katibu.es`
   - Nombre: `Six40 Barbería`

---

## 4. Configurar Google Calendar

### Crear credenciales OAuth2
1. Ve a [Google Cloud Console](https://console.cloud.google.com).
2. Crea un proyecto o usa uno existente.
3. Activa la **Google Calendar API** (Biblioteca → busca "Calendar").
4. Ve a **APIs y servicios → Credenciales → Crear credencial → ID de cliente OAuth 2.0**.
   - Tipo: **Aplicación web**
   - URI de redirección autorizada:
     ```
     https://six40.katibu.es/wp-admin/admin.php?page=six40-settings&oauth=google
     ```
5. Copia el **Client ID** y **Client Secret**.

### Crear calendarios en Google
1. En [calendar.google.com](https://calendar.google.com), crea dos calendarios:
   - *Six40 Málaga*
   - *Six40 Torremolinos*
2. En la configuración de cada uno (⚙️ → Configuración del calendario), copia el **ID del calendario** (algo como `xxxx@group.calendar.google.com`).

### Configurar en WordPress
1. **Six40 Booking → Configuración → Google Calendar**.
2. Rellena Client ID, Client Secret, y los dos Calendar IDs.
3. **Guarda** primero.
4. Pulsa **Conectar con Google** y acepta los permisos.

---

## 5. Crear la página de reservas

1. En WordPress → **Páginas → Añadir nueva**.
2. Título: `Pide tu cita`
3. Slug: `pide-cita` (la URL quedará `/pide-cita/`)
4. En el contenido, añade el shortcode:
   ```
   [six40_booking_form]
   ```
5. Publica la página.

---

## 6. Verificación final

- [ ] Visita `https://six40.katibu.es/pide-cita/` y verifica que el formulario se muestra.
- [ ] Haz una reserva de prueba y comprueba:
  - El email de confirmación llega.
  - La cita aparece en el dashboard admin.
  - Se crea un evento en Google Calendar.
- [ ] En el admin, cambia el estado de un barbero a "Vacaciones" y verifica que sus slots desaparecen.

---

## Solución de problemas

| Problema | Solución |
|---|---|
| Formulario no carga | Verifica que el shortcode está en la página |
| No hay slots disponibles | Comprueba que hay barberos en estado "Disponible" |
| Error de Supabase | Verifica la URL y la service_role key |
| Email no llega | Comprueba el API key de Resend; revisa carpeta spam |
| Google Calendar no conecta | El URI de redirección debe coincidir exactamente |

---

## Variables de entorno (opcional con wp-config.php)

Para mayor seguridad, puedes definir las claves directamente en `wp-config.php`:

```php
define('SIX40_SUPABASE_URL',   'https://xxxxx.supabase.co');
define('SIX40_SUPABASE_KEY',   'tu-service-role-key');
define('SIX40_RESEND_KEY',     'tu-resend-api-key');
```

*(El plugin detecta estas constantes y las usa si están definidas — funcionalidad futura.)*
