<?php

namespace App\Jobs;

use App\Models\Resource;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ResourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Resource $resource;

    /**
     * Create a new job instance.
     */
    public function __construct(Resource $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if($this->resource->acquired_at !== null){
            $this->resource->update([
                'acquired_at' => null,
                'period' => null,
                'key' => null
            ]);
        }
    }
}
