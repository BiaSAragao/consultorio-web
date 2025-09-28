<?php
session_start();
require 'conexao.php'; 

// 1. Verificação de Acesso
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'dentista') {
    header('Location: /login.php');
    exit;
}

$titulo_pagina = "Editar Item do Estoque";
$dentista_id = $_SESSION['usuario_id'];
$mensagem_erro = $_SESSION['mensagem_erro'] ?? null;
unset($_SESSION['mensagem_erro']);

$item_id = $_REQUEST['id'] ?? null;

if (!$item_id) {
    $_SESSION['mensagem_erro'] = "ID do item não fornecido para edição.";
    header('Location: ../admin/dashboard-dentista.php?erro=true');
    exit;
}

// --- LÓGICA DE PROCESSAMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_quantidade = intval($_POST['quantidade']);

    if ($nova_quantidade < 0) {
        $_SESSION['mensagem_erro'] = "A quantidade não pode ser negativa.";
        header('Location: editar_item.php?id=' . $item_id);
        exit;
    }
    
    try {
        // 2. Comando UPDATE
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
        
        $_SESSION['mensagem_sucesso'] = "Quantidade do item atualizada com sucesso.";
        header('Location: ../admin/dashboard-dentista.php?sucesso=true');
        exit;

    } catch (PDOException $e) {
        $_SESSION['mensagem_erro'] = "Erro ao atualizar item: " . $e->getMessage();
        header('Location: editar_item.php?id=' . $item_id);
        exit;
    }
}


// --- LÓGICA DE CARREGAMENTO (GET) ---
// 3. Buscar os dados atuais do item no estoque individual
$sql_item = "
    SELECT 
        ed.qtde, 
        e.nome_item, 
        e.preco 
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
        .btn-primary { padding: 10px 15px; }
        .alert-erro { color: #f00; margin-bottom: 15px; }
        .item-info { margin-bottom: 20px; padding: 10px; background-color: #f4f4f4; border-radius: 4px; }
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
            <p><strong>Preço Unitário:</strong> R$ <?php echo number_format($item_atual['preco'], 2, ',', '.'); ?></p>
        </div>

        <form action="editar_item.php?id=<?php echo $item_id; ?>" method="POST">
            <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
            
            <div class="form-group">
                <label for="quantidade">Nova Quantidade em Estoque:</label>
                <input type="number" id="quantidade" name="quantidade" min="0" required value="<?php echo $item_atual['qtde']; ?>">
            </div>
            
            <a href="../admin/dashboard-dentista.php" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary">Salvar Alterações</button>
        </form>
    </div>
</body>
</html>