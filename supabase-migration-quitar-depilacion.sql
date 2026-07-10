-- ============================================================
-- Six40 Booking — Migración: eliminar depilación (jul 2026)
-- Ejecutar en: Supabase → SQL Editor. Seguro de re-ejecutar.
-- ============================================================
-- La barbería prescinde de los servicios de depilación.

-- Quitar referencias en citas (por si alguna de prueba los usaba)
DELETE FROM public.appointment_services
  WHERE service_id IN (SELECT id FROM public.services WHERE category = 'depilacion');

-- Eliminar los servicios de depilación
DELETE FROM public.services WHERE category = 'depilacion';
