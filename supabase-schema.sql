-- ============================================================
-- Six40 Booking System — Supabase Schema
-- Ejecutar en: Supabase → SQL Editor
-- ============================================================

CREATE TABLE IF NOT EXISTS public.appointments (
  id               BIGSERIAL PRIMARY KEY,
  location         TEXT        NOT NULL CHECK (location IN ('malaga', 'torremolinos')),
  service          TEXT        NOT NULL CHECK (service IN ('barba', 'corte', 'corte_barba')),
  date             DATE        NOT NULL,
  time             TIME        NOT NULL,
  end_time         TIME        NOT NULL,
  barber_id        SMALLINT    NOT NULL CHECK (barber_id BETWEEN 1 AND 8),
  customer_name    TEXT        NOT NULL,
  customer_email   TEXT        NOT NULL,
  status           TEXT        NOT NULL DEFAULT 'confirmed'
                                 CHECK (status IN ('confirmed', 'completed', 'cancelled', 'no_show')),
  notes            TEXT,
  created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Índices
CREATE INDEX IF NOT EXISTS idx_appt_date          ON public.appointments (date);
CREATE INDEX IF NOT EXISTS idx_appt_location_date ON public.appointments (location, date);
CREATE INDEX IF NOT EXISTS idx_appt_barber_date   ON public.appointments (barber_id, date);
CREATE INDEX IF NOT EXISTS idx_appt_status        ON public.appointments (status);
CREATE INDEX IF NOT EXISTS idx_appt_email         ON public.appointments (customer_email);

-- Trigger updated_at
CREATE OR REPLACE FUNCTION public.handle_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN NEW.updated_at = NOW(); RETURN NEW; END; $$;

DROP TRIGGER IF EXISTS set_updated_at ON public.appointments;
CREATE TRIGGER set_updated_at
  BEFORE UPDATE ON public.appointments
  FOR EACH ROW EXECUTE FUNCTION public.handle_updated_at();

-- RLS
ALTER TABLE public.appointments ENABLE ROW LEVEL SECURITY;
CREATE POLICY "read_all"   ON public.appointments FOR SELECT USING (true);
CREATE POLICY "insert_all" ON public.appointments FOR INSERT WITH CHECK (true);
CREATE POLICY "update_all" ON public.appointments FOR UPDATE USING (true);

-- Vista: citas de hoy
CREATE OR REPLACE VIEW public.appointments_today AS
SELECT *, EXTRACT(EPOCH FROM (end_time - time))/60 AS duration_minutes
FROM public.appointments
WHERE date = CURRENT_DATE ORDER BY time;

-- ============================================================
-- Mapa de barberos (referencia, no tabla — están hardcodeados en el plugin)
-- ID 1  Samuel Puertas    — Málaga
-- ID 2  Graciela Arcos    — Málaga
-- ID 3  Adrián Ortigosa   — Málaga
-- ID 4  Alejandro Alfonso — Málaga
-- ID 5  Antonio Pérez     — Torremolinos
-- ID 6  Graciela Arcos    — Torremolinos
-- ID 7  Juan Jose García  — Torremolinos
-- ID 8  Adrián Ortigosa   — Torremolinos
-- ============================================================

-- Datos de prueba (descomenta para testing)
/*
INSERT INTO public.appointments (location,service,date,time,end_time,barber_id,customer_name,customer_email,status) VALUES
  ('malaga','corte',CURRENT_DATE,'10:00','10:30',1,'Carlos García','carlos@test.com','confirmed'),
  ('malaga','barba',CURRENT_DATE,'10:30','10:45',4,'Luis Martínez','luis@test.com','confirmed'),
  ('torremolinos','corte_barba',CURRENT_DATE,'11:00','11:45',5,'Miguel Sánchez','miguel@test.com','confirmed');
*/
