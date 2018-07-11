<?php
use PHPUnit\Framework\TestCase;
require_once 'common.php';

class CommonConfigLoadTest extends TestCase{
    public function testLoad()
    {
        $config = load_config();

        $this->assertEquals('TestARN', $config['topic']);
    }
}

?>