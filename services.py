"""Business logika: validace, CRUD kurzů a účastníků, přihlašování a hlídání kapacity."""  # popis modulu

import re  # regulární výrazy – použijeme je na kontrolu formátu e-mailu
import sqlite3  # potřebujeme kvůli zachycení chyby sqlite3.IntegrityError
from contextlib import contextmanager  # dekorátor pro vytvoření vlastního bloku "with"
from datetime import datetime  # práce s datem a časem

# Stav "přihlášen" – účastník má v kurzu rezervované místo.
STATUS_ENROLLED = "enrolled"
# Stav "zrušeno" – účastník se odhlásil, místo se uvolnilo.
STATUS_CANCELLED = "cancelled"
# Stav "dokončeno" – účastník kurz absolvoval.
STATUS_COMPLETED = "completed"
# Seznam všech povolených stavů (pořadí se použije i ve výběru na webu).
STATUSES = (STATUS_ENROLLED, STATUS_CANCELLED, STATUS_COMPLETED)
# "Aktivní" přihlášky zabírají místo v kurzu. Dokončená přihláška místo nevrací
# (účastník kurz skutečně absolvoval), zrušená ano – proto tu cancelled chybí.
ACTIVE_STATUSES = (STATUS_ENROLLED, STATUS_COMPLETED)

# Jednoduchý regulární výraz pro e-mail: něco@něco.domena, bez mezer a bez druhého "@".
EMAIL_RE = re.compile(r"^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$")

# SQL výraz, který spočítá aktivní přihlášky kurzu "c" (použijeme ho ve více dotazech).
ACTIVE_COUNT_SQL = (
    "(SELECT COUNT(*) FROM enrollment e "  # počítáme řádky v tabulce přihlášek
    " WHERE e.course_id = c.id "  # jen přihlášky tohoto kurzu
    f" AND e.status IN ({', '.join(repr(s) for s in ACTIVE_STATUSES)}))"  # jen aktivní stavy
)


class ValidationError(Exception):  # vlastní výjimka pro chyby zadaných dat
    """Chyba validace – nese seznam srozumitelných zpráv pro uživatele."""  # popis třídy

    def __init__(self, errors):  # konstruktor dostane jednu zprávu nebo seznam zpráv
        if isinstance(errors, str):  # pokud přišel jen jeden text...
            errors = [errors]  # ...zabalíme ho do seznamu, ať se s tím pracuje jednotně
        self.errors = errors  # uložíme seznam chyb, šablony ho pak vypíšou
        super().__init__("; ".join(errors))  # text výjimky = všechny chyby spojené středníkem


class NotFoundError(Exception):  # výjimka pro případ, že záznam s daným ID neexistuje
    pass  # žádné další chování nepotřebujeme


@contextmanager  # z funkce uděláme kontextový manažer, používá se jako "with transaction(conn):"
def transaction(conn):  # zajistí, že se změny uloží celé, nebo vůbec
    conn.execute("BEGIN IMMEDIATE")  # zahájí transakci a hned zamkne databázi pro zápis (ochrana před souběhem)
    try:  # zkusíme provést kód uvnitř bloku "with"
        yield  # tady se spustí kód uvnitř bloku "with"
    except Exception:  # pokud cokoli selhalo...
        conn.execute("ROLLBACK")  # ...vrátíme všechny změny zpět
        raise  # a chybu pošleme dál volajícímu
    else:  # pokud vše proběhlo bez chyby...
        conn.execute("COMMIT")  # ...změny trvale uložíme


def now_iso():  # pomocná funkce: aktuální čas jako text
    return datetime.now().isoformat(timespec="seconds")  # např. "2026-11-12T09:30:00"


# ---------------------------------------------------------------------------
# Validace vstupů
# ---------------------------------------------------------------------------


def _clean(value):  # pomocná funkce: převede vstup na text a ořízne mezery
    return (value or "").strip()  # None se změní na "", pak odstraníme mezery na krajích


