<?php // otevírací značka PHP souboru

namespace App\Models; // jmenný prostor modelů

use App\Enums\EnrollmentStatus; // výčet stavů přihlášky
use Illuminate\Database\Eloquent\Builder; // stavitel SQL dotazů (pro query scope)
use Illuminate\Database\Eloquent\Factories\HasFactory; // podpora továren (factory) v testech
use Illuminate\Database\Eloquent\Model; // základní třída Eloquent modelu
use Illuminate\Database\Eloquent\Relations\HasMany; // typ vztahu 1:N

// Model Course = jeden řádek tabulky "courses".
class Course extends Model // dědíme z Eloquent modelu
{
    use HasFactory; // umožní v testech volat Course::factory()

    protected $fillable = ['name', 'code', 'capacity', 'starts_at']; // sloupce, které lze hromadně vyplnit (ochrana před mass assignment)

    protected function casts(): array // jak převádět hodnoty z databáze na PHP typy
    {
        return [ // pole převodů
            'capacity' => 'integer', // kapacita bude vždy int
            'starts_at' => 'datetime', // termín bude objekt Carbon (datum a čas)
        ]; // konec pole
    } // konec metody casts()

    public function enrollments(): HasMany // vztah: kurz má mnoho přihlášek
    {
        return $this->hasMany(Enrollment::class); // Laravel hledá enrollments.course_id = courses.id
    } // konec metody enrollments()

    public function activeEnrollments(): HasMany // jen přihlášky, které zabírají místo
    {
        return $this->enrollments()->whereIn('status', EnrollmentStatus::activeValues()); // filtr na aktivní stavy
    } // konec metody activeEnrollments()

    public function scopeWithOccupancy(Builder $query): Builder // scope: Course::withOccupancy() přidá sloupec active_count
    {
        return $query->withCount(['activeEnrollments as active_count']); // jedním dotazem spočítá obsazená místa každého kurzu
    } // konec scopeWithOccupancy()

    public function scopeWithFreeSeats(Builder $query): Builder // scope: jen kurzy, kde je volné místo
    {
        return $query->where('capacity', '>', function ($sub) { // kapacita musí být větší než výsledek poddotazu...
            $sub->selectRaw('count(*)') // ...který spočítá...
                ->from('enrollments') // ...řádky v tabulce přihlášek...
                ->whereColumn('enrollments.course_id', 'courses.id') // ...tohoto kurzu...
                ->whereIn('status', EnrollmentStatus::activeValues()); // ...v aktivním stavu
        }); // konec poddotazu
    } // konec scopeWithFreeSeats()

    public function activeCount(): int // počet obsazených míst
    {
        return $this->active_count ?? $this->activeEnrollments()->count(); // použije předpočítanou hodnotu, jinak se zeptá databáze
    } // konec activeCount()

    public function freeSeats(): int // počet volných míst
    {
        return max(0, $this->capacity - $this->activeCount()); // kapacita minus obsazeno, nikdy záporné
    } // konec freeSeats()

    public function isFull(): bool // je kurz plně obsazen?
    {
        return $this->freeSeats() === 0; // žádné volné místo = plný
    } // konec isFull()
} // konec třídy
