<?php
// backend/download_laudo.php

session_start();
require 'conexao.php';

$raiz_projeto = dirname(__DIR__); 
$caminho_completo = $raiz_projeto . '/uploads/laudos/';

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'dentista') {
    die("Acesso negado."); 
}

// 2. PEGA O ID DO DOCUMENTO
$id_documento = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_documento) {
    die("Erro: ID do documento inválido.");
}

try {
    // 3. BUSCA DADOS DO DOCUMENTO NO BANCO
    $sql_download = "
        SELECT nome_arquivo_original, nome_arquivo_disco
        FROM Documento
        WHERE documento_id = :id_documento
    ";
    $stmt = $pdo->prepare($sql_download);
    $stmt->execute([':id_documento' => $id_documento]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$documento) {
        die("Documento não encontrado no registro.");
    }

    $caminho_arquivo_disco = $caminho_completo . $documento['nome_arquivo_disco'];
    $nome_arquivo_original = $documento['nome_arquivo_original'];
    
    // 4. VERIFICA SE O ARQUIVO EXISTE NO DISCO
    if (!file_exists($caminho_arquivo_disco)) {
        die("Arquivo não encontrado no servidor.");
    }

    // 5. FORÇA O DOWNLOAD SEGURO
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($nome_arquivo_original) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($caminho_arquivo_disco));
    readfile($caminho_arquivo_disco);
    exit;

} catch (PDOException $e) {
    die("Erro de banco de dados ao buscar o documento.");
}
?>