def parse_capacity(raw):  # převede kapacitu na celé číslo > 0, jinak vrátí None
    if isinstance(raw, bool):  # True/False jsou v Pythonu taky čísla, ale kapacitou nejsou
        return None  # proto je odmítneme
    if isinstance(raw, int):  # pokud už je to celé číslo...
        return raw if raw > 0 else None  # ...stačí ověřit, že je větší než 0
    text = _clean(str(raw)) if raw is not None else ""  # jinak s tím pracujeme jako s textem
    if not re.fullmatch(r"\d+", text):  # povolíme jen číslice (žádné "2.5", "-3", "abc")
        return None  # neplatný vstup
    value = int(text)  # text převedeme na celé číslo
    return value if value > 0 else None  # nula není platná kapacita


def parse_starts_at(raw):  # převede termín na normalizovaný text "YYYY-MM-DDTHH:MM", jinak None
    text = _clean(raw)  # odstraníme mezery
    if not text:  # prázdný termín je neplatný
        return None  # vrátíme None = chyba
    try:  # pokusíme se text přečíst jako datum a čas
        dt = datetime.fromisoformat(text)  # podporuje "2026-12-01T09:00" i "2026-12-01 09:00"
    except ValueError:  # text není platné datum
        return None  # vrátíme None = chyba
    return dt.strftime("%Y-%m-%dT%H:%M")  # jednotný formát, aby šlo termíny řadit a porovnávat jako text


def validate_course(conn, data, course_id=None):  # zkontroluje data kurzu, vrátí vyčištěný slovník
    errors = []  # sem budeme sbírat všechny nalezené chyby
    name = _clean(data.get("name"))  # název kurzu bez mezer na krajích
    code = _clean(data.get("code"))  # kód kurzu bez mezer na krajích
    capacity = parse_capacity(data.get("capacity"))  # kapacita jako číslo, nebo None
    starts_at = parse_starts_at(data.get("starts_at"))  # termín v jednotném formátu, nebo None

    if not name:  # název je povinný
        errors.append("Název kurzu je povinný.")  # přidáme chybovou zprávu
    if not code:  # kód je povinný
        errors.append("Kód kurzu je povinný.")  # přidáme chybovou zprávu
    elif conn.execute(  # pokud kód vyplněn, ověříme jeho jedinečnost v databázi
        "SELECT 1 FROM course WHERE code = ? AND id IS NOT ?",  # hledáme jiný kurz se stejným kódem
        (code, course_id),  # při úpravě vynecháme kurz sám sebe (id IS NOT ?)
    ).fetchone():  # fetchone() vrátí řádek, pokud nějaký existuje
        errors.append(f"Kurz s kódem „{code}“ už existuje.")  # kód je obsazený
    if capacity is None:  # kapacita není celé číslo > 0
        errors.append("Kapacita musí být celé číslo větší než 0.")  # přidáme chybovou zprávu
    elif course_id is not None:  # při úpravě existujícího kurzu...
        active = count_active(conn, course_id)  # ...zjistíme, kolik míst je už obsazeno
        if capacity < active:  # kapacitu nesmíme snížit pod počet aktivních přihlášek
            errors.append(f"Kapacitu nelze snížit pod počet aktivních přihlášek ({active}).")  # chyba
    if starts_at is None:  # termín chybí nebo je neplatný
        errors.append("Termín začátku musí být platné datum a čas.")  # přidáme chybovou zprávu

    if errors:  # pokud jsme našli aspoň jednu chybu...
        raise ValidationError(errors)  # ...vyhodíme výjimku se všemi chybami najednou
    return {"name": name, "code": code, "capacity": capacity, "starts_at": starts_at}  # vyčištěná data


def validate_participant(conn, data, participant_id=None):  # zkontroluje data účastníka
    errors = []  # seznam chyb
    name = _clean(data.get("name"))  # jméno bez mezer na krajích
    email = _clean(data.get("email")).lower()  # e-mail bez mezer a malými písmeny (jednotný tvar)

    if not name:  # jméno je povinné
        errors.append("Jméno účastníka je povinné.")  # chybová zpráva
    if not EMAIL_RE.match(email):  # e-mail neodpovídá formátu něco@něco.domena
        errors.append("Zadejte platný e-mail.")  # chybová zpráva
    elif conn.execute(  # e-mail má správný formát, ověříme jedinečnost
        "SELECT 1 FROM participant WHERE email = ? AND id IS NOT ?",  # jiný účastník se stejným e-mailem?
        (email, participant_id),  # při úpravě vynecháme účastníka samotného
    ).fetchone():  # vrátí řádek, pokud takový účastník existuje
        errors.append(f"Účastník s e-mailem „{email}“ už existuje.")  # e-mail je obsazený

    if errors:  # našli jsme chyby?
        raise ValidationError(errors)  # vyhodíme výjimku se všemi chybami
    return {"name": name, "email": email}  # vyčištěná data


