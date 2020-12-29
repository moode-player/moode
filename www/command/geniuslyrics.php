<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
$ClientAccessToken = '<<<GENIUS.COM --- CLIENT ACCESS TOKEN>>>';
$ARTIST_=shell_exec('mpc --format %artist% | head -n 1');
$ARTIST_=str_replace("\n","",$ARTIST_);
$ARTIST=str_replace(" ","+",$ARTIST_);

$TITLE_=shell_exec('mpc --format %title% | head -n 1');
$TITLE_=str_replace("\n","",$TITLE_);
$TITLE=str_replace(" ","+",$TITLE_);

function http_request($url, $opt=array()) {
/**
 * Makes a remote HTTP request and returns the response.
 *
 * @author    Allen Fair <allen.fair@gmail.com>
 * @copyright 2018 Allen Fair
 * @license   https://opensource.org/licenses/MIT  MIT
 * @code      https://gist.github.com/afair/a7c7adc52b7b49bf362935e665a87633
 *
 * @param string  $url     The full URL endpoint: "https://example.com/endpoint"
 * @param array   $opt     An Associative Array of extended request options
 *
 *        string  'method'       one of: GET, POST, PUT, PATCH, DELETE, HEAD
 *        string  'url'          URL override
 *        string  'endpoint'     Optional endpoint appended to URL
 *        string  'user'         user name to send for HTTP Basic Authorization
 *        string  'token'        creates an "Authorization: Bearer" header
 *        string  'accept'       creates an Accept: header for the mime/type
 *        string  'password'     password to send for HTTP Basic Authorization
 *        array   'headers'      array of "Header-Name: value", ...
 *        array   'query'        name=>value pairs for the Query String
 *        array   'post'         name=>value pairs for the POST Data body
 *        array   'files'        name=>['path'=>"/path/file", 'filename'=>"name.ext",
 *                                     'type'=>"mime1/mime2"] pairs of files to send
 *        array   'cookies'      name=value pairs for cookies to send
 *        array   'http_options' name=value pairs for "HTTP context options"
 *        callable 'notifications' a stream_notification_callback for events on the stream.
 *
 * @return array  [$http_status_code, $headers_hash, $body]
 *
 * Note: sending very large files/requests must fit into memory. Chunking is not supported.
 *
 * @example
 * list($status, $headers, $content) = http_request('https://www.example.com/endpoint', array(
 *   'method'       => 'POST',
 *   'headers'      => array('From'=>'pat@mycompany.com'),
 *   'accept'       => 'application/json',
 *   'user'         => $apikey, 'password'=>'', 'token'=>$token, // Authentication
 *   'query'        => array('q' => 'PHP HTTP request'),
 *   'post'         => array('email' => 'pat@mycompany.com'),
 *   'files'        => array('path'=> './avatar.png', 'filename'=>'avatar.png', 'type'=>'image/png'),
 *   'cookies'      => array('session' => $jwt),
 *   'http_options' => array('timeout'=> 5.0, 'user_agent'=>"php_request/1.0"),
 *   ));
 */
  $http_options = array_key_exists('http_options', $opt) ? $opt['http_options'] : array();
  $headers      = array_key_exists('headers', $opt) ? $opt['headers'] : array();
  $url          = array_key_exists('url', $opt) ? $opt['url'] : $url;
  $endpoint     = array_key_exists('endpoint', $opt) ? $opt['endpoint'] : null;
  $method       = array_key_exists('method', $opt) ? strtoupper($opt['method']) : 'GET';
  $query        = array_key_exists('query', $opt) ? $opt['query'] : null;
  $post         = array_key_exists('post', $opt) ? $opt['post'] : null;
  $files        = array_key_exists('files', $opt) ? $opt['files'] : null;
  $notifications= array_key_exists('notifications', $opt) ? $opt['notifications'] : null;

  // Headers
  if (array_key_exists('accept', $opt)) {
    $headers[] = "Accept: " . $opt['accept'];
  }
  if (array_key_exists('user', $opt)) {
    $headers[] = "Authorization: Basic ".base64_encode($opt['user'].':'.$opt['password']);
  }
  elseif (array_key_exists('token', $opt)) {
    $headers[] = "Authorization: Bearer ".$opt['token'];
  }
  if (array_key_exists('cookies', $opt)) {
    foreach ($opt['cookies'] as $n=>$v) $headers[] = "Cookie: $n=$v";
  }

  // URL, appended Endpoint, and Query Parameters
  if ($endpoint) $url .= $endpoint;
  if (!empty($query)) {
    $url .= (strpos("?",$url)===false ? "?" : "&") . http_build_query($query);
  }
  // File + Post Parameters in multipart format
  if (!empty($files)) {
    $boundary = '--------------------------'.microtime(true);
    $content = array();
    foreach ($files as $field=>$f) {
      $content[] ="--$boundary";
      $content[] = "Content-Disposition: form-data; name=\"$field\"; filename=\"".basename($filename)."\"";
      $content[] = "Content-Type: {$f['type']}";
      $content[] = "Content-Length: " . filesize($f['path']);
      $content[] = "";
      $content[] = file_get_contents($f['path']);
      $content[] = "";
    }
    foreach ($post as $field=>$v) {
      $content[] ="--$boundary";
      $content[] = "Content-Disposition: form-data; name=\"$field\"";
      $content[] = "";
      $content[] = $v;
      $content[] = "";
    }
    $content[] = "--$boundary--";
    $headers[] = "Content-Type: multipart/form-data; boundary=$boundary";
    $http_options['content'] = implode("\r\n", $content);
    unset($content);
  }
  // Post Parameters in urlencoded format
  elseif (!empty($post)) {
    $headers[] = "Content-Type: application/x-www-form-urlencoded";
    $http_options['content'] = http_build_query($post);
  }

  // Make Request
  if (!$http_options['user_agent']) $http_options['user_agent'] = "http_request.php/1.0";
  $http_options['method'] = $method;
  if ($http_options['content']) $headers[] = "Content-Length: ".strlen($http_options['content']);
  if (!empty($headers)) {
    $http_options['header'] = implode("\r\n", $headers);
  }
  $context = stream_context_create(array('http'=>$http_options));
  if ($notifications) {
    stream_context_set_params($context, array("notification" => $notifications));
  }
  if ($method == 'HEAD') {
    $headlist = @get_headers($url, 0, $context);
    $response = null;
  } else {
    $response = @file_get_contents($url, false, $context);
    $headlist = $http_response_header;
  }

  // Format Response
  $status = preg_match("/(\d\d\d) (.+)/", $headlist[0], $m) ? $m[1] : 500;
  $headers = array("Status" => $headlist[0]);
  foreach ($headlist as $h) {
    if (preg_match("/^([\w\-]+):\s*(.+)/", $h, $m)) $headers[$m[1]] = $m[2];
  }
  return array($status, $headers, $response);
}

