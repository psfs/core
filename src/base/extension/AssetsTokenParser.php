<?php

namespace PSFS\base\extension;

use Twig\Node\Expression\ConstantExpression;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TokenStream;

/**
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
     * @param Token $token
     * @return AssetsNode|\Twig\Node\Node
     * @throws \Twig\Error\SyntaxError
     */
    public function parse(Token $token)
    {
        // Reset parser state on every block parse.
        $this->values = [];
        $this->end = false;

        $path = $this->parser->getStream()->getSourceContext()->getPath();
        $hash = $this->buildHash($path, $token->getLine());
        $name = $token->getValue();
        $this->extractTemplateNodes();
        $node = $this->findTemplateNode();
        return new AssetsNode($name, array('node' => $node, 'hash' => $hash), $token->getLine(), $this->type);
    }

    /**
     * Build a stable but block-unique hash to avoid collisions between multiple
     * assets tags in the same template file.
     */
    protected function buildHash(string $path, int $line): string
    {
        return substr(md5($path . '#' . $this->type . '#' . $line), 0, 8);
    }

    /**
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
     * @param TokenStream $stream
     * @return TokenStream
     * @throws \Twig\Error\SyntaxError
     */
    protected function checkTemplateLine(TokenStream $stream)
    {
        $value = $stream->getCurrent();
        if ($value->test(Token::STRING_TYPE)) {
            $this->values[] = $this->parser->parseExpression();
        } elseif ($value->test(Token::BLOCK_END_TYPE)) {
            $this->end = true;
            $stream->next();
        } else {
            $stream->next();
        }
        return $stream;
    }

    /**
     * @throws \Twig\Error\SyntaxError
     */
    protected function extractTemplateNodes()
    {
        $stream = $this->parser->getStream();
        while (!$this->end) {
            $stream = $this->checkTemplateLine($stream);
        }

        $stream->expect(Token::BLOCK_START_TYPE);
        // Accept both historical singular and plural closing tags.
        // scripts => endscript / endscripts
        // styles  => endstyle  / endstyles
        $closingTag = $stream->expect(Token::NAME_TYPE)->getValue();
        $expectedPlural = 'end' . $this->getTag();
        $expectedSingular = $this->getTag() === 'scripts' ? 'endscript' : 'endstyle';
        if ($closingTag !== $expectedPlural && $closingTag !== $expectedSingular) {
            // Keep parser permissive for legacy templates using custom closings.
        }
        $stream->expect(Token::BLOCK_END_TYPE);
    }

    /**
     * @return mixed|null
     */
    protected function findTemplateNode()
    {
        $node = null;
        if (!empty($this->values)) {
            $node = $this->values[0];
            $assets = [];
            foreach ($this->values as $value) {
                $assets[] = $value->getAttribute('value');
            }
            $node->setAttribute('value', $assets);
        }
        return $node;
    }

    /**
     * @param ConstantExpression $node
     * @return array
     */
    protected function getTmpAttribute($node = null)
    {
        $tmp = [];
        if (null !== $node) {
            $tmp = $node->getAttribute('value');
            if (!is_array($tmp)) {
                $tmp = [$tmp];
            }
        }

        return $tmp;
    }

    /**
     * @param $node
     * @param $value
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
        if (null !== $node) {
            $tmp[] = $value->getAttribute('value');
        }
        return [$tmp, $node];
    }
}
