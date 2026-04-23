<?php

namespace Database\Seeders;

use App\Models\Fileinstance;
use Illuminate\Database\Seeder;

class TestFileDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        // seeding some files
        \DB::insert("INSERT INTO `files` (`ID`, `UserID`, `Scope`, `Filedisk`, `Filepath`, `Virtualpath`, `Filename`, `Thumbdisk`, `Thumbpath`, `Thumbname`, `Original`, `Extension`, `MimeType`, `License`, `By`, `Type`, `Size`, `State`, `Visibility`, `CreationDate`) VALUES
            (1, 3, 'groups.153.profile', 'files', 'raws/2021/03/', '', '1617204369_60649491639b7.jpg', 'thumbs', 'thumbs/2021/03/', 'tn_1617204369_60649491639b7.jpg', '640px-Elvas_(Brixen).JPG', 'jpg', 'image/jpeg', 'C', 'Angerer Harald', 1, 75280, 1, 'auth', '2021-03-31 15:26:09'),
            (2, 3, 'groups.153.profile', 'files', 'raws/2021/03/', 'virt1/virt2/', '1617204369_6064949155f13.jpg', 'thumbs', 'thumbs/2021/03/', 'tn_1617204369_6064949155f13.jpg', 'Patrick Süßkind.jpg', 'jpg', 'image/jpeg', 'C', 'Angerer Harald', 1, 261166, 1, 'auth', '2021-03-31 15:26:09'),
            (3, 3, '', 'files', 'raws/2021/03/', '', '1617204371_606494931c57e.jpg', 'thumbs', 'thumbs/2021/03/', 'tn_1617204371_606494931c57e.jpg', 'lineman.jpg', 'jpg', 'image/jpeg', 'C', 'Angerer Harald', 1, 244049, 1, 'auth', '2021-03-31 15:26:11');"
        );

        // some Fileinstance for testing
        $fidata = [
            ['Userid' => 3, 'FileID' => 1, 'Scope' => 'groups.153.profile', 'Target' => 1, 'Unique' => 1 ],
            ['Userid' => 3, 'FileID' => 2, 'Scope' => 'groups.153.profile', 'Target' => 1, 'Unique' => 1 ],
            ['Userid' => 3, 'FileID' => 1, 'Scope' => 'groups.153.studentprofile', 'Target' => 1,'Link' => 'http://classroom.blikk.it/profile'],
        ];
        foreach ($fidata as $data) {
            $fi = new Fileinstance();
            foreach($data as $key => $value) {
                $fi->$key = $value;
            }
            $fi->creationDate = \Carbon\Carbon::now();
            $fi->save();
        }


        // user table for avatar test
        \DB::table('users')->insert(array('ID' => '3','UserName' => 'hangerer','Password' => '','PHPPass' => '$2y$10$1LAJ8bSNwYZMs2lCK./sx.5vilVi9qEVwj8BsDx9jWJ2dQkWxL.uu','Name' => 'Angerer Harald','Firstname' => 'Harald','Surname' => 'Angerer','Gender' => 'M','Birthyear' => '1969','Place' => 'Brixen','EMail' => 'harald.angerer@schule.suedtirol.it','Privdomain'=>0,'Homepage' => '','NumberLogins' => '84','FirstLogin' => '0000-00-00 00:00:00','LastLogin' => '2016-09-22 06:07:10','UserIcon' => '/forum/forums/leseblikk/userimages/hangerer/me.jpg','Token' => NULL,'TokenCreatedAt' => '2015-10-22 16:26:21','Type' => '1','Locked' => '0'));

        


    }
}
