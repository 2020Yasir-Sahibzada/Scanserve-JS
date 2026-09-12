<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanned_documents', function (Blueprint $table) {
            $table->id();

            // Original filename returned by ScanServJS
            $table->string('original_name');

            // Filename used inside Laravel storage
            $table->string('file_name');

            // Storage path
            $table->string('file_path');

            // Public URL
            $table->text('file_url')->nullable();

            // jpg, png, pdf, etc.
            $table->string('file_type', 20);

            // flatbed / adf
            $table->string('scan_type', 20)->default('flatbed');

            // File size in bytes
            $table->unsignedBigInteger('file_size')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanned_documents');
    }
};
