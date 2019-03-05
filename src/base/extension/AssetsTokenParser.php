<?php

namespace PSFS\base\extension;

use Twig\TokenParser\AbstractTokenParser;

/**
 * Class AssetsTokenParser
 * @package PSFS\base\extension
 */
class AssetsTokenParser extends AbstractTokenParser
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
     * @param \Twig_Token $token
     * @return AssetsNode|\Twig_Node
     * @throws \Twig_Error_Syntax
     */
    public function parse(\Twig_Token $token)
    {
        $hash = substr(md5($this->parser->getStream()->getSourceContext()->getPath()), 0, 8);
        $name = $token->getValue();
        $this->extractTemplateNodes();
        $node = $this->findTemplateNode();
        return new AssetsNode($name, array('node' => $node, 'hash' => $hash), $token->getLine(), $this->getTag(), $this->type);
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
     * @param \Twig_TokenStream $stream
     * @return \Twig_TokenStream
     * @throws \Twig_Error_Syntax
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
     * @return mixed|null
     */
    protected function findTemplateNode()
    {
        $node = null;
        if (0 < count($this->values)) {
            /** @var \Twig_Node_Expression|\Twig_Node_Expression_Conditional $value */
            foreach ($this->values as $value) {
                list($tmp, $node) = $this->extractTmpAttribute($node, $value);
                $node->setAttribute('value', $tmp);
            }
        }
        return $node;
    }

    /**
     * @param null $node
     * @return array
     */
    protected function getTmpAttribute($node = null)
    {
        $tmp = [];
        if(null !== $node) {
            $tmp = $node->getAttribute('value');
            if (!is_array($tmp)) {
                $tmp = [$tmp];
            }
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
        $tmp = [];
        if (null === $node) {
            $node = $value;
        } else {
            $tmp = $this->getTmpAttribute($node);
        }
        if(null !== $node) {
            $tmp[] = $value->getAttribute('value');
        }
        return [$tmp, $node];
    }
}
