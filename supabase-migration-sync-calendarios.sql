-- ============================================================
-- Six40 Booking — Migración: sincronizar todos los calendarios (jul 2026)
-- Ejecutar en: Supabase → SQL Editor. Seguro de re-ejecutar.
-- ============================================================
-- Guarda TODOS los eventos de Google de cada cita (barbero + local) para que
-- al cancelar o mover la cita se sincronicen ambos calendarios.

ALTER TABLE public.appointments ADD COLUMN IF NOT EXISTS google_events TEXT;
