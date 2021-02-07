<?php

if(isset($_POST['btnEnviar'])){
    $nome = $_POST['nome'];
    $rua = $_POST['rua'];
    $bairro = $_POST['bairro'];
    $cep = $_POST['cep'];
    
    $nome_completo = $nome;
    $rua = $rua;
    $bairro = $bairro;
    $cep = $cep;
    
    echo "Nome completo:$nome_completo <br> Rua: $rua <br/> bairro:$bairro <br/> cep:$cep";
   
}

?>

<h1>Formulario</h1>

<form action="formulario.php" method="post">
    <label>Nome Completo</label>
    <input type="text" name="nome" /><br/>
    <label>Rua</label>
    <input type="text" name="rua" /><br/>
    <label>Bairro</label>
    <input type="text" name="bairro" /><br/>
    <label>Cep</label>
    <input type="text" name="cep" /><br/>
    
    <button name="btnEnviar">Enviar</button>
    
</form>