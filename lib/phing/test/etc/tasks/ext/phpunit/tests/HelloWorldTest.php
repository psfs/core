<?php

    require_once "src/HelloWorld.php";

    /**
    * Test class for HelloWorld
    *
    * @author Michiel Rook
    * @version $Id: ac8a756da01d93b51844a4bde5c7a1ce4f3f4616 $
    * @package hello.world
    */
    class HelloWorldTest extends PHPUnit_Framework_TestCase
    {
        public function testSayHello()
        {
            $hello = new HelloWorld();
            $this->assertEquals("Hello World!", $hello->sayHello());
        }
    }

?>
