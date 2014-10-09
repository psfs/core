<?php

namespace PSFS\base\extension;

/**
 * Class AssetsTokenParser
 * @package PSFS\base\extension
 */
class AssetsTokenParser extends \Twig_TokenParser{

    protected $type;

    /**
     * @param string $type
     * @return $this
     */
    public function __construct($type = 'js')
    {
        $this->type = $type;
    }

    public function parse(\Twig_Token $token)
    {
        $parser = $this->parser;
        $env = $parser->getEnvironment();
        $path = $env->getLoader()->getCacheKey($parser->getFilename());
        $hash = substr(md5($path), 0, 8);
        $stream = $parser->getStream();
        $values = array();
        $end = false;

        $name = $token->getValue();

        while(!$end)
        {
            $value = $stream->getCurrent();
            switch($value->getType())
            {
                case \Twig_Token::STRING_TYPE: $values[] = $parser->getExpressionParser()->parseExpression(); break;
                case \Twig_Token::BLOCK_END_TYPE: $end = true;
                default: $stream->next(); break;
            }
        }

        $stream->expect(\Twig_Token::BLOCK_START_TYPE);
        $stream->expect(\Twig_Token::NAME_TYPE);
        $stream->expect(\Twig_Token::BLOCK_END_TYPE);
        $node = null;

        if(!empty($values)) foreach($values as $value)
        {
            $tmp = array();
            if(empty($node)) $node = $value;
            else{
                $tmp = $node->getAttribute("value");
                if(!is_array($tmp)) $tmp = array($tmp);
            }
            $tmp[] = $value->getAttribute("value");
            $node->setAttribute("value", $tmp);
        }

        return new AssetsNode($name, array("node" => $node, "hash" => $hash), $token->getLine(), $this->getTag(), $this->type);
    }

    public function getTag()
    {
        switch($this->type)
        {
            default:
            case 'js': $return = 'scripts'; break;
            case 'css': $return = 'styles'; break;
        }
        return $return;
    }
}