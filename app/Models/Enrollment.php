<?php // otevírací značka PHP souboru

namespace App\Models; // jmenný prostor modelů

use App\Enums\EnrollmentStatus; // výčet stavů
use Illuminate\Database\Eloquent\Model; // základní třída modelu
use Illuminate\Database\Eloquent\Relations\BelongsTo; // typ vztahu N:1

// Model Enrollment = přihláška, propojuje účastníka s kurzem a má vlastní stav.
class Enrollment extends Model // dědíme z Eloquent modelu
{
    protected $fillable = ['course_id', 'participant_id', 'enrolled_at', 'status']; // povolené sloupce

    protected function casts(): array // převody typů
    {
        return [ // pole převodů
            'enrolled_at' => 'datetime', // čas přihlášení jako objekt Carbon
            'status' => EnrollmentStatus::class, // text z DB se automaticky převede na enum EnrollmentStatus
        ]; // konec pole
    } // konec casts()

    public function course(): BelongsTo // vztah: přihláška patří ke kurzu
    {
        return $this->belongsTo(Course::class); // podle sloupce course_id
    } // konec course()

    public function participant(): BelongsTo // vztah: přihláška patří účastníkovi
    {
        return $this->belongsTo(Participant::class); // podle sloupce participant_id
    } // konec participant()
} // konec třídy
