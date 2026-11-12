<?php // otevírací značka PHP souboru

namespace Tests\Feature; // jmenný prostor testů

use App\Models\Participant; // model účastníka
use Illuminate\Foundation\Testing\RefreshDatabase; // čistá databáze pro každý test
use PHPUnit\Framework\Attributes\DataProvider; // více sad dat pro jeden test
use Tests\TestCase; // základní třída testů

// Testy účastníků: CRUD a validace e-mailu.
class ParticipantTest extends TestCase // dědíme ze základního TestCase
{
    use RefreshDatabase; // čistá databáze

    public function test_vytvoreni_uprava_a_smazani_ucastnika(): void // CRUD účastníka
    {
        $this->post('/participants', ['name' => 'Eva', 'email' => ' Eva@Example.com ']) // vytvoření (e-mail s mezerami a velkými písmeny)
            ->assertRedirect(route('participants.index')); // přesměrování na seznam
        $this->assertDatabaseHas('participants', ['email' => 'eva@example.com']); // e-mail se uložil očištěný, malými písmeny

        $participant = Participant::first(); // načteme účastníka
        $this->put(route('participants.update', $participant), ['name' => 'Eva N.', 'email' => 'eva@example.com']) // úprava se stejným e-mailem
            ->assertSessionHasNoErrors(); // vlastní e-mail se nepočítá jako duplicita
        $this->assertSame('Eva N.', $participant->fresh()->name); // jméno se změnilo

        $this->delete(route('participants.destroy', $participant)); // smazání
        $this->assertDatabaseCount('participants', 0); // účastník je pryč
    } // konec testu

    public static function invalidEmails(): array // neplatné e-maily
    {
        return [[''], ['bez-zavinace'], ['a@b'], ['a b@c.cz'], ['a@@b.cz']]; // každá položka = jedno spuštění testu
    } // konec invalidEmails()

    #[DataProvider('invalidEmails')] // spustí test pro každý e-mail
    public function test_email_musi_byt_platny(string $email): void // neplatný e-mail se odmítne
    {
        $this->post('/participants', ['name' => 'Jan', 'email' => $email]) // pokus o vytvoření
            ->assertSessionHasErrors('email'); // chyba u pole "email"
        $this->assertDatabaseCount('participants', 0); // nic se neuložilo
    } // konec testu

    public function test_email_je_jedinecny(): void // stejný e-mail podruhé nelze
    {
        $this->post('/participants', ['name' => 'Jan', 'email' => 'jan@example.com']); // první účastník
        $this->post('/participants', ['name' => 'Jan 2', 'email' => 'JAN@example.com']) // stejný e-mail jinými písmeny
            ->assertSessionHasErrors('email'); // musí selhat
        $this->assertDatabaseCount('participants', 1); // v databázi je jen jeden
    } // konec testu
} // konec třídy
