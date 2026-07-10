-- ============================================================
-- Six40 Booking — Migración: gestión de cita por el cliente (jul 2026)
-- Ejecutar en: Supabase → SQL Editor. Seguro de re-ejecutar.
-- ============================================================
-- Token único por cita para el enlace de cancelar/modificar que se envía
-- en el email y en el evento de Google Calendar.

ALTER TABLE public.appointments ADD COLUMN IF NOT EXISTS manage_token TEXT;
CREATE INDEX IF NOT EXISTS idx_appt_manage_token ON public.appointments (manage_token);