# ---------------------------------------------------------------------------
# Kurzy – CRUD (Create, Read, Update, Delete)
# ---------------------------------------------------------------------------


def create_course(conn, data):  # vytvoří nový kurz a vrátí jeho ID
    with transaction(conn):  # vše proběhne v jedné transakci
        clean = validate_course(conn, data)  # nejdřív data zkontrolujeme
        try:  # vložení může selhat, pokud by kód mezitím někdo obsadil
            cur = conn.execute(  # provedeme INSERT
                "INSERT INTO course (name, code, capacity, starts_at) VALUES (?, ?, ?, ?)",  # SQL s parametry (ochrana proti SQL injection)
                (clean["name"], clean["code"], clean["capacity"], clean["starts_at"]),  # hodnoty místo otazníků
            )
        except sqlite3.IntegrityError:  # databáze odmítla záznam (porušení UNIQUE/CHECK)
            raise ValidationError("Kurz se nepodařilo uložit – kód už existuje.")  # převedeme na srozumitelnou chybu
    return cur.lastrowid  # ID právě vloženého řádku


def update_course(conn, course_id, data):  # upraví existující kurz
    with transaction(conn):  # jedna transakce
        get_course(conn, course_id)  # ověří, že kurz existuje (jinak NotFoundError)
        clean = validate_course(conn, data, course_id=course_id)  # validace, ignoruje vlastní kód kurzu
        conn.execute(  # provedeme UPDATE
            "UPDATE course SET name = ?, code = ?, capacity = ?, starts_at = ? WHERE id = ?",  # SQL příkaz
            (clean["name"], clean["code"], clean["capacity"], clean["starts_at"], course_id),  # nové hodnoty + ID
        )


def delete_course(conn, course_id):  # smaže kurz (přihlášky se smažou automaticky díky ON DELETE CASCADE)
    with transaction(conn):  # jedna transakce
        cur = conn.execute("DELETE FROM course WHERE id = ?", (course_id,))  # smažeme řádek
        if cur.rowcount == 0:  # nic se nesmazalo = kurz neexistoval
            raise NotFoundError(f"Kurz {course_id} neexistuje.")  # oznámíme chybu


def get_course(conn, course_id):  # načte jeden kurz včetně obsazenosti
    row = conn.execute(  # dotaz do databáze
        f"SELECT c.*, {ACTIVE_COUNT_SQL} AS active_count FROM course c WHERE c.id = ?",  # kurz + počet aktivních přihlášek
        (course_id,),  # ID kurzu jako parametr
    ).fetchone()  # očekáváme nejvýš jeden řádek
    if row is None:  # kurz nebyl nalezen
        raise NotFoundError(f"Kurz {course_id} neexistuje.")  # vyhodíme chybu
    return _course_dict(row)  # převedeme na slovník s dopočítanými hodnotami


def _course_dict(row):  # pomocná funkce: řádek z DB -> slovník + volná místa
    course = dict(row)  # sqlite3.Row převedeme na obyčejný slovník
    course["free_seats"] = course["capacity"] - course["active_count"]  # volná místa = kapacita - obsazená
    return course  # vrátíme slovník


