<?php
// excluir_conta.php
session_start();
require '../backend/conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirmar']) && $_POST['confirmar'] === 'sim') {
        try {
            $pdo->beginTransaction();

            // 1️⃣ Excluir serviços relacionados às consultas do paciente
            $stmt_cs = $pdo->prepare("
                DELETE FROM Consulta_servico 
                WHERE consulta_id IN (
                    SELECT consulta_id FROM Consulta 
                    WHERE usuario_paciente = ?
                )
            ");
            $stmt_cs->execute([$usuario_id]);

            // 2️⃣ Excluir itens de estoque relacionados às consultas do paciente
            $stmt_ce = $pdo->prepare("
                DELETE FROM Consulta_estoque 
                WHERE consulta_id IN (
                    SELECT consulta_id FROM Consulta 
                    WHERE usuario_paciente = ?
                )
            ");
            $stmt_ce->execute([$usuario_id]);

            // 3️⃣ Excluir todas as consultas do paciente
            $stmt_c = $pdo->prepare("
                DELETE FROM Consulta 
                WHERE usuario_paciente = ?
            ");
            $stmt_c->execute([$usuario_id]);

            // 4️⃣ Excluir paciente
            $stmt_paciente = $pdo->prepare("DELETE FROM Paciente WHERE usuario_id = ?");
            $stmt_paciente->execute([$usuario_id]);

            // 5️⃣ Excluir usuário
            $stmt_usuario = $pdo->prepare("DELETE FROM Usuario WHERE usuario_id = ?");
            $stmt_usuario->execute([$usuario_id]);

            $pdo->commit();

            // 6️⃣ Encerra a sessão
            session_unset();
            session_destroy();

            header("Location: login.php?msg=conta_excluida");
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = "Erro ao excluir conta: " . $e->getMessage();
        }
    } else {
        $erro = "Operação cancelada.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Excluir Conta - SmileUp</title>
        <link rel="stylesheet" href="css/cadastro.css">
        <style>
            .erro-js {
                color: red;
                background-color: #ffebee;
                border: 1px solid red;
                padding: 10px;
                border-radius: 5px;
                text-align: center;
                margin-bottom: 15px;
            }
            .sucesso-js {
                color: green;
                background-color: #e8f5e9;
                border: 1px solid green;
                padding: 10px;
                border-radius: 5px;
                text-align: center;
                margin-bottom: 15px;
            }
            button {
                padding: 10px 20px;
                margin: 5px;
                cursor: pointer;
            }
            button.confirm {
                background-color: red;
                color: white;
                border: none;
            }
            button.cancel {
                background-color: gray;
                color: white;
                border: none;
            }
        </style>
    </head>
    <body>
        <form method="POST">
            <h1>Excluir Conta</h1>

            <?php if ($erro) echo "<div class='erro-js'>$erro</div>"; ?>
            <?php if ($sucesso) echo "<div class='sucesso-js'>$sucesso</div>"; ?>

            <p>Tem certeza de que deseja excluir sua conta?<br>
            Todas as suas consultas serão excluídas permanentemente.</p>

            <button type="submit" name="confirmar" value="sim" class="confirm">
                Sim, excluir minha conta
            </button>
            <button type="submit" name="confirmar" value="nao" class="cancel">
                Não, cancelar
            </button>
        </form>
    </body>
</html>
