"""Práce s databází SQLite: připojení a vytvoření tabulek (schéma)."""  # popis modulu

import sqlite3  # vestavěná knihovna Pythonu pro práci s databází SQLite

# SQL skript, který vytvoří všechny tři tabulky povinného datového modelu.
# "IF NOT EXISTS" zajistí, že se při opakovaném spuštění nic nesmaže ani nespadne.
SCHEMA = """
-- Tabulka kurzů (Course)
CREATE TABLE IF NOT EXISTS course (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,              -- jedinečné ID kurzu, generuje ho databáze
    name      TEXT    NOT NULL,                               -- název kurzu, nesmí chybět
    code      TEXT    NOT NULL UNIQUE COLLATE NOCASE,         -- kód kurzu, jedinečný (bez ohledu na velikost písmen)
    capacity  INTEGER NOT NULL CHECK (capacity > 0),          -- kapacita, databáze sama hlídá, že je > 0
    starts_at TEXT    NOT NULL                                -- termín začátku ve formátu ISO "YYYY-MM-DDTHH:MM"
);

-- Tabulka účastníků (Participant)
CREATE TABLE IF NOT EXISTS participant (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,                  -- jedinečné ID účastníka
    name  TEXT    NOT NULL,                                   -- jméno účastníka
    email TEXT    NOT NULL UNIQUE COLLATE NOCASE              -- e-mail, jedinečný (bez ohledu na velikost písmen)
);

-- Tabulka přihlášek (Enrollment) – propojuje účastníka s kurzem a má vlastní stav
CREATE TABLE IF NOT EXISTS enrollment (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,         -- jedinečné ID přihlášky
    course_id      INTEGER NOT NULL
                   REFERENCES course(id) ON DELETE CASCADE,   -- odkaz na kurz; smazáním kurzu zmizí i jeho přihlášky
    participant_id INTEGER NOT NULL
                   REFERENCES participant(id) ON DELETE CASCADE, -- odkaz na účastníka; smazáním účastníka zmizí jeho přihlášky
    enrolled_at    TEXT    NOT NULL,                          -- kdy se účastník přihlásil (ISO datum a čas)
    status         TEXT    NOT NULL DEFAULT 'enrolled'
                   CHECK (status IN ('enrolled', 'cancelled', 'completed')), -- povolené stavy přihlášky
    UNIQUE (course_id, participant_id)                        -- jeden účastník může mít v kurzu jen jednu přihlášku
);
"""  # konec SQL skriptu


def connect(path):  # funkce vrátí nové připojení k databázi na zadané cestě
    conn = sqlite3.connect(path, isolation_level=None)  # isolation_level=None = transakce řídíme sami (BEGIN/COMMIT)
    conn.row_factory = sqlite3.Row  # řádky výsledku půjde číst jako slovník: row["name"]
    conn.execute("PRAGMA foreign_keys = ON")  # SQLite má cizí klíče defaultně vypnuté, tady je zapínáme
    return conn  # vrátíme připravené připojení volajícímu


def init_db(conn):  # funkce vytvoří tabulky, pokud ještě neexistují
    conn.executescript(SCHEMA)  # spustí celý SQL skript najednou
