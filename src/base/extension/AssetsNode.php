<?php

namespace PSFS\base\extension;
/**
 * Class AssetsNode
 * @package PSFS\base\extension
 */
class AssetsNode extends \Twig_Node{

    protected $hash;
    protected $type;

    /**
     * @param array $name
     * @param array $values
     * @param int $line
     * @param null $tag
     * @param string $type
     */
    public function __construct($name, $values, $line, $tag = null, $type = 'js')
    {
        parent::__construct(array('scripts' => $values["node"]), array('name' => $name), $line, $tag);
        $this->hash = $values["hash"];
        $this->type = $type;
    }

    public function compile(\Twig_Compiler $compiler)
    {
        $scripts = $this->getNode("scripts");

        //Creamos el parser
        $compiler->addDebugInfo($scripts)->write('$parser = new \\PSFS\\base\\extension\\AssetsParser(\'' . $this->type . '\')')
            ->raw(";\n");

        //Asociamos el hash
        $compiler->write('$parser->setHash(\''. $this->hash .'\')')
            ->raw(";\n");

        //Asociamos los ficheros
        foreach($scripts->getAttribute("value") as $value)
        {
            $compiler->write('$parser->addFile(\''. $value .'\')')->raw(";\n");
        }

        //Procesamos los ficheros
        $compiler->write('$parser->compile()')
            ->raw(";\n");

        //Imprimimos los tags
        $compiler->write('$parser->printHtml()')
            ->raw(";\n");

        //Damos oxigeno
        $compiler->raw("\n");
    }
}