# Kurzy a přihlašování účastníků

Webová aplikace v **Laravelu 13** (PHP 8.3+, SQLite) pro nabídku kurzů a přihlašování účastníků.

## Instalace a spuštění

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

Aplikace poběží na http://127.0.0.1:8000.

## Testy (automatická kontrola)

```bash
php artisan test
```

Test `CourseTest::test_vytvoreni_kurzu_a_overeni_ulozeni` odešle formulář nového kurzu a přes
`assertDatabaseHas` ověří, že je kurz uložený v databázi. Testy se spouští i na GitHubu (`.github/workflows/tests.yml`).

## Co je kde

| Soubor | Co dělá |
|---|---|
| `database/migrations/2026_11_12_*` | tabulky `courses`, `participants`, `enrollments` |
| `app/Models/` | modely `Course`, `Participant`, `Enrollment` a jejich vztahy |
| `app/Enums/EnrollmentStatus.php` | stavy přihlášky `enrolled`, `cancelled`, `completed` |
| `app/Services/EnrollmentService.php` | přihlášení do kurzu, hlídání kapacity a duplicit, změna stavu |
| `app/Http/Requests/` | validace formulářů kurzu a účastníka |
| `app/Http/Controllers/` | CRUD kurzů a účastníků, přihlašování |
| `routes/web.php` | adresy (routy) aplikace |
| `resources/views/` | Blade šablony |
| `tests/Feature/` | automatické testy |

## Datový model

- **Course**: `name`, `code` (jedinečný), `capacity` (celé číslo > 0), `starts_at`
- **Participant**: `name`, `email` (platný a jedinečný, ukládá se malými písmeny)
- **Enrollment**: `course_id`, `participant_id`, `enrolled_at`, `status` (`enrolled` / `cancelled` / `completed`)

## Pravidla přihlašování

- Účastník může mít v kurzu jen jednu přihlášku (kontrola ve službě + unikátní index v databázi).
- Místo v kurzu zabírají přihlášky ve stavu `enrolled` a `completed`; `cancelled` místo uvolní
  (lze změnit v `EnrollmentStatus::active()`).
- Po zrušení se lze znovu přihlásit – původní přihláška se obnoví, pokud je volné místo.
- Kapacitu kurzu nelze snížit pod počet aktivních přihlášek.
- Přihlášení a změna stavu běží v databázové transakci se zámkem kurzu (`lockForUpdate`),
  takže dva lidé nemohou současně zabrat poslední místo.
