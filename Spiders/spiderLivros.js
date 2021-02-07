var request = require('request');
var cheerio = require('cheerio');
//const { fstat } = require('fs');

request('https://catracalivre.com.br/educacao/15-sites-para-baixar-livros-gratuitamente/', function(erro, result, site){
    if(erro) console.log('Erro: ' + erro);

    var $ = cheerio.load(body);

    $('').each(function(){
        var titulo = $(this).find('').text().trim();
        var descricao = $(this).find('. <p>').text().trim();
        var link = $(this).find('').text().trim();

        console.log('Título: ' + titulo, 'Descricao: ' + descricao, 'Link: ' + link);
    });

});