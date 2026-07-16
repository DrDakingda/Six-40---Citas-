-- ============================================================
-- Six40 Booking — Migración: Graciela solo en Málaga (jul 2026)
-- Ejecutar en: Supabase → SQL Editor. Seguro de re-ejecutar.
-- ============================================================
-- Graciela Arcos ya no atiende en Torremolinos (barbero id 6).
-- Se desactiva (no se borra: puede tener citas históricas asociadas).
-- get_barbers() filtra por active = true, así que deja de aparecer
-- en disponibilidad, asignación automática y panel.

UPDATE public.barbers SET active = false WHERE id = 6;

-- Sus tramos de horario en Torremolinos ya no aplican.
DELETE FROM public.barber_schedules WHERE barber_id = 6;
