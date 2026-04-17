/ ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_04_01_000001_create_share_links_table.php
// Public shareable links (one per quiz/content)
// ─────────────────────────────────────────────────────────────
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_links', function (Blueprint $table) {
            $table->id();
            $table->string('share_code', 32)->unique();

            // Polymorphic: Quiz, PracticeSet, CodingTest
            $table->string('shareable_type');
            $table->unsignedBigInteger('shareable_id');

            $table->string('title')->nullable();           // Custom title for share message
            $table->text('message')->nullable();            // Custom message for share
            $table->string('thumbnail_url', 500)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('require_registration')->default(true);
            $table->timestamp('expires_at')->nullable();    // null = never expires
            $table->unsignedInteger('max_registrations')->nullable(); // null = unlimited

            // Stats (denormalized)
            $table->unsignedInteger('click_count')->default(0);
            $table->unsignedInteger('registration_count')->default(0);
            $table->unsignedInteger('attempt_count')->default(0);

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['shareable_type', 'shareable_id']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_links');
    }
};
