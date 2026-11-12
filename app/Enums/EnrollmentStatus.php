<?php // otevírací značka PHP souboru

namespace App\Enums; // jmenný prostor – podle něj Laravel (Composer) najde soubor

// Výčtový typ (enum) se všemi povolenými stavy přihlášky.
// Hodnota v uvozovkách je to, co se ukládá do databáze ve sloupci "status".
enum EnrollmentStatus: string // "string" = každý stav má textovou hodnotu
{
    case Enrolled = 'enrolled'; // přihlášen – účastník má v kurzu rezervované místo
    case Cancelled = 'cancelled'; // zrušeno – účastník se odhlásil, místo se uvolnilo
    case Completed = 'completed'; // dokončeno – účastník kurz absolvoval

    public function label(): string // český popisek stavu pro zobrazení na webu
    {
        return match ($this) { // match vrátí hodnotu podle toho, o který stav jde
            self::Enrolled => 'Přihlášen', // popisek pro enrolled
            self::Cancelled => 'Zrušeno', // popisek pro cancelled
            self::Completed => 'Dokončeno', // popisek pro completed
        }; // konec match
    } // konec metody label()

    public function isActive(): bool // zabírá přihláška v tomto stavu místo v kurzu?
    {
        return in_array($this, self::active(), true); // true, pokud je stav mezi aktivními
    } // konec metody isActive()

    /** @return list<self> */ // dokumentační komentář: metoda vrací seznam stavů
    public static function active(): array // seznam "aktivních" stavů, které se počítají do kapacity
    {
        // Dokončená přihláška místo nevrací (účastník kurz opravdu absolvoval),
        // zrušená ano – proto Cancelled v seznamu není.
        return [self::Enrolled, self::Completed]; // aktivní stavy
    } // konec metody active()

    /** @return list<string> */ // vrací seznam textových hodnot
    public static function activeValues(): array // aktivní stavy jako texty – hodí se do SQL dotazů (whereIn)
    {
        return array_map(fn (self $s) => $s->value, self::active()); // z každého stavu vezmeme jeho hodnotu
    } // konec metody activeValues()
} // konec enumu
