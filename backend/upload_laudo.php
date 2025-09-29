<?php
// backend/upload_laudo.php

session_start();
require 'conexao.php'; 

// Diretório onde os arquivos serão salvos. Cria a pasta 'uploads/laudos/' na raiz do projeto.
$diretorio_uploads = '../uploads/laudos/'; 
$caminho_completo = __DIR__ . '/' . $diretorio_uploads;

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'dentista') {
    header('Location: ../admin/login-dentista.php'); 
    exit;
}

// Garante que o diretório de uploads existe
if (!is_dir($caminho_completo)) {
    mkdir($caminho_completo, 0777, true);
}

// 2. COLETA E VALIDAÇÃO DE DADOS
$id_dentista = $_SESSION['usuario_id'];
$id_paciente = filter_input(INPUT_POST, 'id_paciente', FILTER_VALIDATE_INT);
$arquivo = $_FILES['arquivo'] ?? null;

if (!$id_paciente || !$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['erro_upload'] = "Erro: Paciente ID inválido ou falha no upload do arquivo.";
    header("Location: ../admin/ficha-paciente.php?id=" . $id_paciente);
    exit;
}

// 3. PREPARAÇÃO DO ARQUIVO
$nome_original = basename($arquivo['name']);
$extensao = pathinfo($nome_original, PATHINFO_EXTENSION);
// Cria um nome de arquivo único para salvar no disco
$nome_unico = uniqid('laudo_', true) . '.' . $extensao; 
$caminho_destino = $caminho_completo . $nome_unico;

// 4. MOVIMENTAÇÃO E REGISTRO NO BANCO
try {
    if (!move_uploaded_file($arquivo['tmp_name'], $caminho_destino)) {
        throw new Exception("Falha ao mover o arquivo para o destino.");
    }
    
    // Insere o registro na tabela Documento
    $sql_insert = "
        INSERT INTO Documento (usuario_paciente, usuario_dentista, nome_arquivo_original, nome_arquivo_disco, data_upload) 
        VALUES (:id_paciente, :id_dentista, :nome_original, :nome_unico, NOW())
    ";
    $stmt = $pdo->prepare($sql_insert);
    $stmt->execute([
        ':id_paciente' => $id_paciente,
        ':id_dentista' => $id_dentista,
        ':nome_original' => $nome_original,
        ':nome_unico' => $nome_unico
    ]);

    $_SESSION['sucesso_upload'] = "Documento '$nome_original' enviado com sucesso!";
    
} catch (Exception $e) {
    if (file_exists($caminho_destino)) { unlink($caminho_destino); }
    $_SESSION['erro_upload'] = "Erro ao processar o arquivo: " . $e->getMessage();
} catch (PDOException $e) {
     $_SESSION['erro_upload'] = "Erro de banco de dados ao registrar o documento.";
}

header("Location: ../admin/ficha-paciente.php?id=" . $id_paciente);
exit;
?>