<?php
/**
 * A&B First Aid Training - USI Registry web-service client.
 *
 * Talks to the Commonwealth USI Registry (usi.gov.au) so a student's USI can be
 * checked against the government record at enrolment instead of being trusted
 * because somebody typed it in.
 *
 * Two hops per call:
 *   1. ATO Machine Authentication Service - Secure Token (MAS-ST). We sign a
 *      WS-Trust RequestSecurityToken with the M2M machine credential and get
 *      back an encrypted SAML assertion plus a symmetric proof key.
 *   2. USI web service. The SAML assertion rides in the WS-Security header and
 *      the request timestamp is signed with HMAC-SHA1 using the proof key.
 *
 * Deliberately does NOT use ext-soap - the envelopes are hand-built and posted
 * with curl, so this runs on any PHP 8 host with dom + openssl + curl.
 */
declare(strict_types=1);

final class UsiClientException extends RuntimeException {}

final class UsiClient
{
    /* 3PT = the government's third-party test environment. Fake students only. */
    public const ENV_TEST = 'test';
    public const ENV_LIVE = 'live';

    private const ENDPOINTS = [
        self::ENV_TEST => [
            'sts' => 'https://softwareauthorisations.evte.ato.gov.au/R3.0/S007v1.3/service.svc',
            'usi' => 'https://3pt.portal.usi.gov.au/service/v5/usiservice.svc',
        ],
        self::ENV_LIVE => [
            'sts' => 'https://softwareauthorisations.ato.gov.au/R3.0/S007v1.3/service.svc',
            'usi' => 'https://portal.usi.gov.au/service/v5/usiservice.svc',
        ],
    ];

    private const NS = [
        's'         => 'http://www.w3.org/2003/05/soap-envelope',
        'a'         => 'http://www.w3.org/2005/08/addressing',
        'u'         => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd',
        'wsu'       => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd',
        'o'         => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
        'ds'        => 'http://www.w3.org/2000/09/xmldsig#',
        'trust'     => 'http://docs.oasis-open.org/ws-sx/ws-trust/200512',
        'wsp'       => 'http://schemas.xmlsoap.org/ws/2004/09/policy',
        'i'         => 'http://schemas.xmlsoap.org/ws/2005/05/identity',
        'xenc'      => 'http://www.w3.org/2001/04/xmlenc#',
        'saml'      => 'urn:oasis:names:tc:SAML:2.0:assertion',
        'k'         => 'http://docs.oasis-open.org/wss/oasis-wss-wssecurity-secext-1.1.xsd',
        'usi'       => 'http://usi.gov.au/2022/ws',
    ];

    private const USI_NS  = 'http://usi.gov.au/2022/ws';
    private const ACTION  = 'http://usi.gov.au/2022/ws/';

    private string $env;
    private string $orgCode;
    private string $certificate;         // base64 DER, no PEM wrapper
    private string $privateKeyPem;
    private ?string $tokenCacheFile;
    private int $timeout;

    /** @var array{proof:string,assertion:DOMElement,reference:DOMElement}|null */
    private ?array $token = null;

    /** Last request/response pair, for logging a failure without re-running it. */
    public array $lastExchange = [];

    /**
     * @param string $keystoreFile  the ATO credential store (keystore-usi.xml)
     * @param string $credentialId  e.g. ABRD:27809366375_USIMachine
     */
    public function __construct(
        string $keystoreFile,
        string $credentialId,
        string $keystorePassword,
        string $orgCode,
        string $env = self::ENV_TEST,
        ?string $tokenCacheFile = null,
        int $timeout = 30
    ) {
        if (!isset(self::ENDPOINTS[$env])) {
            throw new UsiClientException("Unknown USI environment '{$env}'.");
        }
        $this->env            = $env;
        $this->orgCode        = $orgCode;
        $this->tokenCacheFile = $tokenCacheFile;
        $this->timeout        = $timeout;

        [$this->certificate, $this->privateKeyPem] =
            self::readKeystore($keystoreFile, $credentialId, $keystorePassword);
    }

    /* ------------------------------------------------------------ keystore */

