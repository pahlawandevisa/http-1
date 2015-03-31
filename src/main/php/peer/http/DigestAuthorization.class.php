<?php namespace peer\http;

use peer\Header;
use security\SecureString;
use lang\IllegalStateException;
use lang\MethodNotImplementedException;

/**
 * Digest Authorization header
 *
 * <quote>
 * "HTTP/1.0", includes the specification for a Basic Access
 * Authentication scheme. This scheme is not considered to be a secure
 * method of user authentication (unless used in conjunction with some
 * external secure system such as SSL), as the user name and
 * password are passed over the network as cleartext.
 * </quote>
 *
 * @see  rfc://2617
 * @see  https://en.wikipedia.org/wiki/Digest_access_authentication
 */
class DigestAuthorization extends Header {

  /** server values */
  private $realm;
  private $qop;
  private $nonce;
  private $opaque;

  /** client credentials */
  private $username;
  private $password;

  /** Internal state */
  private $counter= 1;
  private $cnonce;

  /**
   * Constructor
   *
   * @param string $realm
   * @param string $qop
   * @param string $nonce
   * @param string $opaque
   */
  public function __construct($realm, $qop, $nonce, $opaque) {
    parent::__construct('Authorization', 'Digest');

    $this->realm= $realm;
    $this->qop= $qop;
    $this->nonce= $nonce;
    $this->opaque= $opaque;

    $this->cnonce();
  }

  /**
   * Read digest realm and accompanying data from HTTP response
   * and construct an instance of this class.
   *
   * @param  string $header
   * @param  string $user
   * @param  security.SecureString $pass
   * @return peer.http.DigestAuthorization
   */
  public static function fromChallenge($header, $user, SecureString $pass) {
    if (!preg_match_all('#(([a-z]+)=("[^"$]+)")#m', $header, $matches, PREG_SET_ORDER)) {
      throw new IllegalStateException('Invalid WWW-Authenticate line');
    }

    $values= ['algorithm' => 'md5'];
    foreach ($matches as $m) {
      $values[$m[2]]= trim($m[3], '"');
    }

    if ($values['algorithm'] != 'md5') {
      throw new MethodNotImplementedException('Digest auth only supported via algo "md5".', 'digest-md5');
    }

    $auth= new self(
      $values['realm'],
      $values['qop'],
      $values['nonce'],
      $values['opaque']
    );
    $auth->username($user);
    $auth->password($pass);

    return $auth;
  }

  /**
   * Set username
   *
   * @param  string $u
   */
  public function username($u) {
    $this->username= $u;
  }

  /**
   * Set password
   *
   * @param  security.SecureString $p
   */
  public function password(SecureString $p) {
    $this->password= $p;
  }

  /**
   * Calculate the response code for the given request#
   *
   * @param  peer.http.HttpRequest $request
   * @return string
   */
  public function responseFor(HttpRequest $request) {
    return md5(implode(':', [
      $this->ha1(),
      $this->nonce,
      sprintf('%08x', $this->counter),
      $this->cnonce,
      $this->qop(),
      $this->ha2($request)
    ]));
  }

  /**
   * Sign the given request; ie. add an Authorization: Digest header
   * and increase the internal nonce counter.
   *
   * @param  peer.http.HttpRequest $request
   */
  public function sign(HttpRequest $request) {
    $url= $request->getUrl();
    $parts= [
      'username="'.$this->username.'"',
      'realm="'.$this->realm.'"',
      'nonce="'.$this->nonce.'"',
      'uri="'.$url->getPath().($url->hasParams() ? '?'.$url->getQuery() : '').'"',
      'qop="'.$this->qop().'"',
      'nc='.sprintf('%08x', $this->counter),
      'cnonce="'.$this->cnonce.'"',
      'response="'.$this->responseFor($request).'"'
    ];

    if (sizeof($this->opaque)) {
      $parts[]= 'opaque="'.$this->opaque.'"';
    }

    $request->setHeader('Authorization', new Header('Authorization', 'Digest '.implode(', ', $parts)));

    // Increase internal counter
    $this->counter++;
  }

  /**
   * Create ha1 value
   *
   * @return string
   */
  private function ha1() {
    return md5(implode(':', [$this->username, $this->realm, $this->password->getCharacters()]));
  }

  /**
   * Create ha2 value
   *
   * @param  peer.http.HttpRequest $request
   * @return string
   */
  private function ha2($request) {
    $url= $request->getUrl();
    return md5(implode(':', [
      strtoupper($request->method),
      $url->getPath().($url->hasParams() ? '?'.$url->getQuery() : '')
    ]));
  }

  /**
   * Retrieve quality-of-protection value; hardcoded
   * @return [type] [description]
   */
  private function qop() {
    $qop= explode(',', $this->qop);
    if (!in_array('auth', $qop)) {
      throw new MethodNotImplementedException('QoP not given or not supported (required: "auth", have '.\xp::stringOf($this->qop).').');
    }

    return 'auth';
  }

  /**
   * Initialize the client nonce (randomly, if not given a value).
   *
   * @param  string $c default null
   */
  public function cnonce($c= null) {
    if (null === $c) {
      $c= substr(md5(uniqid(time())), 0, 8);
    }

    $this->cnonce= $c;
  }

  /**
   * Check if instance is equal to this instance
   *
   * @param  lang.Generic $o
   * @return boolean
   */
  public function equals($o) {
    if (!$o instanceof self) return false;

    return (
      $o->realm === $this->realm &&
      $o->qop === $this->qop &&
      $o->nonce === $this->nonce &&
      $o->opaque === $this->opaque
    );
  }

  /**
   * Retrieve string representation
   *
   * @return string
   */
  public function toString() {
    $s= $this->getClassName().' ('.$this->hashCode().") {\n";
    foreach (['realm', 'qop', 'nonce', 'opaque', 'username'] as $attr) {
      $s.= sprintf("  [ %8s ] %s\n", $attr, \xp::stringOf($this->{$attr}));
    }
    return $s.="}\n";
  }
}