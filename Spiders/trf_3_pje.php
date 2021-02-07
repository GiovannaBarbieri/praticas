<?php
include_once(dirname(__FILE__) . '/../doc/config.php');

include_once(CORE_DIR . '/Spider.php');

class ProcessoSpider extends Spider
{
    private $_url;
    private $_viewState;

    private $parametro1;
    private $parametro2;
    private $parametro3;
    private $parametro4;
    private $parametro5;
    private $parametro6;
    private $parametro7;

    protected $urls;

    public function __construct() {
        $ch = '1';
        parent::__construct(dirname(__FILE__), array(), $ch);
        ini_set("pcre.backtrack_limit", 1000000000);
        $this->client->setCookieJar();
        $this->autoCaptcha = false;
        $this->useCaptchaService = true;

        $this->urls = new stdClass;
        $this->useCurl = false;

        $this->urls->base = 'https://pje1g.trf3.jus.br';

        $this->_url = 'https://pje1g.trf3.jus.br/pje/ConsultaPublica/listView.seam';
        $this->aProcesso = array();
        $this->aProcesso["processos"] = array();
        $this->aProcesso['url'] = $this->_url;
        $this->aProcesso["total_processos"] = 0;
        $this->aProcesso["total_processos_retornados"] = 0;
    }

    protected function buildUrls($grau) {
        $this->urls->base = "https://pje{$grau}g.trf3.jus.br";
        $this->urls->detalheProc = $this->urls->base."/pje/ConsultaPublica/DetalheProcessoConsultaPublica/listView.seam";
        $this->urls->view = $this->urls->base."/pje/ConsultaPublica/listView.seam";
        $this->urls->captcha = $this->urls->base."/pje/seam/resource/captcha";
        $this->urls->download = "https://pje{$grau}g.trf3.jus.br/pje/download.seam?";
    }

    public function searchByNome($nome) {
        $this->debug(__METHOD__);
        throw new Exception("Pesquisa Não Implementada");
    }

    public function searchByNomeBatch($nome) {
        $this->debug(__METHOD__);
        return $this->searchByNome($nome);
    }

    public function searchByNumProc($numero, $grau = 1) {
        $this->debug(__METHOD__);
        $this->buildUrls($grau);
        $numero = $this->_mascaraProcesso($numero);

        $params = array(
            'fPP:numProcesso-inputNumeroProcessoDecoration:numProcesso-inputNumeroProcesso' => $numero
        );
        return $this->search($params);
    }

    private function search($params) {
        $this->debug(__METHOD__);
        $tentativas = 0;
        do {
            $tentativas++;
            try {
                $result = $this->request($this->urls->view, $this->urls->base, $params, 'POST', 3);

                $this->_viewState = $this->getViewState($result);

                $fpp = $this->getfPP($result);
                $fpp = [$fpp => $fpp];

                $params = array_merge($fpp, $params);

                $pattern = '#data-sitekey="(.*?)"#is';
                if(preg_match_all($pattern, $result, $matches)){
                    $this->quebraRecaptchaV2($this->urls->base.'/pje/ConsultaPublica/listView.seam', [], 'GET', null, null, $googlekey, true);
                }
        
                $this->start($params);
                break;
            } catch (Exception $e) {
                if ($e->getCode()==CAPTCHA_ERROR && $tentativas < 3) {
                    if ($this->debug) echo $e->getMessage()."\n";
                    $this->limparCookies();
                    continue;
                }
                throw $e;
            }
        } while (true);
        return $this->aProcesso;
    }

    private function getfPP($result) {
        $this->debug(__METHOD__);
        $pattern = "#\'parameters\'\:\{\'(fPP\:j_id\d+)\'#is";
        if(!preg_match($pattern, $result, $match))
            $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Não foi possivel recuperar o FPP");

        return $match[1];
    }

