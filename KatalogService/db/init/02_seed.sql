-- =====================================================================
-- Service 1 - Seed Data Katalog Event
-- =====================================================================

INSERT INTO artists (name, genre) VALUES
    ('Tulus',        'Pop'),
    ('Raisa',        'Pop'),
    ('Sheila on 7',  'Pop Rock'),
    ('Pamungkas',    'Indie'),
    ('Dewa 19',      'Rock');

-- CATATAN PENTING:
-- Konser "Pesta Rakyat Reunion" (Dewa 19) sengaja diberi kuota SANGAT KECIL (2)
-- untuk menguji skenario SOLD OUT / anti-overselling di Service 3.
INSERT INTO concerts (name, artist_id, venue, event_date, price, total_quota, available_quota) VALUES
    ('Manusia Tour 2026',     1, 'ICE BSD, Tangerang',           '2026-09-12 19:00:00+07', 500000, 1000, 1000),
    ('It''s Personal Live',   2, 'Jakarta Convention Center',    '2026-10-03 20:00:00+07', 750000,  500,  500),
    ('Tunggu Aku di Jakarta', 3, 'Stadion Madya GBK, Jakarta',   '2026-11-21 19:30:00+07', 600000, 2000, 2000),
    ('Solipsism 2.0',         4, 'Beach City Ancol, Jakarta',    '2026-08-30 18:00:00+07', 450000,  300,  300),
    ('Pesta Rakyat Reunion',  5, 'Gelora Bung Karno, Jakarta',   '2026-12-19 19:00:00+07', 850000,    2,    2);
