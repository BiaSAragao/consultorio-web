<?php
session_start();
require 'conexao.php'; 

// 1. Verificação de Acesso
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'dentista') {
    header('Location: /login.php');
    exit;
}

$titulo_pagina = "Adicionar Item ao Estoque";
$dentista_id = $_SESSION['usuario_id'];
$mensagem_erro = $_SESSION['mensagem_erro'] ?? null;
unset($_SESSION['mensagem_erro']);

// 2. Buscar todos os itens do Estoque Global (para o <select>)
$sql_itens_global = "SELECT item_id, nome_item, preco FROM Estoque ORDER BY nome_item";
$stmt_itens = $pdo->query($sql_itens_global);
$itens_globais = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

// 3. O formulário POST será enviado para este mesmo arquivo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id_selecionado = $_POST['item_id'] ?? null;
    $quantidade = intval($_POST['quantidade']);

    if (!$item_id_selecionado || $quantidade <= 0) {
        $_SESSION['mensagem_erro'] = "Selecione um item e insira uma quantidade válida.";
        header('Location: adicionar_item.php');
        exit;
    }

    try {
        // Verifica se o item já existe no estoque individual do dentista
        $sql_check = "SELECT qtde FROM Estoque_Dentista WHERE usuario_id = :did AND item_id = :iid";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([':did' => $dentista_id, ':iid' => $item_id_selecionado]);
        $estoque_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($estoque_existente) {
            // Se existir, fazemos um UPDATE (somamos a quantidade)
            $nova_quantidade = $estoque_existente['qtde'] + $quantidade;
            $sql_update = "UPDATE Estoque_Dentista SET qtde = :qtde WHERE usuario_id = :did AND item_id = :iid";
            $stmt = $pdo->prepare($sql_update);
            $stmt->execute([':qtde' => $nova_quantidade, ':did' => $dentista_id, ':iid' => $item_id_selecionado]);
        } else {
            // Se não existir, fazemos um INSERT
            $sql_insert = "INSERT INTO Estoque_Dentista (usuario_id, item_id, qtde) VALUES (:did, :iid, :qtde)";
            $stmt = $pdo->prepare($sql_insert);
            $stmt->execute([':did' => $dentista_id, ':iid' => $item_id_selecionado, ':qtde' => $quantidade]);
        }

        $_SESSION['mensagem_sucesso'] = "Item adicionado ao seu estoque com sucesso.";
        header('Location: ../admin/dashboard-dentista.php?sucesso=true');
        exit;

    } catch (PDOException $e) {
        $_SESSION['mensagem_erro'] = "Erro ao adicionar item: " . $e->getMessage();
        header('Location: adicionar_item.php');
        exit;
    }
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
        .form-group select, .form-group input[type="number"] { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn-secondary { background-color: #6b7280; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; margin-right: 10px; }
        .btn-primary { padding: 10px 15px; }
        .alert-erro { color: #f00; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2><?php echo $titulo_pagina; ?></h2>
        
        <?php if ($mensagem_erro): ?>
            <p class="alert-erro"><?php echo $mensagem_erro; ?></p>
        <?php endif; ?>

        <form action="adicionar_item.php" method="POST">
            <div class="form-group">
                <label for="item_id">Item a Adicionar:</label>
                <select id="item_id" name="item_id" required>
                    <option value="">-- Selecione o item --</option>
                    <?php foreach ($itens_globais as $item): ?>
                        <option value="<?php echo $item['item_id']; ?>">
                            <?php echo htmlspecialchars($item['nome_item']); ?> (R$ <?php echo number_format($item['preco'], 2, ',', '.'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="quantidade">Quantidade:</label>
                <input type="number" id="quantidade" name="quantidade" min="1" required value="1">
            </div>
            
            <a href="../admin/dashboard-dentista.php" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary">Adicionar ao Estoque</button>
        </form>
    </div>
</body>
</html>