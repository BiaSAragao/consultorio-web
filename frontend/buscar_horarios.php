// Arquivo: /frontend/get_horarios_html.php

<?php
// ATENÇÃO: Verifique se a conexão está OK
require_once "../backend/conexao.php"; 

// 1. Validar e capturar dados de entrada (via POST)
if (!isset($_POST['dentista_id']) || !isset($_POST['data'])) {
    echo '<p style="color: red;">Dados insuficientes para buscar horários.</p>';
    exit();
}

$dentista_id = filter_var($_POST['dentista_id'], FILTER_VALIDATE_INT);
$data_consulta = $_POST['data'];

// 2. Lógica do Dia da Semana
try {
    $timestamp = strtotime($data_consulta);
    $dia_semana_en = strtolower(date('l', $timestamp)); 
    
    $dias_semana = [
        'monday' => 'segunda', 'tuesday' => 'terca', 'wednesday' => 'quarta',
        'thursday' => 'quinta', 'friday' => 'sexta',
    ];

    $dia_semana_db = $dias_semana[$dia_semana_en] ?? null;

    if ($dia_semana_db === null) {
        echo '<p>O dentista não trabalha neste dia da semana.</p>';
        exit();
    }

    // 3. Consulta (USANDO A VERSÃO CORRIGIDA com 'consulta' minúsculo)
    $sql = "
        SELECT 
            dd.horario
        FROM 
            disponibilidade_dentista dd
        LEFT JOIN 
            consulta c ON dd.usuario_id = c.usuario_dentista 
                        AND dd.horario = c.hora             
                        AND c.data = :data_consulta
        WHERE 
            dd.usuario_id = :dentista_id 
            AND dd.dia_semana = :dia_semana_db
            AND c.consulta_id IS NULL
        ORDER BY
            dd.horario ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':dentista_id', $dentista_id, PDO::PARAM_INT);
    $stmt->bindParam(':data_consulta', $data_consulta);
    $stmt->bindParam(':dia_semana_db', $dia_semana_db);
    $stmt->execute();
    
    $horarios_disponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($horarios_disponiveis)) {
        echo '<p>Nenhum horário disponível encontrado para esta data.</p>';
    } else {
        // 4. Retorna HTML dos botões de horário
        foreach ($horarios_disponiveis as $horario) {
            $hora_formatada = substr($horario, 0, 5);
            echo "<div class='horario-item' data-horario='{$horario}'>{$hora_formatada}</div>";
        }
    }

} catch (PDOException $e) {
    echo '<p style="color: red;">Erro interno do servidor ao buscar horários. Contate o suporte.</p>';
} catch (Exception $e) {
    echo '<p style="color: red;">Erro: Falha no processamento.</p>';
}
?>