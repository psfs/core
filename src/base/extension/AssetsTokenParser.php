<?php

namespace PSFS\base\extension;

/**
 * Class AssetsTokenParser
 * @package PSFS\base\extension
 */
class AssetsTokenParser extends \Twig_TokenParser
{

    protected $type;
    private $values = array();
    private $end = false;

    /**
     * @param string $type
     */
    public function __construct($type = 'js')
    {
        $this->type = $type;
    }

    /**
     * Método que parsea los nodos de la plantilla
     * @param \Twig_Token $token
     * @return AssetsNode
     * @throws \Twig_Error_Syntax
     * @throws \Twig_Error_Loader
     */
    public function parse(\Twig_Token $token)
    {
        $hash = substr(md5($this->parser->getStream()->getSourceContext()->getPath()), 0, 8);
        $name = $token->getValue();
        $this->extractTemplateNodes();
        $node = $this->findTemplateNode();
        return new AssetsNode($name, array("node" => $node, "hash" => $hash), $token->getLine(), $this->getTag(), $this->type);
    }

    /**
     * Método que devuelve el tag a buscar en la plantilla
     * @return string
     */
    public function getTag()
    {
        switch ($this->type) {
            default:
            case 'js':
                $return = 'scripts';
                break;
            case 'css':
                $return = 'styles';
                break;
        }
        return $return;
    }

    /**
     * Método que revisa cada l�nea de la plantilla
     * @param \Twig_TokenStream $stream
     * @return \Twig_TokenStream
     */
    protected function checkTemplateLine(\Twig_TokenStream $stream)
    {
        $value = $stream->getCurrent();
        switch ($value->getType()) {
            case \Twig_Token::STRING_TYPE:
                $this->values[] = $this->parser->getExpressionParser()->parseExpression();
                break;
            case \Twig_Token::BLOCK_END_TYPE:
                $this->end = true;
                $stream->next();
                break;
            default:
                $stream->next();
                break;
        }
        return $stream;
    }

    /**
     * Método que procesa cada l�nea de la plantilla para extraer los nodos
     * @throws \Twig_Error_Syntax
     */
    protected function extractTemplateNodes()
    {
        $stream = $this->parser->getStream();
        while (!$this->end) {
            $stream = $this->checkTemplateLine($stream);
        }

        $stream->expect(\Twig_Token::BLOCK_START_TYPE);
        $stream->expect(\Twig_Token::NAME_TYPE);
        $stream->expect(\Twig_Token::BLOCK_END_TYPE);
    }

    /**
     * Método que busca el nodo a parsear
     * @return \Twig_Node_Expression|null
     */
    protected function findTemplateNode()
    {
        $node = null;
        if (0 < count($this->values)) {
            /** @var \Twig_Node_Expression|\Twig_Node_Expression_Conditional $value */
            foreach ($this->values as $value) {
                list($tmp, $node) = $this->extractTmpAttribute($node, $value);
                $node->setAttribute("value", $tmp);
            }
        }
        return $node;
    }

    /**
     * Método que extrae el valor del token
     * @param \Twig_Node_Expression|\Twig_Node_Expression_Conditional|null $node
     *
     * @return array
     */
    protected function getTmpAttribute($node = null)
    {
        $tmp = $node->getAttribute("value");
        if (!is_array($tmp)) {
            $tmp = array($tmp);
        }

        return $tmp;
    }

    /**
     * Método
     * @param \Twig_Node_Expression|\Twig_Node_Expression_Conditional|null $node
     * @param \Twig_Node_Expression|\Twig_Node_Expression_Conditional|null $value
     *
     * @return array
     */
    protected function extractTmpAttribute($node = null, $value = null)
    {
        $tmp = array();
        if (NULL === $node) {
            $node = $value;
        } else {
            $tmp = $this->getTmpAttribute($node);
        }
        $tmp[] = $value->getAttribute("value");

        return array($tmp, $node);
    }
}
