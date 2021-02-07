TJ_BA_PJE

<?php
/* ==================================
 * Configurações da fonte
 ====================================*/

set_time_limit(0);  // Seta o timeout
ini_set('include_path', dirname(__FILE__) . '/../../ZendFramework-1.7.4/library');  // Inicia o Zend

define("CORE_DIR", dirname(__FILE__) . '/../../util/'); // Define o diretório da pasta util
define('REQUEST_TIMEOUT', '60');                        // Timeout de resposta do requests
define('REQUEST_CONNECT_TIMEOUT', '30');                // Timeout de conexão do request (apenas curl)

/* Requires necessários */
require_once CORE_DIR . '/Spider.php';

class ProcessoSpider extends Spider
{
    private $url;

    private $_url;
    private $_viewState;

    private $parametro1;
    private $parametro2;
    private $parametro3;
    private $parametro4;
    private $parametro5;
    private $parametro6;
    private $parametro7;

    public function __construct() 
    {
        parent::__construct(dirname(__FILE__));
        
        ini_set("pcre.backtrack_limit", 1000000000);

        $this->aProcesso = [];
        $this->aProcesso["processos"] = [];
        $this->aProcesso["total_processos"] = 0;
        $this->aProcesso["total_processos_retornados"] = 0;

        $this->useCurl = true;

        $this->totalProcessos = NULL;
    }

    public function searchByNumProc($numero, $parametro = "1")
    {
        $this->debug(__METHOD__);

        $dados = [
            'numero' => [
                'valor' => $parametro,
                'nome'  => 'Parâmetro'
            ]
        ];

        Util::verificarDadosRecebidos($dados);

        Self::setGrau($parametro);

        $this->setNumeroUnico($numero);

        $tentativas = 2;
        $tentativa = 0;

        do
        {
            try
            {
                $this->search();
                break;
            } 
            catch (Exception $e)
            {
                if ($e->getCode() == CAPTCHA_ERROR  && $tentativa < $tentativas)
                {
                    $tentativas++;
                    continue;
                }

                throw $e;
            }
        } while (true);

        return $this->aProcesso;
    }

    private function setGrau($parametro)
    {
        $this->debug(__METHOD__);

        if ($parametro == '1')
        {
            $urlBase = 'https://consultapublicapje.tjba.jus.br/';
        }
        elseif ($parametro == '2')
        {
            $urlBase = 'https://pje2g.tjba.jus.br/';
        }
        else
        {            
            $this->erro(__METHOD__, "ERRO_ENTRADA", "Parâmetro inválido");  
        }

        $this->url = [
            'base'  => $urlBase,
            'busca' => $urlBase . 'pje-web/ConsultaPublica/listView.seam',
            'host' => 'https://pje.tjce.jus.br',
            'download' => 'https://consultapublicapje.tjba.jus.br/pje-web/download.seam?',
            'paginaPartesMov' => $urlBase . 'pje-web/ConsultaPublica/DetalheProcessoConsultaPublica/listView.seam'
        ];

        $this->aProcesso['url'] = $urlBase;
    }

    private function search() 
    {
        $this->debug(__METHOD__);

        $result = $this->request($this->url['busca']);
        
        $this->_viewState = Self::getViewState($result);

        $pattern = '#data-sitekey=(?:"|\')(.*?)(?:"|\')#is';
        if (preg_match($pattern, $result, $sitekey)) {
            $captcha = $this->quebraRecaptchaV2($this->url['busca'], [], 'GET', null, null, $sitekey[1], true);
        }

        $data = [
            'AJAXREQUEST'                   => '_viewRoot',
            'fPP:numProcessoDecoration:numProcesso' => $this->getNumeroUnico(true),
            'fPP:dnp:nomeParte'             => '',
            'fPP:j_id154:nomeAdv'            => '',
            'fPP:j_id163:classeProcessualProcessoHidden' => '',
            'tipoMascaraDocumento'          => 'on',
            'fPP:dpDec:documentoParte'      => '',
            'fPP:Decoration:numeroOAB'      => '',
            'fPP:Decoration:j_id198'        => '',
            'fPP:Decoration:estadoComboOAB' => 'org.jboss.seam.ui.NoSelectionConverter.noSelectionValue',
            'g-recaptcha-response'          => $this->captcha,
            'fPP'                           => 'fPP',
            'autoScroll'                    => '',
            'javax.faces.ViewState'         => $this->_viewState,
            'fPP:j_id205'                   => 'fPP:j_id205',
            'AJAX:EVENTS_COUNT'             => '1',
        ];

        $result = $this->request($this->url['busca'], $this->url['base'], $data, 'POST', 3);

        Self::ondeEstou($result); 
    }

