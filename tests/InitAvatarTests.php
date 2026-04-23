<?php

namespace Tests;

use Artisan;
use Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use \Firebase\JWT\JWT;


trait InitAvatarTests
{

    protected static $db_inited = false;

    public function setUp() : void
    {
        parent::setUp();

        $this->initDatabase();

        $this->withHeaders([
            'Authorization' => static::generate(154,'avataredit',3)
        ]);
    }

    public function migrate()
    {
        // the file tables
        Artisan::call('migrate --env=testing --path="database/migrations/"');

        // the oauth tables (for api-testing)
        //Artisan::call('migrate --env=testing --path="database/migrations/oauth/"');
    }

    public function seeder()
    {
        // seed files data
        Artisan::call('db:seed --env=testing --class=TestFileDatabaseSeeder');
    }

    public function initDatabase()
    {
        if (!static::$db_inited) {

            $this->reset();
            
            // done-flag
            static::$db_inited = true;
        }
    }

    public function reinitDatabase()
    {
        static::$db_inited = false;
        $this->initDatabase();
    }

    public function reset()
    {
        $database = env('DB_DATABASE');
        $blueprintdb = env('DB_BLUEPRINT');

        // alte Datenbank löschen
        // if (file_exists($database)) {
        //     try {
        //         unlink($database);
        //     } catch(Exception $exception) {
        //         die($exception->getMessage());
        //     }
        // }

        // blueprintdb existiert
        if (is_file($blueprintdb)) {
            copy($blueprintdb,$database);
            return;
        }

        // blueprint und database neu erzeugen
        $handle = fopen($database, 'w');
        fclose($handle);

        // migrate and seed
        $this->migrate();
        $this->seeder();

        if (is_file($blueprintdb)) copy($database,$blueprintdb);
    }

    /**
     * erzeugt ein JWT-Token
     *
     * @param int $instance (optional)
     * @param App\User $user (optional)
     *
     * @return String
     */
     public static function generate($instance,$appname,$userid)
     {
        $algo = config('auth.api.v1.algo');
        $key = config('auth.api.v1.secret');

        $now = time();

        $payload = array(
            "iss" => 'Testing',
            "sub" => 'Testing',
            "aud" => 'TEST-ENV',
            "iat" => $now,
            "exp" => $now + 300000,
            "instance" => $instance,
            "appname" => $appname,
            "userid" => $userid
        );

        /**
         * IMPORTANT:
         * You must specify supported algorithms for your application. See
         * https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40
         * for a list of spec-compliant algorithms.
         */
        return 'Bearer '.JWT::encode($payload, $key, $algo);
     }
}
