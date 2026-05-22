-- ─────────────────────────────────────────────────────────────────────
-- PostgreSQL initialisation
--
-- Scripts in this directory run once, in alphabetical order, the first
-- time the database container starts against an empty data volume.
-- They execute against the database named by POSTGRES_DB.
--
-- To re-run them, remove the volume:  docker compose down -v
-- ─────────────────────────────────────────────────────────────────────

-- Case-insensitive text — handy for emails and usernames.
CREATE EXTENSION IF NOT EXISTS citext;

-- Cryptographic functions, incl. gen_random_uuid() for UUID keys.
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Accent-insensitive search (e.g. course titles).
CREATE EXTENSION IF NOT EXISTS unaccent;
