<?php

namespace App\Models;

use Storage;
use App\Models\File;
use App\Exceptions\AvatarException;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Avatar extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'vcards';

    /**
     * Set primary-key-name
     */
    protected $primaryKey = 'ID';

    /**
     * mass assignable
     */
    public $fillable = ['UserID','GroupID','Scope','Descriptor','Content'];

    /**
     * No automated timestamps
     */
    public $timestamps = false;


    /**
     * Creating an Avatar-Image
     *
     * @param array $avdata
     * @param integer $userid
     * @param string $type (avatar|team)
     * @return string path to avatar-image
     */
     public function createAvatar($avdata, $userid, $auth_token, $type = 'avatar')
     {
        // shortcuts
        $w = $avdata['width'];
        $h = $avdata['height'];
        $y = $avdata['top'];
        $x = $avdata['left'];
        $teint = $avdata['teint'];
        $color = $avdata['color'];
        $face = $avdata['face'];
        $template = $avdata['template'];
        $fileid = $avdata['file'];

        // processable?
        if (empty($w) || empty($h)) {
            throw new AvatarException(trans('avatar.exceptions.missing_dimensions'));
        }

        // is color defined?
        if ($color == -1) {
            $template = storage_path(config('avatar.templatepath.'.$type).'av_nocolor.png');
        } else {
            $template = storage_path(config('avatar.templatepath.'.$type).'av_'.$teint.'_'.$template.'_'.$color.'.png');
        }

        // get image-Path
        if ($fileid == 0) {
            // Face verwendet
            $face = storage_path(config('avatar.templatepath.'.$type).'face_'.$teint.'_'.$face.'.png');
        } else {
            // image verwendet
            $file = File::find($fileid);
            $face = config('filesystems.disks.files.root').'/'.$file->getFilepath();
        }

        
        // templates available?
        if (!is_file($face) || !is_file($template)) {
            $message = trans('avatar.exceptions.templates_not_found');
            if (config('app.env') != 'production') {
                $message .= ': "'.$face.'" or "'.$template.'"';
            }
            throw new AvatarException($message);
        }
        
        // scale and merge background-image
        // Images aus der Quelle erzeugen
        $image_x = $this->createByExtension($template);
        $image_y = $this->createByExtension($face);
        
        // Avatargrößen
        $avw = config('avatar.sizes.'.$type.'.width');
        $avh = config('avatar.sizes.'.$type.'.height');
        list($face_w, $face_h) = getimagesize($face);
        list($templ_w, $templ_h) = getimagesize($template);
        
        // neue images erzeugen
        $image = imagecreatetruecolor($avw, $avh);
        $bgimage = imagecreatetruecolor($w,$h);
        $fgimage = imagecreatetruecolor($templ_w,$templ_h);
        
        // Background skalieren
        imagecopyresampled($bgimage,$image_y,0,0,0,0,$w,$h,$face_w,$face_h);
        //imagepng($bgimage,$this->conf->avatarpath.'result.png');
        
        // Images in neues mergen
        imagecopy($fgimage, $bgimage, $x, $y, 0, 0, $w, $h);
        imagecopy($fgimage, $image_x, 0, 0, 0, 0, $templ_w, $templ_h);
        //imagepng($fgimage,$this->conf->avatarpath.'result1.png');
        imagecopyresampled($image, $fgimage, 0, 0, 0, 0, $avw, $avh, $templ_w, $templ_h);
        
        if (config('app.env') == 'testing') {
            
            // local saving for testing
            $profilepath = config('avatar.targettestpath.'.$type).$userid.'.png';
            if (!is_dir(dirname($profilepath))) {
                static::mkDirs(dirname($profilepath));
            }
            imagepng($image, $profilepath);
            \DB::table('users')->where('ID',$userid)->update(['UserIcon' => '/auth/profiles/'.$userid.'.png']);
            
        } else {
            
            // remote saving
            $this->saveProfile($auth_token, $image, $userid, $type);

        }

        return config('avatar.targeturl.'.$type).$userid.'.png';

     }


    /**
     * Delete Avatar-Image
     */
    public function deleteProfile($userid,$auth_token,$type = 'avatar')
    {
        if (config('app.env') == 'testing') {
            
            // deleting local file for testing
            $profilepath = config('avatar.targettestpath.'.$type).$userid.'.png';
            if (!is_file($profilepath)) {
                @unlink($profilepath);
            }
            return;
        }

        if (empty($auth_token) || !isset($auth_token['tokentype']) || !isset($auth_token['accesstoken'])) {
            return;
        }

        // delete from remote
        try {
            $resp = Http::withOptions(config('app.guzzle.options'))
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => $auth_token['tokentype'].' '.$auth_token['accesstoken'],
                ])
                ->delete(config('avatar.targetapiurl.'.$type), ['userid' => $userid]);
        } catch(\Throwable $exception) {
            throw new AvatarException($exception->getMessage());
        }

        $result = json_decode($resp->getBody(),true);

        if ($result['result'] != 'success') {
            $error_message = $result['message'] ?? trans('avatar.exceptions.saving_failed');
            throw new AvatarException($error_message);    
        }

    }
    


    /**
     * Saving the generated profile-image
     */
    public function saveProfile($token, $image, $userid, $type = 'avatar')
    {
        $profilepath = storage_path(config('avatar.profilepath')).$userid.'.png';
    
        imagepng($image, $profilepath);

        // testing workaround
         if (empty($auth_token) || !isset($auth_token['tokentype']) || !isset($auth_token['accesstoken'])) {
            return;
        }

        try {
            
            $resp = Http::withOptions(config('app.guzzle.options'))
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => $token['tokentype'].' '.$token['accesstoken'],
                    ])
                ->post(config('avatar.targetapiurl.'.$type), [
                    'profile' => $userid.'.png'
                ]);
                    
        } catch(\Throwable $exception) {
            throw new AvatarException($exception->getMessage());
        }
        
        $result = json_decode($resp->getBody(),true);

        if ($result['result'] != 'success') {
            $error_message = $result['message'] ?? trans('avatar.exceptions.saving_failed');
            throw new AvatarException($error_message);    
        }

    }


    /**
    * Create an gd-image from the extension
    * 
    * @param string $filepath
    */
    private function createByExtension($filepath)
    {
        $extension = pathinfo($filepath)['extension'];
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return imagecreatefromjpeg($filepath); 
            case 'png':
                return imagecreatefrompng($filepath);
            case 'gif':
                return imagecreatefromgif($filepath);
            case 'webp':
                return imagecreatefromwebp($filepath);
            case 'bmp':
                return imagecreatefromwbmp($filepath);
        }
        throw new AvatarException(trans('avatar.exceptions.noimageconverter'));
    }


    /**
    * Die Funktion erzeugt eine Verzeichnishierarchie
    *
    * @param string $dir Verzeichnispfad
    * @return bool true on success
    * @todo Exception-Handling
    */
    static function mkDirs($dir)
    {
        $dir = preg_replace('/\\\\/','/',$dir);
        $dirs = explode('/',$dir);
        if (!is_array($dirs) || empty($dirs)) {
            return false;
        }
        $dirpath = '';
        foreach ($dirs as $d) {
            $dirpath .= $d;
            if (!empty($d) && !strstr($d,':') && !is_dir($dirpath)) {
                if (!mkdir($dirpath,0766)) {
                    return false;
                }
            }
            $dirpath .= '/';
        }
        return true;
    }


}
