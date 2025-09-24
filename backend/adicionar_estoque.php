<?php
session_start();
// include '../backend/conexao.php'; // Arquivo de conexão com o banco

// 1. Validação inicial de segurança
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'dentista') {
    die("Acesso negado.");
}

// 2. Recebe e valida os dados do formulário
$id_dentista = $_SESSION['usuario_id'];
$nome_produto = trim($_POST['nome_produto'] ?? '');
$quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT);
$preco_str = str_replace(',', '.', $_POST['preco'] ?? '0'); // Troca vírgula por ponto
$preco = filter_var($preco_str, FILTER_VALIDATE_FLOAT);

if (empty($nome_produto) || $quantidade === false || $preco === false) {
    // Se algum dado for inválido, redireciona com erro
    header('Location: ../admin/dashboard-dentista.php?erro=dados_invalidos#estoque');
    exit();
}

// $pdo = conectar(); // Conecta ao banco

// 3. Insere os dados no banco, incluindo a data atual
// $stmt = $pdo->prepare(
//     "INSERT INTO estoque (id_dentista, nome_produto, quantidade, preco_unitario, data_compra) 
//      VALUES (?, ?, ?, ?, CURDATE())"
// );
// $stmt->execute([$id_dentista, $nome_produto, $quantidade, $preco]);


// 4. Redireciona de volta para o painel com uma mensagem de sucesso
header('Location: ../admin/dashboard-dentista.php?status=item_adicionado#estoque');
exit();
?>