    private function stepCaptcha($result)
    {
        $this->debug(__METHOD__);

        $pattern = '#data-sitekey=(?:"|\')(.*?)(?:"|\')#is';
        if(!preg_match($pattern, $result, $match))
            $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Sitekey não encontrada");

        return $this->quebraRecaptchaV2($this->url['busca'], [], 'GET', null, null, $match[1]);
    }

    private function ondeEstou($result) 
    {
        $this->debug(__METHOD__);

        $pattern = '#N.{0,3}o\s*foi\s*poss.{0,3}vel\s*validar\s*o\s*captcha#is';
        if (preg_match($pattern, $result))
        {
            if ($this->captchaService)
                Util::captchaServiceInvalidate($this->captchaService);
            
            $this->erro(__METHOD__, "ERRO_CAPTCHA", "Captcha inválido");  
        }

        $pattern = '#Sua\s*pesquisa\s*n.{1,8}o\s*encontrou\s*nenhum\s*processo\s*dispon.{1,8}vel#is';
        if (preg_match($pattern, $result))
            $this->erro(__METHOD__, "ERRO_INEXISTENTE", "Nada Encontrado");              

        $pattern = '#\/pje-web\/ConsultaPublica\/DetalheProcessoConsultaPublica\/listView\.seam\?ca=.*?\'#i';
        if (preg_match($pattern, $result))
            return $this->stepListaProcessos($result);

        $pattern = '#Dados\s*do\s*Processo#is';
        if (preg_match($pattern, $result))
            return $this->stepFinalParse($result);

        $this->erro(__METHOD__, "ERRO_DESCONHECIDO", "Retorno não reconhecido");          
    }

    private function stepListaProcessos($result)
    {
        $this->debug(__METHOD__);

        $pattern = '#\/(pje-web\/ConsultaPublica\/DetalheProcessoConsultaPublica\/listView\.seam\?ca=.*?)\'#i';
        if (!preg_match_all($pattern, $result, $matches))
            $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro na Expressão Regular");

        $urls = [];

        foreach($matches[1] as $k => $link)
        {
            $url = $this->url['base'].$link;

            if (in_array($url, $urls))
                continue;

            $urls[] = $url;

            $result = $this->request($url, $this->url['base'], [], 'GET', 3);
            
            Self::ondeEstou($result);
        }
    }

