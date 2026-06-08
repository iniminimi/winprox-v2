<?php

namespace App\Http\Controllers;

use App\Models\EmailUnsubscribe;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * TODO: SuperUser Operationalization compliant met de core rules.
 *
 * Deze controller is voorbereid voor SuperUser-functionaliteit maar is nog niet
 * operationeel. Volgens winprox-core.mdc en legal memory is SuperUser-functionaliteit
 * niet operationeel in V2. Activeer deze pas wanneer expliciet toegestaan.
 *
 * Routes (uitgecommentarieerd in web.php):
 * - GET /admin/email-unsubscribes - Lijst van alle unsubscribes
 * - DELETE /admin/email-unsubscribes/{emailUnsubscribe} - Verwijder unsubscribe (herstel)
 */
class AdminEmailUnsubscribeController extends Controller
{
    /**
     * Lijst van alle e-mail uitschrijvingen.
     * Alleen beschikbaar voor SuperUser wanneer geactiveerd.
     */
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $rowsQuery = EmailUnsubscribe::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . addcslashes($q, '%_\\') . '%';
                $query->where('email', 'like', $like);
            });

        $rows = $rowsQuery
            ->orderByDesc('unsubscribed_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.email-unsubscribes', [
            'rows' => $rows,
            'q' => $q,
            'matchedUsers' => $this->matchedUsersForUnsubscribeEmails($rows->getCollection()),
        ]);
    }

    /**
     * Verwijder een unsubscribe record (herstel e-mail ontvangst).
     * Alleen beschikbaar voor SuperUser wanneer geactiveerd.
     */
    public function destroy(Request $request, EmailUnsubscribe $emailUnsubscribe): RedirectResponse
    {
        $email = $emailUnsubscribe->email;
        $emailUnsubscribe->delete();

        return redirect()
            ->route('admin.email-unsubscribes.index', $request->only(['q']))
            ->with('success', __('admin.email_unsubscribe.restored', ['email' => $email]));
    }

    /**
     * @param  Collection<int, EmailUnsubscribe>  $rows
     * @return Collection<string, User>
     */
    private function matchedUsersForUnsubscribeEmails(Collection $rows): Collection
    {
        $emails = $rows
            ->pluck('email')
            ->map(static fn (string $email): string => EmailUnsubscribe::normalizeEmail($email))
            ->unique()
            ->values()
            ->all();

        if ($emails === []) {
            return collect();
        }

        return User::query()
            ->with('tenant')
            ->whereIn('email', $emails)
            ->get()
            ->keyBy(static fn (User $user): string => EmailUnsubscribe::normalizeEmail((string) $user->email));
    }
}