    private function start($params) {
        $this->debug(__METHOD__);

        $data = array(
            'AJAXREQUEST' => '_viewRoot',
            'fPP:idElementoDecoration:idElemento' => '_______-__.____.4.01.____',
            'mascaraProcessoReferenciaRadio'=>'on',
            'fPP:j_id156:processoReferenciaInput'=> '',
            'fPP:dnp:nomeParte'             => '',
            'fPP:j_id174:nomeAdv'           => '',
            'fPP:j_id183:classeProcessualProcessoHidden' => '',
            'tipoMascaraDocumento'          => 'on',
            'fPP:dpDec:documentoParte'      => '',
            'fPP:Decoration:numeroOAB'      => '',
            'fPP:Decoration:j_id218'        => '',  
            'fPP:Decoration:estadoComboOAB' => 'org.jboss.seam.ui.NoSelectionConverter.noSelectionValue',
            'g-recaptcha-response' => $this->captcha,
            'fPP' => 'fPP',
            'autoScroll' => '',
            'javax.faces.ViewState' => $this->_viewState,
            'fPP:j_id224' => 'fPP:j_id224',
            'AJAX:EVENTS_COUNT' => '1',
        );

        $params = array_merge($data, $params);
       
        $result = $this->request($this->urls->view, $this->urls->base, $params, 'POST', 3);
        
        $this->ondeEstou($result);
    }

    private function ondeEstou($result) {
        $this->debug(__METHOD__);

        $pattern = '#A\s*verifica.{1,6}o\s*de\s*captcha\s*n.{1,3}o\s*est.{1,3}\s*correta#is';
        if (preg_match($pattern, $result)) {
            if (preg_match($pattern, $result)) {
                if ($this->captchaService) {
                    Util::captchaServiceInvalidate($this->captchaService);
                }
                $this->erro(__METHOD__, "ERRO_CAPTCHA", "Captcha Inválido");
            }
        }

        $pattern = '#Sua\s*pesquisa\s*n.{1,8}o\s*encontrou\s*nenhum\s*processo\s*dispon.{1,8}vel#is';
        if (preg_match($pattern, $result)) {
            $this->erro(__METHOD__, "ERRO_INEXISTENTE", "Nada Encontrado");
        }

        $pattern = "#\/pje\/ConsultaPublica\/DetalheProcessoConsultaPublica\/listView\.seam\?ca=.*?\'#i";
        
        if (preg_match($pattern, $result)) {
            $this->stepListaProcessos($result);
            return;
        }

        $pattern = '#Dados\s*do\s*Processo#is';
        if (preg_match($pattern, $result)) {
            $this->stepFinalParse($result);
            return;
        }

        $pattern = '#Total\s*de\s*registros\s*:\s*(?:<span[^>]*?\>)?\s*0\s*</#is';
        if (preg_match($pattern, $result)) {
            $this->erro(__METHOD__, "ERRO_INEXISTENTE", "Nada Encontrado");
        }

        if(preg_match('#errorUnexpected#is', $result)) {
            $this->erro(__METHOD__, "ERRO_TEMPORARIO_TRIBUNAL", 'Erro no processo');
        }

        $this->erro(__METHOD__, "ERRO_DESCONHECIDO", "Possivel Mudança no site/ Pagina não Identificada");
        
    }

    protected function form() {
        $this->debug(__METHOD__);

        $ping = $this->request($this->urls->view, $this->urls->base, [], 'GET', 3);

        $this->_viewState = $this->getViewState($ping);

        $this->form['urlcaptcha'] = $this->urls->captcha;
        $this->form['ref'] = $this->_url;
        $this->form['path'] = null;

        return $this->form;
    }

    private function stepListaProcessos($result) {
        $this->debug(__METHOD__);

        $pattern = '#(\/pje\/ConsultaPublica\/DetalheProcessoConsultaPublica\/listView\.seam\?ca=.*?)\'#i';
        if (preg_match_all($pattern, $result, $matches)) {
            $matches[1] = array_unique($matches[1]);
            foreach($matches[1] as $k => $link) {
                $this->linkCA = $this->urls->base.$link;
                $html = $this->request($this->urls->base.$link, $this->urls->base, [], 'GET', 3);
                $this->ondeEstou($html);
            }
            return;
        }
        $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Não foi possível recuperar a lista de processos");
    }

