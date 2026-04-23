<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         // the files-table
         Schema::create('files', function(Blueprint $table) {
            $table->increments('ID');
            $table->integer('UserID')->unsigned();
            $table->string('Scope')->default('');
            $table->string('Filedisk');
            $table->string('Filepath');
            $table->string('Filename');
            $table->string('Thumbdisk');
            $table->string('Thumbpath');
            $table->string('Thumbname')->default('');
            $table->string('Original');
            $table->string('Extension');
            $table->string('MimeType');
            $table->string('License');
            $table->string('By');
            $table->integer('Type')->default(1);
            $table->integer('Size')->unsigned();
            $table->text('Meta')->nullable();
            $table->integer('State')->default(1);
            // auth, owner, public
            $table->string('Visibility')->default('auth');
            $table->dateTime('CreationDate');
            // index
            $table->index('UserID');
        });

         // the file_workspace-table
         Schema::create('fileinstances', function(Blueprint $table) {
            $table->increments('ID');
            $table->integer('FileID')->unsigned();
            $table->integer('UserID')->unsigned();
            $table->integer('Unique')->unsigned()->default(0);
            $table->string('Scope')->default('');
            $table->string('Target')->default('');
            $table->string('Link')->default('');
            $table->dateTime('CreationDate');

            // foreigns files
            $table->foreign('FileID')
                ->references('ID')
                ->on('files')
                ->onDelete('cascade');

        });


        // only for testing:

        if ( ! Schema::hasTable('vcards')) {
             Schema::create('vcards', function($table) {
                    $table->increments('ID');
                    $table->integer('UserID')->unsigned()->nullable();
                    $table->integer('GroupID')->unsigned()->nullable();
                    $table->string('Scope', 255)->nullable();
                    $table->string('Descriptor', 255)->nullable();
                    $table->text('Content')->nullable();
                    // indizes
                    $table->index('UserID');
                    $table->index('GroupID');
            });
        }

        if ( ! Schema::hasTable('users')) {
            // users-table
            Schema::create('users', function(Blueprint $t) {
                $t->increments('ID');
                $t->string('UserName');
                $t->string('Password')->nullable();
                $t->string('PHPPass');
                $t->string('Name');
                $t->string('Firstname');
                $t->string('Surname');
                $t->string('Gender')->default('M');
                $t->string('Birthyear')->default('');
                $t->string('Place')->default('');
                $t->string('EMail');
                $t->unsignedinteger('Privdomain')->default(0);
                $t->string('Homepage')->default('');
                $t->unsignedinteger('NumberLogins')->default(0);
                $t->timestamp('FirstLogin')->default(0);
                $t->timestamp('LastLogin')->default(0);
                $t->string('UserIcon')->default('');
                $t->string('Token')->nullable();
                $t->timestamp('TokenCreatedAt')->default('');
                $t->unsignedinteger('Type')->default(1);
                $t->unsignedinteger('Locked')->default(0);
            });
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // delete fileworkspaces-table
        Schema::dropIfExists('fileworkspaces');
        // delete files-table
        Schema::dropIfExists('files');
    }
}