    /**
     * Pull one credential out of the ATO credential store.
     *
     * publicCertificate is a base64 PKCS#7 blob (a cert *chain*); we want the
     * leaf. protectedPrivateKey is a password-encrypted PKCS#8.
     *
     * @return array{0:string,1:string} [base64 DER cert, private key PEM]
     */
    public static function readKeystore(string $file, string $credentialId, string $password): array
    {
        if (!is_readable($file)) {
            throw new UsiClientException("Credential store not readable: {$file}");
        }
        $doc = new DOMDocument();
        if (!$doc->loadXML((string)file_get_contents($file))) {
            throw new UsiClientException("Credential store is not valid XML: {$file}");
        }
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('c', 'http://auth.abr.gov.au/credential/xsd/SBRCredentialStore');

        $node = $xp->query(sprintf('//c:credential[@id=%s]', self::xpathLiteral($credentialId)))->item(0);
        if (!$node instanceof DOMElement) {
            $ids = [];
            foreach ($xp->query('//c:credential/@id') as $attr) {
                $ids[] = $attr->nodeValue;
            }
            throw new UsiClientException(
                "Credential '{$credentialId}' not in the store. Available: " . implode(', ', $ids)
            );
        }

        $notAfter = $xp->evaluate('string(c:notAfter)', $node);
        if ($notAfter !== '' && strtotime($notAfter) < time()) {
            throw new UsiClientException("Machine credential expired on {$notAfter}.");
        }

        $pkcs7 = trim($xp->evaluate('string(c:publicCertificate)', $node));
        $chain = null;
        $wrapped = "-----BEGIN PKCS7-----\n" . chunk_split($pkcs7, 64, "\n") . "-----END PKCS7-----\n";
        if (!openssl_pkcs7_read($wrapped, $chain) || !$chain) {
            throw new UsiClientException('Could not read the public certificate out of the credential store.');
        }
        $certificate = str_replace(
            ["\r", "\n", '-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'],
            '',
            $chain[0]
        );

        $encryptedKey = trim($xp->evaluate('string(c:protectedPrivateKey)', $node));
        $wrappedKey = "-----BEGIN ENCRYPTED PRIVATE KEY-----\n"
            . chunk_split($encryptedKey, 64, "\n")
            . "-----END ENCRYPTED PRIVATE KEY-----\n";
        $key = openssl_pkey_get_private($wrappedKey, $password);
        if ($key === false) {
            throw new UsiClientException('Wrong keystore password, or the private key is unreadable.');
        }
        $keyPem = '';
        openssl_pkey_export($key, $keyPem);

        return [$certificate, $keyPem];
    }

    /* --------------------------------------------------------- public API */

    /**
     * Verify a USI against the registry.
     *
     * Pass either $firstName + $familyName, or $singleName for students with
     * one legal name. $dob is yyyy-mm-dd.
     *
     * @return array{status:string,firstName:?string,familyName:?string,singleName:?string,dateOfBirth:?string,verified:bool}
     */
    public function verifyUsi(
        string $usi,
        ?string $firstName,
        ?string $familyName,
        string $dob,
        ?string $singleName = null
    ): array {
        $usi = strtoupper(trim($usi));

        $doc = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElementNS(self::USI_NS, 'VerifyUSI');
        $doc->appendChild($root);
        $add = static function (string $name, string $value) use ($doc, $root): void {
            $root->appendChild($doc->createElementNS(self::USI_NS, $name, htmlspecialchars($value, ENT_XML1)));
        };
        $add('OrgCode', $this->orgCode);
        $add('USI', $usi);
        if ($singleName !== null && $singleName !== '') {
            $add('SingleName', $singleName);
        } else {
            $add('FirstName', (string)$firstName);
            $add('FamilyName', (string)$familyName);
        }
        $add('DateOfBirth', $dob);

        $responseXml = $this->invoke('VerifyUSI', $doc->saveXML($root));

        [, $xp] = self::dom($responseXml);
        $node = $xp->query('//usi:VerifyUSIResponse')->item(0);
        if (!$node instanceof DOMElement) {
            throw new UsiClientException('USI service returned no VerifyUSIResponse: ' . self::faultText($xp, $responseXml));
        }
        $get = static function (string $name) use ($xp, $node): ?string {
            $n = $xp->query('usi:' . $name, $node)->item(0);
            return $n ? trim($n->textContent) : null;
        };

        $status  = (string)$get('USIStatus');
        $first   = $get('FirstName');
        $family  = $get('FamilyName');
        $single  = $get('SingleName');
        $birth   = $get('DateOfBirth');

        // "Verified" means the USI is live AND every field we sent matched.
        $matched = static fn(?string $v): bool => $v === null || strcasecmp($v, 'Match') === 0;
        $verified = strcasecmp($status, 'Valid') === 0
            && $matched($first) && $matched($family) && $matched($single) && $matched($birth);

        return [
            'status'      => $status,
            'firstName'   => $first,
            'familyName'  => $family,
            'singleName'  => $single,
            'dateOfBirth' => $birth,
            'verified'    => $verified,
        ];
    }