    private function stepFinalParse($result) {
        $this->debug(__METHOD__);
        $processo = $aErro = array();
        $patterns = array(
                'numero_processo' => array('|N.{1,8}mero\s*Processo\s*</label>\s*<\/div>\s*(?:<div[^>]*?\>\s*)?<div[^>]*?\>\s*(.*?)\s*</div|is'),
                'data_distribuicao' => array('|Data\s*da\s*Distribui.{1,16}o\s*</label>\s*<\/div>\s*(?:<div[^>]*?\>\s*)?<div[^>]*?\>\s*(.*?)\s*<|is'),
                'classe_judicial' => array('|Classe\s*Judicial\s*</label>\s*<\/div>\s*(?:<div[^>]*?\>\s*)?<div[^>]*?\>\s*(.*?)\s*<|is'),
                'assunto' => array('|Assunto\s*</label>\s*<\/div>\s*(?:<div[^>]*?\>\s*)?<div[^>]*?\>\s*(.*?)\s*</div|is'),
                'orgao_julgador' => array('|>\s*.{1,8}rg.{1,16}o\s*Julgador\s*</label>\s*<\/div>\s*(?:<div[^>]*?\>\s*)?<div[^>]*?\>\s*(.*?)\s*<|is'),
                'jurisdicao' => ['#>\s*Jurisdi.{2,16}o\s*<\/label>\s*<\/div>\s*(?:<div[^>]*?\>\s*)?<div[^>]*?\>\s*(.*?)\s*</div#is', null],
                );
        foreach($patterns as $desc => $aRegExp) {
            foreach($aRegExp as $sRegExp) {
                if($sRegExp == NULL){
                    $processo[$desc] = NULL ;
                    unset($aErro[$desc]);
                } else {
                    if(preg_match($sRegExp,$result,$matches)) {
                        if($desc == 'assunto') {
                            $assunto = str_replace('&quot;', '"', utf8_encode($matches[1]));
                            $processo[$desc] = trim(str_replace('\n', ' ', $assunto));
                        } else {
                            $processo[$desc] = self::tratarString($matches[1]);
                        }
                        unset($aErro[$desc]);
                        break;
                    } else {
                        $aErro[$desc] = $desc;
                    }
                }
            }
        }

        if(count($aErro) > 0){
            $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Spider Broken! - ".implode(",",$aErro));
        }
        $this->setParametersRequests($result);

        $processo = array_merge($processo,
                                $this->getPartes($result),
                                //$this->getMovimentacoes($result)
                                );
        $processo["documentos"] = $this->getDocumentos($result);
        $this->aProcesso["processos"][] = $processo;
        $this->aProcesso["total_processos_retornados"]++;
    }

    private function setParametersRequests($result) {
        $patterns = array(
                'parametro1' => "#\'containerId\':\'(.*?)\'#is",
                'parametro2' => "#\>new\s*Richfaces.Slider\(\"(.*?)\"#is",
                'parametro3' => "#,\\\'parameters\\\':{\\\'(.*?)\\\':\\\'.*?\\\'}#is",
                'parametro4' => "#,\\\'parameters\\\':{\\\'.*?\\\':\\\'(.*?)\\\'}#is",
                'parametro5' => "#\>new\s*Richfaces.Slider\(\"(.*?\:.*?):.*?\"#is",
                'parametro6' => "#<input\s*type=\"hidden\"\s*name=\"processoPartesPoloPassivoResumidoList:(.*?)\"\s*value=\"processoPartesPoloPassivoResumidoList:(.*?)\"\s*\/>#is",
                'parametro7' => "#class=\"rich-dtascroller-table \"\s*id=\"processoPartesPoloPassivoResumidoList:(.*?)_table#is",
                );
        foreach($patterns as $param => $pattern) {
            $result_tmp = $result;
            if ($param=="parametro1") {
                $result_tmp = preg_replace("#<script.*?/script>#is","",$result);
            }
            if (preg_match($pattern, $result_tmp, $match)) {
                $this->$param = $match[1];
            }
        }
    }

