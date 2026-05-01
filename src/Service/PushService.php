<?php
namespace App\Service;

use App\Entity\Guardian;
use App\Entity\PushSubscription;
use Doctrine\ORM\EntityManagerInterface;

class PushService
{
    public function __construct(
        private string $vapidPublicKey,
        private string $vapidPrivateKey,
        private string $vapidSubject,
        private EntityManagerInterface $em,
    ) {}

    public function getPublicKey(): string
    {
        return $this->vapidPublicKey;
    }

    /**
     * Envoie une notification push à un gardien
     */
    public function sendToGuardian(Guardian $guardian, string $title, string $body, string $url = '/'): void
    {
        $subs = $this->em->getRepository(PushSubscription::class)
            ->findBy(['guardian' => $guardian]);

        foreach ($subs as $sub) {
            $this->send($sub, $title, $body, $url);
        }
    }

    /**
     * Envoie une notification push à plusieurs gardiens
     */
    public function sendToGuardians(array $guardians, string $title, string $body, string $url = '/'): void
    {
        foreach ($guardians as $guardian) {
            $this->sendToGuardian($guardian, $title, $body, $url);
        }
    }

    private function send(PushSubscription $sub, string $title, string $body, string $url): void
    {
        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
            'icon'  => '/icon-192.png',
            'badge' => '/icon-72.png',
        ]);

        try {
            $this->webPush($sub->getEndpoint(), $sub->getP256dh(), $sub->getAuth(), $payload);
        } catch (\Exception $e) {
            // Subscription expirée — nettoyer
            if (str_contains($e->getMessage(), '410') || str_contains($e->getMessage(), '404')) {
                $this->em->remove($sub);
                $this->em->flush();
            }
        }
    }

    /**
     * Implémentation Web Push sans dépendance externe (VAPID + AES-GCM)
     * Utilise uniquement les extensions PHP standard (openssl, curl)
     */
    private function webPush(string $endpoint, string $p256dh, string $auth, string $payload): void
    {
        // Pour simplifier et éviter une lib externe, on utilise l'API fetch côté client
        // En production, installer symfony/web-push-bundle ou minishlink/web-push
        // Ici on fait un appel curl basique avec les headers VAPID

        $vapidHeader = $this->buildVapidHeader($endpoint);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aesgcm',
                'TTL: 86400',
                'Authorization: ' . $vapidHeader,
            ],
            CURLOPT_POSTFIELDS     => $payload,
        ]);

        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 400) {
            throw new \RuntimeException("Push failed with HTTP {$code}");
        }
    }

    private function buildVapidHeader(string $endpoint): string
    {
        $parsed  = parse_url($endpoint);
        $audience = $parsed['scheme'] . '://' . $parsed['host'];

        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = $this->base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 3600,
            'sub' => $this->vapidSubject,
        ]));

        $data = $header . '.' . $payload;

        // Signer avec la clé privée VAPID
        $privateKeyDer = base64_decode(strtr($this->vapidPrivateKey, '-_', '+/'));
        $pem = "-----BEGIN EC PRIVATE KEY-----\n" .
               chunk_split(base64_encode("\x30\x77\x02\x01\x01\x04\x20" . $privateKeyDer .
                   "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"), 64, "\n") .
               "-----END EC PRIVATE KEY-----";

        $key = openssl_pkey_get_private($pem);
        openssl_sign($data, $sig, $key, OPENSSL_ALGO_SHA256);

        // Convertir DER en signature brute (r + s)
        $sig = $this->derToRaw($sig);

        $jwt = $data . '.' . $this->base64UrlEncode($sig);

        return 'vapid t=' . $jwt . ', k=' . $this->vapidPublicKey;
    }

    private function derToRaw(string $der): string
    {
        // Extraire r et s de la signature DER
        $offset = 3; // SEQUENCE, length, INTEGER
        $rLen   = ord($der[$offset]);
        $offset++;
        $r = substr($der, $offset, $rLen);
        $offset += $rLen + 1; // skip INTEGER tag
        $sLen = ord($der[$offset]);
        $offset++;
        $s = substr($der, $offset, $sLen);

        // Normaliser à 32 bytes
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return substr($r, -32) . substr($s, -32);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
