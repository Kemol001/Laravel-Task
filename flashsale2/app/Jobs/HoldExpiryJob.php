<?php

namespace App\Jobs;

use App\Models\Hold;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class HoldExpiryJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected Hold $hold;

    public function __construct(Hold $hold)
    {
        $this->hold = $hold;
    }

    public function handle(): void
    {
        $hold = Hold::find($this->hold->id);

        if ($hold && $hold->status === 'active' && $hold->expires_at->isPast()) {
            $hold->release();
            \Log::info("Hold expired and released", ['hold_id' => $hold->id]);
        }
    }
}