def list_courses(conn, date_from=None, date_to=None, only_free=False):  # seznam kurzů s filtrem
    sql = f"SELECT c.*, {ACTIVE_COUNT_SQL} AS active_count FROM course c WHERE 1 = 1"  # "1 = 1" usnadní přidávání podmínek přes AND
    params = []  # hodnoty pro otazníky v SQL
    if date_from:  # filtr "termín od"
        sql += " AND substr(c.starts_at, 1, 10) >= ?"  # porovnáváme jen datum (prvních 10 znaků "YYYY-MM-DD")
        params.append(date_from)  # přidáme hodnotu parametru
    if date_to:  # filtr "termín do"
        sql += " AND substr(c.starts_at, 1, 10) <= ?"  # datum začátku musí být nejpozději v tento den
        params.append(date_to)  # přidáme hodnotu parametru
    if only_free:  # filtr "jen s volnými místy"
        sql += f" AND {ACTIVE_COUNT_SQL} < c.capacity"  # počet aktivních přihlášek je menší než kapacita
    sql += " ORDER BY c.starts_at, c.name"  # seřadíme podle termínu, pak podle názvu
    return [_course_dict(r) for r in conn.execute(sql, params)]  # každý řádek převedeme na slovník


# ---------------------------------------------------------------------------
# Účastníci – CRUD
# ---------------------------------------------------------------------------


def create_participant(conn, data):  # vytvoří nového účastníka a vrátí jeho ID
    with transaction(conn):  # jedna transakce
        clean = validate_participant(conn, data)  # validace jména a e-mailu
        try:  # INSERT může selhat při souběžném vložení stejného e-mailu
            cur = conn.execute(  # vložíme řádek
                "INSERT INTO participant (name, email) VALUES (?, ?)",  # SQL s parametry
                (clean["name"], clean["email"]),  # hodnoty
            )
        except sqlite3.IntegrityError:  # porušení UNIQUE u e-mailu
            raise ValidationError("Účastníka se nepodařilo uložit – e-mail už existuje.")  # srozumitelná chyba
    return cur.lastrowid  # ID nového účastníka


def update_participant(conn, participant_id, data):  # upraví existujícího účastníka
    with transaction(conn):  # jedna transakce
        get_participant(conn, participant_id)  # ověří existenci účastníka
        clean = validate_participant(conn, data, participant_id=participant_id)  # validace
        conn.execute(  # UPDATE v databázi
            "UPDATE participant SET name = ?, email = ? WHERE id = ?",  # SQL příkaz
            (clean["name"], clean["email"], participant_id),  # nové hodnoty + ID
        )


def delete_participant(conn, participant_id):  # smaže účastníka i jeho přihlášky
    with transaction(conn):  # jedna transakce
        cur = conn.execute("DELETE FROM participant WHERE id = ?", (participant_id,))  # smazání řádku
        if cur.rowcount == 0:  # nic se nesmazalo
            raise NotFoundError(f"Účastník {participant_id} neexistuje.")  # účastník neexistoval


def get_participant(conn, participant_id):  # načte jednoho účastníka
    row = conn.execute("SELECT * FROM participant WHERE id = ?", (participant_id,)).fetchone()  # dotaz podle ID
    if row is None:  # nenalezeno
        raise NotFoundError(f"Účastník {participant_id} neexistuje.")  # chyba
    return dict(row)  # vrátíme jako slovník


def list_participants(conn):  # seznam všech účastníků
    return [dict(r) for r in conn.execute("SELECT * FROM participant ORDER BY name")]  # seřazeno podle jména


# ---------------------------------------------------------------------------
# Přihlášky – přihlášení do kurzu, hlídání kapacity, změna stavu
# ---------------------------------------------------------------------------


def count_active(conn, course_id):  # spočítá aktivní přihlášky (obsazená místa) kurzu
    placeholders = ", ".join("?" for _ in ACTIVE_STATUSES)  # vytvoří "?, ?" podle počtu aktivních stavů
    return conn.execute(  # dotaz do databáze
        f"SELECT COUNT(*) FROM enrollment WHERE course_id = ? AND status IN ({placeholders})",  # počet aktivních přihlášek
        (course_id, *ACTIVE_STATUSES),  # ID kurzu + jednotlivé aktivní stavy
    ).fetchone()[0]  # výsledkem je jedno číslo v prvním sloupci


def _ensure_free_seat(conn, course):  # ověří, že v kurzu je volné místo, jinak vyhodí chybu
    if count_active(conn, course["id"]) >= course["capacity"]:  # obsazeno je už tolik míst, kolik je kapacita
        raise ValidationError(f"Kurz „{course['name']}“ je plně obsazen ({course['capacity']} míst).")  # chyba


