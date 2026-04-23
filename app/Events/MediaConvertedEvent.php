<?php

namespace App\Events;

use App\Custom\MediaProcessor;
use App\Models\File;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaConvertedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public File $file;
    public MediaProcessor $mediaprocessor;
    public bool $error = false;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(MediaProcessor $mediaprocessor, bool $error = false)
    {
        $this->file = File::find($mediaprocessor->file_id);
        $this->mediaprocessor = $mediaprocessor;
        $this->error = $error;
    }
 
   


}
