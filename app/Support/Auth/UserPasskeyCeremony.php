<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Models\UserPasskeyCredential;
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
 * WebAuthn voor de beheerlogin. De challenge leeft op het ticket, niet in de
 * browsersessie: computer en telefoon delen die niet.
 */
final class UserPasskeyCeremony
{
    private const TIMEOUT_MS = 120000;

    /**
     * @param  list<string>  $origins
     * @return array<string, mixed>
     */
    public static function assertionOptions(User $user, string $rpId): array
    {
        $options = PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: $rpId,
            allowCredentials: $user->passkeyCredential instanceof UserPasskeyCredential
                ? [self::descriptorFor($user->passkeyCredential)]
                : [],
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: self::TIMEOUT_MS,
        );

        return json_decode(self::serializer()->serialize($options, 'json'), true);
    }

    /**
     * @return array<string, mixed>
     */
    public static function discoverableAssertionOptions(string $rpId): array
    {
        $options = PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: $rpId,
            allowCredentials: [],
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: self::TIMEOUT_MS,
        );

        return json_decode(self::serializer()->serialize($options, 'json'), true);
    }

    /**
     * @param  list<string>  $origins
     * @return array{0: UserPasskeyCredential, 1: CredentialRecord}
     */
    public static function verifyAssertion(
        User $user,
        string $credentialJson,
        string $optionsJson,
        string $rpId,
        array $origins,
    ): array {
        $options = self::serializer()->deserialize($optionsJson, PublicKeyCredentialRequestOptions::class, 'json');
        $credential = self::parse($credentialJson);

        if (! $credential->response instanceof AuthenticatorAssertionResponse) {
            throw new InvalidArgumentException('invalid_response');
        }

        $stored = $user->passkeyCredential;
        if ($stored === null || $stored->credential_id !== Base64UrlSafe::encodeUnpadded($credential->rawId)) {
            throw new InvalidArgumentException('unknown_credential');
        }

        $record = self::recordFrom($stored);
        $updated = AuthenticatorAssertionResponseValidator::create(self::requestManager($origins))
            ->check($record, $credential->response, $options, $rpId, $stored->user_handle);

        return [$stored, $updated];
    }

    /**
     * @return array<string, mixed>
     */
    public static function registrationOptions(User $user, string $rpId): array
    {
        $exclude = $user->passkeyCredential instanceof UserPasskeyCredential
            ? [self::descriptorFor($user->passkeyCredential)]
            : [];

        $options = PublicKeyCredentialCreationOptions::create(
            rp: PublicKeyCredentialRpEntity::create('WinProx', $rpId),
            user: PublicKeyCredentialUserEntity::create(
                $user->name,
                self::userHandle($user),
                $user->name,
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
            excludeCredentials: $exclude,
            timeout: self::TIMEOUT_MS,
        );

        return json_decode(self::serializer()->serialize($options, 'json'), true);
    }

    /**
     * @param  list<string>  $origins
     */
    public static function verifyRegistration(
        User $user,
        string $credentialJson,
        string $optionsJson,
        string $rpId,
        array $origins,
    ): CredentialRecord {
        $options = self::serializer()->deserialize($optionsJson, PublicKeyCredentialCreationOptions::class, 'json');
        $credential = self::parse($credentialJson);

        if (! $credential->response instanceof AuthenticatorAttestationResponse) {
            throw new InvalidArgumentException('invalid_response');
        }

        $record = AuthenticatorAttestationResponseValidator::create(self::creationManager($origins))
            ->check($credential->response, $options, $rpId);

        if ($record->userHandle !== self::userHandle($user)) {
            throw new InvalidArgumentException('user_handle_mismatch');
        }

        return $record;
    }

    public static function userHandle(User $user): string
    {
        return 'wp-user-'.(int) $user->id;
    }

    /**
     * @return array{rpId: string, origins: list<string>}
     */
    public static function contextFromHost(string $schemeAndHost): array
    {
        $parts = parse_url($schemeAndHost);
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) ($parts['host'] ?? '');
        $origin = $scheme.'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
        $appHost = (string) config('marketing.domains.app_host', 'winprox.app');

        if ($appHost !== '' && ($host === $appHost || $host === 'www.'.$appHost)) {
            return [
                'rpId' => $appHost,
                'origins' => [$scheme.'://'.$appHost, $scheme.'://www.'.$appHost],
            ];
        }

        return [
            'rpId' => $host,
            'origins' => [$origin],
        ];
    }

    private static function serializer(): SerializerInterface
    {
        return (new WebauthnSerializerFactory(
            new AttestationStatementSupportManager([new NoneAttestationStatementSupport()])
        ))->create();
    }

    /**
     * @param  list<string>  $origins
     */
    private static function creationManager(array $origins): \Webauthn\CeremonyStep\CeremonyStepManager
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins($origins);

        return $factory->creationCeremony();
    }

    /**
     * @param  list<string>  $origins
     */
    private static function requestManager(array $origins): \Webauthn\CeremonyStep\CeremonyStepManager
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins($origins);

        return $factory->requestCeremony();
    }

    private static function descriptorFor(UserPasskeyCredential $credential): PublicKeyCredentialDescriptor
    {
        return PublicKeyCredentialDescriptor::create(
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            Base64UrlSafe::decodeNoPadding($credential->credential_id),
            $credential->transports ?? [],
        );
    }

    private static function recordFrom(UserPasskeyCredential $stored): CredentialRecord
    {
        return new CredentialRecord(
            publicKeyCredentialId: Base64UrlSafe::decodeNoPadding($stored->credential_id),
            type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            transports: $stored->transports ?? [],
            attestationType: 'none',
            trustPath: new EmptyTrustPath(),
            aaguid: $stored->aaguid !== null && $stored->aaguid !== '' ? Uuid::fromString($stored->aaguid) : Uuid::v4(),
            credentialPublicKey: base64_decode($stored->credential_public_key, true) ?: '',
            userHandle: $stored->user_handle,
            counter: (int) $stored->sign_count,
            backupEligible: (bool) $stored->backup_eligible,
            backupStatus: (bool) $stored->backup_status,
        );
    }

    private static function parse(string $credentialJson): PublicKeyCredential
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