    private function stepFinalParse($result)
    {
        $this->debug(__METHOD__);

        $processo = $aErro = [];

        $patterns = [
            'numero_processo'   => ['#N.{1,8}mero\s*Processo\s*<\/label>\s*<\/div>\s*(?:<div[^>]*?\>\s*)?<div[^>]*?\>\s*(.*?)\s*</div#is'],
            'data_distribuicao' => ['#Data\s*da\s*Distribui.{1,16}o\s*<\/label>\s*<\/div>\s*(?:<div[^>]*?\>\s*)?<div[^>]*?\>\s*(.*?)\s*<#is'],
            'classe_judicial'   => ['#Classe\s*Judicial\s*<\/label>\s*<\/div>\s*(?:<div[^>]*?\>\s*)?<div[^>]*?\>\s*(.*?)\s*<#is'],
            'assunto'           => ['#Assunto\s*<\/label>\s*<\/div>\s*<div[^>]*?\>\s*(?:<div[^>]*?\>\s*)?(.*?)\s*</div#is'],
            'jurisdicao'        => ['@viewcolumn">[^>]*?">\s*<div\s[^>]*?>\s*<.*?jurisdi.*?col-sm-12\s">\s*(.*?)\s*<\/div@is'],
            'orgao_julgador'    => ['#>\s*.{1,8}rg.{1,16}o\s*Julgador\s*<\/label>\s*<\/div>\s*(?:<div[^>]*?\>\s*)?<div[^>]*?\>\s*(.*?)\s*<#is'],
        ];

        foreach($patterns as $desc => $aRegExp)
        {
            foreach($aRegExp as $sRegExp)
            {
                if($sRegExp == NULL)
                {
                    $processo[$desc] = NULL;
                    unset($aErro[$desc]);
                }
                else
                {
                    if(preg_match($sRegExp,$result,$matches))
                    {
                        $processo[$desc] = Self::tratarString($matches[1]);
                        
                        if($desc == 'assunto' && $processo[$desc] == '')
                            $processo[$desc] = trim(utf8_encode($matches[1]));

                        unset($aErro[$desc]);
                        break;
                    }
                    else
                    {
                        $aErro[$desc] = $desc;
                    }
                }
            }
        }

        if(count($aErro) > 0)
            $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro na Expressão Regular");  
        
        Self::setParametersRequests($result); 

        $processo = array_merge($processo, Self::getPartes($result), Self::getMovimentacoes($result), Self::getDocumentos($result));

        $this->aProcesso["processos"][] = $processo;
        $this->aProcesso["total_processos_retornados"]++;
    }

    private function setParametersRequests($result)                             
    {
        $this->debug(__METHOD__);

        $patterns = [
                'parametro1' => "#\'containerId\':\'(.*?)\'#is",//--
                'parametro2' => "#\>new\s*Richfaces.Slider\(\"(.*?)\"#is",
                'parametro3' => "#,\\\'parameters\\\':{\\\'(.*?)\\\':\\\'.*?\\\'}#is",
                'parametro4' => "#,\\\'parameters\\\':{\\\'.*?\\\':\\\'(.*?)\\\'}#is",
                'parametro5' => "#\>new\s*Richfaces.Slider\(\"(.*?\:.*?):.*?\"#is",
                'parametro6' => "#<input\s*type=\"hidden\"\s*name=\"((?:j_id\d+\:)?processoPartesPolo(?:Passivo|Ativo)ResumidoList:(.*?))\"\s*value=\"(?:j_id\d+\:)?processoPartesPolo(?:Passivo|Ativo)ResumidoList:(.*?)\"\s*\/>#is",
                'parametro7' => "#class=\"rich-dtascroller-table \"\s*id=\"((?:j_id\d+\:)?processoPartesPolo(?:Passivo|Ativo)ResumidoList:.*?)_table#is",
        ];

        foreach($patterns as $param => $pattern)
        {
            $result_tmp = $result;
            if ($param == "parametro1")
                $result_tmp = preg_replace("#<script.*?/script>#is","",$result);

            if (preg_match($pattern, $result_tmp, $match))
                $this->$param = $match[1];
        }                                                                       
    }   

