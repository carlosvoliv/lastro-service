<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\LastroProcessorService; // Importar o Service
use Throwable;

class ProcessLastroZip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutos de limite

    protected $batchId;
    protected $zipPath;

    public function __construct($batchId, $zipPath)
    {
        $this->batchId = $batchId;
        $this->zipPath = $zipPath;
    }

    public function handle(LastroProcessorService $service): void
    {
        // O Laravel injeta o Service automaticamente aqui
        $service->processar($this->batchId, $this->zipPath);
    }

    public function failed(Throwable $exception): void
    {
        // Se o Job quebrar feio (ex: falta de memória), marcamos no banco
        \App\Models\LastroBatch::find($this->batchId)?->update([
            'status' => 'FALHA_SISTEMA',
            'resumo_erro' => $exception->getMessage()
        ]);
    }
}
