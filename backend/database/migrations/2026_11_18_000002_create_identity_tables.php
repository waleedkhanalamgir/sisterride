<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('cnic_number', 15);
            $table->string('cnic_image_path');
            $table->string('student_id_image_path')->nullable();
            $table->string('selfie_path');
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');          // bike | scooty | car | rickshaw
            $table->string('plate_number')->unique();
            $table->string('make_model')->nullable();
            $table->unsignedTinyInteger('seats')->default(1);
            $table->string('license_image_path');
            $table->string('registration_image_path')->nullable();
            $table->string('photo_path');
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->timestamps();
        });

        Schema::create('trusted_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('relation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_contacts');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('verifications');
    }
};
