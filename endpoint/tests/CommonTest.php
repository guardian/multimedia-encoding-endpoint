<?php
use PHPUnit\Framework\TestCase;
require_once 'common.php';

class CommonTest extends TestCase{
    public function testDodgyM3u8()
    {
        $result = has_dodgy_m3u8_format('video/mp4');
        $this->assertNull($result);
        
        $result = has_dodgy_m3u8_format('video/160434testVideo.m3u8');
        $this->assertArraySubset(['format'=>'video/m3u8', 'filename'=>'160434testVideo.m3u8'],$result);
        
        $result = has_dodgy_m3u8_format('video/160434testVideo.mP4');
        $this->assertNull($result);
    }
    
    /**
     * @runInSeparateProcess
     */
    public function testFindContentNoConfig()
    {
        $this->expectException(ContentErrorException);
        $result = find_content([]);
    }
}

?>