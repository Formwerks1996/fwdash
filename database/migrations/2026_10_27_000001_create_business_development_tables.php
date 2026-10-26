<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pursuits are typed by hand on the Pipeline page; there is no other record of them.
        Schema::create('pursuits', function (Blueprint $table) {
            $table->id();
            $table->string('developer')->default('');
            $table->string('project')->nullable();
            $table->decimal('fee', 14, 2)->nullable();
            $table->string('stage')->default('Contacted')->index();
            $table->string('owner')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Developers added by hand, plus the city and note for any developer, billing ones included.
        Schema::create('developers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->string('city')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // "This spelling means that firm". An overlay: project and pursuit text is never rewritten.
        Schema::create('developer_aliases', function (Blueprint $table) {
            $table->string('alias_key')->primary();
            $table->string('canon_key')->index();
            $table->string('alias_name')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('outreach_prospects', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('score')->default(0);
            $table->string('tier', 1)->nullable();
            $table->string('region')->nullable();
            $table->string('hq')->nullable();
            $table->text('product')->nullable();
            $table->text('size')->nullable();
            $table->text('style')->nullable();
            $table->text('why')->nullable();
            $table->text('architect')->nullable();
            $table->text('phased')->nullable();
            $table->string('website')->nullable();
            $table->string('webcheck')->nullable();
            $table->text('others')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('contact_page')->nullable();
            $table->text('route')->nullable();
            $table->text('contact_notes')->nullable();
            $table->string('confidence')->nullable();
            $table->text('client_note')->nullable();
            $table->text('flag')->nullable();
            $table->text('passes')->nullable();
            $table->boolean('top')->default(false);
            $table->string('img')->nullable();
            $table->boolean('has_contact')->default(false);
            $table->boolean('has_linkedin')->default(false);
            $table->boolean('has_email')->default(false);
            $table->json('person')->nullable();
            $table->json('activity')->nullable();
            $table->json('types')->nullable();
            $table->json('tags')->nullable();
            $table->json('sources')->nullable();
            $table->json('recent')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('outreach_projects', function (Blueprint $table) {
            $table->id();
            $table->string('prospect_key')->index();
            $table->string('name')->nullable();
            $table->string('where_text')->nullable();
            $table->string('units')->nullable();
            $table->text('url')->nullable();
            $table->string('away')->nullable();
            $table->string('img')->nullable();
            $table->boolean('hero')->default(false);
            $table->json('types')->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lon', 9, 6)->nullable();
            $table->boolean('exact')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
        });

        // Prospect and project share-images, kept in the database: the hosting filesystem is read-only.
        Schema::create('outreach_images', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->string('mime_type', 40);
            $table->longText('data');
        });

        // Shared sticky notes, pinned to a page (route name + parameter). Chatter, not records: not audited.
        Schema::create('sticky_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('place_target', 40);
            $table->string('place_arg', 120)->nullable();
            $table->string('place_label', 160)->default('');
            $table->text('body');
            $table->string('author', 60)->default('');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 8)->default('open');
            $table->decimal('pos_x', 6, 4)->default(0.7);
            $table->unsignedInteger('pos_y')->default(24);
            $table->string('deleted_by', 60)->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['place_target', 'place_arg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sticky_notes');
        Schema::dropIfExists('outreach_images');
        Schema::dropIfExists('outreach_projects');
        Schema::dropIfExists('outreach_prospects');
        Schema::dropIfExists('developer_aliases');
        Schema::dropIfExists('developers');
        Schema::dropIfExists('pursuits');
    }
};
