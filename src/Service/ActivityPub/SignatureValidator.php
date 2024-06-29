<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\Exception\InboxForwardingException;
use App\Exception\InvalidApSignatureException;
use App\Exception\InvalidUserPublicKeyException;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Service\ActivityPubManager;
use Psr\Log\LoggerInterface;

/**
 * @phpstan-import-type RequestType from ActivityMessage
 */
readonly class SignatureValidator
{
    public function __construct(
        private ActivityPubManager $activityPubManager,
        private ApHttpClient $client,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Attempts to validate an incoming signed HTTP request.
     *
     * @phpstan-param RequestType $request
     *
     * @param array  $request The information about the incoming request
     * @param array  $headers Headers attached to the incoming request
     * @param string $body    The body of the incoming request
     *
     * @throws InvalidApSignatureException   The HTTP request was not signed appropriately
     * @throws InvalidUserPublicKeyException The public key of the specified user is invalid or null
     * @throws InboxForwardingException
     */
    public function validate(array $request, array $headers, string $body): void
    {
        $payload = json_decode($body, true);

        $signature = \is_array($headers['signature']) ? $headers['signature'][0] : $headers['signature'];
        $date = \is_array($headers['date']) ? $headers['date'][0] : $headers['date'];

        if (!$signature || !$date) {
            throw new InvalidApSignatureException('Missing required signature and/or date header');
        }

        // @todo verify headers date

        $signature = HttpSignature::parseSignatureHeader($signature);

        $this->validateUrl($signature['keyId']);
        $this->validateUrl($id = \is_array($payload['id']) ? $payload['id'][0] : $payload['id']);

        $keyDomain = parse_url($signature['keyId'], PHP_URL_HOST);
        $idDomain = parse_url($id, PHP_URL_HOST);

        $actorKeyIdMismatch = false;
        $firstActorHost = null;
        $erroredActor = null;

        if (isset($payload['actor'])) {
            $actors = $payload['actor'];
            if (\is_string($actors)) {
                $actors = [$actors];
            }
            foreach ($actors as $actor) {
                $url = $actor;
                if (!\is_string($actor) and isset($actor['id'])) {
                    $url = $actor['id'];
                }
                $host = parse_url($url, PHP_URL_HOST);
                if (!$firstActorHost) {
                    $firstActorHost = $host;
                }
                if ($host !== $keyDomain) {
                    $actorKeyIdMismatch = true;
                    $erroredActor = $url;
                    break;
                }
            }
        }

        $forwardedMessage = false;
        $keyAndIdMismatch = false;
        if (!$keyDomain || !$idDomain || $keyDomain !== $idDomain) {
            $keyAndIdMismatch = true;
        }

        if ($keyAndIdMismatch or $actorKeyIdMismatch and $firstActorHost === $idDomain) {
            foreach (ActivityPubManager::getReceivers($payload) as $item) {
                // if the payload has an inbox of the keyId domain than this is a case of inbox forwarding
                // and we should dispatch a new message to get the activity from the "real" host
                $itemDomain = parse_url($item, PHP_URL_HOST);
                if ($itemDomain === $keyDomain) {
                    $forwardedMessage = true;
                    break;
                }
            }
        }

        if ($forwardedMessage) {
            throw new InboxForwardingException($signature['keyId'], $id);
        } elseif ($actorKeyIdMismatch) {
            throw new InvalidApSignatureException("Supplied key domain does not match domain of incoming activities 'actor' property. actor: '$erroredActor', keyId : '$keyDomain'");
        } elseif ($keyAndIdMismatch) {
            throw new InvalidApSignatureException("Supplied key domain does not match domain of incoming activity. idDomain: '$idDomain' keyDomain: '$keyDomain'");
        }

        $actorUrl = \is_array($payload['actor']) ? $payload['actor'][0] : $payload['actor'];

        $user = $this->activityPubManager->findActorOrCreate($actorUrl);
        if (!empty($user)) {
            $pkey = openssl_pkey_get_public($this->client->getActorObject($user->apProfileId)['publicKey']['publicKeyPem']);

            if (false === $pkey) {
                throw new InvalidUserPublicKeyException("Unable to extract public key for user: '$user->apProfileId'");
            }

            $this->verifySignature($pkey, $signature, $headers, $request['uri'], $body);
        }
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
     * Verifies the signature of request against the given public key.
     *
     * @param array $signature Parsed signature value
     *
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

        if (isset($headers['digest']) && $digest !== $suppliedDigest = \is_array($headers['digest']) ? $headers['digest'][0] : $headers['digest']) {
            $this->logger->warning('Supplied digest of incoming request does not match calculated value', ['supplied-digest' => $suppliedDigest]);
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

        $this->logger->debug('Successfully verified signature of incoming AP request.', ['digest' => $digest]);
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
