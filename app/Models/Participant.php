<?php // otevírací značka PHP souboru

namespace App\Models; // jmenný prostor modelů

use Illuminate\Database\Eloquent\Factories\HasFactory; // podpora továren v testech
use Illuminate\Database\Eloquent\Model; // základní třída modelu
use Illuminate\Database\Eloquent\Relations\HasMany; // typ vztahu 1:N

// Model Participant = jeden řádek tabulky "participants".
class Participant extends Model // dědíme z Eloquent modelu
{
    use HasFactory; // umožní Participant::factory()

    protected $fillable = ['name', 'email']; // sloupce povolené pro hromadné vyplnění

    public function enrollments(): HasMany // vztah: účastník má mnoho přihlášek
    {
        return $this->hasMany(Enrollment::class); // enrollments.participant_id = participants.id
    } // konec enrollments()
} // konec třídy
