<?php // otevírací značka PHP souboru

use Illuminate\Database\Migrations\Migration; // základní třída pro migrace
use Illuminate\Database\Schema\Blueprint; // popis sloupců tabulky
use Illuminate\Support\Facades\Schema; // práce se schématem databáze

return new class extends Migration // anonymní třída migrace
{
    public function up(): void // vytvoření tabulky
    {
        Schema::create('participants', function (Blueprint $table) { // tabulka "participants" (Participant)
            $table->id(); // primární klíč
            $table->string('name'); // jméno účastníka
            $table->string('email')->unique(); // e-mail – unikátní (ukládáme ho malými písmeny, viz ParticipantRequest)
            $table->timestamps(); // created_at a updated_at
        }); // konec definice tabulky
    } // konec metody up()

    public function down(): void // vrácení migrace
    {
        Schema::dropIfExists('participants'); // smaže tabulku
    } // konec metody down()
}; // konec třídy migrace
