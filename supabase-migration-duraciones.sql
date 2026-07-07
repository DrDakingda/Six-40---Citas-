-- ============================================================
-- Six40 Booking — Migración: ajuste de duraciones (jul 2026)
-- Ejecutar en: Supabase → SQL Editor. Seguro de re-ejecutar.
-- ============================================================
-- Nota: las reglas por combinación (corte+barba=40, corte+mechas=60, etc.)
-- se aplican en el plugin (PHP/JS). Estas duraciones base se usan para
-- mostrar los minutos de cada servicio y para las combinaciones "sin corte".

-- Todas las barbas: 20 min
UPDATE public.services SET duration = 20 WHERE category = 'barba';

-- Toda la depilación: 10 min cada una
UPDATE public.services SET duration = 10 WHERE category = 'depilacion';