    /** Post one signed operation to the USI service and return the raw response. */
    public function invoke(string $operation, string $bodyXml): string
    {
        $token = $this->getToken();

        $doc = self::envelope();
        $xp  = new DOMXPath($doc);
        foreach (self::NS as $prefix => $uri) {
            $xp->registerNamespace($prefix, $uri);
        }
        $header = $xp->query('/s:Envelope/s:Header')->item(0);

        $header->appendChild(self::el($doc, self::NS['a'], 'a:Action', self::ACTION . $operation))
               ->setAttributeNS(self::NS['s'], 's:mustUnderstand', '1');
        $header->appendChild(self::el($doc, self::NS['a'], 'a:MessageID', 'urn:uuid:' . self::guid()));
        $replyTo = self::el($doc, self::NS['a'], 'a:ReplyTo');
        $replyTo->appendChild(self::el($doc, self::NS['a'], 'a:Address', 'http://www.w3.org/2005/08/addressing/anonymous'));
        $header->appendChild($replyTo);
        $to = self::el($doc, self::NS['a'], 'a:To', $this->url('usi'));
        $to->setAttributeNS(self::NS['s'], 's:mustUnderstand', '1');
        $header->appendChild($to);

        $security = self::el($doc, self::NS['o'], 'o:Security');
        $security->setAttributeNS(self::NS['s'], 's:mustUnderstand', '1');
        $header->appendChild($security);

        $timestamp = $this->timestamp($doc, 300);
        $security->appendChild($timestamp);

        // The encrypted SAML assertion, lifted verbatim out of the STS response.
        $security->appendChild($doc->importNode($token['assertion'], true));

        $signature = self::el($doc, self::NS['ds'], 'ds:Signature');
        $security->appendChild($signature);
        $signedInfo = self::el($doc, self::NS['ds'], 'ds:SignedInfo');
        $signature->appendChild($signedInfo);
        self::algo($doc, $signedInfo, 'ds:CanonicalizationMethod', 'http://www.w3.org/2001/10/xml-exc-c14n#');
        self::algo($doc, $signedInfo, 'ds:SignatureMethod', 'http://www.w3.org/2000/09/xmldsig#hmac-sha1');
        $signedInfo->appendChild(self::reference($doc, '#_0', 'http://www.w3.org/2000/09/xmldsig#sha1',
            base64_encode(hash('sha1', $timestamp->C14N(true), true))));

        $signature->appendChild(self::el($doc, self::NS['ds'], 'ds:SignatureValue',
            base64_encode(hash_hmac('sha1', $signedInfo->C14N(true), $token['proof'], true))));

        $keyInfo = self::el($doc, self::NS['ds'], 'ds:KeyInfo');
        $keyInfo->appendChild($doc->importNode($token['reference'], true));
        $signature->appendChild($keyInfo);

        $body = $xp->query('/s:Envelope/s:Body')->item(0);
        $fragment = new DOMDocument();
        $fragment->loadXML($bodyXml);
        $body->appendChild($doc->importNode($fragment->documentElement, true));

        $request  = $doc->saveXML();
        $response = $this->post($this->url('usi'), $request);
        $this->lastExchange['usi'] = ['request' => $request, 'response' => $response];

        return $response;
    }

    /* ----------------------------------------------------------- MAS-ST */

