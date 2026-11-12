<?php // otevírací značka PHP souboru

use Illuminate\Database\Migrations\Migration; // základní třída pro migrace
use Illuminate\Database\Schema\Blueprint; // popis sloupců tabulky
use Illuminate\Support\Facades\Schema; // práce se schématem databáze

return new class extends Migration // anonymní třída migrace
{
    public function up(): void // vytvoření tabulky
    {
        Schema::create('enrollments', function (Blueprint $table) { // tabulka "enrollments" (Enrollment) – propojuje účastníka s kurzem
            $table->id(); // primární klíč
            $table->foreignId('course_id')->constrained()->cascadeOnDelete(); // cizí klíč na courses.id; smazáním kurzu zmizí jeho přihlášky
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete(); // cizí klíč na participants.id; smazáním účastníka zmizí jeho přihlášky
            $table->dateTime('enrolled_at'); // kdy se účastník přihlásil
            $table->string('status', 20)->default('enrolled'); // stav přihlášky: enrolled / cancelled / completed
            $table->timestamps(); // created_at a updated_at
            $table->unique(['course_id', 'participant_id']); // jeden účastník může mít v kurzu jen jednu přihlášku (pojistka v databázi)
            $table->index(['course_id', 'status']); // index pro rychlé počítání aktivních přihlášek kurzu
        }); // konec definice tabulky
    } // konec metody up()

    public function down(): void // vrácení migrace
    {
        Schema::dropIfExists('enrollments'); // smaže tabulku
    } // konec metody down()
}; // konec třídy migrace
