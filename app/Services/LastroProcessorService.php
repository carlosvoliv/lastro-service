<?php

namespace App\Services;

use App\Models\LastroBatch;
use App\Models\LastroItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;
use Exception;

class LastroProcessorService
{
    // Lista de tipos permitidos (Whitelist)
    const TIPOS_PERMITIDOS = [
        'ANTIFRAUDE',
        'AVERBACAO',
        'DOC_IDENT',
        'DOC_IDENT_F', // <--- Adicionado
        'DOC_IDENT_V', // <--- Adicionado por precaução (Verso)
        'CNH',
        'RG',
        'CCB',
        'COMPROVANTE'
    ];

    public function processar(string $batchId, string $zipPath)
    {
        $batch = LastroBatch::findOrFail($batchId);
        $batch->update(['status' => 'PROCESSANDO']);

        $fullZipPath = storage_path('app/private/' . $zipPath);
        $extractPath = storage_path('app/private/temp_extract/' . $batchId);

        try {
            // 1. Descompactar
            $this->extrairZip($fullZipPath, $extractPath);

            // 2. Varrer e Validar
            $arquivos = $this->listarArquivosRecursivos($extractPath);
            $batch->update(['total_arquivos' => count($arquivos)]);

            foreach ($arquivos as $arquivoPath) {
                $this->processarArquivoIndividual($batch, $arquivoPath, $extractPath);
            }

            // 3. Finalizar Lote
            $erros = $batch->items()->where('valido', false)->count();
            $sucessos = $batch->items()->where('valido', true)->count();

            $batch->update([
                'status' => $erros > 0 ? 'CONCLUIDO_PARCIAL' : 'CONCLUIDO',
                'total_sucesso' => $sucessos,
                'total_erros' => $erros
            ]);

        } catch (Exception $e) {
            Log::error("Erro fatal no batch $batchId: " . $e->getMessage());
            $batch->update([
                'status' => 'ERRO_FATAL',
                'resumo_erro' => $e->getMessage()
            ]);
        } finally {
            // 4. Limpeza (Apaga ZIP e Pasta extraída)
            if (file_exists($fullZipPath)) unlink($fullZipPath);
            $this->deltree($extractPath);
        }
    }

    private function processarArquivoIndividual($batch, $pathFisico, $rootPath)
    {
        $nomeArquivo = basename($pathFisico);
        $caminhoRelativo = str_replace($rootPath . '/', '', $pathFisico);

        // Pega a pasta pai (espera-se que seja o CPF)
        $pastaPai = basename(dirname($pathFisico));

        $item = LastroItem::create([
            'batch_id' => $batch->id,
            'nome_original' => $caminhoRelativo,
            'pasta_origem' => $pastaPai,
            'valido' => false
        ]);

        // --- VALIDAÇÕES ---

        // Regra 1: Pasta é CPF? (Formato 000.000.000-00)
        if (!preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', $pastaPai)) {
            $item->update(['motivo_rejeicao' => "Pasta '$pastaPai' não é um CPF válido"]);
            return;
        }

        // Regra 2: Nome do Arquivo (Contrato_CPF_Tipo.pdf)
        // Regex: Numeros_CPF_Texto.pdf
        if (!preg_match('/^(\d+)_(\d{3}\.\d{3}\.\d{3}-\d{2})_([A-Z_]+)\.pdf$/i', $nomeArquivo, $matches)) {
            $item->update(['motivo_rejeicao' => 'Nome fora do padrão (Use: Contrato_CPF_Tipo.pdf)']);
            return;
        }

        $contrato = $matches[1];
        $cpfArquivo = $matches[2];
        $tipoDoc = strtoupper($matches[3]);

        // Atualiza metadados extraídos
        $item->update([
            'cpf_detectado' => $cpfArquivo,
            'contrato_detectado' => $contrato,
            'tipo_documento' => $tipoDoc
        ]);

        // Regra 3: Consistência (CPF Pasta == CPF Arquivo)
        if ($cpfArquivo !== $pastaPai) {
            $item->update(['motivo_rejeicao' => "CPF do arquivo ($cpfArquivo) diverge da pasta ($pastaPai)"]);
            return;
        }

        // Regra 4: Tipo Permitido
        if (!in_array($tipoDoc, self::TIPOS_PERMITIDOS)) {
            $item->update(['motivo_rejeicao' => "Tipo de documento '$tipoDoc' não aceito"]);
            return;
        }

        // --- UPLOAD ZADARA ---
        try {
            // NOVO CÓDIGO (Respeita o prefixo e remove datas)
            $prefixo = env('ZADARA_PREFIX', ''); // Pega FIDC_AKRK do .env

            // Monta: FIDC_AKRK / 111.222.333-44 / UUID_NomeOriginal.pdf
            // Adicionei o nome original depois do UUID para facilitar sua visualização no bucket
            $s3Path = $prefixo . '/' . $cpfArquivo . '/' . $nomeArquivo;

            // Upload via Stream (Baixo consumo de RAM)
            $stream = fopen($pathFisico, 'r');
            Storage::disk('zadara')->put($s3Path, $stream);
            fclose($stream);

            $item->update([
                'valido' => true,
                'caminho_zadara' => $s3Path,
                // 'url_temporaria' => Storage::disk('s3')->temporaryUrl($s3Path, now()->addHour())
            ]);

        } catch (Exception $e) {
            $item->update(['motivo_rejeicao' => 'Erro no upload S3: ' . $e->getMessage()]);
        }
    }

    // --- Helpers de Arquivo ---

    private function extrairZip($zipPath, $destPath)
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) === TRUE) {
            if (!is_dir($destPath)) mkdir($destPath, 0777, true);
            $zip->extractTo($destPath);
            $zip->close();
        } else {
            throw new Exception("Não foi possível abrir o ZIP.");
        }
    }

    private function listarArquivosRecursivos($dir, &$results = []) {
        $files = scandir($dir);
        foreach ($files as $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($path)) {
                $results[] = $path;
            } else if ($value != "." && $value != "..") {
                $this->listarArquivosRecursivos($path, $results);
            }
        }
        return $results;
    }

    private function deltree($dir) {
        if (!file_exists($dir)) return;
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->deltree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
}
