<?php
session_start();

// Redireciona se o usuário não estiver logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

require '../backend/conexao.php';
require '../backend/validacao_cadastro.php'; // Funções de validação

$usuario_id = $_SESSION['usuario_id'];
$erro = '';
$sucesso = '';
$dados_usuario = [];

// --- 1. Carregar dados atuais do perfil ---
try {
    $stmt = $pdo->prepare(
        "SELECT u.nome, u.email, u.tel, p.cpf, p.plano, p.data_nascimento 
         FROM usuario u
         LEFT JOIN paciente p ON u.usuario_id = p.usuario_id
         WHERE u.usuario_id = ?"
    );
    $stmt->execute([$usuario_id]);
    $dados_usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dados_usuario) {
        // Se, por algum motivo, o usuário não for encontrado
        $erro = "Usuário não encontrado. Faça login novamente.";
        // session_destroy(); // Talvez forçar um novo login
        // header("Location: login.php");
        // exit();
    }

} catch (PDOException $e) {
    $erro = "Erro ao carregar os dados para edição: " . $e->getMessage();
}


// --- 2. Processar o POST de Edição ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$erro) {
    
    // Captura os dados do formulário
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $cpf = $_POST['cpf'];
    $plano = $_POST['plano'];
    $data_nascimento = $_POST['data_nascimento'];
    
    // A senha é opcional, mas se for enviada, precisa ser validada e hashed
    $senha = $_POST['senha'] ?? ''; 
    $nova_senha_hash = null;

    // Inicia as validações
    if (!validaEmail($email)) {
        $erro = "Email inválido.";
    } elseif ($senha && !validaSenha($senha)) { // Se a senha foi preenchida
        $erro = "Nova senha fraca. Use ao menos 8 caracteres, com letra maiúscula e número.";
    } elseif (!validaCPF($cpf)) {
        $erro = "CPF inválido.";
    } elseif (!validaDataNascimento($data_nascimento)) {
        $erro = "Data de nascimento inválida.";
    } else {
        // Validação de Email único (se o email mudou)
        if ($email !== $dados_usuario['email']) {
            $stmt_check = $pdo->prepare("SELECT usuario_id FROM usuario WHERE email = ? AND usuario_id != ?");
            $stmt_check->execute([$email, $usuario_id]);
            if ($stmt_check->rowCount() > 0) {
                $erro = "Este e-mail já está cadastrado por outro usuário.";
            }
        }
    }

    // Se não houver erros, prossiga com a atualização
    if (!$erro) {
        try {
            $pdo->beginTransaction();

            // 1. Atualiza a tabela usuario
            $sql_usuario = "UPDATE usuario SET nome = ?, email = ?, tel = ?";
            $params_usuario = [$nome, $email, $telefone];

            if ($senha) { // Se o usuário enviou uma nova senha
                $nova_senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $sql_usuario .= ", senha = ?";
                $params_usuario[] = $nova_senha_hash;
            }

            $sql_usuario .= " WHERE usuario_id = ?";
            $params_usuario[] = $usuario_id;

            $stmt_usuario = $pdo->prepare($sql_usuario);
            $stmt_usuario->execute($params_usuario);

            // 2. Atualiza a tabela paciente
            $stmt_paciente = $pdo->prepare(
                "UPDATE paciente SET cpf = ?, plano = ?, data_nascimento = ? WHERE usuario_id = ?"
            );
            $stmt_paciente->execute([$cpf, $plano, $data_nascimento, $usuario_id]);

            $pdo->commit();

            $sucesso = "Perfil atualizado com sucesso!";
            
            // Recarrega os dados para exibir a versão atualizada no formulário
            // Se a senha foi alterada, o campo senha não será preenchido
            $dados_usuario['nome'] = $nome;
            $dados_usuario['email'] = $email;
            $dados_usuario['tel'] = $telefone;
            $dados_usuario['cpf'] = $cpf;
            $dados_usuario['plano'] = $plano;
            $dados_usuario['data_nascimento'] = $data_nascimento;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = "Erro ao atualizar o perfil: " . $e->getMessage();
        }
    }
}

// O formulário usará os $dados_usuario para preencher os campos.
// Se houve um erro POST, os campos devem ser preenchidos com os dados
// enviados no POST para que o usuário não perca o que digitou.
// Vamos assumir que se houver um POST, usamos os dados do POST
// para preencher o formulário se houver um erro.
$dados_formulario = ($_SERVER['REQUEST_METHOD'] == 'POST' && $erro) ? $_POST : $dados_usuario;

?>

<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editar Perfil - SmileUp</title>
        <link rel="stylesheet" href="css/cadastro.css"> 
        <style>
            .mensagem-js {
                padding: 10px;
                border-radius: 5px;
                text-align: center;
                margin-bottom: 15px;
            }
            .erro-js { color: red; background-color: #ffebee; border: 1px solid red; }
            .sucesso-js { color: green; background-color: #e8f5e9; border: 1px solid green; }
        </style>
    </head>
    <body>
        <form id="form-edicao" action="" method="POST">
            <h1>Editar Perfil</h1>

            <?php if ($erro) echo "<div class='mensagem-js erro-js'>$erro</div>"; ?>
            <?php if ($sucesso) echo "<div class='mensagem-js sucesso-js'>$sucesso</div>"; ?>

            <label for="nome">Nome:</label>
            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($dados_formulario['nome'] ?? ''); ?>" required><br><br>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($dados_formulario['email'] ?? ''); ?>" required><br><br>
            
            <label for="senha">Nova Senha (deixe em branco para não alterar):</label>
            <input type="password" id="senha" name="senha"><br><br>

            <label for="telefone">Telefone:</label>
            <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($dados_formulario['tel'] ?? ''); ?>"><br><br>

            <label for="cpf">CPF:</label>
            <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($dados_formulario['cpf'] ?? ''); ?>" required><br><br>

            <label for="plano">Plano de Saúde:</label>
            <input type="text" id="plano" name="plano" value="<?php echo htmlspecialchars($dados_formulario['plano'] ?? ''); ?>"><br><br>

            <label for="data_nascimento">Data de Nascimento:</label>
            <input type="date" id="data_nascimento" name="data_nascimento" value="<?php echo htmlspecialchars($dados_formulario['data_nascimento'] ?? ''); ?>" required><br><br>

            <button type="submit">Salvar Alterações</button>

            <p class="login-link">
                <a href="perfil-paciente.php">Voltar para o Perfil</a>
            </p>
        </form>
    </body>
</html>