<?php

namespace App\Support\Portal;

use App\Models\Worker;
use App\Models\WorkerPasskeyCredential;
use Illuminate\Http\Request;
use InvalidArgumentException;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * WebAuthn-ceremonies voor het time-portaal: passkey registratie (alleen binnen
 * een al geverifieerde sessie) en verificatie als alternatief voor icoon/PIN.
 * Challenges zitten eenmalig + tijdsgebonden in de portalsessie; worker en
 * tenant worden altijd server-side bepaald, nooit uit de request-body.
 */
final class WorkerPasskeyCeremony
{
    private const REG_OPTIONS_SESSION = 'wp_passkey_reg_options';

    private const ASSERT_OPTIONS_SESSION = 'wp_passkey_assert_options';

    private const OPTIONS_TTL_SECONDS = 120;

    private const TIMEOUT_MS = 120000;

    public static function rpId(Request $request): string
    {
        return $request->getHost();
    }

    /**
     * Registratie-opties voor de al geverifieerde worker (native create()-prompt).
     *
     * @return array<string, mixed>
     */
    public static function issueRegistrationOptions(Worker $worker, Request $request): array
    {
        $options = PublicKeyCredentialCreationOptions::create(
            rp: PublicKeyCredentialRpEntity::create('WinProx', self::rpId($request)),
            user: PublicKeyCredentialUserEntity::create(
                $worker->displayName(),
                self::userHandleFor($worker),
                $worker->displayName(),
            ),
            challenge: random_bytes(32),
            pubKeyCredParams: [
                PublicKeyCredentialParameters::createPk(-7),
                PublicKeyCredentialParameters::createPk(-257),
            ],
            authenticatorSelection: new AuthenticatorSelectionCriteria(
                authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $worker->passkeyCredentials
                ->map(fn (WorkerPasskeyCredential $c) => self::descriptorFor($c))
                ->all(),
            timeout: self::TIMEOUT_MS,
        );

        $json = self::serializer()->serialize($options, 'json');
        self::storeOptions(self::REG_OPTIONS_SESSION, $json);

        return json_decode($json, true);
    }

    /**
     * Valideer de attestation-response tegen de sessie-challenge (eenmalig).
     */
    public static function verifyRegistration(Worker $worker, string $credentialJson, Request $request): CredentialRecord
    {
        $options = self::pullOptions(self::REG_OPTIONS_SESSION, PublicKeyCredentialCreationOptions::class);
        $credential = self::parseCredential($credentialJson);

        if (! $credential->response instanceof AuthenticatorAttestationResponse) {
            throw new InvalidArgumentException('invalid_response');
        }

        $record = AuthenticatorAttestationResponseValidator::create(self::creationCsm($request))
            ->check($credential->response, $options, self::rpId($request));

        if ($record->userHandle !== self::userHandleFor($worker)) {
            throw new InvalidArgumentException('user_handle_mismatch');
        }

        return $record;
    }

    /**
     * Assert-opties voor de onthouden worker (native get()-prompt).
     *
     * @return array<string, mixed>
     */
    public static function issueAssertionOptions(Worker $worker, Request $request): array
    {
        $options = PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: self::rpId($request),
            allowCredentials: $worker->passkeyCredentials
                ->map(fn (WorkerPasskeyCredential $c) => self::descriptorFor($c))
                ->all(),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: self::TIMEOUT_MS,
        );

        $json = self::serializer()->serialize($options, 'json');
        self::storeOptions(self::ASSERT_OPTIONS_SESSION, $json);

        return json_decode($json, true);
    }

    /**
     * Valideer de assertion. Geeft de bijbehorende credential-rij terug wanneer
     * challenge, origin, RP ID, handtekening en user-verification kloppen én de
     * credential server-side aan deze worker hangt.
     *
     * @return array{0: WorkerPasskeyCredential, 1: CredentialRecord}
     */
    public static function verifyAssertion(Worker $worker, string $credentialJson, Request $request): array
    {
        $options = self::pullOptions(self::ASSERT_OPTIONS_SESSION, PublicKeyCredentialRequestOptions::class);
        $credential = self::parseCredential($credentialJson);

        if (! $credential->response instanceof AuthenticatorAssertionResponse) {
            throw new InvalidArgumentException('invalid_response');
        }

        $stored = $worker->passkeyCredentials()
            ->where('credential_id', Base64UrlSafe::encodeUnpadded($credential->rawId))
            ->first();

        if ($stored === null) {
            throw new InvalidArgumentException('unknown_credential');
        }

        $record = new CredentialRecord(
            publicKeyCredentialId: Base64UrlSafe::decodeNoPadding($stored->credential_id),
            type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            transports: $stored->transports ?? [],
            attestationType: 'none',
            trustPath: new EmptyTrustPath(),
            aaguid: $stored->aaguid !== null ? Uuid::fromString($stored->aaguid) : Uuid::v4(),
            credentialPublicKey: base64_decode($stored->credential_public_key, true) ?: '',
            userHandle: $stored->user_handle,
            counter: (int) $stored->sign_count,
            backupEligible: $stored->backup_eligible,
            backupStatus: $stored->backup_status,
        );

        $updated = AuthenticatorAssertionResponseValidator::create(self::requestCsm($request))
            ->check($record, $credential->response, $options, self::rpId($request), $stored->user_handle);

        return [$stored, $updated];
    }

    public static function userHandleFor(Worker $worker): string
    {
        return 'wp-worker-'.(int) $worker->id;
    }

    private static function serializer(): SerializerInterface
    {
        return (new WebauthnSerializerFactory(
            new AttestationStatementSupportManager([new NoneAttestationStatementSupport()])
        ))->create();
    }

    private static function creationCsm(Request $request): \Webauthn\CeremonyStep\CeremonyStepManager
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins([$request->getSchemeAndHttpHost()]);

        return $factory->creationCeremony();
    }

    private static function requestCsm(Request $request): \Webauthn\CeremonyStep\CeremonyStepManager
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins([$request->getSchemeAndHttpHost()]);

        return $factory->requestCeremony();
    }

    private static function descriptorFor(WorkerPasskeyCredential $credential): PublicKeyCredentialDescriptor
    {
        return PublicKeyCredentialDescriptor::create(
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            Base64UrlSafe::decodeNoPadding($credential->credential_id),
            $credential->transports ?? [],
        );
    }

    private static function storeOptions(string $key, string $optionsJson): void
    {
        session()->put($key, [
            'options' => $optionsJson,
            'issued_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Eenmalig + tijdsgebonden: pull verwijdert de challenge direct.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     * @return T
     */
    private static function pullOptions(string $key, string $class): object
    {
        $stored = session()->pull($key);
        if (! is_array($stored) || empty($stored['options']) || empty($stored['issued_at'])) {
            throw new InvalidArgumentException('challenge_missing');
        }

        if (now()->diffInSeconds(\Carbon\Carbon::parse($stored['issued_at']), false) > self::OPTIONS_TTL_SECONDS) {
            throw new InvalidArgumentException('challenge_expired');
        }

        /** @var T $options */
        $options = self::serializer()->deserialize($stored['options'], $class, 'json');

        return $options;
    }

    private static function parseCredential(string $credentialJson): PublicKeyCredential
    {
        try {
            $credential = self::serializer()->deserialize($credentialJson, PublicKeyCredential::class, 'json');
        } catch (\Throwable) {
            throw new InvalidArgumentException('invalid_credential');
        }

        if (! $credential instanceof PublicKeyCredential) {
            throw new InvalidArgumentException('invalid_credential');
        }

        return $credential;
    }
}
