-- =====================================================================
-- Service 1 - Skema Katalog Event (PostgreSQL)
-- Berkas ini dijalankan OTOMATIS oleh container Postgres saat database
-- pertama kali dibuat (lihat volume docker-entrypoint-initdb.d).
-- =====================================================================

CREATE TABLE IF NOT EXISTS artists (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    genre       VARCHAR(80),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS concerts (
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    artist_id       INTEGER NOT NULL REFERENCES artists(id) ON DELETE CASCADE,
    venue           VARCHAR(200) NOT NULL,
    event_date      TIMESTAMPTZ NOT NULL,
    price           NUMERIC(12,2) NOT NULL CHECK (price >= 0),
    total_quota     INTEGER NOT NULL CHECK (total_quota >= 0),
    available_quota INTEGER NOT NULL CHECK (available_quota >= 0),  -- lapisan pertahanan: tak boleh < 0
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT available_not_exceed_total CHECK (available_quota <= total_quota)
);

CREATE INDEX IF NOT EXISTS idx_concerts_artist_id  ON concerts(artist_id);
CREATE INDEX IF NOT EXISTS idx_concerts_event_date ON concerts(event_date);