    private function getPartes($result) {
        $this->debug(__METHOD__);
        $partes = $advogados = $inserted = array();
        $pattern = '#<div\s*id=\"(?:j_id\d+\:)?processoPartesPolo(Ativo|Passivo)ResumidoDiv\">\s*.*?<tbody[^>]*?id="(?:j_id\d+\:)?processoPartesPolo(?:Ativo|Passivo)ResumidoList:tb"[^>]*?\>(.*?)</table>#is';
        if (preg_match_all($pattern, $result, $mPartesPolo)) {
            foreach($mPartesPolo[0] as $i => $htmlTable) {
                if (strip_tags($htmlTable)=="") continue;
                $tipo = $mPartesPolo[1][$i];
                $polo = 'Passivo';
                if(preg_match('#Polo\s*ativo#is', $tipo))
                    $polo = 'Ativo';
                $pagina = 1;
                do {
                    if($pagina > 1) {
                        $this->debug(__METHOD__, "Pagina {$pagina}");
                        $this->_viewState = $this->getViewState($result);
                        $htmlTable = utf8_decode($this->requestPaginaPartes($result, $pagina, $polo));
                    }
                    $htmlTable = preg_replace("#<script.*?/script>#is","",$htmlTable);
                    $htmlTable = preg_replace("#<style.*?/style>#is","",$htmlTable);
                    $tableTmp = $htmlTable;
                    $pattern = '#tbody[^>]*?processoPartesPolo(?:Ativo|Passivo)ResumidoList:.*?>(.*?)<\/table>#is';
                    if (preg_match($pattern, $htmlTable, $mfiltro)) {
                        $tableTmp = $mfiltro[1];
                    }
                    $pattern = '|<tr[^>]*?\>\s*<td[^>]*?\>\s*(.*?)\s*</td>\s*<td[^>]*?\>\s*(.*?)\s*</td>\s*</tr>|is';
                    if (!preg_match_all($pattern, $tableTmp, $matches)) {
                        //throw new Exception('Spider broken at [' . __METHOD__ . '] - #2', PARSE_ERROR);
                    }
                    foreach($matches[1] as $k => $nome_tipo) {
                        foreach($matches[1] as $k => $nome_tipo) {
                            $nome_tipo =self::tratarString($nome_tipo, true);
                            if (preg_match('#^(.*?)\s*-\s*(?:CPF|CNPJ):\s*(.*?)\s*\((.*?)\)#', $nome_tipo, $match)) {
                                $parte = array('tipo' => self::tratarString($match[3]),'nome' => self::tratarString($match[1]),'documento' => self::tratarString($match[2]));
                                if (in_array(json_encode($parte), $inserted)) {
                                    continue;
                                }
                                if (strtoupper(substr($match[3],0,3)) == 'ADV') {
                                    $advogados[] = $parte;
                                    $inserted[] = json_encode($parte);
                                    continue;
                                }
                                $partes[] = $parte;
                                $inserted[] = json_encode($parte);
                            } else {
                                $parte = array('tipo' => self::tratarString($mPartesPolo[1][$i]), 'nome' => self::tratarString($nome_tipo));
                                if (in_array(json_encode($parte), $inserted)) {
                                    continue;
                                }
                                $partes[] = $parte;
                                $inserted[] = json_encode($parte);
                            }
                        }
                    }
                    $pagina++;
                    $pregMatchNext = preg_match_all("#<td[^>]+'fastforward\'\}\)#is", $htmlTable, $match);
                } while ($pregMatchNext);
            }
            return array('partes' => $partes, 'advogados' => $advogados);
        }
        $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Não foi possível recuperar as partes");
        
    }

