<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LastroBatch;
use App\Jobs\ProcessLastroZip;

class LastroIngestionController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validação Rápida (Síncrona)
        $request->validate([
            'arquivo_zip' => 'required|file|mimes:zip|max:102400', // 100MB
        ]);

        $file = $request->file('arquivo_zip');
        $originalName = $file->getClientOriginalName();

        // 2. Salvar no Disco Local (Temporário)
        // Pasta: storage/app/private/temp_uploads
        $path = $file->store('temp_uploads');

        // 3. Criar o Registro do Lote (Status: PENDENTE)
        $batch = LastroBatch::create([
            'nome_arquivo_zip' => $originalName,
            'status' => 'PENDENTE',
            'total_arquivos' => 0, // Será atualizado pelo Job
        ]);

        // 4. Despachar o Job (Aqui acontece a mágica do Async)
        // Passamos o ID do lote e o caminho do arquivo para o Job se virar
        ProcessLastroZip::dispatch($batch->id, $path);

        // 5. Resposta Imediata (Código 202: Accepted)
        return response()->json([
            'message' => 'Arquivo recebido com sucesso. O processamento iniciou.',
            'batch_id' => $batch->id,
            'status_url' => url("/api/lastro/status/{$batch->id}") // Link para acompanhar
        ], 202);
    }

    // Rota para o Frontend consultar o progresso (Polling)
    public function show($id)
    {
        $batch = LastroBatch::withCount('items')->findOrFail($id);

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status,
            'total_processado' => $batch->total_arquivos,
            'sucessos' => $batch->total_sucesso,
            'erros' => $batch->total_erros,
            'resumo_erro' => $batch->resumo_erro,
            'created_at' => $batch->created_at,
        ]);
    }
}
