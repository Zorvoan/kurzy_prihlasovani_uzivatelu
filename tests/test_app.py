"""Automatické testy – spouští se příkazem: python -m pytest"""  # popis modulu

import os  # práce s cestami
import sys  # úprava cesty pro import modulů

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))  # přidá kořen projektu, aby šlo importovat app, db, services

import pytest  # testovací framework

import db  # modul s databází
import services  # modul s business logikou
from app import create_app  # továrna na webovou aplikaci
from services import ValidationError  # výjimka validace


@pytest.fixture  # fixture = příprava, kterou pytest automaticky předá testům podle názvu parametru
def db_path(tmp_path):  # tmp_path je dočasná složka, kterou pytest po testu uklidí
    return str(tmp_path / "test.sqlite3")  # každý test má vlastní prázdnou databázi


@pytest.fixture  # připojení k testovací databázi s vytvořenými tabulkami
def conn(db_path):  # dostane cestu z fixture db_path
    c = db.connect(db_path)  # připojíme se
    db.init_db(c)  # vytvoříme tabulky
    yield c  # předáme připojení testu
    c.close()  # po testu připojení zavřeme


@pytest.fixture  # testovací klient Flasku – umí posílat HTTP požadavky bez spuštěného serveru
def client(db_path):  # používá stejnou dočasnou databázi
    app = create_app(db_path)  # vytvoříme aplikaci nad testovací DB
    app.config["TESTING"] = True  # testovací režim (chyby se hlásí přímo)
    return app.test_client()  # vrátíme klienta


def make_course(conn, **overrides):  # pomocná funkce: vytvoří kurz s výchozími hodnotami
    data = {"name": "Python", "code": "PY1", "capacity": "2", "starts_at": "2026-12-01T09:00"}  # výchozí data
    data.update(overrides)  # přepíšeme hodnoty, které test zadal
    return services.create_course(conn, data)  # vytvoříme kurz a vrátíme ID


def make_participant(conn, email, name="Jan"):  # pomocná funkce: vytvoří účastníka
    return services.create_participant(conn, {"name": name, "email": email})  # vrátí ID účastníka


# --------------------------------------------- povinná automatická kontrola


def test_vytvoreni_kurzu_pres_web_a_overeni_ulozeni(client, db_path):  # vytvoří kurz přes web a ověří, že je v DB
    response = client.post(  # odešleme formulář "Nový kurz"
        "/courses/new",  # adresa formuláře
        data={"name": "Účetnictví", "code": "UC-01", "capacity": "15", "starts_at": "2026-12-10T10:00"},  # data kurzu
    )
    assert response.status_code == 302  # úspěch = přesměrování na detail kurzu

    conn = db.connect(db_path)  # nové, nezávislé připojení -> ověříme, že data jsou opravdu uložená na disku
    row = conn.execute("SELECT * FROM course WHERE code = ?", ("UC-01",)).fetchone()  # najdeme kurz podle kódu
    conn.close()  # připojení zavřeme
    assert row is not None  # kurz v databázi existuje
    assert row["name"] == "Účetnictví"  # název sedí
    assert row["capacity"] == 15  # kapacita je uložená jako celé číslo
    assert row["starts_at"] == "2026-12-10T10:00"  # termín sedí

    detail = client.get(response.headers["Location"])  # otevřeme detail kurzu, kam nás web přesměroval
    assert "Účetnictví".encode() in detail.data  # na stránce je název kurzu
    assert b"0 / 15" in detail.data  # obsazenost nového kurzu je 0 z 15


# ------------------------------------------------------- validace kurzu


def test_kod_kurzu_je_jedinecny(conn):  # dva kurzy nesmí mít stejný kód
    make_course(conn, code="ABC")  # první kurz
    with pytest.raises(ValidationError):  # očekáváme chybu validace...
        make_course(conn, code="abc")  # ...i když se kód liší jen velikostí písmen