    private function getMovimentacoes($result) {
        $this->debug(__METHOD__);

        $andamentos = array();
        
        $total_paginas = 1;
//esta regex não funciona
        $pattern = '#<table.*?id=".*?processoEvento"[^>]*?\>.*?<thead.*?</thead>\s*(.*?)</table>\s*<span[^>]*?\>\s*\d+\s*resultados\s*encontrados\s*</span>.{0,3700}rich-inslider-right-num\s*\"\s*>\s*(\d+)\s*<.*?idEditarModeloDocumento#is';
        if (!preg_match($pattern, $result, $match))
        {	//regex ok
            $pattern = '#<table.*?id=".*?processoEvento"[^>]*?\>.*?<thead.*?</thead>\s*(.*?)</table>\s*<span[^>]*?\>\s*\d+\s*resultados\s*encontrados\s*</span><#is';
            if(!preg_match($pattern, $result, $match))
            {
                $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Não foi possível recuperar as movimentacoes");
            }
        }else{
            $total_paginas = $match[2];
        }
        
        $pagina = 1;

        do {
            $htmlTable = $match[1];
            if ($pagina > 1) {
                $this->_viewState = $this->getViewState($result);
                $htmlTable = $this->requestPaginaMovimentacao($pagina);
            }///regex ok
            $pattern = '|<tr[^>]*?\>\s*<td[^>]*?\>\s*(.*?)\s*</td>\s*<td[^>]*?\>\s*(.*?)\s*</td>\s*</tr>|is';
            if (preg_match_all($pattern, $htmlTable, $matches)) {
                foreach($matches[1] as $k => $descricao) {
					
                    $des = trim(strip_tags($descricao));//mudança de codigo
						if($descricao=="1") //mudança
							continue;//mudança
                    $andamento = []; //mudança
					
                    list($data, $desc) = explode(" - ", $descricao); //
					
					$pattern = '@@is';//
					if(preg_match($pattern, $descricao, $mlinha))//
					{
						$data = (substr($mlinha[1]), '0','10');//
						$desc = ($mlinha[2]);///
					}
					
                    $andamento['data'] = Self::tratarString($data);//
                    $andamento['descricao'] = Self::tratarString($desc);//
					
                    if (preg_match('#<a[^>]*?\>#is', $matches[2][$k], $anex_matches)) {//			
                       //retirado//$andamento['link'] = array('label' => self::tratarString(str_replace("-", " ", $matches[2][$k])), 'url' => 'Existe link no processo');

                        if($this->getFlOrigemPlataforma())
                        {	
							$anexo = $this->recuperarLinkEGerarAnexo($anex_matches, $andamento['data'], $andamento['descricao']);
							
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
		}while ($pagina <= $paginasTotais);
			return ['andamentos' => $andamentos];
	}   
	

    private function getDocumentos($result) {
        $this->debug(__METHOD__);

        $documentos = [];

        // Separa a div de documentos
        $pattern = '#<div[^>]*?id=\"j_id\d+\:processoDocumentoGridTabPanel\"[^>]*?>(.*)#is';
        if(!preg_match($pattern, $result, $matchDocumentos))
        {
            $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro em getDocumentos #1");
        }

        // Pega o número de páginas
        $pattern = '#<form[^>]*?id=\"([^\"]*?)\"[^>]*?>\s*<table[^>]*?class=\"rich\-inslider[^>]*?>.*?rich\-inslider\-right\-num[^>]*?>\s*(\d+)#is';
        if(!preg_match($pattern, $matchDocumentos[1], $matchPaginas)) {
			$pattern = "@<div[^>]*?j_id140:processoDocumentoGridTabPanel_body[^>]*?>.*?<span[^>]*?>\s*(\d+)\s*resultados\s*encontrados@is";
			if(!preg_match($pattern, $matchDocumentos[1], $matchNDocumentos)) {
				$this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro em getDocumentos #2");
			} else {
				$nPaginas = 1;
			}
		} else {
			$nPaginas = $matchPaginas[2];
		}

        $result = $matchDocumentos[1];
        $paginaAtual = 1;

        do
        {   
            if($paginaAtual > 1)
            {
				
                $result = $this->paginarDocumentos($paginaAtual, $result);
            }

            $pattern = '#<tr[^>]*?rich\-table\-row[^>]*?>\s*(.*?)\s*<\/tr#is';
            if(!preg_match_all($pattern, $result, $matches))
            {
				
				foreach($matches[1] as $key => $value)
				{
					$documentos = [];
					
					$pattern = '#0\sresultados\sencontrados#is';
					if(!preg_match($pattern, $value, $match))
                    $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro em getDocumentos #3");
					
					$documento['data'] = $match[1];
					$documento['documento'] Self::tratarString($match[2]);
					
					$pattern = '@@is';
					if(preg_match($pattern, $value, $linkMatch))
					{
						if($this->getFlOrigemPlataforma())
						{
							$anexo = $this->getDadosDoDocumento($linkMatch, $documento['data'], $documento['documento']));
							
							if($anexo !=false) $documento['anexo'] = $anexo;
							else $documento['link'] = 'Existe link no processo';
						}
						else
						{
							$documento['link'] = 'Existe link no processo';
						}
					}
					if(preg_match($pattern, $matches[2][$k], $linkMatch))
						$documento['certidao'] = $anexo = $this->getDadosDoDocumento(array($matches[2][$k]), $documento['data'], $documento['documento'], true);
					
					$documentos[] = $documento;		
				}
            }

            $paginaAtual++;
		}while($nPaginas <= $paginaAtual);
		
		return ['documentos' => $documentos];
	}

    private function getDadosDoDocumento($result)
    {
        $this->debug(__METHOD__);

        $documento = [];

        // Recupera a label
        $pattern = '#<span[^>]*?class=\"sr\-only[^>]*?>\s*[^<]*?<\/span>\s*([^<]*)#is';
        if(!preg_match($pattern, $result, $match))
        {
            if(!preg_match('#<i\sclass=\"fa\sfa\-file[^>]*?>[^>]*?>(.*?)<\/a>#is',$result, $match)){
                $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro em getDadosDoDocumento #1");
            }
        }

        $pattern = '#<i[^>]*class=\"fa\s*fa\-file\-pdf\-o\"[^>]*?>\s*<\/i>\s*([^<]*?)\s*<#is';
        if(preg_match($pattern, $result, $matchPdf))
        {
            $match[1] = $matchPdf[1];
        }

        $labelData = $match[1];

        $pattern = '#(\d+\/\d+\/\d+)[\d\:\s]+?\-\s*(.*)#is';
        if(!preg_match($pattern, $labelData, $match))
        {
            $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro em getDadosDoDocumento #2");
        }

        $data   = $match[1];
        $label  = html_entity_decode($match[2]);


        if($this->getFlOrigemPlataforma())
        {
            // Recupera os links dos documentos
            $pattern = '#<a[^>]*?onclick=\"openPopUp\(\'([^\']*?)\'\,\s*\'([^\']*)#is';
            if(!preg_match_all($pattern, $result, $matches))
            {
                $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro em getDadosDoDocumento #3");
            }

            $identificador = $matches[1][0];

            $pattern = '#about\:blank#is';
            if(preg_match($pattern, $matches[2][0]))
            {
                $pattern = '#<span[^>]*?>\s*<a[^>]*?href=\"(\/pje[^\"]*)#is';
                if(preg_match($pattern, $result, $matchLink))
                {
                    $matches[2][0] = $matchLink[1];

                    $pattern = '#idbin=(\d+)#is';
                    if(!preg_match($pattern, $matchLink[1], $matchIdentificador))
                    {
                        $this->erro(__METHOD__, '', 'Identificador de PDF não encontrado (idBin)');
                    }

                    $identificador = $matchIdentificador[1];
                }                
            }

            // Documento
            $link = $this->arrumarLink($matches[2][0]);

            $anexos[] = $this->recuperarLinkEGerarAnexo($link, $data, $label, $identificador);

            // Certidão
            if(isset($matches[2][1])) {
                $link = $this->arrumarLink($matches[2][1]);

                $identificador = $matches[1][1];
                
                $pattern = '#about\:blank#is';
                if(preg_match($pattern, $matches[2][1]))
                {
                    $identificador = null;
                }

				$anexos[] = $this->recuperarLinkEGerarAnexo($link, $data, 'Certidão - ' . $label, $identificador);
            }
        } 
        else 
        {
            $anexos[] = [
                'data' => $data,
                'label' => $label,
            ];
        }

        return $anexos;

    }

    private function recuperarLinkEGerarAnexo($link, $data, $label, $identificador)
    { var_dump($data, $label);
        $flSegredo = false;

        //$hashDataLabel = md5($data.$label);

        //$identificador = $this->getDocumentoIdentificador($link).$hashDataLabel;

        $this->curlHeader = [
            'Host: pje1g.trf3.jus.br',
            'Referer: ' . $this->urls->detalheProc. '?'  . $this->linkCA,
        ];

        $result = $this->request($link);
        $ext = $this->getExtConteudo();

        $flSegredo = $this->verificarDocumentoSegredoJustica($result);

        $pattern = '#^\%PDF#i';
        if(!preg_match($pattern, $result) && ($ext !='html' && $ext != 'pdf') && !$flSegredo)
        {        
            $pattern = '#<a[^>]*?id=\"(j_id\d+)\:downloadPDF[^\}]*?\}[^\,]*?\,\s*(\{[^\}]*\})#is';
            if(!preg_match($pattern, $result, $match))
            {
                $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro em recuperarLinkEGerarAnexo #1");
            }

            $json = $this->json_decode(str_replace("'", '"', $match[2]));

            $params = [];

            $params[$match[1]]					= $match[1];
            $params['javax.faces.ViewState']	= $this->getViewState($result);

            foreach($json as $key => $value)
            {
                $params[$key] = $value;
            }

            $link = $this->urls->base . '/pje/ConsultaPublica/DetalheProcessoConsultaPublica/documentoSemLoginHTML.seam';

            $result = $this->request($link, null, $params, 'POST');
            $ext = $this->getExtConteudo();

        }

        return $this->getDocumentoPadrao($link, $identificador, $data, $label, $flSegredo ? null : $result, $ext, $flSegredo);
    }

    private function arrumarLink($link)
    {
        $link = str_replace('&amp;', '&', html_entity_decode($link));

        $pattern = '#https?\:\/\/#is';
        if(preg_match($pattern, $link))
        {
            return $link;
        }

        $pattern = '@nomeArqProcDocBin@is';
        if(!preg_match($pattern, $link)){

            $link = str_replace('/pje-web', '', $this->urls->base) . ':443' . $link;

        }else{
            preg_match('@\?(.*?)$@is', $link, $matches);
            $link = $this->urls->download . $matches[1];
        }

        return $link;

    }

    private function getDocumentoIdentificador($link)
    {
        $pattern = '#\?(.*)#is';
        if(!preg_match($pattern, $link, $match))
        {
            $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro em getDocumentoIdentificador#1");
        }

        $pattern = '#\=([^\&|$]*)#is';
        if(!preg_match_all($pattern, $match[1], $matches))
        {
            $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Erro em getDocumentoIdentificador#2");
        }

        $identificador = '';

        foreach($matches[1] as $value)
        {
            $identificador .= $value;
        }

        return $value;
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

    private function paginarDocumentos($nPagina, $result)
    {
        $this->debug(__METHOD__);        
        $data = array(
            'AJAXREQUEST' => 'j_id140:j_id509',
            "j_id140:j_id593:j_id594" => $nPagina,
            "j_id140:j_id593" => "j_id140:j_id593",
            'autoScroll' => '',
            'javax.faces.ViewState' => $this->getViewState($result),
            "j_id140:j_id593:j_id595" => "j_id140:j_id593:j_id595",
            'AJAX:EVENTS_COUNT' => '1',
        );
        $result = $this->request($this->urls->detalheProc, $this->urls->base, $data, 'POST', 3);
        return $result;
    }

    private function getViewState($html) {
        $this->debug(__METHOD__);
        $pattern = '|name\=\"javax\.faces\.ViewState\"[^>]+?value\=\"([^>]+?)\"|i';
        if (preg_match($pattern, $html, $matches)) {
            return $matches[1];
        }
        $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Não foi possível recuperar o view_state");
        
    }

    protected function _mascaraProcesso($numero) {
        $numero = preg_replace('#\D+#i', '', $numero);
        if (empty($numero)) {
            $this->erro(__METHOD__, "ERRO_ENTRADA", "Número de processo não informado");
        }
        $numero = substr($numero, 0, 7)."-".substr($numero, 7, 2).".".substr($numero, 9, 4).".".substr($numero, 13, 1).".".substr($numero, 14, 2).".".substr($numero, 16, 4);
        return $numero;
    }

    public static function tratarString ($value, $utf8 = false) {
        if ($utf8) {
            $value = utf8_encode($value);
        }
        $value = html_entity_decode($value, ENT_COMPAT, 'UTF-8');
        $value = strip_tags(preg_replace(array('/\p{Cc}+/us', '/^\p{Z}+|\p{Z}+$/u'),array('',''),$value));
        $value = trim($value);
        return $value;
    }

    private function requestPaginaMovimentacao($pagina) {
        $this->debug(__METHOD__);
        
        $data = array(
                'AJAXREQUEST' => 'j_id140:j_id428',
                'j_id140:j_id501:j_id502' => $pagina,
                'j_id140:j_id501' => 'j_id140:j_id501',
                'autoScroll'=>'',
                'javax.faces.ViewState'=> $this->_viewState,
                'j_id140:j_id501:j_id503' => 'j_id140:j_id501:j_id503',
                'AJAX:EVENTS_COUNT'=>'1',
                );

        $html = $this->request($this->urls->detalheProc, $this->urls->detalheProc, $data, 'POST', 3);
        
        $pattern = '#<table[^>]*?id=".*?processoEvento"[^>]*?\>\s*(?:<colgroup[^>]*?\></colgroup>\s*)?<thead.*?</thead>\s*(.*?)</table>#is';
        if (preg_match($pattern, $html, $match)) {
            return $match[0];
        }
        $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Não foi possível recuperar a pagina de movimentacoes");
    }

    private function requestPaginaPartes($result, $pagina, $polo) {
        $this->debug(__METHOD__);

        $data = $this->getPaginaPartesParams($result, $pagina, $polo);

        $html = $this->request($this->urls->detalheProc, $this->urls->base, $data, 'POST', 3);

        $pattern = '#processoPartesPolo(Ativo|Passivo)ResumidoList:.*?>(.*?)<\/table>#is';
        if (preg_match($pattern, $html)) {
            return $html;
        }
        $this->erro(__METHOD__, "ERRO_EXPRESSAO_REGULAR", "Não foi possível recuperar a pagina de partes");
    }

    protected function getPaginaPartesParams($result, $pagina, $polo) {
        $data = array(
            'AJAXREQUEST' => '_viewRoot',
            'AJAX:EVENTS_COUNT' => '1',
            'javax.faces.ViewState' => $this->_viewState,
        );
        if(preg_match("@richfaces\.datascroller\('(j_id\d+?:processoPartesPolo{$polo}.*?)'.*?submit\('(.*?)'@is", $result, $match)) {
            $data[$match[1]] = $pagina;
            $data[$match[2]] = $match[2];
            $data['ajaxSingle'] = $match[1];
        }
        return $data;
    }
}
?>

