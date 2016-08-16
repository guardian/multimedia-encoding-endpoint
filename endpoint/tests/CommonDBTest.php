<?php
use PHPUnit\Framework\TestCase;
require_once 'common.php';

class CommonDBTest extends PHPUnit_Extensions_Database_TestCase
{
    /**
     * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
     */
    public function getConnection()
    {
        $pdo = new PDO($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD'] );
        return $this->createDefaultDBConnection($pdo, ':memory:');
    }

    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet()
    {
        return $this->createFlatXMLDataSet(dirname(__FILE__).'/_files/encodings_seed.xml');
    }
    
    /**
     * @runInSeparateProcess
     * @dataProvider findContentProvider
     */
    public function testFindContent($octopusid, $format, $maxbitrate, $allow_insecure, $expected)
    {
        $test_config = [
            'dbhost' => [
                '127.0.0.1'
            ],
            'dbuser' => $GLOBALS['DB_USER'],
            'dbpass' => $GLOBALS['DB_PASSWD'],
            'dbname' => $GLOBALS['DB_DBNAME'],
            'memcache_host' => $GLOBALS['MEMCACHE_HOST'],
            'memcache_port' => $GLOBALS['MEMCACHE_PORT']
          ];
        
        $_GET['octopusid'] = $octopusid;
        $_GET['format'] = $format;
        $_GET['maxbitrate'] = $maxbitrate;
        if($allow_insecure) $_GET['allow_insecure'] = "true";
                $qstr="";
        foreach($_GET as $key=>$value){
            $qstr.="$key=$value&";
        }
        rtrim($qstr,"&");
        
        $_SERVER['REQUEST_URI'] = 'https://localhost/interactivevids/video.php?'.$qstr;
        
        $result = find_content($test_config);
        
        //print_r($result);
        if(! $expected){
            $this->assertNull($result);
        } else {
            $this->assertEquals($result['url'],$expected);
        }
    }
    
    public function findContentProvider()
    {
        return [
            ['123456','video/mp4',4096,false,"https://test.cdn.com/video/first_test_file_4096k.mp4"],
            ['123456','video/mp4',4096,true,"http://test.cdn.com/video/first_test_file_4096k.mp4"],
            ['123456','video/mp4',6048,false,"https://test.cdn.com/video/first_test_file_4096k.mp4"],
            ['123456','video/mp4',3712,false,"https://test.cdn.com/video/first_test_file_2048k.mp4"],
            ['123456','video/mp4',2048,false,"https://test.cdn.com/video/first_test_file_2048k.mp4"],
            ['123456','video/mp4',1200,false,"https://test.cdn.com/video/first_test_file_640k.mp4"],
            ['123456','video/mp4',800,false,"https://test.cdn.com/video/first_test_file_640k.mp4"],
            ['123456','video/mp4',400,false,"https://test.cdn.com/video/first_test_file_320k.mp4"],
            ['123456','video/mp4',120,false,NULL],
            
            ['123456','video/webm',4096,false,"https://test.cdn.com/video/first_test_file_4096k.webm"],
            ['123456','video/webm',4096,true,"http://test.cdn.com/video/first_test_file_4096k.webm"],
            ['123456','video/webm',6048,false,"https://test.cdn.com/video/first_test_file_4096k.webm"],
            ['123456','video/webm',3712,false,"https://test.cdn.com/video/first_test_file_2048k.webm"],
            ['123456','video/webm',2048,false,"https://test.cdn.com/video/first_test_file_2048k.webm"],
            ['123456','video/webm',1200,false,"https://test.cdn.com/video/first_test_file_640k.webm"],
            ['123456','video/webm',800,false,"https://test.cdn.com/video/first_test_file_640k.webm"],
            ['123456','video/webm',400,false,"https://test.cdn.com/video/first_test_file_320k.webm"],
            ['123456','video/webm',120,false,NULL],
            
            ['123456','video/m3u8',4096,false,"https://test.cdn.com/video/first_test_file_4096k.m3u8"],
            ['123456','video/m3u8',4096,true,"http://test.cdn.com/video/first_test_file_4096k.m3u8"],
            ['123456','video/m3u8',6048,false,"https://test.cdn.com/video/first_test_file_4096k.m3u8"],
            ['123456','video/m3u8',3712,false,"https://test.cdn.com/video/first_test_file_2048k.m3u8"],
            ['123456','video/m3u8',2048,false,"https://test.cdn.com/video/first_test_file_2048k.m3u8"],
            ['123456','video/m3u8',1200,false,"https://test.cdn.com/video/first_test_file_640k.m3u8"],
            ['123456','video/m3u8',800,false,"https://test.cdn.com/video/first_test_file_640k.m3u8"],
            ['123456','video/m3u8',400,false,"https://test.cdn.com/video/first_test_file_320k.m3u8"],
            ['123456','video/m3u8',120,false,NULL],
            
            ['123456','video/webm',4096,false,"https://test.cdn.com/video/first_test_file_4096k.webm"],
            ['123456','video/webm',4096,true,"http://test.cdn.com/video/first_test_file_4096k.webm"],
            ['123456','video/webm',6048,false,"https://test.cdn.com/video/first_test_file_4096k.webm"],
            ['123456','video/webm',3712,false,"https://test.cdn.com/video/first_test_file_2048k.webm"],
            ['123456','video/webm',2048,false,"https://test.cdn.com/video/first_test_file_2048k.webm"],
            ['123456','video/webm',1200,false,"https://test.cdn.com/video/first_test_file_640k.webm"],
            ['123456','video/webm',800,false,"https://test.cdn.com/video/first_test_file_640k.webm"],
            ['123456','video/webm',400,false,"https://test.cdn.com/video/first_test_file_320k.webm"],
            ['123456','video/webm',120,false,NULL],
            
            ['90123','video/m3u8',4096,false,"https://test.cdn.com/video/third_test_file_4096k.m3u8"],
            ['90123','video/m3u8',4096,true,"http://test.cdn.com/video/third_test_file_4096k.m3u8"],
            ['90123','video/m3u8',6048,false,"https://test.cdn.com/video/third_test_file_4096k.m3u8"],
            ['90123','video/m3u8',3712,false,"https://test.cdn.com/video/third_test_file_2048k.m3u8"],
            ['90123','video/m3u8',2048,false,"https://test.cdn.com/video/third_test_file_2048k.m3u8"],
            ['90123','video/m3u8',1200,false,"https://test.cdn.com/video/third_test_file_640k.m3u8"],
            ['90123','video/m3u8',800,false,"https://test.cdn.com/video/third_test_file_640k.m3u8"],
            ['90123','video/m3u8',400,false,"https://test.cdn.com/video/third_test_file_320k.m3u8"],
            ['90123','video/m3u8',120,false,NULL],            
            
            
        ];
    }
}
?>