    private function getPartes($result)
    {
        $this->debug(__METHOD__);

        $partes = $advogados = $inserted = [];

        $pattern = '#<tbody[^>]*?id="(?:j_id\d+:)?processoPartesPolo(Ativo|Passivo)ResumidoList:tb"[^>]*?\>(.*?)</table>#is';
        if (preg_match_all($pattern, $result, $mPartesPolo))
        {
            foreach($mPartesPolo[0] as $i => $htmlTable)
            {
                $htmlTable = preg_replace('#<script.*?/script>#is', '', $htmlTable);

                $pattern = '#N.{1,8}o\s*existem\s*Partes\s*cadastradas\s*ao\s*Polo#is';
                if (strip_tags($htmlTable)== "" || preg_match($pattern, $htmlTable))
                    continue;

                $tipo = $mPartesPolo[1][$i];
                $pagina = 1;

                do
                {
                    if ($pagina > 1)
                    {
                        $this->debug(__METHOD__, "Página {$pagina}");

                        $this->_viewState = Self::getViewState($result);
                        $htmlTable = utf8_decode(Self::requestPaginaPartes($pagina));
                    }

                    $htmlTable = preg_replace("#<script.*?/script>#is","",$htmlTable);
                    $tableTmp = $htmlTable;

                    $pattern = '#tbody[^>]*?processoPartesPolo(?:Ativo|Passivo)ResumidoList:.*?>(.*?)<\/tbody>#is';
                    if (preg_match($pattern, $htmlTable, $mfiltro))
                        $tableTmp = $mfiltro[1];

                    $pattern = '|<tr[^>]*?\>\s*<td[^>]*?\>\s*(.*?)\s*</td>\s*<td[^>]*?\>\s*(.*?)\s*</td>\s*</tr>|is';
                    if (!preg_match_all($pattern, $tableTmp, $matches))
                        $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro na Expressão Regular #1");  

                    foreach($matches[1] as $k => $nome_tipo)
                    {
                        $nome_tipo = Self::tratarString($nome_tipo, true);

                        if (preg_match('#^(.*?)\s*-\s*(?:CPF|CNPJ):\s*(.*?)\s*\((.*?)\)$#', $nome_tipo, $match))
                        {
                            $parte = [
                                'tipo' => Self::tratarString($match[3]),
                                'nome' => Self::tratarString($match[1]),
                                'documento' => self::tratarString($match[2])
                            ];

                            if (in_array(json_encode($parte), $inserted))
                                continue;

                            if (strtoupper(substr($match[3],0,3)) == 'ADV')
                            {
                                $advogados[] = $parte;
                                $inserted[] = json_encode($parte);
                                continue;
                            }

                            $partes[] = $parte;
                            $inserted[] = json_encode($parte);
                        }
                        else
                        {
                            $parte = [
                                'tipo' => Self::tratarString($mPartesPolo[1][$i]),
                                'nome' => Self::tratarString($nome_tipo)
                            ];

                            if (in_array(json_encode($parte), $inserted))
                                continue;
           
                            $partes[] = $parte;
                            $inserted[] = json_encode($parte);
                        }
                    }

                    $pagina++;
                    $pregMatchNext = preg_match_all("#<td[^>]+'fastforward\'\}\)#is", $htmlTable, $match);
                } while ($pregMatchNext);
            }

            return ['partes' => $partes, 'advogados' => $advogados];
        }

        $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro na Expressão Regular #2");  
    }

    private function getMovimentacoes($result)
    {
        $this->debug(__METHOD__);

        $andamentos = $paginas = [];

        $paginasTotais = 1;
        $pagina = 1;

        $patternPaginasDe = "#<td\s*class=\"rich-inslider-(left)-num\s*\">(.*?)<\/td>#is";
        $patternPaginasAte = "#<td\s*class=\"rich-inslider-(right)-num\s*\">(.*?)<\/td>#is";
        if (preg_match_all($patternPaginasAte, $result, $matchPaginas))
            $paginasTotais = ceil(trim(strip_tags($matchPaginas[2][0])));

        $pattern = '#processoEvento"[^>]*?\>\s*(?:<colgroup[^>]*?\></colgroup>\s*)?<thead.*?</thead>\s*(.*?)</table>\s*<span[^>]*?\>\s*(\d+)\s*resultados\s*encontrados\s*</span>#is';
        if (preg_match($pattern, $result, $match))
        {
            $htmlTable = $match[0];
        }
        else
        {
            $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro na Expressão Regular #1");  
        }

        do
        {
            if ($pagina > 1)
            {
                $this->_viewState = Self::getViewState($result);
                $htmlTable = Self::requestPaginaMovimentacao($pagina);
            }

            $pattern = '#<tr[^>]*?\>\s*<td[^>]*?\>\s*(.*?)\s*</td>\s*<td[^>]*?\>\s*(.*?)\s*</td>\s*</tr>#is';
            if (preg_match_all($pattern, $htmlTable, $matches))
            {
                foreach($matches[1] as $k => $descricao)
                {
                    $descricao = trim(strip_tags($descricao));

                    if ($descricao=="1") 
                        continue;

                    $andamento = [];

                    list($data, $desc) = explode(" - ", $descricao);

                    $pattern = '#^(\d{1,2}\/\d{1,2}\/\d{2,4}\s*\d{1,2}\:\d{1,2}\:\d{1,2})\s*\-\s*(.*?)$#is';
                    if (preg_match($pattern, $descricao, $mlinha))
                    {
                        $data = (substr(($mlinha[1]),'0','10'));
                        $desc = ($mlinha[2]);
                    }

                    $andamento['data'] = Self::tratarString($data);
                    $andamento['descricao'] = Self::tratarString($desc);

                    if (preg_match('#<a[^>]*?>.*?<\/a>#is', $matches[2][$k], $anex_matches))
                    {
                        if($this->getFlOrigemPlataforma())
                        {
                            $anexo = $this->getArquivoAnexo($anex_matches, $andamento['data'], $andamento['descricao']);

                            if($anexo != false) $andamento['anexo'] = $anexo;
                            else $andamento['link'] = ['label' => Self::tratarString($matches[2][$k]), 'url' => 'Existe link no processo'];
                        }
                        else
                        {
                            $andamento['link'] = ['label' => Self::tratarString($matches[2][$k]), 'url' => 'Existe link no processo'];
                        }
                    }

                    $andamentos[] = $andamento;
                }
            }
            else
            {
                $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro na Expressão Regular #2");  
            }

            $pagina++;
        } while ($pagina <= $paginasTotais);

        return ['andamentos' =>  $andamentos];
    }

