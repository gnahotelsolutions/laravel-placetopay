<?php

namespace GNAHotelSolutions\LaravelPlaceToPay;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

class Client
{
    public function __construct(protected object $settings)
    {
    }

    public function createSession(
        string $name,
        string $surname,
        string $email,
        string $documentType,
        string $documentId,
        string $phone,
        string $locale,
        string $reference,
        string $description,
        float  $amountTotal,
        ?float $amountVat,
        float  $amountBase,
        string $currency,
        string $returnUrl,
        string $ip,
        string $userAgent,
    ): object
    {
        return Http::asJson()->post("{$this->settings->endpoint}/api/session", [
            'locale' => $this->getLocale($locale),
            ...$this->getAuth(),
            'payment' => [
                'reference' => $reference,
                'description' => $description,
                'amount' => $this->getAmount(
                    amountTotal: $amountTotal,
                    amountVat: $amountVat,
                    amountBase: $amountBase,
                    currency: $currency,
                )
            ],
            'expiration' => Carbon::now()->addMinutes(20)->format('c'),
            'returnUrl' => $returnUrl,
            'ipAddress' => $ip,
            'userAgent' => $userAgent,
            'buyer' => [
                'name' => $name,
                'surname' => $surname,
                'email' => $email,
                'documentType' => $documentType,
                'document' => $documentId,
                'mobile' => str_replace(' ', '', $phone)
            ]
        ])
            ->object();
    }

    public function querySession(string $sessionId): PaymentResponse
    {
        return Http::asJson()->post("{$this->settings->endpoint}/api/session/$sessionId", $this->getAuth())
            ->object();
    }

    public function reverse(string $reference): PaymentResponse
    {
        return Http::asJson()->post("{$this->settings->endpoint}/api/reverse", [
            ...$this->getAuth(),
            'internalReference' => $reference
        ])
            ->object();
    }

    protected function getLocale(string $locale): string
    {
        if ($locale === 'es') {
            return 'es_ES';
        }

        return 'en_US';
    }

    protected function getAuth(): array
    {
        $randomValue = Carbon::now()->format('YmdHisu');
        $seed = date('c');

        return [
            'auth' => [
                'login' => $this->settings->code_client,
                'nonce' => base64_encode($randomValue),
                'seed' => $seed,
                'tranKey' => base64_encode(sha1($randomValue . $seed . $this->settings->password, true)),
            ]
        ];
    }

    protected function getAmount(float $amountTotal, ?float $amountVat, float $amountBase, string $currency,): array
    {
        if ($amountVat === null) {
            return [
                'currency' => $currency,
                'total' => $amountTotal
            ];
        }

        return [
            'currency' => $currency,
            'total' => $amountTotal,
            'taxes' => [
                [
                    'kind' => 'valueAddedTax',
                    'amount' => $amountVat,
                    'base' => $amountBase
                ]
            ]
        ];
    }
}
