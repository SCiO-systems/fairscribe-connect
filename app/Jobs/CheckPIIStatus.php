<?php

namespace App\Jobs;

use Log;
use Exception;
use App\Enums\PIIStatus;
use App\Models\ResourceFile;
use Illuminate\Bus\Queueable;
use App\Utilities\SCIO\PIIChecker;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class CheckPIIStatus implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The files that should be checked.
     */
    protected $files;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->files = ResourceFile::where('pii_check_status', PIIStatus::PENDING)
            ->whereNotNull('pii_check_status_identifier')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        foreach ($this->files as $resourceFile) {

            $id = $resourceFile->pii_check_status_identifier;

            if (empty($id)) {
                Log::error('Failed to check PII status. Missing identifier.', [
                    'resource_file' => $resourceFile->id,
                    'job' => get_class($this),
                ]);
                continue;
            }

            try {
                $status = (new PIIChecker())->getStatus($id);
                $resourceFile->setPIIStatus($status);

                Log::info('Got PII status for resource file.', [
                    'status' => $status,
                    'id' => $id,
                    'resource_file' => $resourceFile->id,
                ]);
            } catch (Exception $ex) {
                Log::error('Failed to check status for resource file.', [
                    'resource_file' => $resourceFile->id,
                    'job' => get_class($this),
                    'error' => $ex->getMessage(),
                ]);
            }
        }
    }
}
