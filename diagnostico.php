<?php
/**
 * ERP Condomínio - Diagnóstico de Infraestrutura (Sênior)
 */
require_once 'includes/config.php';

echo "<h1>Relatório de Diagnóstico Sênior</h1>";
echo "<hr>";

// 1. Teste de Conexão e Permissões
try {
    $db = getDB();
    echo "<p style='color:green'>✅ Conexão com Banco de Dados: OK</p>";
    
    // 2. Verificar Tabelas
    $tabelas = ['clientes', 'chamados', 'mensagens', 'historico_status'];
    foreach ($tabelas as $t) {
        $stmt = $db->query("SHOW TABLES LIKE '$t'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color:green'>✅ Tabela '$t': EXISTE</p>";
            
            // Verificar estrutura básica
            $cols = $db->query("DESCRIBE $t")->fetchAll();
            echo "<ul>";
            foreach ($cols as $c) {
                echo "<li>{$c['Field']} ({$c['Type']}) - Null: {$c['Null']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color:red'>❌ Tabela '$t': NÃO ENCONTRADA</p>";
        }
    }
    
    // 3. Teste de Escrita (Log)
    sys_log("Teste de diagnóstico iniciado.");
    if (file_exists('sistema_debug.log')) {
        echo "<p style='color:green'>✅ Escrita de Log: OK</p>";
    } else {
        echo "<p style='color:red'>❌ Escrita de Log: FALHOU (Verifique permissões da pasta)</p>";
    }
    
    // 4. Teste de Inserção Temporária (Rollback)
    $db->beginTransaction();
    try {
        $db->exec("INSERT INTO clientes (razao_social, cnpj, email, senha) VALUES ('TESTE DIAGNOSTICO', '00.000.000/0000-00', 'teste@teste.com', '123')");
        echo "<p style='color:green'>✅ Permissão de INSERT em 'clientes': OK</p>";
        $db->rollBack();
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Permissão de INSERT em 'clientes': FALHOU (" . $e->getMessage() . ")</p>";
        $db->rollBack();
    }

} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erro Geral: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p>Remova este arquivo após o uso por segurança.</p>";
