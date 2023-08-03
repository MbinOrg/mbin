<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\Exception\InvalidApSignatureException;
use App\Service\ActivityPubManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SignatureValidator
{
    public function __construct(
        private readonly ActivityPubManager $activityPubManager,
        private readonly ApHttpClient $client,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Attempts to validate an incoming signed HTTP request
     *
     * @param string $body The body of the incoming request
     * @param array $headers Headers attached to the incoming request
     * @return void
     * @throws InvalidApSignatureException The HTTP request was not signed appropriately
     */
    public function validate(string $body, array $headers): void
    {
        $payload = json_decode($body, true);

        $signature = is_array($headers['signature']) ? $headers['signature'][0] : $headers['signature'];
        $date = is_array($headers['date']) ? $headers['date'][0] : $headers['date'];

        if (!$signature || !$date) {
            throw new InvalidApSignatureException('Missing required signature and/or date header');
        }

        // @todo verify headers date

        $signature = HttpSignature::parseSignatureHeader($signature);

        $this->validateUrl($id = is_array($payload['id']) ? $payload['id'][0] : $payload['id']);

        $keyDomain = parse_url($signature['keyId'], PHP_URL_HOST);
        $idDomain  = parse_url($id, PHP_URL_HOST);

        // @TODO this check appears to essentially be 'attributedTo' !== id
        if (isset($payload['object']) && is_array($payload['object']) && isset($payload['object']['attributedTo'])) {
            if (parse_url($payload['object']['attributedTo'], PHP_URL_HOST) !== $keyDomain) {
                throw new InvalidApSignatureException(
                    'Supplied key domain does not match domain of incoming activities "attributedTo" property'
                );
            }
        }

        if (!$keyDomain || !$idDomain || $keyDomain !== $idDomain) {
            throw new InvalidApSignatureException('Supplied key domain does not match domain of incoming activity.');
        }

        $actorUrl = is_array($payload['actor']) ? $payload['actor'][0] : $payload['actor'];

        $user = $this->activityPubManager->findActorOrCreate($actorUrl);

        $pkey = openssl_pkey_get_public($this->client->getActorObject($user->apProfileId)['publicKey']['publicKeyPem']);

        $this->verifySignature($pkey, $signature, $headers, $this->urlGenerator->generate('ap_shared_inbox'), $body);
    }

    private function validateUrl(string $url): void
    {
        $valid = filter_var($url, FILTER_VALIDATE_URL);
        if (!$valid) {
            throw new InvalidApSignatureException('Necessary supplied URL not valid.');
        }

        $parsed = parse_url($url);
        if ('https' !== $parsed['scheme']) {
            throw new InvalidApSignatureException('Necessary supplied URL does not use HTTPS.');
        }
    }

    /**
     * Verifies the signature of request against the given public key 
     *
     * @param \OpenSSLAsymmetricKey $pkey 
     * @param array $signature Parsed signature value
     * @param array $headers
     * @param string $inboxUrl
     * @param string $payload
     * @return void
     * @throws InvalidApSignatureException Signature failed verification
     */
    private function verifySignature(
        \OpenSSLAsymmetricKey $pkey,
        array $signature,
        array $headers,
        string $inboxUrl,
        string $payload,
    ): void {
        $digest = 'SHA-256='.base64_encode(hash('sha256', $payload, true));
        
        if (isset($headers['digest']) && $digest !== is_array($headers['digest']) ? $headers['digest'][0] : $headers['digest']) {
            $this->logger->warning('Supplied digest of incoming request does not match calculated value');
        }

        $headersToSign = [];
        foreach (explode(' ', $signature['headers']) as $h) {
            if ('(request-target)' === $h) {
                $headersToSign[$h] = 'post '.$inboxUrl;
            } elseif ('digest' === $h) {
                $headersToSign[$h] = $digest;
            } elseif (isset($headers[$h][0])) {
                $headersToSign[$h] = $headers[$h][0];
            }
        }

        $signingString = self::headersToSigningString($headersToSign);

        $verified = openssl_verify($signingString, base64_decode($signature['signature']), $pkey, OPENSSL_ALGO_SHA256);

        if (!$verified) {
            throw new InvalidApSignatureException('Signature of request could not be verified.');
        }

        $this->logger->info('Successfully verified signature of incoming AP request.', ['digest' => $digest]);
    }

    private static function headersToSigningString($headers): string
    {
        return implode(
            "\n",
            array_map(function ($k, $v) {
                return strtolower($k).': '.$v;
            }, array_keys($headers), $headers)
        );
    }
}
