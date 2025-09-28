<?php
session_start();
require 'conexao.php'; // Inclui a conexão PDO

// 1. Verificação de Acesso e Segurança
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'dentista') {
    header('Location: /login.php');
    exit;
}

$dentista_id = $_SESSION['usuario_id'];
$item_id = $_GET['id'] ?? null; // Pega o ID do item da URL

if (!$item_id) {
    $_SESSION['mensagem_erro'] = "ID do item não fornecido.";
    header('Location: ../admin/dashboard-dentista.php?erro=true');
    exit;
}

try {
    // 2. Comando DELETE
    // O comando verifica tanto o item_id quanto o usuario_id para garantir que
    // o dentista só possa remover itens do seu próprio estoque.
    $sql_delete = "
        DELETE FROM Estoque_Dentista 
        WHERE usuario_id = :dentista_id AND item_id = :item_id
    ";
    
    $stmt = $pdo->prepare($sql_delete);
    $stmt->execute([
        ':dentista_id' => $dentista_id,
        ':item_id' => $item_id
    ]);

    // 3. Verificação e Redirecionamento
    if ($stmt->rowCount() > 0) {
        $_SESSION['mensagem_sucesso'] = "Item removido do seu estoque com sucesso.";
    } else {
        $_SESSION['mensagem_erro'] = "Falha ao remover item. Item não encontrado ou você não tem permissão.";
    }

    header('Location: ../admin/dashboard-dentista.php?sucesso=true');
    exit();

} catch (PDOException $e) {
    $_SESSION['mensagem_erro'] = "Erro de comunicação com o sistema ao remover item.";
    // Para debug: $_SESSION['mensagem_erro'] = "Erro: " . $e->getMessage();
    header('Location: ../admin/dashboard-dentista.php?erro=true');
    exit();
}