@pytest.mark.parametrize("capacity", ["0", "-1", "2.5", "abc", "", None])  # test se spustí pro každou hodnotu
def test_kapacita_musi_byt_cele_cislo_vetsi_nez_0(conn, capacity):  # neplatné kapacity se odmítnou
    with pytest.raises(ValidationError):  # očekáváme chybu
        make_course(conn, capacity=capacity)  # pokus o vytvoření kurzu s neplatnou kapacitou


def test_neplatny_formular_vrati_chybu(client):  # web vrátí formulář s chybou a nic neuloží
    response = client.post("/courses/new", data={"name": "X", "code": "X", "capacity": "0", "starts_at": "2026-12-01T09:00"})  # kapacita 0
    assert response.status_code == 400  # chybný požadavek
    assert "Kapacita musí být celé číslo".encode() in response.data  # zobrazí se chybová zpráva


# --------------------------------------------------- validace účastníka


@pytest.mark.parametrize("email", ["", "bez-zavinace", "a@b", "a b@c.cz", "a@@b.cz"])  # neplatné e-maily
def test_email_musi_byt_platny(conn, email):  # neplatný e-mail se odmítne
    with pytest.raises(ValidationError):  # očekáváme chybu
        make_participant(conn, email)  # pokus o vytvoření účastníka


def test_email_je_jedinecny(conn):  # dva účastníci nesmí mít stejný e-mail
    make_participant(conn, "jan@example.com")  # první účastník
    with pytest.raises(ValidationError):  # očekáváme chybu...
        make_participant(conn, "JAN@example.com")  # ...i při jiné velikosti písmen


# ---------------------------------------------- přihlašování a kapacita


def test_ucastnik_se_nesmi_prihlasit_dvakrat(conn):  # duplicitní přihláška se odmítne
    course_id = make_course(conn)  # kurz
    p = make_participant(conn, "a@example.com")  # účastník
    services.enroll(conn, course_id, p)  # první přihlášení projde
    with pytest.raises(ValidationError):  # druhé musí selhat
        services.enroll(conn, course_id, p)  # pokus o duplicitní přihlášení


def test_kapacita_nesmi_byt_prekrocena(conn):  # do plného kurzu se nikdo nepřihlásí
    course_id = make_course(conn, capacity="2")  # kurz pro 2 lidi
    a, b, c = (make_participant(conn, f"{x}@example.com") for x in "abc")  # tři účastníci
    services.enroll(conn, course_id, a)  # 1. místo
    services.enroll(conn, course_id, b)  # 2. místo
    with pytest.raises(ValidationError):  # třetí se nevejde
        services.enroll(conn, course_id, c)  # pokus o přihlášení do plného kurzu
    assert services.get_course(conn, course_id)["active_count"] == 2  # obsazená zůstávají 2 místa


def test_zruseni_uvolni_misto_a_obnoveni_hlida_kapacitu(conn):  # stav cancelled uvolní místo
    course_id = make_course(conn, capacity="1")  # kurz pro 1 člověka
    a = make_participant(conn, "a@example.com")  # účastník A
    b = make_participant(conn, "b@example.com")  # účastník B
    enrollment_a = services.enroll(conn, course_id, a)  # A zabere jediné místo
    services.change_status(conn, enrollment_a, services.STATUS_CANCELLED)  # A se odhlásí -> místo je volné
    services.enroll(conn, course_id, b)  # B se teď přihlásit může
    with pytest.raises(ValidationError):  # A už se zpět nevejde...
        services.change_status(conn, enrollment_a, services.STATUS_ENROLLED)  # ...ani změnou stavu
    with pytest.raises(ValidationError):  # ...ani novým přihlášením
        services.enroll(conn, course_id, a)  # pokus o opětovné přihlášení do plného kurzu


