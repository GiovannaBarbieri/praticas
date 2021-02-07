<?php

class PessoaVO {
    private $nome;
    private $endereco;
//    private $telefone;
    
    public function setNome($nome){ 
        $this->nome = trim($nome);
    }
    public function getNome(){
        return $this->nome;
    }
    public function setEndereco($endereco){
        $this->endereco = trim($endereco);
    }
    public function getEndereco(){
        return $this->endereco;
    }
}
