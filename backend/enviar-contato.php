<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: ../frontend/contato.php");
    exit();
}

// Carrega o autoloader do Composer (verifique se a pasta 'vendor' está na raiz do projeto)
require '../vendor/autoload.php';

// Validação simples dos campos
if (empty($_POST['nome']) || empty($_POST['email']) || empty($_POST['assunto']) || empty($_POST['mensagem'])) {
    $_SESSION['mensagem_erro'] = "Por favor, preencha todos os campos.";
    header("Location: ../frontend/contato.php");
    exit();
}

$mail = new PHPMailer(true);

try {
    // ===================================================================
    // == PREENCHA AS INFORMAÇÕES DO SEU E-MAIL AQUI ==
    // ===================================================================

    // --- Configurações do Servidor SMTP ---
    $mail->isSMTP();
    $mail->Host       = 'smtp.seuprovedor.com';    // <<< COLOQUE O SERVIDOR SMTP DO SEU E-MAIL AQUI (ex: smtp.gmail.com, smtp.umbler.com)
    $mail->SMTPAuth   = true;
    $mail->Username   = 'seu-email-criado@email.com'; // <<< O E-MAIL COMPLETO QUE VOCÊ CRIOU
    $mail->Password   = 'a-senha-do-seu-email';    // <<< A SENHA DO E-MAIL QUE VOCÊ CRIOU
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Geralmente 'ssl'
    $mail->Port       = 465;                         // Geralmente 465 para SSL ou 587 para TLS
    $mail->CharSet    = 'UTF-8';

    // --- Quem envia e quem recebe ---
    // Remetente (DEVE ser o mesmo e-mail do Username acima)
    $mail->setFrom('seu-email-criado@email.com', 'Contato do Site SmileUp');
    
    // Destinatário (o e-mail da clínica que vai receber a mensagem, PODE ser o mesmo que você criou)
    $mail->addAddress('seu-email-criado@email.com', 'SmileUp Clínica');

    // ===================================================================
    // == FIM DA ÁREA DE CONFIGURAÇÃO ==
    // ===================================================================

    // O campo "Responder Para" será o e-mail da pessoa que preencheu o formulário
    $mail->addReplyTo($_POST['email'], $_POST['nome']);

    // --- Conteúdo do E-mail ---
    $mail->isHTML(true);
    $mail->Subject = 'Nova Mensagem do Site: ' . htmlspecialchars($_POST['assunto']);
    $mail->Body    = "<h2>Nova mensagem recebida pelo formulário de contato:</h2>" .
                     "<p><strong>Nome:</strong> " . htmlspecialchars($_POST['nome']) . "</p>" .
                     "<p><strong>Email para resposta:</strong> " . htmlspecialchars($_POST['email']) . "</p>" .
                     "<p><strong>Mensagem:</strong><br>" . nl2br(htmlspecialchars($_POST['mensagem'])) . "</p>";

    $mail->send();

    // Redireciona de volta com mensagem de sucesso
    $_SESSION['mensagem_sucesso'] = 'Sua mensagem foi enviada com sucesso! Agradecemos o contato.';
    header("Location: ../frontend/contato.php");
    exit();

} catch (Exception $e) {
    // Redireciona de volta com mensagem de erro
    // A linha abaixo é útil para depuração, para ver qual foi o erro exato
    // $_SESSION['mensagem_erro'] = "A mensagem não pôde ser enviada. Erro: {$mail->ErrorInfo}";
    $_SESSION['mensagem_erro'] = "A mensagem não pôde ser enviada. Por favor, tente novamente mais tarde.";
    header("Location: ../frontend/contato.php");
    exit();
}