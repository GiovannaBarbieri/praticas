<?php


class Pessoa {
    
    public function RetornarDadosCompletos(PessoaVO $vo){
        return 'Nome: ' . $vo->getNome() . '<br /> Endereço ' . $vo->getEndereco();
    }
    
}