if($TITLE!="" && $ARTIST!=""){
  $result = (http_request('https://api.genius.com/search', array(
   'method'       => 'GET',
   'accept'       => 'application/json',
   'token'         => $ClientAccessToken, // Authentication
   'query'        => array('q' => $TITLE.' '.$ARTIST),
   'http_options' => array('timeout'=> 5.0, 'user_agent'=>"php_request/1.0"),
   )));

  $json = json_decode($result[2], TRUE);
  // print_r($json);
  $val = $json["response"]["hits"];
  // print_r($val);
  $song_api_path="";
  $song_url="";
  foreach($val as $hit){
    if(stripos ($hit["result"]["primary_artist"]["name"],$ARTIST_)!==False){
        //print('found' . chr(10));
        $artistFound=$hit["result"]["primary_artist"]["name"];
        $songFound=$hit["result"]["title"];
        $song_api_path = $hit["result"]["api_path"];
        $song_url=$hit["result"]["url"];
        break;
    };
  };
  if($song_url != ""){
    $songlyrics="";
    $found=false;
    $i=0;
    while(!$found){
      $file = file_get_contents($song_url);
//      file_put_contents ('tmp',$file);
      $file =str_replace("<br>","",$file);
      $dom = new DOMDocument;
      $dom->loadHTML($file);
      $xpath = new DOMXPath($dom);
      foreach( $xpath->query('//div[@class="lyrics"]') as $e ) {
         $songlyrics=$e->nodeValue;
         $found=true;
      };
      $i++;
      if($i>10){break;};
    };
    if($songlyrics!=""){
      $songlyrics=nl2br($songlyrics);
//      $songlyrics=str_replace("]","]<br />",$songlyrics);
//      $songlyrics=str_replace("[","<br />[",$songlyrics);
      echo $artistFound.' - '.$songFound;
      echo $songlyrics;
    }
    else{
      echo "cannot parse the lyricspage<br />";
      echo "<a target='_blank' href='".$song_url."'>".$song_url."</a>";
    };
  }
  else{echo "no lyrics found..." . chr(10);};

//
}
else{echo "please start playback..." . chr(10);}

?>
