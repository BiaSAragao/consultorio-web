<?php
session_start();

// Redireciona se o usuário não estiver logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

require '../backend/conexao.php'; // Inclui a conexão com o banco

$usuario_id = $_SESSION['usuario_id'];
$dados_usuario = null;
$erro = '';

try {
    // 1. Buscar dados da tabela 'usuario'
    $stmt_usuario = $pdo->prepare("SELECT nome, email, tel FROM usuario WHERE usuario_id = ?");
    $stmt_usuario->execute([$usuario_id]);
    $dados_usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

    if ($dados_usuario) {
        // 2. Buscar dados da tabela 'paciente' (assumindo que todo usuário é paciente neste contexto)
        $stmt_paciente = $pdo->prepare("SELECT cpf, plano, data_nascimento FROM paciente WHERE usuario_id = ?");
        $stmt_paciente->execute([$usuario_id]);
        $dados_paciente = $stmt_paciente->fetch(PDO::FETCH_ASSOC);

        // Combina os arrays
        if ($dados_paciente) {
            $dados_usuario = array_merge($dados_usuario, $dados_paciente);
        }
    } else {
        $erro = "Usuário não encontrado.";
    }

} catch (PDOException $e) {
    $erro = "Erro ao carregar o perfil: " . $e->getMessage();
}

// Redireciona para o login se não encontrar o usuário e não for erro de PDO
if (!$dados_usuario && !$erro) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Meu Perfil - SmileUp</title>
        <link rel="stylesheet" href="css/perfil.css"> 
        <style>
            .container { max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; }
            .info-item { margin-bottom: 10px; padding: 5px 0; border-bottom: 1px dashed #eee; }
            .info-item strong { display: inline-block; width: 150px; }
            .botao-edicao { display: block; width: 100%; padding: 10px; background-color: #007bff; color: white; text-align: center; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .erro { color: red; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Meu Perfil</h1>
            
            <?php if ($erro): ?>
                <div class="erro"><?php echo $erro; ?></div>
            <?php elseif ($dados_usuario): ?>
                <div class="info-item">
                    <strong>Nome:</strong> <?php echo htmlspecialchars($dados_usuario['nome']); ?>
                </div>
                <div class="info-item">
                    <strong>Email:</strong> <?php echo htmlspecialchars($dados_usuario['email']); ?>
                </div>
                <div class="info-item">
                    <strong>Telefone:</strong> <?php echo htmlspecialchars($dados_usuario['tel'] ?? 'Não informado'); ?>
                </div>
                <div class="info-item">
                    <strong>CPF:</strong> <?php echo htmlspecialchars($dados_usuario['cpf'] ?? 'Não informado'); ?>
                </div>
                <div class="info-item">
                    <strong>Plano de Saúde:</strong> <?php echo htmlspecialchars($dados_usuario['plano'] ?? 'Não informado'); ?>
                </div>
                <div class="info-item">
                    <strong>Data de Nascimento:</strong> <?php echo htmlspecialchars($dados_usuario['data_nascimento'] ? date('d/m/Y', strtotime($dados_usuario['data_nascimento'])) : 'Não informada'); ?>
                </div>

                <a href="editar-perfil-paciente.php" class="botao-edicao">Editar Perfil</a>

                <a href="dashboard-paciente.php" class="botao-edicao">Voltar dashboard</a>

            <?php endif; ?>
        </div>
    </body>
</html>