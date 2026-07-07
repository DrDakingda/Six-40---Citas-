-- ============================================================
-- Six40 Booking — Migración: anulación desde Google Calendar (jul 2026)
-- Ejecutar en: Supabase → SQL Editor. Seguro de re-ejecutar.
-- ============================================================
-- Guarda el evento de Google de cada cita para poder detectar cuando el
-- barbero lo borra/cancela en su calendario y avisar al cliente.

ALTER TABLE public.appointments ADD COLUMN IF NOT EXISTS google_event_id    TEXT;
ALTER TABLE public.appointments ADD COLUMN IF NOT EXISTS google_calendar_id TEXT;
