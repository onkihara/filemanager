<?php
namespace App\Custom;

use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Filters\Video\ResizeFilter as VideoFilters;
use Intervention\Image\Facades\Image;
use App\Exceptions\MediaException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use \App\Events\MediaConvertedEvent;
use Illuminate\Support\Facades\Log;

class MediaProcessor
{
    public bool $hasVideo = false;
    public bool $hasAudio = false;
    public int $file_id;
    public ?Dimension $dimensions = null;

    private String $disk;
    private String $thumbdisk;
    private String $original;
    private string $tmpfile;
    private string $thumbfile;
    private String $absoriginal;
    private String $abstmpfile;
    private String $absthumbfile;
    private Array $info;

    // testing
    public bool $testerror = false;


    public function __construct(String $path, String $thumbpath, String $filename, String $disk = 'files', $thumbdisk = 'thumbs')
    {
        ini_set('max_execution_time', config('filemanager.conversions.timeout', 30 * 60 * 60)); // in seconds
        set_time_limit(config('filemanager.conversions.timeout', 30 * 60 * 60)); // in seconds

        $this->disk = $disk;
        $this->thumbdisk = $thumbdisk;
        $this->original = $path.$filename;
        $this->tmpfile = $path.'tmp_'.$filename;
        $this->thumbfile = $thumbpath.config('filemanager.thumbnail.prefix').preg_replace('/\.[^\.]+$/','.'.config('filemanager.thumbnail.extension'),$filename);
        $this->absoriginal = Storage::disk($disk)->path($this->original);
        $this->abstmpfile = Storage::disk($disk)->path($this->tmpfile);
        $this->absthumbfile = Storage::disk($thumbdisk)->path($this->thumbfile);

        // get Media-Info
        $this->info = $this->getMediaInfo();

        // audio or video file?
        if (! empty($this->info['audiostreams'])) {
            $this->hasAudio = true;
        }
        if (! empty($this->info['videostreams'])) {
            $this->hasVideo = true;
        }

        //info($this->hasVideo ? 'Video file detected.' : ($this->hasAudio ? 'Audio file detected.' : 'No audio or video streams detected.'));

        // info($this->disk);
        // info($this->thumbdisk);
        // info($this->original);
        // info($this->tmpfile);
        // info($this->thumbfile);
        // info($this->absoriginal);
        // info($this->abstmpfile);
        // info($this->absthumbfile);
        // info($this->info);
    }
        
        
    public function run(int $file_id)
    {
        $this->file_id = $file_id;

        if ( ! Storage::disk($this->disk)->exists($this->original)) {
           throw new MediaException('File not found: '.$this->original.' on disk '.$this->disk);
        }

        // rename to tempfile
        Storage::disk($this->disk)->move($this->original, $this->tmpfile);

        // convert
        $this->convert();

    }


    public function mediaThumbnail(String $thumbfilename, String $targetpath)
    {
        // create thumbnail with FFMpeg
        if ($this->hasVideo) {
            $ffmpeg = FFMpeg::create();
            $media = $ffmpeg->open($this->absoriginal);
            $media->frame(TimeCode::fromSeconds(config('filemanager.thumbnail.frame_seconds')))->save($targetpath.$thumbfilename);
        } else if ($this->hasAudio) {
            // use default audio background
            $absthumbfile = Storage::disk('local')->path(config('filemanager.thumbnail.overlays.audio').'bg.jpg');
            copy($absthumbfile, $this->absthumbfile);
        }

    }
        
    
    private function updateThumbnail(int $step, string $type)
    {
        // store original thumbnail as backup
        if ( ! Storage::disk($this->thumbdisk)->exists($this->thumbfile.'.bak')) {
            Storage::disk($this->thumbdisk)->copy($this->thumbfile, $this->thumbfile.'.bak');
        }
            
        // update thumbnail with progress overlay
        $image = Image::make(Storage::disk($this->thumbdisk)->get($this->thumbfile.'.bak'));
        $overlay = Image::make(Storage::disk('local')->get(config('filemanager.thumbnail.overlays.'.$type).$step.'.png'));
        $image->insert($overlay, 'center');
        Storage::disk($this->thumbdisk)->put($this->thumbfile, (string) $image->encode());
        
        if ($step >= 9 && Storage::disk($this->thumbdisk)->exists($this->thumbfile.'.bak')) {
            // delete backup thumbnail
            Storage::disk($this->thumbdisk)->delete($this->thumbfile.'.bak');
        }
    }   



