<?php
session_start();
require 'conexao.php'; 

// 1. Verificação de Acesso
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'dentista') {
    header('Location: /login.php');
    exit;
}

$titulo_pagina = "Remover Quantidade do Estoque";
$dentista_id = $_SESSION['usuario_id'];
$mensagem_erro = $_SESSION['mensagem_erro'] ?? null;
unset($_SESSION['mensagem_erro']);

$item_id = $_REQUEST['id'] ?? null;

if (!$item_id) {
    $_SESSION['mensagem_erro'] = "ID do item não fornecido para remoção.";
    header('Location: ../admin/dashboard-dentista.php?erro=true');
    exit;
}

// --- LÓGICA DE PROCESSAMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantidade_a_remover = intval($_POST['quantidade_a_remover']);
    $estoque_atual = intval($_POST['estoque_atual']);

    if ($quantidade_a_remover <= 0) {
        $_SESSION['mensagem_erro'] = "A quantidade a remover deve ser maior que zero.";
        header('Location: remover_quantidade_item.php?id=' . $item_id);
        exit;
    }

    if ($quantidade_a_remover > $estoque_atual) {
        $_SESSION['mensagem_erro'] = "A quantidade a remover (" . $quantidade_a_remover . ") é maior que o estoque atual (" . $estoque_atual . ").";
        header('Location: remover_quantidade_item.php?id=' . $item_id);
        exit;
    }
    
    // Calcula o novo estoque
    $nova_quantidade = $estoque_atual - $quantidade_a_remover;

    try {
        // 2. Comando UPDATE (Subtração)
        $sql_update = "
            UPDATE Estoque_Dentista 
            SET qtde = :qtde 
            WHERE usuario_id = :did AND item_id = :iid
        ";
        $stmt = $pdo->prepare($sql_update);
        $stmt->execute([
            ':qtde' => $nova_quantidade, 
            ':did' => $dentista_id, 
            ':iid' => $item_id
        ]);

        // Se a nova quantidade for zero, podemos remover o registro para manter o banco limpo (opcional)
        if ($nova_quantidade === 0) {
             $sql_delete = "DELETE FROM Estoque_Dentista WHERE usuario_id = :did AND item_id = :iid";
             $pdo->prepare($sql_delete)->execute([':did' => $dentista_id, ':iid' => $item_id]);
             $_SESSION['mensagem_sucesso'] = "Todo o estoque do item foi removido com sucesso.";
        } else {
            $_SESSION['mensagem_sucesso'] = "Quantidade de " . $quantidade_a_remover . " itens removida do estoque.";
        }
        
        header('Location: ../admin/dashboard-dentista.php?sucesso=true');
        exit;

    } catch (PDOException $e) {
        $_SESSION['mensagem_erro'] = "Erro ao remover quantidade: " . $e->getMessage();
        header('Location: remover_quantidade_item.php?id=' . $item_id);
        exit;
    }
}


// --- LÓGICA DE CARREGAMENTO (GET) ---
// 3. Buscar os dados atuais do item no estoque individual
$sql_item = "
    SELECT 
        ed.qtde, 
        e.nome_item
    FROM Estoque_Dentista ed
    JOIN Estoque e ON ed.item_id = e.item_id
    WHERE ed.usuario_id = :did AND ed.item_id = :iid
";
$stmt_item = $pdo->prepare($sql_item);
$stmt_item->execute([':did' => $dentista_id, ':iid' => $item_id]);
$item_atual = $stmt_item->fetch(PDO::FETCH_ASSOC);

if (!$item_atual) {
    $_SESSION['mensagem_erro'] = "Item não encontrado no seu estoque.";
    header('Location: ../admin/dashboard-dentista.php?erro=true');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo $titulo_pagina; ?></title>
    <link rel="stylesheet" href="../frontend/css/main.css" /> 
    <style>
        .form-container { max-width: 600px; margin: 2rem auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input[type="number"] { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn-secondary { background-color: #6b7280; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; margin-right: 10px; }
        .btn-primary { background-color: #dc2626; padding: 10px 15px; } /* Cor vermelha para remoção */
        .alert-erro { color: #f00; margin-bottom: 15px; }
        .item-info { margin-bottom: 20px; padding: 10px; background-color: #fee2e2; border: 1px solid #fca5a5; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2><?php echo $titulo_pagina; ?></h2>
        
        <?php if ($mensagem_erro): ?>
            <p class="alert-erro"><?php echo $mensagem_erro; ?></p>
        <?php endif; ?>

        <div class="item-info">
            <p><strong>Produto:</strong> <?php echo htmlspecialchars($item_atual['nome_item']); ?></p>
            <p><strong>Estoque Atual:</strong> <?php echo $item_atual['qtde']; ?> unidades</p>
        </div>

        <form action="remover_quantidade_item.php?id=<?php echo $item_id; ?>" method="POST">
            <input type="hidden" name="estoque_atual" value="<?php echo $item_atual['qtde']; ?>">
            
            <div class="form-group">
                <label for="quantidade_a_remover">Quantidade a Remover:</label>
                <input type="number" id="quantidade_a_remover" name="quantidade_a_remover" min="1" max="<?php echo $item_atual['qtde']; ?>" required value="1">
            </div>
            
            <a href="../admin/dashboard-dentista.php" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary">Confirmar Remoção</button>
        </form>
    </div>
</body>
</html>