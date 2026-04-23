<?php

namespace App\Models;

use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use App\Custom\MediaProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File as Httpfile;
use Iman\Streamer\VideoStreamer;
use App\Exceptions\MediaException;

class File extends Model
{
    /**
     * Set primary-key-name
     */
    protected $primaryKey = 'ID';

    /**
    * should be stored in session
    */
    static public $sessionable = ['scope','type','target','link','width','height','size'];

    /**
     * mass assignable
     */
    public $fillable = ['UserID','Scope','Filedisk','Filepath','Filename','Virtualpath','Thumbdisk','Thumbpath','Thumbname','Original','Extension','MimeType','License','By','Type','Size','Meta','State','Visibility', 'CreationDate'];

    /**
     * No automated timestamps
     */
    public $timestamps = false;

    /**
     * Path to (raw) files
     */
    public $fpath = '';
    public $absfpath = '';

    /**
     * Path to thumbnails
     */
    public $tpath = '';
    public $abstpath = '';

    /**
     * File-type by extension
     * 'viewable' => [ ... ]
     */
    protected $contenttypes = [];

   /**
     * File-types by number
     * 1 => 'viewable'
     */
    protected $filetypes = [];

    /**
     * Unknown type number
     */
    protected $unknowntype;

    /**
     * The Iconpath and Iconurl
     */
    public $iconurl;
    public $iconpath;

    /**
     * The MediaProcessor-Instance
     */
    public $mediaprocessor;


    /**
     * Constructor sets the configuration
     */
    public function __construct(array $attributes = [])
    {
        // Pathinformation
        $rawpath = config('filemanager.paths.file') ? config('filemanager.paths.file').'/' : '';
        $thumbpath = config('filemanager.paths.thumb') ? config('filemanager.paths.thumb').'/' : '';
        $sub = '';
        if (config('filemanager.paths.sub') == 'year') {
            $sub = date('Y').'/';
        }
        if (config('filemanager.paths.sub') == 'month') {
            $sub = date('Y').'/'.date('m'.'/');
        }
        $this->fpath = $rawpath.$sub;
        $this->tpath = $thumbpath.$sub;
        $this->absfpath = Storage::disk('files')->path($this->fpath);
        $this->abstpath = Storage::disk('files')->path($this->tpath);

        // icons
        $this->iconurl = url(config('filemanager.thumbnail.icons'));
        $this->iconpath = public_path('files/'.config('filemanager.thumbnail.icons'));
    
        // File- and Content-Types by extensions
        foreach (config('filemanager.filetypes') as $type => $typeset) {
            $this->filetypes[$type] = key($typeset);
            if (key($typeset) == 'unknown') {
                $this->unknowntype = $type;
            }
            foreach (explode(',',current($typeset)) as $typename => $types) {
                $this->contenttypes[key($typeset)][] = strtolower(trim($types));
            }
        }
        // parent constructor
        parent::__construct($attributes);
    }

    /**
     * Fileinstances-Relastionship
     */
    public function fileinstances()
    {
        return $this->hasMany(Fileinstance::class,'FileID');
    }

    /**
     * Adds a File to Filesystem and Database
     *
     * @param UploadedFile $file
     * @param booean
     */
    public function addFile(UploadedFile $uploaded_file, Request $request) : ?File
    {
        [$namewithextension, $extension] = $this->fillData($uploaded_file, $request);

        // move file to user-file-system divided by years/month
        $this->saveRaw($uploaded_file,$namewithextension);
        
        // determine type
        $type = $this->determineType($extension);

        if ($this->isMediaType($type)) {
            // create MediaProcessor-Instance for mediable files
            $this->mediaprocessor = new MediaProcessor($this->fpath, $this->tpath, $namewithextension, 'files', 'thumbs');
        }
        
        // try to create a thumbnail
        $thumbfile = '';
        if (config('filemanager.thumbnail.on')) {
            $thumbfile = $this->makeThumb($namewithextension,$extension,$type);
        }
        
        $this->Thumbname = $thumbfile;
        $this->Type = $type;
        $this->Size = $uploaded_file->getSize();
        $this->Meta = $this->setMeta();
        $this->State = $this->isMediaType($type) ? 0 : 1;

        // save database, change or cleant aferwards
        if ( ! $this->save()) {
            return null;
        }

        // mediable
        if ($this->isMediaType($type)) {

            // adjusting database-entries for converted file after conversion is done in Mediaprocessor

            if (config('app.env') == 'testing') {

                // synchronous conversion (for testing)
                $this->mediaprocessor->run($this->getKey());

            } else {

                // asynchronous conversion with artisan command
                exec('php '.base_path().'/artisan media:convert "'.$this->getKey().'" "'.$this->fpath.'" "'.$this->tpath.'" "'.$namewithextension.'" "files" "thumbs" > /dev/null &');
                
            }
           
        } else {
            
            // clean database, if file not exists
            if ( ! Storage::disk('files')->exists($this->fpath.$namewithextension)) {
                $this->delete();  
                return null;
            }

        } 
                    
        return $this;
    }