    private function getArquivoAnexo($attrs, $data, $descricao, $certidao = false)
    { 
        $this->debug(__METHOD__);
        $link = '';
        $anex = $ext = $identificador = null;
        $flSegredo = false;
        if($certidao){
            $pattern = '#openPopUp\([^>]*?,\s*\'/(.*?)\'\)[^>]*?\>#is'; 
            preg_match($pattern, $attrs[0], $saida);
            $link = $this->url['host'] . "/" . $saida[1];         
        }else
        if(preg_match("@onclick\=\"alert\(\'(.*?)\'@is", $attrs[0], $obs)){

            return array(
                        "data" => $data,
                        "descricao" => $descricao,
                        "obs" => html_entity_decode(strip_tags(utf8_decode($this->cleanString($obs[1])))));
        }else{
            
            $pattern_dados = '#\<a\shref\=\"(.*?)\"#is';
            if(!preg_match($pattern_dados, $attrs[0], $dados)) return false;

            if(!preg_match('@https\:\/\/@is', $dados[0])){      
                if (preg_match('@\?(.*?setDownloadInstance.*?)$@is', $dados[1], $down)) {
                    $link = $this->url['download'] . $down[1];
        
                } else {
                    $link = $this->url['host'] . $dados[1];
        
                }  
            }
        }

    
        $link = str_replace("&amp;", "&", $link);

        $url = $this->url['paginaPartesMov'];  

        $anex_page = $this->request($link);

        $ext = $this->getExtConteudo();
        $flSegredo = $this->verificarDocumentoSegredoJustica($anex_page);

        $pattern = '#^\%PDF#i';
        if(!preg_match($pattern, $anex_page) && ($ext != 'html' && $ext != 'pdf') && !$flSegredo){

            $pattern_infos = "#downloadPDF.*?getElementById\('(?<id>.*?)'\).*?{(?P<parameters>.*?)}#is";
            if(!preg_match($pattern_infos, $anex_page, $infos)) return false;

            $params = $this->getFormParameters($infos['id'], $anex_page);
        foreach(explode(",", $infos['parameters']) as $param)
        {
            $aux = explode("':'", $param);
            $key = str_replace("'", "", $aux[0]);

            $params[$key] = str_replace("'", "", $aux[1]);
        }try{
            $anex = $this->request($url_download, $link, $params, 'POST');
            $ext = $this->getExtConteudo();
            $flSegredo = $this->verificarDocumentoSegredoJustica($anex);
        }catch(\Exception $e){
            if(preg_match($anex_page)){
                $anex = $anex_page;
            }else{
                $this->erro(__METHOD__, '', 'Erro ao recuperar movimentos #1 -' . $e->getMessage());
            }
            
        }
        }else{
            $anex = $anex_page;
        }
        
        $identificador = $this->getIdentificadorAnexo($link, $data, ['idProcessoDoc'], $descricao);
        $identificador = md5($this->getNumeroUnico() . $identificador);
        
        $anexo = $this->getDocumentoPadrao($link,
                                            $identificador,
                                            $data,
                                            $descricao,
                                            $flSegredo ? null : $anex,
                                            $ext,
                                            $flSegredo,
                                            null);

        return $anexo;        
    }

