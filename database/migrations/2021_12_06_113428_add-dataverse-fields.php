<?php

use App\Enums\RepositoryType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDataverseFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropColumns('user_repositories', ['connection_verified', 'type']);

        Schema::table('user_repositories', function (Blueprint $table) {
            $table->string('type')->nullable()->after('user_id');
            $table->json('metadata')->nullable()->after('client_secret');
            $table->timestamp('connection_verified_at')
                ->nullable()
                ->default(null)
                ->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropColumns('user_repositories', ['connection_verified_at', 'type', 'metadata']);

        Schema::table('user_repositories', function (Blueprint $table) {
            $table->boolean('connection_verified')->default(false);
            $table->enum('type', RepositoryType::getValues())->nullable();
        });
    }
}
