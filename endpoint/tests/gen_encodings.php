<?php
$output_file = "tests/_files/encodings_seed.xml";

$url_base = "http://test.cdn.com/video/";

$files = [
  'first_test_file' => [
                        'contentid'=>2,
                        'duration'=>543,
                        'octopus_id'=>123456,
                        'project'=>'KP-234',
                        'fcs_id'=>'KP-1234_1',
                        'lastupdate'=>'2014-01-02 15:38:06',
                        'aspect'=>'16x9',
                        'filebase'=>'first_test_file'
                       ],
  'second_test_file'=>[
                        'contentid'=>3,
                        'duration'=>1024,
                        'octopus_id'=>45678,
                        'project'=>'KP-432',
                        'fcs_id'=>'KP-456_1',
                        'lastupdate'=>'2014-01-02 15:38:06',
                        'aspect'=>'16x9',
                        'filebase'=>'second_test_file'
                       ],
  'third_test_file'=>[
                        'contentid'=>4,
                        'duration'=>232,
                        'octopus_id'=>90123,
                        'project'=>'KP-891',
                        'fcs_id'=>'KP-789_2',
                        'lastupdate'=>'2014-01-02 15:38:06',
                        'aspect'=>'16x9',
                        'filebase'=>'third_test_file'
                       ]
];

$formats = [
  'video/mp4'=>[
                'mobile'=>'1',
                'multirate'=>'0',
                'vcodec'=>'h264',
                'acodec'=>'aac',
                'extension'=>'.mp4'
               ],
  'video/m3u8'=>[
                'mobile'=>'1',
                'multirate'=>'1',
                'vcodec'=>'h264',
                'acodec'=>'aac',
                'extension'=>'.m3u8'
               ],
  'video/webm'=>[
                'mobile'=>'0',
                'multirate'=>'0',
                'vcodec'=>'On2 VP8',
                'acodec'=>'Ogg Vorbis',
                'extension'=>'.webm'
               ]
];

$params = [
  '4096k' => [
    'vbitrate' => '4096',
    'abitrate' => '192',
    'frame_width' => '1920',
    'frame_height' => '1080',
    'file_size' => 2347624
  ],
  '2048k' => [
    'vbitrate' => '2048',
    'abitrate' => '128',
    'frame_width' => '1280',
    'frame_height' => '720',
    'file_size' => 123263
  ],
  '640k' => [
    'vbitrate' => '640',
    'abitrate' => '128',
    'frame_width' => '640',
    'frame_height' => '360',
    'file_size' => 12342
  ],
  '320k' => [
    'vbitrate' => '288',
    'abitrate' => '64',
    'frame_width' => '320',
    'frame_height' => '180',
    'file_size' => 232462
  ]
];

$fh = fopen($output_file,"w");

fwrite($fh,'<?xml version="1.0" encoding="UTF-8"?>

<dataset>
  <mime_equivalents id="1" real_name="application/x-mpegURL" mime_equivalent="video/m3u8" />
  <mime_equivalents id="2" real_name="audio/mpeg" mime_equivalent="audio/mp3" />
  <mime_equivalents id="3" real_name="video/3gpp" mime_equivalent="video/3gp" />
');

/*
 *<idmapping contentid="2" filebase="first_test_file" project="KP-1234" lastupdate="2014-01-02 15:38:06" octopus_id="123456"/>
 */
foreach($files as $name=>$file_props){
  fwrite($fh,"\t<idmapping ");
  foreach($file_props as $key=>$value){
    if($key=="duration") continue;
    if($key=="aspect") continue;
    if($key=="fcs_id") continue;
    
    fwrite($fh,"$key=\"$value\" ");
  }
  fwrite($fh,"/>\n");
}
/*
 *
 *   `encodingid` int(11) NOT NULL AUTO_INCREMENT,
  `contentid` int(11) NOT NULL,
  `url` text NOT NULL,
  `format` varchar(254) CHARACTER SET ascii NOT NULL,
  `mobile` tinyint(1) NOT NULL,
  `multirate` tinyint(1) NOT NULL,
  `vcodec` text,
  `acodec` text,
  `vbitrate` int(11) DEFAULT NULL,
  `abitrate` int(11) DEFAULT NULL,
  `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `frame_width` int(11) NOT NULL,
  `frame_height` int(11) NOT NULL,
  `duration` float NOT NULL,
  `file_size` int(11) NOT NULL,
  `fcs_id` varchar(64) NOT NULL,
  `octopus_id` int(11) NOT NULL,
  `aspect` text NOT NULL,
  */

$n=10;
foreach($files as $name=>$file_props){
  foreach($params as $file_subtype=>$param){
    foreach($formats as $format=>$format_props){
      $record = [
        'encodingid'=>$n,
        'contentid'=>$file_props['contentid'],
        'url'=>$url_base.$name."_".$file_subtype.$format_props['extension'],
        'format'=>$format,
        'mobile'=>$format_props['mobile'],
        'multirate'=>$format_props['multirate'],
        'vcodec'=>$format_props['vcodec'],
        'acodec'=>$format_props['acodec'],
        'lastupdate'=>$file_props['lastupdate'],
        'frame_width'=>$param['frame_width'],
        'frame_height'=>$param['frame_height'],
        'duration'=>$file_props['duration'],
        'file_size'=>$param['file_size'],
        'fcs_id'=>$file_props['fcs_id'],
        'octopus_id'=>$file_props['octopus_id'],
        'aspect'=>$file_props['aspect'],
        'vbitrate'=>$param['vbitrate'],
        'abitrate'=>$param['abitrate']
      ];
      
      fwrite($fh,"\t<encodings ");
      
      foreach($record as $key=>$value){
        fwrite($fh,"$key=\"$value\" ");
      }
      fwrite($fh,"/>\n");
      /*
      fwrite($fh,"
        <encodings encodingid=".$n."
                   contentid=".$file_props['contentid']."
                   url=".$url_base.$name."_".$file_subtype."
                   format=".$format."
                   mobile=".$file_props['mobile']."
                   multirate=".$file_props['multirate']."
                   vcodec=\"h264
      ";*/
      ++$n;
    }
  }
}

fwrite($fh,"</dataset>");
?>
