<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\LastroBatch;
use Illuminate\Support\Facades\Log;

class ProcessLastroZip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batchId;
    protected $zipPath;

    public function __construct($batchId, $zipPath)
    {
        $this->batchId = $batchId;
        $this->zipPath = $zipPath;
    }

    public function handle(): void
    {
        // Por enquanto, apenas finge que trabalha
        Log::info("Iniciando processamento do Batch: {$this->batchId}");

        $batch = LastroBatch::find($this->batchId);
        if ($batch) {
            $batch->update(['status' => 'PROCESSANDO']);

            // Simula demora de 5 segundos
            sleep(5);

            $batch->update(['status' => 'CONCLUIDO_TESTE']);
        }
    }
}