    private function verificarDocumentoSegredoJustica($result)
    {
        $pattern = '#precisa\s*estar\s*logado\s*para\s*realizar\s*esta\s*opera#is';
        if(preg_match($pattern, $result))
        {
            return true;
        }

        return false;
    }

    private function getFormParameters($id_form, $page)
    {
        $params = [];
        $pattern_form = '#<form\s*?[^>]*?\s*?id="'.$id_form.'"\s*?[^>]*?\s*?>.*?<\/form>#is';
        preg_match($pattern_form, $page, $form_match);

        $pattern_inputs = '#input\s*?[^>]*?\s*?name="(?P<name>[^"]*?)"\s*?[^>]*?\s*?value="(?P<value>[^"]*?)"\s*?[^>]*?>#is';
        preg_match_all($pattern_inputs, $form_match[0], $input_matches);

        foreach($input_matches[0] as $k => $input)
        {
            $params[$input_matches['name'][$k]] = $input_matches['value'][$k];
        }

        return $params;
    }

    private function getDocumentos($result)
    {
        $this->debug(__METHOD__);

        $documentos = [];
        $paginasTotais = 1;
        $pagina = 1;

        $pattern = '#processoDocumentoGridTabPanel_body\">(.*?)<\/div><\/div><\/div>#is';
        if (!preg_match_all($pattern, $result, $matchDivGridDocumento))
            $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro na Expressão Regular #1");  

        $html = $matchDivGridDocumento[1][0];

        $patternPaginasDe = "#<td\s*class=\"rich-inslider-(left)-num\s*\">(.*?)<\/td>#is";
        $patternPaginasAte = "#<td\s*class=\"rich-inslider-(right)-num\s*\">(.*?)<\/td>#is";
        if (preg_match_all($patternPaginasAte, $html, $matchPaginas))
            $paginasTotais = ceil(trim(strip_tags($matchPaginas[2][0])));
        
        $pattern = '#processoDocumentoGridTab"[^>]*?\>\s*(?:<colgroup[^>]*?\></colgroup>\s*)?<thead.*?</thead>\s*(.*?)</table>#is';
        if (preg_match($pattern, $html, $match))
            $htmlTable = preg_replace('#<script.*?/script>#is', '', $match[1]);

        Self::setParametersRequests($html);

        do
        {
            if ($pagina > 1)
            {
                $this->_viewState = Self::getViewState($result);
                $htmlTable = Self::requestPaginaDocumentos($pagina);
            }

            $pattern = '|<tr[^>]*?\>\s*<td[^>]*?\>\s*(.*?)\s*</td>\s*<td[^>]*?\>\s*(.*?)\s*</td>\s*</tr>|is';
            if (preg_match_all($pattern, $htmlTable, $matches))
            {
                foreach($matches[1] as $k => $doc)
                {
                    $documento = [];

                    $pattern = '#(\d{2}\/\d{2}\/\d{4})\s*\d{2}\:\d{2}\:\d{2}\s*\-\s*(.*?)\s*<#is';
                    if(!preg_match($pattern, $doc, $match))
                        $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro na Expressão Regular");  
        
                    $documento['data'] = $match[1];
                    $documento['documento'] = Self::tratarString($match[2]);

                    $pattern = '#<a[^>]*?>#is';
                    if (preg_match($pattern, $doc, $linkMatch))
                    {
                        if($this->getFlOrigemPlataforma())
                        {
                         
                            $anexo = $this->getArquivoAnexo($linkMatch, $documento['data'], $documento['documento']); 
                            
                            if($anexo != false) $documento['anexo'] = $anexo;
                            else $documento['link'] = 'Existe link no processo';
                        }
                        else
                        {
                            $documento['link'] = 'Existe link no processo';
                        }
                    }

                    
                    if (preg_match($pattern, $matches[2][$k], $linkMatch))
                        $documento['certidao'] =  $anexo = $this->getArquivoAnexo(array($matches[2][$k]), $documento['data'], $documento['documento'], true); 

                    $documentos[] = $documento;
                }
            }

            $pagina++;
        } while ($pagina <= $paginasTotais);

        return ['documentos' => $documentos];
    }