    /** @return array{proof:string,assertion:DOMElement,reference:DOMElement} */
    private function getToken(): array
    {
        if ($this->token !== null) {
            return $this->token;
        }
        $cached = $this->readCachedToken();
        if ($cached !== null) {
            return $this->token = $cached;
        }

        $doc = self::envelope();
        $xp  = new DOMXPath($doc);
        foreach (self::NS as $prefix => $uri) {
            $xp->registerNamespace($prefix, $uri);
        }
        $header = $xp->query('/s:Envelope/s:Header')->item(0);

        $action = self::el($doc, self::NS['a'], 'a:Action', 'http://docs.oasis-open.org/ws-sx/ws-trust/200512/RST/Issue');
        $action->setAttributeNS(self::NS['s'], 's:mustUnderstand', '1');
        $header->appendChild($action);
        $header->appendChild(self::el($doc, self::NS['a'], 'a:MessageID', 'urn:uuid:' . self::guid()));
        $replyTo = self::el($doc, self::NS['a'], 'a:ReplyTo');
        $replyTo->appendChild(self::el($doc, self::NS['a'], 'a:Address', 'http://www.w3.org/2005/08/addressing/anonymous'));
        $header->appendChild($replyTo);

        $to = self::el($doc, self::NS['a'], 'a:To', $this->url('sts'));
        $to->setAttributeNS(self::NS['s'], 's:mustUnderstand', '1');
        $to->setAttributeNS(self::NS['u'], 'u:Id', '_1');
        $header->appendChild($to);

        $security = self::el($doc, self::NS['o'], 'o:Security');
        $security->setAttributeNS(self::NS['s'], 's:mustUnderstand', '1');
        $header->appendChild($security);

        $timestamp = $this->timestamp($doc, 300);
        $security->appendChild($timestamp);

        $bstId = 'uuid-' . self::guid();
        $bst = self::el($doc, self::NS['o'], 'o:BinarySecurityToken', $this->certificate);
        $bst->setAttribute('EncodingType', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary');
        $bst->setAttribute('ValueType', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3');
        $bst->setAttributeNS(self::NS['wsu'], 'wsu:Id', $bstId);
        $security->appendChild($bst);

        $signature = self::el($doc, self::NS['ds'], 'ds:Signature');
        $security->appendChild($signature);
        $signedInfo = self::el($doc, self::NS['ds'], 'ds:SignedInfo');
        $signature->appendChild($signedInfo);
        self::algo($doc, $signedInfo, 'ds:CanonicalizationMethod', 'http://www.w3.org/2001/10/xml-exc-c14n#');
        self::algo($doc, $signedInfo, 'ds:SignatureMethod', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
        $sha256 = 'http://www.w3.org/2001/04/xmlenc#sha256';
        $signedInfo->appendChild(self::reference($doc, '#_0', $sha256,
            base64_encode(hash('sha256', $timestamp->C14N(true), true))));
        $signedInfo->appendChild(self::reference($doc, '#_1', $sha256,
            base64_encode(hash('sha256', $to->C14N(true), true))));

        $key = openssl_pkey_get_private($this->privateKeyPem);
        $raw = '';
        if (!openssl_sign($signedInfo->C14N(true), $raw, $key, OPENSSL_ALGO_SHA256)) {
            throw new UsiClientException('Could not sign the MAS-ST request.');
        }
        $signature->appendChild(self::el($doc, self::NS['ds'], 'ds:SignatureValue', base64_encode($raw)));

        $keyInfo = self::el($doc, self::NS['ds'], 'ds:KeyInfo');
        $str = self::el($doc, self::NS['o'], 'o:SecurityTokenReference');
        $ref = self::el($doc, self::NS['o'], 'o:Reference');
        $ref->setAttribute('URI', '#' . $bstId);
        $str->appendChild($ref);
        $keyInfo->appendChild($str);
        $signature->appendChild($keyInfo);

        /* ---- body: WS-Trust RequestSecurityToken ---- */
        $body = $xp->query('/s:Envelope/s:Body')->item(0);
        $rst = self::el($doc, self::NS['trust'], 'trust:RequestSecurityToken');
        $body->appendChild($rst);

        $appliesTo = self::el($doc, self::NS['wsp'], 'wsp:AppliesTo');
        $epr = self::el($doc, self::NS['a'], 'a:EndpointReference');
        $epr->appendChild(self::el($doc, self::NS['a'], 'a:Address', $this->url('usi')));
        $appliesTo->appendChild($epr);
        $rst->appendChild($appliesTo);

        $claims = self::el($doc, self::NS['trust'], 'trust:Claims');
        $claims->setAttribute('Dialect', 'http://schemas.xmlsoap.org/ws/2005/05/identity');
        foreach (['abn', 'credentialtype'] as $claim) {
            $ct = self::el($doc, self::NS['i'], 'i:ClaimType');
            $ct->setAttribute('Uri', 'http://vanguard.ebusiness.gov.au/2008/06/identity/claims/' . $claim);
            $ct->setAttribute('Optional', 'false');
            $claims->appendChild($ct);
        }
        $rst->appendChild($claims);

        $rst->appendChild(self::el($doc, self::NS['trust'], 'trust:KeyType',
            'http://docs.oasis-open.org/ws-sx/ws-trust/200512/SymmetricKey'));

        $lifetime = self::el($doc, self::NS['trust'], 'trust:Lifetime');
        $created = new DateTime('now', new DateTimeZone('GMT'));
        $lifetime->appendChild(self::el($doc, self::NS['wsu'], 'wsu:Created', self::xmlTime($created)));
        $expires = (clone $created)->add(new DateInterval('P1D'));
        $lifetime->appendChild(self::el($doc, self::NS['wsu'], 'wsu:Expires', self::xmlTime($expires)));
        $rst->appendChild($lifetime);

        $rst->appendChild(self::el($doc, self::NS['trust'], 'trust:RequestType',
            'http://docs.oasis-open.org/ws-sx/ws-trust/200512/Issue'));
        $rst->appendChild(self::el($doc, self::NS['trust'], 'trust:TokenType',
            'http://docs.oasis-open.org/wss/oasis-wss-saml-token-profile-1.1#SAMLV2.0'));

        $request  = $doc->saveXML();
        $response = $this->post($this->url('sts'), $request);
        $this->lastExchange['sts'] = ['request' => $request, 'response' => $response];

        [, $rxp] = self::dom($response);
        $rstr = $rxp->query('//trust:RequestSecurityTokenResponseCollection/trust:RequestSecurityTokenResponse')->item(0);
        if (!$rstr instanceof DOMElement) {
            throw new UsiClientException('MAS-ST did not issue a token: ' . self::faultText($rxp, $response));
        }
        $assertion = $rxp->query('trust:RequestedSecurityToken/saml:EncryptedAssertion/xenc:EncryptedData', $rstr)->item(0);
        $proof     = $rxp->query('trust:RequestedProofToken/trust:BinarySecret', $rstr)->item(0);
        $reference = $rxp->query('trust:RequestedAttachedReference/o:SecurityTokenReference', $rstr)->item(0);
        if (!$assertion || !$proof || !$reference) {
            throw new UsiClientException('MAS-ST response is missing the assertion, proof token or attached reference.');
        }

        $token = [
            'proof'     => base64_decode(trim($proof->textContent)),
            'assertion' => $assertion,
            'reference' => $reference,
        ];
        $this->writeCachedToken($rstr);

        return $this->token = $token;
    }

    /* ------------------------------------------------------- token cache */

    /**
     * The proof key is a live secret, so the cache file is written 0600 and
     * only ever holds a token the ATO already considers short-lived.
     */
    private function writeCachedToken(DOMElement $rstr): void
    {
        if ($this->tokenCacheFile === null) {
            return;
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->appendChild($doc->importNode($rstr, true));
        $payload = json_encode([
            'env'     => $this->env,
            'expires' => time() + 3600,   // well inside the 1 day we asked for
            'xml'     => $doc->saveXML(),
        ]);
        $tmp = $this->tokenCacheFile . '.tmp';
        if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) {
            @chmod($tmp, 0600);
            @rename($tmp, $this->tokenCacheFile);
        }
    }

    /** @return array{proof:string,assertion:DOMElement,reference:DOMElement}|null */
    private function readCachedToken(): ?array
    {
        if ($this->tokenCacheFile === null || !is_readable($this->tokenCacheFile)) {
            return null;
        }
        $payload = json_decode((string)file_get_contents($this->tokenCacheFile), true);
        if (!is_array($payload) || ($payload['env'] ?? '') !== $this->env || ($payload['expires'] ?? 0) < time()) {
            return null;
        }
        [, $xp] = self::dom((string)$payload['xml']);
        $assertion = $xp->query('//trust:RequestedSecurityToken/saml:EncryptedAssertion/xenc:EncryptedData')->item(0);
        $proof     = $xp->query('//trust:RequestedProofToken/trust:BinarySecret')->item(0);
        $reference = $xp->query('//trust:RequestedAttachedReference/o:SecurityTokenReference')->item(0);
        if (!$assertion || !$proof || !$reference) {
            return null;
        }
        return [
            'proof'     => base64_decode(trim($proof->textContent)),
            'assertion' => $assertion,
            'reference' => $reference,
        ];
    }

    /** Throw the cached token away - call this after an auth failure. */
    public function forgetToken(): void
    {
        $this->token = null;
        if ($this->tokenCacheFile !== null) {
            @unlink($this->tokenCacheFile);
        }
    }

    /* --------------------------------------------------------- transport */

    private function post(string $url, string $xml): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/soap+xml; charset=utf-8'],
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new UsiClientException("Could not reach {$url}: {$err}");
        }
        // A SOAP fault comes back as 500 with a usable body, so only bail on
        // statuses that carry nothing worth parsing.
        if ($code >= 400 && $code !== 500 && stripos((string)$body, 'Envelope') === false) {
            throw new UsiClientException("{$url} returned HTTP {$code}: " . substr((string)$body, 0, 500));
        }
        return (string)$body;
    }

    /* ------------------------------------------------------------ helpers */

    private function url(string $which): string
    {
        return self::ENDPOINTS[$this->env][$which];
    }

    private static function envelope(): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $envelope = $doc->createElementNS(self::NS['s'], 's:Envelope');
        $doc->appendChild($envelope);
        $envelope->appendChild($doc->createElementNS(self::NS['s'], 's:Header'));
        $envelope->appendChild($doc->createElementNS(self::NS['s'], 's:Body'));
        return $doc;
    }

