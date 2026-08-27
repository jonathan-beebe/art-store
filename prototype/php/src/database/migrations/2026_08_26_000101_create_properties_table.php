<?php

declare(strict_types=1);

use App\Domain\Configurator\PropertyDataType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->string('name');
            $table->string('data_type')->default(PropertyDataType::Enum->value);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
