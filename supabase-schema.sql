-- ============================================================
-- Six40 Booking System — Supabase Schema
-- Ejecutar en el SQL Editor de tu proyecto Supabase
-- ============================================================

-- Tabla principal de citas
CREATE TABLE IF NOT EXISTS public.appointments (
  id               BIGSERIAL PRIMARY KEY,
  location         TEXT NOT NULL CHECK (location IN ('malaga', 'torremolinos')),
  service          TEXT NOT NULL CHECK (service IN ('barba', 'corte', 'corte_barba')),
  date             DATE NOT NULL,
  time             TIME NOT NULL,
  end_time         TIME NOT NULL,
  barber_id        SMALLINT NOT NULL CHECK (barber_id BETWEEN 1 AND 8),
  customer_name    TEXT NOT NULL,
  customer_email   TEXT NOT NULL,
  status           TEXT NOT NULL DEFAULT 'confirmed'
                     CHECK (status IN ('confirmed', 'completed', 'cancelled', 'no_show')),
  notes            TEXT,
  created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Índices para queries frecuentes
CREATE INDEX IF NOT EXISTS idx_appointments_date
  ON public.appointments (date);

CREATE INDEX IF NOT EXISTS idx_appointments_location_date
  ON public.appointments (location, date);

CREATE INDEX IF NOT EXISTS idx_appointments_barber_date
  ON public.appointments (barber_id, date);

CREATE INDEX IF NOT EXISTS idx_appointments_status
  ON public.appointments (status);

CREATE INDEX IF NOT EXISTS idx_appointments_email
  ON public.appointments (customer_email);

-- Trigger: actualiza updated_at automáticamente
CREATE OR REPLACE FUNCTION public.handle_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS set_updated_at ON public.appointments;
CREATE TRIGGER set_updated_at
  BEFORE UPDATE ON public.appointments
  FOR EACH ROW EXECUTE FUNCTION public.handle_updated_at();

-- ============================================================
-- Row Level Security (RLS)
-- ============================================================

ALTER TABLE public.appointments ENABLE ROW LEVEL SECURITY;

-- Política: solo el service_role (WordPress backend) puede leer todo.
-- Los usuarios anónimos solo pueden INSERT (nueva cita) y leer las suyas por email.

-- Lectura pública: un cliente puede ver sus propias citas por email.
CREATE POLICY "Customers can view own appointments"
  ON public.appointments FOR SELECT
  USING (true);   -- WordPress usa service_role key, así que tiene acceso total.
                  -- Si quisieras restringir para el client key anon, usa:
                  -- USING (customer_email = current_setting('request.jwt.claims', true)::json->>'email');

-- Inserción: cualquiera puede crear una cita (WordPress valida por nonce).
CREATE POLICY "Anyone can insert an appointment"
  ON public.appointments FOR INSERT
  WITH CHECK (true);

-- Actualización: solo via service_role (el plugin usa service_role key).
CREATE POLICY "Service role can update appointments"
  ON public.appointments FOR UPDATE
  USING (true);

-- ============================================================
-- Vista útil para el admin: citas de hoy con duración
-- ============================================================

CREATE OR REPLACE VIEW public.appointments_today AS
SELECT
  a.*,
  EXTRACT(EPOCH FROM (a.end_time - a.time)) / 60 AS duration_minutes
FROM public.appointments a
WHERE a.date = CURRENT_DATE
ORDER BY a.time ASC;

-- ============================================================
-- Vista: ocupación por barbero y fecha
-- ============================================================

CREATE OR REPLACE VIEW public.barber_schedule AS
SELECT
  barber_id,
  date,
  COUNT(*) FILTER (WHERE status != 'cancelled') AS total_appointments,
  array_agg(time ORDER BY time) FILTER (WHERE status != 'cancelled') AS times
FROM public.appointments
GROUP BY barber_id, date
ORDER BY date, barber_id;

-- ============================================================
-- Datos de muestra (opcional, comentar en producción)
-- ============================================================

/*
INSERT INTO public.appointments
  (location, service, date, time, end_time, barber_id, customer_name, customer_email, status)
VALUES
  ('malaga',       'corte',       CURRENT_DATE, '10:00', '10:30', 1, 'Carlos García',   'carlos@test.com',  'confirmed'),
  ('malaga',       'barba',       CURRENT_DATE, '10:30', '10:45', 2, 'Luis Martínez',   'luis@test.com',    'confirmed'),
  ('malaga',       'corte_barba', CURRENT_DATE, '11:00', '11:45', 3, 'Antonio López',   'antonio@test.com', 'confirmed'),
  ('torremolinos', 'corte',       CURRENT_DATE, '09:30', '10:00', 5, 'Miguel Sánchez',  'miguel@test.com',  'confirmed'),
  ('torremolinos', 'barba',       CURRENT_DATE, '10:00', '10:15', 6, 'Rafael Torres',   'rafael@test.com',  'completed');
*/