    private static function el(DOMDocument $doc, string $ns, string $qname, ?string $value = null): DOMElement
    {
        return $value === null
            ? $doc->createElementNS($ns, $qname)
            : $doc->createElementNS($ns, $qname, htmlspecialchars($value, ENT_XML1));
    }

    private static function algo(DOMDocument $doc, DOMElement $parent, string $qname, string $algorithm): void
    {
        $el = self::el($doc, self::NS['ds'], $qname);
        $el->setAttribute('Algorithm', $algorithm);
        $parent->appendChild($el);
    }

    private static function reference(DOMDocument $doc, string $uri, string $digestAlgo, string $digest): DOMElement
    {
        $ref = self::el($doc, self::NS['ds'], 'ds:Reference');
        $ref->setAttribute('URI', $uri);
        $transforms = self::el($doc, self::NS['ds'], 'ds:Transforms');
        self::algo($doc, $transforms, 'ds:Transform', 'http://www.w3.org/2001/10/xml-exc-c14n#');
        $ref->appendChild($transforms);
        self::algo($doc, $ref, 'ds:DigestMethod', $digestAlgo);
        $ref->appendChild(self::el($doc, self::NS['ds'], 'ds:DigestValue', $digest));
        return $ref;
    }

    private function timestamp(DOMDocument $doc, int $seconds): DOMElement
    {
        $timestamp = self::el($doc, self::NS['u'], 'u:Timestamp');
        $timestamp->setAttributeNS(self::NS['u'], 'u:Id', '_0');
        $created = new DateTime('now', new DateTimeZone('GMT'));
        $timestamp->appendChild(self::el($doc, self::NS['u'], 'u:Created', self::xmlTime($created)));
        $expires = (clone $created)->add(new DateInterval('PT' . $seconds . 'S'));
        $timestamp->appendChild(self::el($doc, self::NS['u'], 'u:Expires', self::xmlTime($expires)));
        return $timestamp;
    }

