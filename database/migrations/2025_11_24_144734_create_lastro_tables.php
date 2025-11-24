<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabela do Lote (O ZIP em si)
        Schema::create('lastro_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome_arquivo_zip');
            // Status macro: PENDENTE, PROCESSANDO, CONCLUIDO, ERRO
            $table->string('status')->default('PENDENTE')->index();
            $table->text('resumo_erro')->nullable();
            $table->integer('total_arquivos')->default(0);
            $table->integer('total_sucesso')->default(0);
            $table->integer('total_erros')->default(0);
            $table->timestamps();
        });

        // 2. Tabela dos Documentos (O conteúdo do ZIP)
        Schema::create('lastro_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Link com o lote pai (se deletar o lote, apaga os itens)
            $table->foreignUuid('batch_id')->constrained('lastro_batches')->onDelete('cascade');

            // Metadados extraídos
            $table->string('nome_original');
            $table->string('pasta_origem')->nullable(); // Ex: 111.222.333-44
            $table->string('cpf_detectado', 14)->nullable()->index();
            $table->string('contrato_detectado')->nullable()->index();
            $table->string('tipo_documento')->nullable(); // ANTIFRAUDE, CCB, etc

            // Controle de Qualidade
            $table->boolean('valido')->default(false);
            $table->string('motivo_rejeicao')->nullable();

            // Sucesso
            $table->string('caminho_zadara')->nullable(); // Só preenche se subir
            $table->string('url_temporaria')->nullable(); // Opcional para cache

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lastro_items');
        Schema::dropIfExists('lastro_batches');
    }
};