    private function getMediaInfo()
    {
        $ffprobe = FFProbe::create();
        $info = $ffprobe->format($this->absoriginal)->all();

        if ($info['nb_streams'] > 0) {
            $info['videostreams'] = $ffprobe->streams($this->absoriginal)->videos()->all();
            $info['audiostreams'] = $ffprobe->streams($this->absoriginal)->audios()->all();
        } else {
            $info['videostreams'] = [];
            $info['audiostreams'] = [];
        }

        return $info;
    }


    private function convert()
    {
        if ($this->hasAudio && ! $this->hasVideo) {
            // audio only file
            $conversion = 'FFMpeg\Format\Audio\\'.config('filemanager.conversions.audio.format');
            $type = 'audio';
        } else {
            // video file
            $conversion = 'FFMpeg\Format\Video\\'.config('filemanager.conversions.video.format');
            $type = 'video';
        }

        $format = new $conversion();

        $format->on('progress', function ($video, $format, $percentage) {
            $this->progress($percentage);
        });

        $logger = Log::getLogger();
        $ffmpeg = FFMpeg::create([
            'timeout' => config('filemanager.conversions.timeout', 30 * 60 * 60), // in seconds
            'threads' => config('filemanager.conversions.threads', 12),
        ], $logger);
        
        $media = $ffmpeg->open($this->abstmpfile);

        
        if ($type == 'video') {
            
            $this->calcDimensions();
            $mode = VideoFilters::RESIZEMODE_SCALE_HEIGHT;
            
            $media->filters()->resize($this->dimensions, $mode, false)->synchronize();
            
        }

        if ($type == 'audio') {
            $format->setAudioKiloBitrate(config('filemanager.conversions.audio.bitrate', 128));
        }

        try {
            $media->save($format, $this->getTargetFilePath($type));
        } catch (\Exception $e) {
            //info('My Conversion failed! '.$e->getMessage());
            $this->testerror = true;
        }

        // delete temp file
        @unlink($this->abstmpfile);

        Event::dispatch(new MediaConvertedEvent($this, $this->testerror));
   }
   
   
   private function calcDimensions()
   {
        $maxWidth = config('filemanager.conversions.video.max_width', 720);
        $maxHeight = config('filemanager.conversions.video.max_height', 720);

        $dims = $this->info['videostreams'][0]->getDimensions();
        $width = $dims->getWidth();
        $height = $dims->getHeight();

        if ($width > $maxWidth || $height > $maxHeight) {
            $aspectRatio = $width / $height;

            if ($aspectRatio > 1) {
                // Landscape
                $newWidth = min($width, $maxWidth);
                $newHeight = (int) ($newWidth / $aspectRatio);
            } else {
                // Portrait
                $newHeight = min($height, $maxHeight);
                $newWidth = (int) ($newHeight * $aspectRatio);
            }

            $this->dimensions = new Dimension($newWidth, $newHeight);
        }

        $this->dimensions = new Dimension($width, $height);
    }


    public function getTargetFilePath(String $type) : String
    {
        $extension = config('filemanager.conversions.'.$type.'.extension');
        $pwoext = pathinfo($this->absoriginal, PATHINFO_FILENAME);
        return dirname($this->absoriginal).'/'.$pwoext.'.'.$extension;
    }


    private function progress($percentage)
    {
        //info($percentage.'% converted for file ID '.$this->file_id);

        static $step = -1;
        $newstep = floor($percentage / 10);

        if ($this->hasVideo) {
            $type = 'video';
        } else if ($this->hasAudio) {
            $type = 'audio';
        }

        if ($percentage == 100) {
            //info('Conversion completed for file ID '.$this->file_id);
            @unlink($this->abstmpfile);
            @unlink($this->absthumbfile.'.bak');
            Event::dispatch(new MediaConvertedEvent($this));
            return;
        }

        if ($newstep > $step) {
            $step = $newstep;
            $this->updateThumbnail($step, $type);
        }

    }   

}