    private function fillData(UploadedFile $uploaded_file, Request $request) : array
    {
        // should file be replaced (only first one if there
        // are multiple files with same virtualpath and original name)
        $path = urldecode($request->input('path'));
        $original = basename($path);
        $oldfile = $this->getOriginalFromVirtualpath($path)->first();
        if ($request->input('to_replace',false) && $oldfile) {
            $this->Id = $oldfile->getKey();
            if ( ! $oldfile->delete()) {
                throw new MediaException('Could not replace file with virtual path '.$path);
            }
        }

        // rename the file and get infos
        $name = time().'_'.uniqid();
        $extension = strtolower($uploaded_file->getClientOriginalExtension());
        $namewithextension = $name.'.'.$extension;
        $mimetype = $uploaded_file->getClientMimeType();
        $originalfilename = $original ?: $uploaded_file->getClientOriginalName();

        // scope set?
        $scope = '';
        if ($request->has('scope')) {
            $scope = $request->input('scope');
        } elseif ($request->session()->has('scope')) {
            $scope = $request->session()->get('scope');
        }

        // fill model
        $data = [
            'UserID' => $request->userid ?? session('userdata')['ID'],
            'Scope' => $scope,
            'Filedisk' => 'files',
            'Filepath' => $this->fpath,
            'Virtualpath' => $request->vpath ?? '',
            'Filename' => $namewithextension,
            'Thumbdisk' => 'thumbs',
            'Thumbpath' => $this->tpath,
            'Original' => $originalfilename,
            'Extension' => $extension,
            'MimeType' => $mimetype,
            'License' => 'C',
            'By' => $request->by ?? $request->username ?? session('userdata')['Name'] ?? 'api',
            'Visibility' => 'auth',
            'CreationDate' => new \DateTime(),
        ];
        
        $this->fill($data);

        return [$namewithextension, $extension];
    }


    /**
     * Save the raw-Version of the file with corrected dimensions if given
     */
    private function saveRaw(UploadedFile $file, string $namewithextension)
    {
        // save row version
        Storage::disk('files')->putFileAs($this->fpath,$file,$namewithextension);
    }

    /**
     * Determine the type of the file out of config
     */
    public function determineType(string $extension) : int
    {
        foreach ($this->filetypes as $type => $typename) {
            if (in_array($extension,$this->contenttypes[$typename])) {
                return $type;
            }
        }
        return $this->unknowntype;
    }

    /**
     * Make a Thumbnail
     */
    public function makeThumb(string $filename, string $extension, int $type) : string
    {

        // check if file exists
        if ( ! Storage::disk('files')->exists($this->fpath.$filename)) {
            return '';
        }

        // not media or non-thumbable file?
        if ( !$this->isMediaType($type) && ! $this->isThumbable($extension)) {
            return '';
        }

        // create thumb and store as thumbnail
        $filenamewoextension = pathinfo($filename, PATHINFO_FILENAME);
        $thumbfilename = config('filemanager.thumbnail.prefix').$filenamewoextension.'.'.config('filemanager.thumbnail.extension');

        $this->createThumbNail($filename, $thumbfilename, $type);

        // check if exists
        if ( ! Storage::disk('thumbs')->exists($this->tpath.$thumbfilename) ) {
            return '';
        }

        return $thumbfilename;
    }

    /**
     * Create the thumbnail in temporary directory and store with Storage
     */
    private function createThumbNail(string $filename, string $thumbfilename, int $type)
    {
        // thumbnail-Dimensions
        $dimensions = ['width' => config('filemanager.thumbnail.width'), 'height' => config('filemanager.thumbnail.height')];
        $filepath = $this->fpath.$filename;

        // preprocess by MediaProcessor for video files
        if ($this->isMediaType($type)) {

            $this->mediaprocessor->mediaThumbnail($thumbfilename, $this->abstpath);
            $filepath = $this->tpath.$thumbfilename;

        }

        $this->imageThumbnail($filepath, $thumbfilename, $dimensions);

    }


