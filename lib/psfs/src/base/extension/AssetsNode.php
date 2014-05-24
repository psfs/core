<?php

namespace PSFS\base\extension;

class AssetsNode extends \Twig_Node{

    protected $hash;
    protected $type;

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
        $compiler->addDebugInfo($scripts)->write('$jsParser = new \\PSFS\\base\\extension\\AssetsParser(\'' . $this->type . '\')')
            ->raw(";\n");

        //Asociamos el hash
        $compiler->write('$jsParser->setHash(\''. $this->hash .'\')')
            ->raw(";\n");

        //Asociamos los ficheros
        foreach($scripts->getAttribute("value") as $value)
            $compiler->write('$jsParser->addFile(\''. $value .'\')')->raw(";\n");

        //Procesamos los ficheros
        $compiler->write('$jsParser->compile()')
            ->raw(";\n");

        //Imprimimos los tags
        $compiler->write('$jsParser->printHtml()')
            ->raw(";\n");

        //Damos oxigeno
        $compiler->raw("\n");
    }
}