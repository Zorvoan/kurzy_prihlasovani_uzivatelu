<?php // otevírací značka PHP souboru

use Illuminate\Database\Migrations\Migration; // základní třída pro migrace
use Illuminate\Database\Schema\Blueprint; // objekt, kterým popisujeme sloupce tabulky
use Illuminate\Support\Facades\Schema; // fasáda pro vytváření a mazání tabulek

// Migrace = verzovaná změna databáze. Spouští se příkazem "php artisan migrate".
return new class extends Migration // anonymní třída migrace
{
    public function up(): void // up() se spustí při migraci – vytvoří tabulku
    {
        Schema::create('courses', function (Blueprint $table) { // vytvoříme tabulku "courses" (Course)
            $table->id(); // sloupec id – primární klíč s automatickým číslováním
            $table->string('name'); // název kurzu (text, povinný)
            $table->string('code')->unique(); // kód kurzu – unikátní index, databáze nedovolí duplicitu
            $table->unsignedInteger('capacity'); // kapacita – celé nezáporné číslo (> 0 hlídá validace)
            $table->dateTime('starts_at')->index(); // termín začátku; index zrychlí filtrování podle termínu
            $table->timestamps(); // sloupce created_at a updated_at, Laravel je plní sám
        }); // konec definice tabulky
    } // konec metody up()

    public function down(): void // down() se spustí při vrácení migrace (php artisan migrate:rollback)
    {
        Schema::dropIfExists('courses'); // smaže tabulku, pokud existuje
    } // konec metody down()
}; // konec třídy migrace
