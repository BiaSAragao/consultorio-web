<?php
session_start();

require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: ../frontend/contato.php");
    exit();
}

// Recebe os dados do formulário
$nome = trim($_POST['nome']);
$email = trim($_POST['email']);
$assunto = trim($_POST['assunto']);
$mensagem = trim($_POST['mensagem']);

// Validação simples
if (empty($nome) || empty($email) || empty($assunto) || empty($mensagem)) {
    $_SESSION['mensagem_erro'] = "Por favor, preencha todos os campos.";
    header("Location: ../frontend/contato.php");
    exit();
}

// Validação de e-mail
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['mensagem_erro'] = "E-mail inválido.";
    header("Location: ../frontend/contato.php");
    exit();
}

$mail = new PHPMailer(true);

try {
    // Configurações SMTP do Gmail
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'consultorioweb25@gmail.com'; // seu e-mail
    $mail->Password   = 'cozeahmbhpfilofm';           // senha de app
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';

    // Quem envia
    $mail->setFrom('consultorioweb25@gmail.com', 'Contato SmileUp');

    // Quem recebe
    $mail->addAddress('consultorioweb25@gmail.com', 'SmileUp Clínica');

    // Responder para o paciente
    $mail->addReplyTo($email, $nome);

    // Conteúdo do e-mail
    $mail->isHTML(true);
    $mail->Subject = 'Nova mensagem do site: ' . htmlspecialchars($assunto);
    $mail->Body    = "
        <h2>Nova mensagem recebida pelo formulário de contato:</h2>
        <p><strong>Nome:</strong> " . htmlspecialchars($nome) . "</p>
        <p><strong>Email para resposta:</strong> " . htmlspecialchars($email) . "</p>
        <p><strong>Mensagem:</strong><br>" . nl2br(htmlspecialchars($mensagem)) . "</p>
    ";

    $mail->send();

    $_SESSION['mensagem_sucesso'] = "Sua mensagem foi enviada com sucesso!";
    header("Location: ../frontend/contato.php");
    exit();

} catch (Exception $e) {
    $_SESSION['mensagem_erro'] = "A mensagem não pôde ser enviada. Erro: " . $mail->ErrorInfo;
    header("Location: ../frontend/contato.php");
    exit();
}
