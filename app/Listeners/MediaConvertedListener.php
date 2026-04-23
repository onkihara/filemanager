<?php

namespace App\Listeners;

use App\Custom\MediaProcessor;
use App\Models\File;
use App\Events\MediaConvertedEvent;
use FFMpeg\Coordinate\Dimension;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

class MediaConvertedListener
{
    private File $file;
    private MediaProcessor $mediaprocessor;
    private String $type;
    private String $filepath;


    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(MediaConvertedEvent $event)
    {
        $this->file = File::find($event->file->getKey());
        $this->mediaprocessor = $event->mediaprocessor;
        $this->type = $this->mediaprocessor->hasVideo ? 'video' : ($this->mediaprocessor->hasAudio ? 'audio' : 'unknown');
        $this->filepath = $this->mediaprocessor->getTargetFilePath($this->type);

        // error handling
        if ($event->error) {
            $this->rollback();
            return;
        }

        // adjust filename and path of converted media
        $this->adjust();

        // adjust meta data of converted media
        $this->adjustMetaData($event->mediaprocessor->dimensions);

        $this->file->save();
    }


    private function adjust()
    {
        // update file record        
        $this->file->Filename = basename($this->filepath);
        $this->file->MimeType = mime_content_type($this->filepath);
        $this->file->Extension = pathinfo($this->filepath, PATHINFO_EXTENSION);
        $this->file->Original = preg_replace('/\.[^\.]+$/','.'.$this->file->Extension, $this->file->Original);
        $this->file->Size = filesize($this->filepath);
        $this->file->State = 1;
    }
    


    private function adjustMetaData(?Dimension $dimensions)
    {
        // update meta data
        $meta = ['filesize' => $this->file->Size];

        if ($dimensions) {
            $meta['width'] = $dimensions->getWidth();
            $meta['height'] = $dimensions->getHeight();
        }

        $this->file->Meta = json_encode($meta);
    }



    // untested!
    private function rollback()
    {
        // delete converted file if exists
        if (file_exists($this->filepath)) {
            unlink($this->filepath);    
        }

        // delete thumbnail if exists
        $filenamewoextension = pathinfo($this->filepath, PATHINFO_FILENAME);
        $thumbfilename = config('filemanager.thumbnail.prefix').$filenamewoextension.'.'.config('filemanager.thumbnail.extension');
        $thumbpath = $this->file->abstpath.$thumbfilename;
        if (file_exists($thumbpath)) {
            unlink($thumbpath);    
        }

        // delete record
        $this->file->delete();
    }
}
