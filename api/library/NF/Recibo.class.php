<?php
/**
 * MIT License
 * 
 * Copyright (c) 2016 MZ Desenvolvimento de Sistemas LTDA
 * 
 * @author Francimar Alves <mazinsw@gmail.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */
namespace NF;
use NF;
use Exception;
use DOMDocument;
use ValidationException;

class Recibo extends Retorno {

	const INFO_TAGNAME = 'infRec';

	private $numero;
	private $tempo_medio;
	private $codigo;
	private $mensagem;
	private $modelo;

	public function __construct($recibo = array()) {
		parent::__construct($recibo);
	}

	/**
	 * Número do Recibo
	 */
	public function getNumero($normalize = false) {
		if(!$normalize)
			return $this->numero;
		return $this->numero;
	}

	public function setNumero($numero) {
		$this->numero = $numero;
		return $this;
	}

	/**
	 * Tempo médio de resposta do serviço (em segundos) dos últimos 5 minutos
	 */
	public function getTempoMedio($normalize = false) {
		if(!$normalize)
			return $this->tempo_medio;
		return $this->tempo_medio;
	}

	public function setTempoMedio($tempo_medio) {
		$this->tempo_medio = $tempo_medio;
		return $this;
	}

	/**
	 * Código da Mensagem (v2.0) alterado para tamanho variavel 1-4.
	 * (NT2011/004)
	 */
	public function getCodigo($normalize = false) {
		if(!$normalize)
			return $this->codigo;
		return $this->codigo;
	}

	public function setCodigo($codigo) {
		$this->codigo = $codigo;
		return $this;
	}

	/**
	 * Mensagem da SEFAZ para o emissor. (v2.0)
	 */
	public function getMensagem($normalize = false) {
		if(!$normalize)
			return $this->mensagem;
		return $this->mensagem;
	}

	public function setMensagem($mensagem) {
		$this->mensagem = $mensagem;
		return $this;
	}

	public function getModelo($normalize = false) {
		if(!$normalize)
			return $this->modelo;
		return $this->modelo;
	}

	public function setModelo($modelo) {
		$this->modelo = $modelo;
		return $this;
	}

	public function toArray() {
		$recibo = parent::toArray();
		$recibo['numero'] = $this->getNumero();
		$recibo['tempo_medio'] = $this->getTempoMedio();
		$recibo['codigo'] = $this->getCodigo();
		$recibo['mensagem'] = $this->getMensagem();
		$recibo['modelo'] = $this->getModelo();
		return $recibo;
	}

	public function fromArray($recibo = array()) {
		if($recibo instanceof Recibo)
			$recibo = $recibo->toArray();
		else if(!is_array($recibo))
			return $this;
		parent::fromArray($recibo);
		$this->setNumero($recibo['numero']);
		$this->setTempoMedio($recibo['tempo_medio']);
		$this->setCodigo($recibo['codigo']);
		$this->setMensagem($recibo['mensagem']);
		$this->setModelo($recibo['modelo']);
		return $this;
	}

	public function envia($dom) {
		$envio = new Envio();
		$envio->setServico(Envio::SERVICO_RETORNO);
		$envio->setAmbiente($this->getAmbiente());
		$envio->setModelo($this->getModelo());
		$envio->setEmissao(NF::EMISSAO_NORMAL);
		$envio->setConteudo($dom);
		$resp = $envio->envia();
		$this->loadNode($resp);
		if(!$this->isProcessado())
			return $this;
		$protocolo = new Protocolo();
		$protocolo->loadNode($resp);
		return $protocolo;
	}

	public function consulta($nota = null) {
		if(!is_null($nota)) {
			$this->setAmbiente($nota->getAmbiente());
			$this->setModelo($nota->getModelo());
		}
		$dom = $this->getNode()->ownerDocument;
		$dom = $this->validar($dom);
		$retorno = $this->envia($dom);
		if($retorno->isAutorizado() && !is_null($nota))
			$nota->setProtocolo($retorno);
		return $retorno;
	}

	public function getNode($name = null) {
		$dom = new DOMDocument('1.0', 'UTF-8');
		$element = $dom->createElement(is_null($name)?'consReciNFe':$name);
		$element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', NF::PORTAL);
		$versao = $dom->createAttribute('versao');
		$versao->value = NF::VERSAO;
		$element->appendChild($versao);

		$element->appendChild($dom->createElement('tpAmb', $this->getAmbiente(true)));
		$element->appendChild($dom->createElement('nRec', $this->getNumero(true)));
		$dom->appendChild($element);
		return $element;
	}

	public function loadNode($element, $name = null) {
		$name = is_null($name)?'retConsReciNFe':$name;
		if($name == 'infRec') {
			$_fields = $element->getElementsByTagName($name);
			if($_fields->length == 0)
				throw new Exception('Tag "'.$name.'" não encontrada', 404);
			$element = $_fields->item(0);
		} else {
			$element = parent::loadNode($element, $name);
		}
		$_fields = $element->getElementsByTagName('nRec');
		if($_fields->length > 0)
			$numero = $_fields->item(0)->nodeValue;
		else
			throw new Exception('Tag "nRec" do campo "Numero" não encontrada', 404);
		$this->setNumero($numero);
		$_fields = $element->getElementsByTagName('tMed');
		$tempo_medio = null;
		if($_fields->length > 0)
			$tempo_medio = $_fields->item(0)->nodeValue;
		$this->setTempoMedio($tempo_medio);
		$_fields = $element->getElementsByTagName('cMsg');
		$codigo = null;
		if($_fields->length > 0)
			$codigo = $_fields->item(0)->nodeValue;
		$this->setCodigo($codigo);
		$_fields = $element->getElementsByTagName('xMsg');
		$mensagem = null;
		if($_fields->length > 0)
			$mensagem = $_fields->item(0)->nodeValue;
		$this->setMensagem($mensagem);
		return $element;
	}

	/**
	 * Valida o documento após assinar
	 */
	public function validar($dom) {
		$dom->loadXML($dom->saveXML());
		$xsd_path = dirname(dirname(dirname(__FILE__))) . '/schema';
		$xsd_file = $xsd_path . '/consReciNFe_v3.10.xsd';
		if(!file_exists($xsd_file))
			throw new Exception('O arquivo "'.$xsd_file.'" de esquema XSD não existe!', 404);
		// Enable user error handling
		$save = libxml_use_internal_errors(true);
		if ($dom->schemaValidate($xsd_file)) {
			libxml_use_internal_errors($save);
			return $dom;
		}
		$msg = array();
		$errors = libxml_get_errors();
		foreach ($errors as $error) {
			$msg[] = 'Não foi possível validar o XML: '.$error->message;
		}
		libxml_clear_errors();
		libxml_use_internal_errors($save);
		throw new ValidationException($msg);
	}

}