    /**
     * Create a thumbnail for an image
     */
    private function imageThumbnail(string $filepath, string $thumbfilename, array $dimensions)
    {
        // tempdir if not existing
        $temppath = config('filemanager.paths.tempdir');
        if ( ! Storage::disk('local')->exists($temppath)) {
            Storage::disk('local')->makeDirectory($temppath);
        }

        // create temp thumbnail
        $thumbfilepath = $this->getThumbfilePath($temppath.'/'.$thumbfilename);
        try {
            $image = Image::make(Storage::disk('files')->get($filepath));
        } catch (\Exception $e) {
            info($e->getMessage());
            return;
        }
        $thumb = $image->fit($dimensions['width'], $dimensions['height'])->save($thumbfilepath);

        // move to thumbpath
        if (Storage::disk('local')->exists($temppath.'/'.$thumbfilename)) {
            Storage::disk('thumbs')->putFileAs($this->tpath,new Httpfile($thumbfilepath),$thumbfilename);
            Storage::disk('local')->delete($temppath.'/'.$thumbfilename);
        }

        // doing garbage-collection
        self::gc($temppath,'local',config('filemanager.gctime'),config('filemanager.thumbnail.prefix'));
    }


    /**
     * Get the path to the thumbfile
     *
     * @param string $thumbfilepath
     * @return string Full path to the thumbs
     */
    private function getThumbfilePath($thumbfilepath)
    {
        return config('filesystems.disks.local.root').'/'.$thumbfilepath;
    }

    /**
     * Checks if file is "thumbable"
     *
     * @param string $extension
     * @return boolean
     */
    private function isThumbable($extension)
    {
        // thumbable extensions
        foreach (explode(',',config('filemanager.thumbnail.thumbables')) as $ext) {
            $thumbables[] = strtolower(trim($ext));
        }
        return in_array($extension, $thumbables);
    }

    /**
     * Gets an Icon for the file, if exists a thumbnail, the thumbnail-accessor-function
     * will be returned (File-Object needs to be filled)
     *
     * @param void
     * @return string Icon-url
     */
    public function icon()
    {
        // thumbnail
        if ($this->Thumbname) {
            return url('thumbnail').'/'.$this->getKey();
        }

        // or icon
        $icon = $this->iconurl.'/'.$this->getAttribute('Extension').'.jpg';
        if (is_file($this->iconpath.'/'.$this->getAttribute('Extension').'.jpg')) {
            return $icon;
        }

        // default icon
        return $this->iconurl.'/'.'default.jpg';
    }

    /**
     * Get the url to retrieve the file
     *
     * @param 
     * @return string
     */
    public function fileurl()
    {
        // viewable?
        if ($this->isViewable()) {
            return url('image').'/'.$this->getKey();
        }

        // mediable?
        if ($this->isMediable()) {
            return url('media').'/'.$this->getKey();
        }

        // default
        return url('download').'/'.$this->getKey();
    }

   /**
     * Get the download-url to retrieve the file
     *
     * @param 
     * @return string
     */
    public function downurl()
    {
        return url('download').'/'.$this->getKey();
    }

   /**
     * Get the url to delete the file
     *
     * @param 
     * @return string
     */
    public function deleteurl()
    {
        // default
        return url('request/delete').'/'.$this->getKey();
    }

   /**
     * Refresh Media thumbnail
     *
     * @param 
     * @return string
     */
    public function fileDataUrl()
    {
        return url('request/filedata').'/'.$this->getKey();
    }

    /**
     * is viewable (viewable image in browsers)
     *
     * @return bool
     */
    public function isViewable()
    {
        return in_array($this->getAttribute('Extension'), $this->contenttypes['viewable']);
    }

    /**
     * is mediable (playable video/audio in browsers)
     *
     * @return bool
     */
    public function isMediable()
    {
        return in_array($this->getAttribute('Extension'), array_merge($this->contenttypes['audible'], $this->contenttypes['videable']));
    }

    /**
     * get the path to the actual loaded file
     *
     * @return string
     */
    public function getFilepath()
    {
        return $this->getAttribute('Filepath').$this->getAttribute('Filename');
    }

    /**
     * get the path to the actual loaded file
     *
     * @return string
     */
    public function getVirtualpath()
    {
        return $this->getAttribute('Virtualpath').$this->getAttribute('Original');
    }

    /**
     * get the path to the actual loaded file
     *
     * @return string
     */
    public function getFiledisk()
    {
        return $this->getAttribute('Filedisk');
    }

    /**
     * get the thumbpath to the actual loaded file
     *
     * @return string
     */
    public function getThumbpath()
    {
        return $this->getAttribute('Thumbpath').$this->getAttribute('Thumbname');
    }

    /**
     * get the path to the actual loaded file
     *
     * @return string
     */
    public function getThumbdisk()
    {
        return $this->getAttribute('Thumbdisk');
    }

    /**
     * Delete File(s) and entry
     *
     * @param void
     * @return boolean 
     */
    public function delete()
    {
        // delete db-entry
        if ( ! parent::delete()) {
            return false;
        }

        // exists?
        if ( ! Storage::disk('files')->exists($this->Filepath.$this->Filename)) {
            return false;
        }

        // delete
        if ( ! Storage::disk('files')->delete($this->Filepath.$this->Filename)) {
            return false;
        }

        // delete thumnail if it exists (ignoring any errors)
        if ( ! empty($this->Thumbname)) {
            Storage::disk('thumbs')->delete($this->Thumbpath.$this->Thumbname);
        }

        return true;
    }