def test_zrusena_prihlaska_lze_znovu_aktivovat(conn):  # po odhlášení se lze znovu přihlásit
    course_id = make_course(conn)  # kurz
    a = make_participant(conn, "a@example.com")  # účastník
    eid = services.enroll(conn, course_id, a)  # přihlášení
    services.change_status(conn, eid, services.STATUS_CANCELLED)  # odhlášení
    assert services.enroll(conn, course_id, a) == eid  # nové přihlášení obnoví tutéž přihlášku
    assert services.list_enrollments(conn, course_id)[0]["status"] == services.STATUS_ENROLLED  # stav je zase enrolled


def test_vsechny_tri_stavy_a_neplatny_stav(conn):  # stavy enrolled, cancelled, completed + odmítnutí neznámého
    course_id = make_course(conn)  # kurz
    eid = services.enroll(conn, course_id, make_participant(conn, "a@example.com"))  # přihláška ve stavu enrolled
    for status in services.STATUSES:  # vyzkoušíme všechny tři stavy
        services.change_status(conn, eid, status)  # změna stavu
        assert services.list_enrollments(conn, course_id)[0]["status"] == status  # stav se uložil
    with pytest.raises(ValidationError):  # neznámý stav se odmítne
        services.change_status(conn, eid, "paused")  # neplatná hodnota


def test_kapacitu_nelze_snizit_pod_pocet_prihlasenych(conn):  # úprava kurzu hlídá obsazenost
    course_id = make_course(conn, capacity="2")  # kurz pro 2
    services.enroll(conn, course_id, make_participant(conn, "a@example.com"))  # 1. přihlášený
    services.enroll(conn, course_id, make_participant(conn, "b@example.com"))  # 2. přihlášený
    with pytest.raises(ValidationError):  # snížení na 1 musí selhat
        services.update_course(conn, course_id, {"name": "Python", "code": "PY1", "capacity": "1", "starts_at": "2026-12-01T09:00"})  # pokus


# ----------------------------------------------------------------- filtr


def test_filtr_podle_terminu_a_volnych_mist(conn):  # filtr kurzů
    early = make_course(conn, code="A", capacity="1", starts_at="2026-12-01T09:00")  # prosincový kurz pro 1
    make_course(conn, code="B", starts_at="2027-01-15T09:00")  # lednový kurz
    services.enroll(conn, early, make_participant(conn, "a@example.com"))  # prosincový kurz zaplníme

    december = services.list_courses(conn, date_from="2026-12-01", date_to="2026-12-31")  # jen prosinec
    assert [c["code"] for c in december] == ["A"]  # vyjde jen kurz A

    free = services.list_courses(conn, only_free=True)  # jen kurzy s volným místem
    assert [c["code"] for c in free] == ["B"]  # plný kurz A se nezobrazí


def test_web_filtr_a_crud_ucastnika(client):  # průchod webem: účastník, přihláška, filtr
    assert client.post("/participants/new", data={"name": "Eva", "email": "eva@example.com"}).status_code == 302  # vytvoření účastníka
    assert client.post("/participants/new", data={"name": "Eva 2", "email": "eva@example.com"}).status_code == 400  # duplicitní e-mail
    client.post("/courses/new", data={"name": "Excel", "code": "XL", "capacity": "1", "starts_at": "2026-12-05T08:00"})  # kurz
    client.post("/courses/1/enroll", data={"participant_id": "1"})  # přihlášení účastníka 1 do kurzu 1
    assert b"1 / 1" in client.get("/courses/1").data  # detail ukazuje plnou obsazenost
    assert b"Excel" not in client.get("/courses?only_free=1").data  # plný kurz se ve filtru volných míst nezobrazí
    assert client.post("/participants/1/edit", data={"name": "Eva N.", "email": "eva.n@example.com"}).status_code == 302  # úprava
    assert client.post("/participants/1/delete").status_code == 302  # smazání
    assert b"0 / 1" in client.get("/courses/1").data  # se smazaným účastníkem zmizela i jeho přihláška
    assert client.get("/courses/999").status_code == 404  # neexistující kurz -> 404
