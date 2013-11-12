<?php namespace peer\http;

use io\streams\MemoryInputStream;


/**
 * Transport via curl functions
 *
 * @ext     curl
 * @see     xp://peer.http.HttpConnection
 */
class CurlHttpTransport extends HttpTransport {
  protected
    $handle = null;

  /**
   * Constructor
   *
   * @param   peer.URL url
   * @param   string arg
   */
  public function __construct(\peer\URL $url, $arg) {
    $this->handle= curl_init();
    curl_setopt($this->handle, CURLOPT_HEADER, 1);
    curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, 1); 
    curl_setopt($this->handle, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($this->handle, CURLOPT_SSL_VERIFYPEER, 0);
    if (1 === sscanf($arg, 'v%d', $version)) {
      curl_setopt($this->handle, CURLOPT_SSLVERSION, $version);
    }
  }

  /**
   * Sends a request
   *
   * @param   peer.http.HttpRequest request
   * @param   int timeout default 60
   * @param   float connecttimeout default 2.0
   * @return  peer.http.HttpResponse response object
   */
  public function send(\HttpRequest $request, $timeout= 60, $connecttimeout= 2.0) {
    $curl= curl_copy_handle($this->handle);
    curl_setopt($curl, CURLOPT_URL, $request->url->getCanonicalURL());
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request->getRequestString());
    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
    
    if ($this->proxy && !$this->proxy->isExcluded($request->getUrl())) {
      curl_setopt($curl, CURLOPT_PROXY, $this->proxy->host);
      curl_setopt($curl, CURLOPT_PROXYPORT, $this->proxy->port);
    }
    
    $response= curl_exec($curl);

    if (false === $response) {
      $errno= curl_errno($curl);
      $error= curl_error($curl);
      curl_close($curl);
      throw new \io\IOException(sprintf('%d: %s', $errno, $error));
    }
    // ensure handle is closed
    curl_close($curl);

    $this->cat && $this->cat->info('>>>', $request->getHeaderString());
    $response= new \HttpResponse(new MemoryInputStream($response));
    $this->cat && $this->cat->info('<<<', $response->getHeaderString());
    return $response;
  }
}
