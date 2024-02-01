<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRepositoriesForResources extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('resource_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')
                ->constrained('resources')
                ->onDelete('cascade');
            $table->foreignId('repository_id')
                ->constrained('user_repositories')
                ->onDelete('cascade');
            $table->json('metadata')->nullable();
            $table->string('collection')->nullable();
            $table->timestamps();
            $table->unique([
                'resource_id', 'repository_id', 'collection',
            ], 'resource_repo_collection_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('resource_repositories');
    }
}