    /**
     * Gibt die Type-Nummer eines Types zurück
     *
     * @param string $typename ('viewable', ...)
     * @return integer
     */
    static public function getType($typename)
    {
        foreach (config('filemanager.filetypes') as $type => $typeset) {
            if (key($typeset) == $typename) {
                return $type;
            }
        }
        return 255;
    }

    // gibt den typnamen aufgrund der typenummer zurück
    static public function getTypeName(int $typenumber) : string
    {
        foreach (config('filemanager.filetypes') as $type => $typeset) {
            if ($type == $typenumber) {
                return key($typeset);
            }
        }
        return 'unknown';
    }

    /** 
     * @param $path Virtual path + originalfilename
     * @return Collection fo DB-File-entries
     * */
    public function getOriginalFromVirtualpath($path)
    {
        $virtualpath = dirname($path).'/';
        $original = basename($path);
        return $this->where('Virtualpath',$virtualpath)->where('Original',$original)->get();
    }


    public function stream()
    {
        $path = Storage::disk($this->getFiledisk())->getDriver()->getAdapter()->getPathPrefix();
        $path .= $this->getFilepath();
        VideoStreamer::streamFile($path);
    }

    /**
     * Stream Media: Model must be retrieved
     * @return Response-Stream
     */
    public function streamMedia()
    {
        $fs = Storage::disk($this->getFiledisk())->getDriver();
        $stream = $fs->readStream($this->getFilepath());

        // clean buffer before fpassthru
        if (ob_get_level()) ob_end_clean();

        return response()->stream(
            function() use ($stream) {
                fpassthru($stream);
            },
            200,
            [
                'Content-Type' => $this->getAttribute('MimeType'),
                'Content-Length' => $this->getAttribute('Size'),
                'Expires' => '-1',
                'Cache-Control' => "no-store, no-cache, must-revalidate",
                'Cache-Control' => "post-check=0, pre-check=0"
            ]);

        // alternative Method:
        // header('Content-Type: '.$this->getAttribute('MimeType'));
        // header('Content-Length: '.$this->getAttribute('size'));
        // header('Content-disposition: attachment; filename='.$this->getAttribute('Original'));
        // header("Expires: -1");
        // header("Cache-Control: ");
        // header("Cache-Control: ", false);
        // $fd = fopen($filepath, "r");
        // while(!feof($fd)) {
        //     echo fread($fd, 1024 * 5);
        //     ob_flush();
        // }
        // fclose ($fd);
        // return;

    }


    /**
     * Download-File: Model must be retrieved
     * @return Response-Stream
     */
    public function download()
    {
        $fs = Storage::disk($this->getFiledisk())->getDriver();
        $stream = $fs->readStream($this->getFilepath());
        // clean buffer before fpassthru
        if (ob_get_level()) ob_end_clean();

        return response()->stream(
            function() use ($stream) {
                fpassthru($stream);
            },
            200,
            [
                'Content-Type' => $this->getAttribute('MimeType'),
                'Content-Length' => $this->getAttribute('Size'),
                'Content-disposition' => 'attachment; filename='.$this->getAttribute('Original')
            ]);

    }


    /**
     * doing the garbage-collection
     *
     * @param string $dirname
     * @param integer $gc-time in minutes
     * @return integer
     */
    static public function gc($dirname,$disk='local',$gctime=200,$filenamepart='')
    {
        $files = Storage::disk($disk)->files($dirname);

        // nothing to do
        if (empty($files)) {
            return;
        }

        foreach ($files as $file) {
            if (Storage::disk($disk)->lastModified($file) < time() - $gctime * 60) {
                if (empty($filenamepart) || strstr($file,$filenamepart) !== false) {
                    Storage::disk($disk)->delete($file);
                }
            }

        }
    }


    private function setMeta() {
        $meta = ['filesize' => $this->Size];
        // meta for viewables
        if ($this->isViewable()) {
            $filename = config('filesystems.disks.files.root').'/'.$this->getFilepath();
            if (config('app.env') != 'testing') {
                $size = getimagesize($filename);
            }
            $meta['width'] = $size[0] ?? 0;
            $meta['height'] = $size[1] ?? 0;
        } 
        return json_encode($meta);
    }


    public function getMeta($metaname) { 
        // workaround for old Files
        if ( ! $this->Meta) {
            $this->Meta = $this->setMeta();
            $this->save();
        }
        $meta = json_decode($this->Meta,true);
        return $meta[$metaname] ?? null;
    }


    private function isMediaType(int $type) : bool {
        return $type == 2 || $type == 4;
    }


}
