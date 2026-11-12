# Kurzy a přihlašování účastníků

Webová aplikace (Python + Flask + SQLite) pro nabídku kurzů a přihlašování účastníků.

## Spuštění

```bash
pip install -r requirements.txt
python app.py
```

Aplikace poběží na http://127.0.0.1:5000. Databáze `kurzy.sqlite3` se vytvoří sama.

## Testy (automatická kontrola)

```bash
python -m pytest -v
```

Test `test_vytvoreni_kurzu_pres_web_a_overeni_ulozeni` odešle formulář nového kurzu a novým
připojením ověří, že je kurz opravdu uložený v databázi. Testy se spouští i na GitHubu (`.github/workflows/tests.yml`).

## Struktura

| Soubor | Co dělá |
|---|---|
| `db.py` | připojení k SQLite a schéma tabulek `course`, `participant`, `enrollment` |
| `services.py` | validace, CRUD, přihlášení do kurzu, hlídání kapacity, změna stavu |
| `app.py` | webové stránky (Flask routy) |
| `templates/` | HTML šablony |
| `tests/test_app.py` | automatické testy |

## Datový model

- **Course**: `name`, `code` (jedinečný), `capacity` (celé číslo > 0), `starts_at`
- **Participant**: `name`, `email` (platný a jedinečný)
- **Enrollment**: `course_id`, `participant_id`, `enrolled_at`, `status` (`enrolled` / `cancelled` / `completed`)

## Pravidla přihlašování

- Účastník může mít v kurzu jen jednu přihlášku (kontrola v kódu + `UNIQUE` v databázi).
- Místo v kurzu zabírají přihlášky ve stavu `enrolled` a `completed`; `cancelled` místo uvolní.
- Po zrušení se lze znovu přihlásit – původní přihláška se obnoví, pokud je volné místo.
- Kapacitu kurzu nelze snížit pod počet aktivních přihlášek.
- Přihlášení běží v transakci `BEGIN IMMEDIATE`, takže dva lidé nemohou současně zabrat poslední místo.
