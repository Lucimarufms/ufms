<?php
// conexao.php

$host = 'localhost'; // Ou o IP do seu banco de dados
$dbname = 'estilochic'; // Nome do seu banco de dados
$user = 'seu_usuario'; // Seu usuário do banco de dados
$password = 'sua_senha'; // Sua senha do banco de dados

try {
    // Para PostgreSQL
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $password);
    // Para MySQL (se for o caso, descomente e ajuste)
    // $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Conexão bem-sucedida!"; // Apenas para teste, remover em produção
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
?>