    private function getViewState($html)
    {
        $pattern = '|name\=\"javax\.faces\.ViewState\"[^>]+?value\=\"([^>]+?)\"|i';
        if (preg_match($pattern, $html, $matches))
            return $matches[1];

        $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro na Expressão Regular");  
    }

    private function tratarString ($value, $utf8 = false)
    {
        if ($utf8)
            $value = utf8_encode($value);

        $value = preg_replace(['/\p{Cc}+/u','/\&\#160;/is'], [''], strip_tags($value));
        $value = html_entity_decode($value, ENT_COMPAT, 'UTF-8');
        $value = trim($value);

        return $value;
    }

    private function requestPaginaMovimentacao($pagina)
    {
        $this->debug(__METHOD__);

        $data = [
            'AJAXREQUEST' => $this->parametro1,
            $this->parametro2 => $pagina,
            $this->parametro5 => $this->parametro5,
            'autoScroll' => '',
            'javax.faces.ViewState' => $this->_viewState,
            $this->parametro3 => $this->parametro4,
            'AJAX:EVENTS_COUNT' => '1',
        ];

        $result = $this->request($this->url['paginaPartesMov'], $this->url['base'], $data, 'POST', 3);

        $pattern = '#<table[^>]*?id="[^"]*?(?:j_id\d+)?processoEvento"[^>]*?\>\s*(?:<colgroup[^>]*?\></colgroup>\s*)?<thead.*?</thead>\s*(.*?)</table>\s*<span[^>]*?\>\s*(\d+)\s*resultados\s*encontrados\s*</span>#is';
        if (preg_match($pattern, $result, $match))
            return $match[0];
        
        $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro na Expressão Regular");  
    }

    private function requestPaginaPartes($pagina)
    {
        $this->debug(__METHOD__);

        $data = [
            'AJAXREQUEST'           => '_viewRoot',
            $this->parametro6       => $this->parametro6,
            'javax.faces.ViewState' => $this->_viewState,
            'ajaxSingle'            => $this->parametro7,
            $this->parametro7       => 'fastforward',
            'AJAX:EVENTS_COUNT'     => '1',
        ];

        $result = $this->request($this->url['paginaPartesMov'], $this->url['base'], $data, 'POST', 3);

        $pattern = '#processoPartesPolo(Ativo|Passivo)ResumidoList:.*?>(.*?)<\/table>#is';
        if (preg_match($pattern, $result))
            return $result;

        $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro na Expressão Regular");  
    }                                                                           

    private function requestPaginaDocumentos($pagina)
    {
        $this->debug(__METHOD__);

        $data = [
            'AJAXREQUEST'           => '_viewRoot',                                   
            $this->parametro2       => $pagina,                                   
            $this->parametro5       => $this->parametro5,                         
            'autoScroll'            => '',                                             
            'javax.faces.ViewState' => $this->_viewState,                   
            $this->parametro3       => $this->parametro4,                         
            'AJAX:EVENTS_COUNT'     => '1',                                     
        ];

        $result = $this->request($this->url['paginaPartesMov'], $this->url['base'], $data, 'POST', 3);

        $pattern = '#<table[^>]*?id="processoDocumentoGridTabList"[^>]*?\>\s*(?:<colgroup[^>]*?\></colgroup>\s*)?<thead.*?</thead>\s*(.*?)</table>#is';
        if (preg_match($pattern, $result, $match))
            return $match[0];
    }
}
?>


