<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\IssueUserPasskeyOptionsAction;
use App\Actions\Auth\StoreUserPasskeyCredentialAction;
use App\Actions\Auth\VerifyUserPasskeyAssertionAction;
use App\Support\Auth\ComputerLabel;
use App\Support\Auth\UserPasskeyCeremony;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * WebAuthn voor de telefoonpagina. Dun: host doorgeven, Action doet de rest.
 */
class PhonePasskeyController
{
    public function assertOptions(Request $request, string $token, IssueUserPasskeyOptionsAction $issue): JsonResponse
    {
        return $this->options($request, $token, $issue, false);
    }

    public function assert(Request $request, string $token, VerifyUserPasskeyAssertionAction $verify): JsonResponse
    {
        $context = $this->context($request);

        try {
            $verify->handle($token, (string) $request->input('credential', ''), $context['rpId'], $context['origins']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'error' => 'failed'], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function registerOptions(Request $request, string $token, IssueUserPasskeyOptionsAction $issue): JsonResponse
    {
        return $this->options($request, $token, $issue, true);
    }

    public function register(Request $request, string $token, StoreUserPasskeyCredentialAction $store): JsonResponse
    {
        $context = $this->context($request);

        try {
            $store->handle(
                $token,
                (string) $request->input('credential', ''),
                $context['rpId'],
                $context['origins'],
                ComputerLabel::fromUserAgent($request->userAgent()),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'error' => 'failed'], 422);
        }

        return response()->json(['ok' => true]);
    }

    private function options(Request $request, string $token, IssueUserPasskeyOptionsAction $issue, bool $registration): JsonResponse
    {
        $context = $this->context($request);

        try {
            return response()->json($issue->handle($token, $context['rpId'], $registration));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @return array{rpId: string, origins: list<string>}
     */
    private function context(Request $request): array
    {
        return UserPasskeyCeremony::contextFromHost($request->getSchemeAndHttpHost());
    }
}