def enroll(conn, course_id, participant_id):  # přihlásí účastníka do kurzu a vrátí ID přihlášky
    with transaction(conn):  # BEGIN IMMEDIATE zamkne zápis -> dva lidé nemohou zabrat poslední místo současně
        course = get_course(conn, course_id)  # načteme kurz (ověří existenci)
        get_participant(conn, participant_id)  # ověříme, že účastník existuje
        existing = conn.execute(  # podíváme se, jestli už účastník v kurzu nějakou přihlášku má
            "SELECT id, status FROM enrollment WHERE course_id = ? AND participant_id = ?",  # hledáme dvojici kurz+účastník
            (course_id, participant_id),  # parametry
        ).fetchone()  # nejvýš jeden řádek (UNIQUE v databázi)

        if existing and existing["status"] != STATUS_CANCELLED:  # už je přihlášen (nebo kurz dokončil)
            raise ValidationError("Účastník je do tohoto kurzu už přihlášen.")  # nesmí se přihlásit dvakrát

        _ensure_free_seat(conn, course)  # hlídání kapacity – je ještě volné místo?

        if existing:  # účastník se dříve odhlásil (stav cancelled) -> přihlášku znovu aktivujeme
            conn.execute(  # místo nového řádku upravíme ten starý (UNIQUE nedovolí druhý)
                "UPDATE enrollment SET status = ?, enrolled_at = ? WHERE id = ?",  # nový stav a nový čas přihlášení
                (STATUS_ENROLLED, now_iso(), existing["id"]),  # hodnoty
            )
            return existing["id"]  # vrátíme ID obnovené přihlášky (COMMIT proběhne při opuštění bloku with)

        cur = conn.execute(  # účastník v kurzu ještě nebyl -> vytvoříme novou přihlášku
            "INSERT INTO enrollment (course_id, participant_id, enrolled_at, status) VALUES (?, ?, ?, ?)",  # SQL
            (course_id, participant_id, now_iso(), STATUS_ENROLLED),  # nová přihláška ve stavu enrolled
        )
        return cur.lastrowid  # ID nové přihlášky


def change_status(conn, enrollment_id, new_status):  # změní stav přihlášky (enrolled/cancelled/completed)
    if new_status not in STATUSES:  # neznámý stav odmítneme
        raise ValidationError(f"Neplatný stav „{new_status}“. Povolené: {', '.join(STATUSES)}.")  # chyba
    with transaction(conn):  # jedna transakce
        row = conn.execute("SELECT * FROM enrollment WHERE id = ?", (enrollment_id,)).fetchone()  # načteme přihlášku
        if row is None:  # přihláška neexistuje
            raise NotFoundError(f"Přihláška {enrollment_id} neexistuje.")  # chyba
        was_active = row["status"] in ACTIVE_STATUSES  # zabírala přihláška místo dosud?
        will_be_active = new_status in ACTIVE_STATUSES  # bude zabírat místo po změně?
        if will_be_active and not was_active:  # přechod z cancelled do enrolled/completed obsadí nové místo
            _ensure_free_seat(conn, get_course(conn, row["course_id"]))  # proto musíme zkontrolovat kapacitu
        conn.execute("UPDATE enrollment SET status = ? WHERE id = ?", (new_status, enrollment_id))  # uložíme nový stav
        return row["course_id"]  # vrátíme ID kurzu (web podle něj přesměruje zpět na detail kurzu)


def list_enrollments(conn, course_id):  # seznam přihlášených v kurzu (včetně jména a e-mailu)
    return [  # vrátíme seznam slovníků
        dict(r)  # každý řádek převedeme na slovník
        for r in conn.execute(  # projdeme výsledky dotazu
            "SELECT e.*, p.name AS participant_name, p.email AS participant_email "  # přihláška + údaje účastníka
            "FROM enrollment e JOIN participant p ON p.id = e.participant_id "  # spojení tabulek přes participant_id
            "WHERE e.course_id = ? ORDER BY e.enrolled_at",  # jen daný kurz, seřazeno podle času přihlášení
            (course_id,),  # parametr
        )
    ]
