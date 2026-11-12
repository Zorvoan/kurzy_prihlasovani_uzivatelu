<?php // otevírací značka PHP souboru

namespace Tests\Feature; // jmenný prostor testů

use App\Enums\EnrollmentStatus; // výčet stavů
use App\Models\Course; // model kurzu
use App\Models\Enrollment; // model přihlášky
use App\Models\Participant; // model účastníka
use Illuminate\Foundation\Testing\RefreshDatabase; // čistá databáze pro každý test
use Tests\TestCase; // základní třída testů

// Testy přihlašování: duplicity, kapacita, stavy.
class EnrollmentTest extends TestCase // dědíme ze základního TestCase
{
    use RefreshDatabase; // čistá databáze

    private function course(int $capacity): Course // pomocná metoda: vytvoří kurz s danou kapacitou
    {
        return Course::create(['name' => 'Excel', 'code' => 'XL', 'capacity' => $capacity, 'starts_at' => '2026-12-05 08:00']); // uložíme kurz
    } // konec course()

    private function participant(string $email): Participant // pomocná metoda: vytvoří účastníka
    {
        return Participant::create(['name' => $email, 'email' => $email]); // uložíme účastníka
    } // konec participant()

    private function enroll(Course $course, Participant $participant) // pomocná metoda: odešle formulář přihlášení
    {
        return $this->from(route('courses.show', $course)) // jako bychom formulář odeslali ze stránky detailu kurzu
            ->post(route('courses.enroll', $course), ['participant_id' => $participant->id]); // POST přihlášení
    } // konec enroll()

    private function setStatus(Enrollment $enrollment, EnrollmentStatus $status) // pomocná metoda: změní stav přes web
    {
        return $this->patch(route('enrollments.status', $enrollment), ['status' => $status->value]); // PATCH změny stavu
    } // konec setStatus()

    public function test_prihlaseni_ucastnika_do_kurzu(): void // základní přihlášení
    {
        $course = $this->course(2); // kurz pro 2
        $this->enroll($course, $this->participant('a@example.com')) // přihlásíme účastníka
            ->assertRedirect(route('courses.show', $course)) // zpět na detail
            ->assertSessionHasNoErrors(); // bez chyb
        $enrollment = Enrollment::first(); // načteme přihlášku
        $this->assertSame(EnrollmentStatus::Enrolled, $enrollment->status); // stav je enrolled
        $this->assertNotNull($enrollment->enrolled_at); // čas přihlášení je vyplněný
        $this->get(route('courses.show', $course))->assertSee('1 / 2')->assertSee('a@example.com'); // obsazenost a seznam přihlášených
    } // konec testu

    public function test_ucastnik_se_nesmi_prihlasit_dvakrat(): void // duplicitní přihlášení
    {
        $course = $this->course(5); // kurz
        $p = $this->participant('a@example.com'); // účastník
        $this->enroll($course, $p); // první přihlášení
        $this->enroll($course, $p)->assertSessionHasErrors('enrollment'); // druhé musí selhat
        $this->assertDatabaseCount('enrollments', 1); // jen jedna přihláška
    } // konec testu

    public function test_kapacita_nesmi_byt_prekrocena(): void // plný kurz
    {
        $course = $this->course(2); // kurz pro 2
        $this->enroll($course, $this->participant('a@example.com')); // 1. místo
        $this->enroll($course, $this->participant('b@example.com')); // 2. místo
        $this->enroll($course, $this->participant('c@example.com'))->assertSessionHasErrors('enrollment'); // třetí se nevejde
        $this->assertSame(2, $course->activeEnrollments()->count()); // obsazená jsou jen 2 místa
    } // konec testu

    public function test_vsechny_tri_stavy(): void // enrolled -> cancelled -> completed
    {
        $course = $this->course(1); // kurz
        $this->enroll($course, $this->participant('a@example.com')); // přihláška (enrolled)
        $enrollment = Enrollment::first(); // načteme ji
        foreach (EnrollmentStatus::cases() as $status) { // projdeme všechny stavy
            $this->setStatus($enrollment, $status)->assertSessionHasNoErrors(); // změníme stav
            $this->assertSame($status, $enrollment->fresh()->status); // stav se uložil
        } // konec cyklu
        $this->patch(route('enrollments.status', $enrollment), ['status' => 'paused']) // neznámý stav
            ->assertSessionHasErrors('status'); // se odmítne
    } // konec testu

    public function test_zruseni_uvolni_misto_a_obnoveni_hlida_kapacitu(): void // cancelled uvolní místo
    {
        $course = $this->course(1); // kurz pro 1
        $a = $this->participant('a@example.com'); // účastník A
        $this->enroll($course, $a); // A zabere jediné místo
        $enrollmentA = Enrollment::first(); // přihláška A
        $this->setStatus($enrollmentA, EnrollmentStatus::Cancelled); // A se odhlásí

        $this->enroll($course, $this->participant('b@example.com'))->assertSessionHasNoErrors(); // B se teď vejde
        $this->setStatus($enrollmentA, EnrollmentStatus::Enrolled)->assertSessionHasErrors('enrollment'); // A zpět nejde – kurz je plný
        $this->enroll($course, $a)->assertSessionHasErrors('enrollment'); // ani novým přihlášením
        $this->assertSame(EnrollmentStatus::Cancelled, $enrollmentA->fresh()->status); // A zůstává zrušený
    } // konec testu

    public function test_po_zruseni_se_lze_znovu_prihlasit(): void // opětovné přihlášení
    {
        $course = $this->course(1); // kurz
        $a = $this->participant('a@example.com'); // účastník
        $this->enroll($course, $a); // přihlášení
        $this->setStatus(Enrollment::first(), EnrollmentStatus::Cancelled); // odhlášení
        $this->enroll($course, $a)->assertSessionHasNoErrors(); // znovu přihlášení projde
        $this->assertDatabaseCount('enrollments', 1); // obnovila se původní přihláška, nevznikla nová
        $this->assertSame(EnrollmentStatus::Enrolled, Enrollment::first()->status); // stav je zase enrolled
    } // konec testu

    public function test_smazani_ucastnika_smaze_jeho_prihlasky(): void // cascadeOnDelete
    {
        $course = $this->course(1); // kurz
        $p = $this->participant('a@example.com'); // účastník
        $this->enroll($course, $p); // přihlášení
        $this->delete(route('participants.destroy', $p)); // smažeme účastníka
        $this->assertDatabaseCount('enrollments', 0); // přihláška zmizela
        $this->get(route('courses.show', $course))->assertSee('0 / 1'); // místo je zase volné
    } // konec testu
} // konec třídy
