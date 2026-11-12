<?php // otevírací značka PHP souboru

namespace Tests\Feature; // jmenný prostor testů

use App\Models\Course; // model kurzu
use Illuminate\Foundation\Testing\RefreshDatabase; // před každým testem připraví čistou databázi
use PHPUnit\Framework\Attributes\DataProvider; // atribut pro spuštění testu s více sadami dat
use Tests\TestCase; // základní třída testů Laravelu

// Testy kurzů: povinná automatická kontrola, validace, filtr.
class CourseTest extends TestCase // dědíme ze základního TestCase
{
    use RefreshDatabase; // každý test běží nad prázdnou databází (v paměti, viz phpunit.xml)

    private function courseData(array $overrides = []): array // pomocná metoda: platná data kurzu
    {
        return array_merge([ // výchozí hodnoty, které lze přepsat
            'name' => 'Python', // název
            'code' => 'PY1', // kód
            'capacity' => '2', // kapacita (jako text, tak ji posílá formulář)
            'starts_at' => '2026-12-01T09:00', // termín
        ], $overrides); // přepíšeme zadané hodnoty
    } // konec courseData()

    // ------------------------------------------------ povinná automatická kontrola

    public function test_vytvoreni_kurzu_a_overeni_ulozeni(): void // vytvoří kurz přes web a ověří, že je uložený
    {
        $response = $this->post('/courses', [ // odešleme formulář "Nový kurz"
            'name' => 'Účetnictví', // název
            'code' => 'UC-01', // kód
            'capacity' => '15', // kapacita
            'starts_at' => '2026-12-10T10:00', // termín
        ]); // konec požadavku

        $course = Course::where('code', 'UC-01')->first(); // kurz načteme znovu z databáze
        $response->assertRedirect(route('courses.show', $course)); // úspěch = přesměrování na detail nového kurzu
        $response->assertSessionHasNoErrors(); // žádné chyby validace

        $this->assertDatabaseHas('courses', [ // ověříme, že v tabulce courses je řádek s těmito hodnotami
            'name' => 'Účetnictví', // název sedí
            'code' => 'UC-01', // kód sedí
            'capacity' => 15, // kapacita je uložená jako celé číslo
        ]); // konec kontroly
        $this->assertSame('2026-12-10 10:00', $course->starts_at->format('Y-m-d H:i')); // termín sedí

        $this->get(route('courses.show', $course)) // otevřeme detail kurzu
            ->assertOk() // stránka se načetla (HTTP 200)
            ->assertSee('Účetnictví') // obsahuje název
            ->assertSee('0 / 15'); // obsazenost nového kurzu je 0 z 15
    } // konec testu

    // ---------------------------------------------------------- validace

    public function test_kod_kurzu_je_jedinecny(): void // dva kurzy nesmí mít stejný kód
    {
        $this->post('/courses', $this->courseData(['code' => 'ABC'])); // první kurz
        $this->post('/courses', $this->courseData(['code' => 'ABC'])) // druhý se stejným kódem
            ->assertSessionHasErrors('code'); // musí vrátit chybu u pole "code"
        $this->assertDatabaseCount('courses', 1); // v databázi je pořád jen jeden kurz
    } // konec testu

    public static function invalidCapacities(): array // neplatné kapacity pro test níže
    {
        return [['0'], ['-1'], ['2.5'], ['abc'], ['']]; // každá položka = jedno spuštění testu
    } // konec invalidCapacities()

    #[DataProvider('invalidCapacities')] // test se spustí pro každou hodnotu z invalidCapacities()
    public function test_kapacita_musi_byt_cele_cislo_vetsi_nez_0(string $capacity): void // neplatná kapacita se odmítne
    {
        $this->post('/courses', $this->courseData(['capacity' => $capacity])) // pokus o vytvoření kurzu
            ->assertSessionHasErrors('capacity'); // chyba u pole "capacity"
        $this->assertDatabaseCount('courses', 0); // nic se neuložilo
    } // konec testu

    public function test_kapacitu_nelze_snizit_pod_pocet_prihlasenych(): void // úprava kurzu hlídá obsazenost
    {
        $course = Course::create($this->courseData(['capacity' => 2])); // kurz pro 2
        foreach (['a', 'b'] as $x) { // dva účastníci
            $this->post('/participants', ['name' => $x, 'email' => "$x@example.com"]); // vytvoříme účastníka
        } // konec cyklu
        $this->post(route('courses.enroll', $course), ['participant_id' => 1]); // 1. přihlášený
        $this->post(route('courses.enroll', $course), ['participant_id' => 2]); // 2. přihlášený

        $this->put(route('courses.update', $course), $this->courseData(['capacity' => '1'])) // snížení kapacity na 1
            ->assertSessionHasErrors('capacity'); // musí selhat
        $this->assertSame(2, $course->fresh()->capacity); // kapacita zůstala 2
    } // konec testu

    // ---------------------------------------------------------- CRUD a filtr

    public function test_uprava_a_smazani_kurzu(): void // úprava a smazání
    {
        $course = Course::create($this->courseData()); // kurz
        $this->put(route('courses.update', $course), $this->courseData(['name' => 'Python 2'])) // úprava názvu
            ->assertRedirect(route('courses.show', $course)); // přesměrování na detail
        $this->assertSame('Python 2', $course->fresh()->name); // název se změnil
        $this->delete(route('courses.destroy', $course))->assertRedirect(route('courses.index')); // smazání
        $this->assertDatabaseMissing('courses', ['id' => $course->id]); // kurz už neexistuje
    } // konec testu

    public function test_filtr_podle_terminu_a_volnych_mist(): void // filtr kurzů
    {
        $december = Course::create($this->courseData(['name' => 'Prosincový', 'code' => 'A', 'capacity' => 1])); // prosincový kurz pro 1
        Course::create($this->courseData(['name' => 'Lednový', 'code' => 'B', 'starts_at' => '2027-01-15T09:00'])); // lednový kurz
        $this->post('/participants', ['name' => 'Eva', 'email' => 'eva@example.com']); // účastník
        $this->post(route('courses.enroll', $december), ['participant_id' => 1]); // prosincový kurz zaplníme

        $this->get('/courses?date_from=2026-12-01&date_to=2026-12-31') // filtr na prosinec
            ->assertSee('Prosincový') // prosincový kurz je vidět
            ->assertDontSee('Lednový'); // lednový ne

        $this->get('/courses?only_free=1') // jen kurzy s volnými místy
            ->assertSee('Lednový') // lednový má místo
            ->assertDontSee('Prosincový'); // prosincový je plný
    } // konec testu

    public function test_neexistujici_kurz_vrati_404(): void // neexistující kurz
    {
        $this->get('/courses/999')->assertNotFound(); // HTTP 404
    } // konec testu
} // konec třídy
