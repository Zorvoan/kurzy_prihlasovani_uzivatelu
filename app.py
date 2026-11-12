"""Webová aplikace (Flask): stránky pro kurzy, účastníky a přihlášky."""  # popis modulu

import os  # práce s cestami a proměnnými prostředí

from flask import Flask, abort, flash, g, redirect, render_template, request, url_for  # potřebné části Flasku

import db  # náš modul pro připojení k databázi
import services  # náš modul s business logikou a validací
from services import NotFoundError, ValidationError  # výjimky, které budeme zachytávat


def create_app(database_path=None):  # "továrna" na aplikaci – testy si tak mohou předat vlastní databázi
    app = Flask(__name__)  # vytvoříme Flask aplikaci
    app.config["SECRET_KEY"] = os.environ.get("SECRET_KEY", "dev-only-secret")  # klíč pro podepisování session (flash zprávy)
    app.config["DATABASE"] = database_path or os.environ.get(  # cesta k souboru databáze...
        "DATABASE", os.path.join(app.root_path, "kurzy.sqlite3")  # ...výchozí je soubor vedle app.py
    )

    def get_conn():  # vrátí připojení k DB pro aktuální požadavek (jedno na požadavek)
        if "conn" not in g:  # "g" je úložiště platné po dobu jednoho HTTP požadavku
            g.conn = db.connect(app.config["DATABASE"])  # při prvním použití se připojíme
        return g.conn  # vrátíme připojení

    @app.teardown_appcontext  # tato funkce se spustí na konci každého požadavku
    def close_conn(_exc):  # zavře připojení k databázi
        conn = g.pop("conn", None)  # vyjmeme připojení z "g" (pokud existuje)
        if conn is not None:  # pokud se během požadavku připojení otevřelo...
            conn.close()  # ...zavřeme ho

    with app.app_context():  # dočasný kontext aplikace, abychom mohli pracovat s DB hned při startu
        db.init_db(get_conn())  # vytvoříme tabulky, pokud ještě nejsou

    @app.errorhandler(NotFoundError)  # pokud kdekoli vznikne NotFoundError...
    def handle_not_found(exc):  # ...zobrazíme stránku 404
        return render_template("error.html", message=str(exc)), 404  # šablona + HTTP kód 404

    @app.context_processor  # proměnné dostupné ve všech šablonách
    def inject_statuses():  # předáme šablonám seznam stavů přihlášek
        return {"STATUSES": services.STATUSES}  # v šablonách pak lze psát {{ STATUSES }}

    # ------------------------------------------------------------------ kurzy

    @app.route("/")  # úvodní stránka
    def index():  # jen přesměruje na seznam kurzů
        return redirect(url_for("course_list"))  # přesměrování (HTTP 302)

    @app.route("/courses")  # seznam kurzů s filtrem
    def course_list():  # zobrazí kurzy
        filters = {  # načteme hodnoty filtru z adresy URL (?date_from=...&date_to=...&only_free=1)
            "date_from": request.args.get("date_from", ""),  # termín od (YYYY-MM-DD)
            "date_to": request.args.get("date_to", ""),  # termín do (YYYY-MM-DD)
            "only_free": request.args.get("only_free") == "1",  # zaškrtnuto "jen s volnými místy"?
        }
        courses = services.list_courses(get_conn(), **filters)  # získáme vyfiltrované kurzy z databáze
        return render_template("courses/list.html", courses=courses, filters=filters)  # vykreslíme šablonu

    @app.route("/courses/new", methods=["GET", "POST"])  # formulář pro nový kurz (GET = zobrazit, POST = uložit)
    def course_create():  # vytvoření kurzu
        if request.method == "POST":  # formulář byl odeslán
            try:  # zkusíme kurz uložit
                course_id = services.create_course(get_conn(), request.form)  # validace + INSERT
            except ValidationError as exc:  # data nejsou v pořádku
                return render_template("courses/form.html", course=request.form, errors=exc.errors), 400  # formulář znovu s chybami
            flash("Kurz byl vytvořen.")  # zpráva, která se zobrazí na další stránce
            return redirect(url_for("course_detail", course_id=course_id))  # přesměrujeme na detail nového kurzu
        return render_template("courses/form.html", course={}, errors=[])  # GET: prázdný formulář

    @app.route("/courses/<int:course_id>")  # detail kurzu: obsazenost + seznam přihlášených
    def course_detail(course_id):  # course_id se vezme z adresy URL
        conn = get_conn()  # připojení k DB
        course = services.get_course(conn, course_id)  # načteme kurz (nebo 404)
        enrollments = services.list_enrollments(conn, course_id)  # všechny přihlášky kurzu
        enrolled_ids = {e["participant_id"] for e in enrollments if e["status"] != services.STATUS_CANCELLED}  # kdo už je přihlášen
        candidates = [p for p in services.list_participants(conn) if p["id"] not in enrolled_ids]  # koho lze ještě přihlásit
        return render_template(  # vykreslíme šablonu detailu
            "courses/detail.html", course=course, enrollments=enrollments, candidates=candidates  # předáme data
        )

    @app.route("/courses/<int:course_id>/edit", methods=["GET", "POST"])  # úprava kurzu
    def course_edit(course_id):  # zobrazí/uloží formulář úprav
        conn = get_conn()  # připojení k DB
        course = services.get_course(conn, course_id)  # načteme kurz (nebo 404)
        if request.method == "POST":  # formulář odeslán
            try:  # zkusíme uložit změny
                services.update_course(conn, course_id, request.form)  # validace + UPDATE
            except ValidationError as exc:  # chyba ve vstupech
                return render_template(  # formulář znovu s chybami
                    "courses/form.html", course={**request.form, "id": course_id}, errors=exc.errors  # zachováme zadané hodnoty
                ), 400  # HTTP 400 = špatný požadavek
            flash("Kurz byl upraven.")  # zpráva pro uživatele
            return redirect(url_for("course_detail", course_id=course_id))  # zpět na detail
        return render_template("courses/form.html", course=course, errors=[])  # GET: předvyplněný formulář

    @app.route("/courses/<int:course_id>/delete", methods=["POST"])  # smazání kurzu (jen POST, aby se nedalo smazat odkazem)
    def course_delete(course_id):  # smaže kurz
        services.delete_course(get_conn(), course_id)  # DELETE v databázi
        flash("Kurz byl smazán.")  # zpráva pro uživatele
        return redirect(url_for("course_list"))  # zpět na seznam kurzů

    # -------------------------------------------------------------- přihlášky

    @app.route("/courses/<int:course_id>/enroll", methods=["POST"])  # přihlášení účastníka do kurzu
    def course_enroll(course_id):  # zpracuje formulář "Přihlásit"
        participant_id = request.form.get("participant_id", type=int)  # ID vybraného účastníka (číslo)
        if participant_id is None:  # nic nebylo vybráno
            flash("Vyberte účastníka.", "error")  # chybová zpráva
            return redirect(url_for("course_detail", course_id=course_id))  # zpět na detail
        try:  # zkusíme přihlásit
            services.enroll(get_conn(), course_id, participant_id)  # kontrola duplicity + kapacity + uložení
            flash("Účastník byl přihlášen.")  # úspěch
        except ValidationError as exc:  # plný kurz nebo duplicitní přihláška
            flash(str(exc), "error")  # zobrazíme důvod
        return redirect(url_for("course_detail", course_id=course_id))  # vždy zpět na detail kurzu

    @app.route("/enrollments/<int:enrollment_id>/status", methods=["POST"])  # změna stavu přihlášky
    def enrollment_status(enrollment_id):  # zpracuje výběr nového stavu
        conn = get_conn()  # připojení k DB
        course_id = request.form.get("course_id", type=int)  # kam se vrátit, kdyby změna selhala
        try:  # zkusíme změnit stav
            course_id = services.change_status(conn, enrollment_id, request.form.get("status", ""))  # uloží nový stav
            flash("Stav přihlášky byl změněn.")  # úspěch
        except ValidationError as exc:  # např. obnovení přihlášky do plného kurzu
            flash(str(exc), "error")  # zobrazíme důvod
        if course_id is None:  # neznáme kurz, kam se vrátit
            abort(400)  # HTTP 400 = chybný požadavek
        return redirect(url_for("course_detail", course_id=course_id))  # zpět na detail kurzu

    # ------------------------------------------------------------- účastníci

    @app.route("/participants")  # seznam účastníků
    def participant_list():  # zobrazí účastníky
        return render_template("participants/list.html", participants=services.list_participants(get_conn()))  # šablona + data

    @app.route("/participants/new", methods=["GET", "POST"])  # nový účastník
    def participant_create():  # zobrazí/uloží formulář
        if request.method == "POST":  # formulář odeslán
            try:  # zkusíme uložit
                services.create_participant(get_conn(), request.form)  # validace + INSERT
            except ValidationError as exc:  # neplatné jméno/e-mail
                return render_template("participants/form.html", participant=request.form, errors=exc.errors), 400  # znovu s chybami
            flash("Účastník byl vytvořen.")  # úspěch
            return redirect(url_for("participant_list"))  # zpět na seznam
        return render_template("participants/form.html", participant={}, errors=[])  # GET: prázdný formulář

    @app.route("/participants/<int:participant_id>/edit", methods=["GET", "POST"])  # úprava účastníka
    def participant_edit(participant_id):  # zobrazí/uloží formulář úprav
        conn = get_conn()  # připojení k DB
        participant = services.get_participant(conn, participant_id)  # načteme účastníka (nebo 404)
        if request.method == "POST":  # formulář odeslán
            try:  # zkusíme uložit
                services.update_participant(conn, participant_id, request.form)  # validace + UPDATE
            except ValidationError as exc:  # chyba ve vstupech
                return render_template(  # formulář znovu s chybami
                    "participants/form.html", participant={**request.form, "id": participant_id}, errors=exc.errors  # zachováme hodnoty
                ), 400  # HTTP 400
            flash("Účastník byl upraven.")  # úspěch
            return redirect(url_for("participant_list"))  # zpět na seznam
        return render_template("participants/form.html", participant=participant, errors=[])  # GET: předvyplněný formulář

    @app.route("/participants/<int:participant_id>/delete", methods=["POST"])  # smazání účastníka
    def participant_delete(participant_id):  # smaže účastníka
        services.delete_participant(get_conn(), participant_id)  # DELETE (přihlášky zmizí díky CASCADE)
        flash("Účastník byl smazán.")  # zpráva
        return redirect(url_for("participant_list"))  # zpět na seznam

    return app  # hotovou aplikaci vrátíme


if __name__ == "__main__":  # tento blok se spustí jen při "python app.py", ne při importu
    create_app().run(debug=True)  # spustí vývojový server na http://127.0.0.1:5000
