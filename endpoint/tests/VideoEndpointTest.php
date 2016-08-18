<?php
use PHPUnit\Framework\TestCase;

class VideoEndpointTest extends TestCase{
    public function testEndpoint()
    {
        // create our http client (Guzzle)
        $client = new GuzzleHttp\Client([ 'base_uri'=>'http://localhost:8080',
            'request.options' => array(
                'exceptions' => false,
            ),
            'redirect.disable'=>true
            ]
        );
    
        $response = $client->get('/video.php?octopusid=45678&format=video/webm&maxbitrate=4096', array('allow_redirects' => false, 'http_errors'=>false));

        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Location'));
        $this->assertEquals('https://test.cdn.com/video/second_test_file_4096k.webm',$response->getHeader('Location')[0]);
    }
}

?>
