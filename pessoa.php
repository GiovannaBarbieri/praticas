<?php

require_once 'Classe/Pessoa.php';
require_once 'Classe/PessoaVO.php';

if(isset($_POST['btnEnviar'])){
  
    $vo = new PessoaVO();
    $pess = new Pessoa();
    
    $vo->setNome($_POST['nome']);
    $vo->setEndereco($_POST['endereco']);
    
    $ret = $pess->RetornarDadosCompletos($vo);
    
    echo $ret;
}
?>
<h1>Pessoa</h1>

<form method="post" action="pessoa.php">
    <label>Nome:</label>
    <input type="text" name="nome" />
    
    <label>Endereço:</label>
    <input type="text" name="endereco" />
    
    <button name="btnEnviar">Enviar</button>
    
</form>