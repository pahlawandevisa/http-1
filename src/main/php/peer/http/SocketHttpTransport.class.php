<?php
/* This class is part of the XP framework
 *
 * $Id$ 
 */

  uses('peer.http.HttpTransport', 'peer.Socket', 'peer.SocketInputStream');

  /**
   * Transport via sockets
   *
   * @see      xp://peer.Socket
   * @see      xp://peer.http.HttpConnection
   * @purpose  Transport
   */
  class SocketHttpTransport extends HttpTransport {
    protected
      $socket      = NULL,
      $proxySocket = NULL;

    /**
     * Constructor
     *
     * @param   peer.URL url
     * @param   string arg
     */
    public function __construct(URL $url, $arg) {
      $this->socket= $this->newSocket($url, $arg);
    }

    /**
     * Creates a socket
     *
     * @param   peer.URL url
     * @param   string arg
     * @return  peer.Socket
     */
    protected function newSocket(URL $url, $arg) {
      return new Socket($url->getHost(), $url->getPort(80));
    }

    /**
     * Set proxy
     *
     * @param   peer.http.HttpProxy proxy
     */
    public function setProxy(HttpProxy $proxy) {
      parent::setProxy($proxy);
      $this->proxySocket= $this->newSocket(create(new URL())->setHost($proxy->host)->setPort($proxy->port));
    }
    
    /**
     * Sends a request
     *
     * @param   peer.http.HttpRequest request
     * @param   int timeout default 60
     * @param   float connecttimeout default 2.0
     * @return  peer.http.HttpResponse response object
     */
    public function send(HttpRequest $request, $timeout= 60, $connecttimeout= 2.0) {

      // Use proxy socket and Modify target if a proxy is to be used for this request, 
      // a proxy wants "GET http://example.com/ HTTP/X.X"
      if ($this->proxy && !$this->proxy->isExcluded($url= $request->getUrl())) {
        $request->setTarget(sprintf(
          '%s://%s%s%s',
          $url->getScheme(),
          $url->getHost(),
          $url->getPort() ? ':'.$url->getPort() : '',
          $url->getPath('/')
        ));

        $s= $this->proxySocket;
      } else {
        $s= $this->socket;
      }
      
      // Socket still open from last request. This is the case when unread
      // data is left on the socket (by not reading the body, e.g.), so do
      // it the quick & dirty way: Close and reopen!
      $s->isConnected() && $s->close();
    
      $s->setTimeout($timeout);
      $s->connect($connecttimeout);
      $s->write($request->getRequestString());

      $this->cat && $this->cat->info('>>>', $request->getHeaderString());
      $response= new HttpResponse(new SocketInputStream($s));
      $this->cat && $this->cat->info('<<<', $response->getHeaderString());
      return $response;
    }
  }
?>