    /** The ATO wants ".000Z", not "+00:00". */
    private static function xmlTime(DateTime $t): string
    {
        return str_replace('+00:00', '.000Z', $t->setTimezone(new DateTimeZone('GMT'))->format('c'));
    }

    private static function guid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /** @return array{0:DOMDocument,1:DOMXPath} */
    private static function dom(string $xml): array
    {
        $doc = new DOMDocument();
        if (!@$doc->loadXML($xml)) {
            throw new UsiClientException('Service returned something that is not XML: ' . substr($xml, 0, 300));
        }
        $xp = new DOMXPath($doc);
        foreach (self::NS as $prefix => $uri) {
            $xp->registerNamespace($prefix, $uri);
        }
        return [$doc, $xp];
    }

    /** Pull a readable message out of a SOAP fault. */
    private static function faultText(DOMXPath $xp, string $raw): string
    {
        $bits = [];
        foreach (['//s:Fault/s:Reason/s:Text', '//s:Fault/s:Code/s:Subcode/s:Value', '//s:Fault/s:Detail'] as $q) {
            foreach ($xp->query($q) as $n) {
                $text = trim(preg_replace('/\s+/', ' ', $n->textContent));
                if ($text !== '') {
                    $bits[] = $text;
                }
            }
        }
        return $bits ? implode(' | ', array_unique($bits)) : substr($raw, 0, 500);
    }

    private static function xpathLiteral(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'{$value}'";
        }
        return 'concat(' . implode(",\"'\",", array_map(fn($p) => "'{$p}'", explode("'", $value))) . ')';
    }
}
