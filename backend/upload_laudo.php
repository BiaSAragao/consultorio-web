<?php
// backend/upload_laudo.php

session_start();
require 'conexao.php'; 

$diretorio_uploads = '../uploads/laudos/'; 
$caminho_completo = __DIR__ . '/' . $diretorio_uploads;

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'dentista') {
    header('Location: ../admin/login-dentista.php'); 
    exit;
}

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
$extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
$nome_unico = uniqid('laudo_', true) . '.' . $extensao; 
$caminho_destino = $caminho_completo . $nome_unico;

try {
    // 4. TRATA IMAGEM OU OUTRO TIPO DE ARQUIVO
    $tipos_imagem = ['jpg', 'jpeg', 'png'];
    if (in_array($extensao, $tipos_imagem)) {
        // Redimensiona a imagem antes de salvar
        $max_width = 1280;
        $max_height = 1280;

        list($width_orig, $height_orig) = getimagesize($arquivo['tmp_name']);
        $ratio_orig = $width_orig / $height_orig;

        if ($max_width / $max_height > $ratio_orig) {
            $new_width = (int)($max_height * $ratio_orig);
            $new_height = $max_height;
        } else {
            $new_width = $max_width;
            $new_height = (int)($max_width / $ratio_orig);
        }

        // Cria nova imagem
        $image_p = imagecreatetruecolor($new_width, $new_height);
        if ($extensao === 'jpg' || $extensao === 'jpeg') {
            $image = imagecreatefromjpeg($arquivo['tmp_name']);
        } elseif ($extensao === 'png') {
            $image = imagecreatefrompng($arquivo['tmp_name']);
            // Preserva transparência
            imagealphablending($image_p, false);
            imagesavealpha($image_p, true);
        }

        imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width_orig, $height_orig);

        // Salva a imagem redimensionada
        if ($extensao === 'jpg' || $extensao === 'jpeg') {
            imagejpeg($image_p, $caminho_destino, 85); // qualidade 85%
        } elseif ($extensao === 'png') {
            imagepng($image_p, $caminho_destino, 6); // compressão 0-9
        }

        imagedestroy($image);
        imagedestroy($image_p);
    } else {
        // Se não for imagem, apenas move
        if (!move_uploaded_file($arquivo['tmp_name'], $caminho_destino)) {
            throw new Exception("Falha ao mover o arquivo para o destino.");
        }
    }

    // 5. REGISTRO NO BANCO
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
