<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDeletionNotice;
use App\Services\ActivityLogger;
use App\Services\ReservationService;
use App\Services\UserExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Password;

/** Actions sur plusieurs utilisateurs a la fois, depuis la liste (cases a cocher). */
class UserBulkController extends Controller
{
    private const ACTIONS = ['activate', 'deactivate', 'reset_password', 'export', 'delete'];

    public function __construct(private UserExporter $exporter, private AccountDeletionNotice $deletionNotice, private ReservationService $reservations) {}

    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', 'in:' . implode(',', self::ACTIONS)],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'exists:users,id'],
        ], ['ids.required' => 'Sélectionnez au moins un utilisateur.', 'ids.min' => 'Sélectionnez au moins un utilisateur.']);

        $users = User::with('role')->withCount('reservations')->whereIn('id', $data['ids'])->orderBy('name')->get()
            ->filter(fn (User $user) => $request->user()->canManage($user));
        if ($users->isEmpty()) {
            return back()->with('error', "Vous ne pouvez pas agir sur les comptes sélectionnés.");
        }

        return match ($data['action']) {
            'export' => $this->export($users),
            'reset_password' => $this->resetPasswords($users),
            default => $this->apply($data['action'], $users),
        };
    }

    /** Activer, desactiver, supprimer : jamais le compte de l'administrateur qui agit, pour ne pas s'enfermer dehors. */
    private function apply(string $action, Collection $users)
    {
        $others = $users->reject(fn (User $user) => $user->id === auth()->id());
        $skipped = $users->count() - $others->count();

        $cancelled = 0;
        match ($action) {
            'activate' => User::whereIn('id', $others->modelKeys())->update(['is_active' => true]),
            'deactivate' => $cancelled = $this->deactivate($others),
            'delete' => $others->each(function (User $user) {
                $this->deletionNotice->send($user, true);
                $user->delete();
            }),
        };

        $label = ['activate' => 'activé(s)', 'deactivate' => 'désactivé(s)', 'delete' => 'supprimé(s)'][$action];
        ActivityLogger::log('Action groupée sur les utilisateurs', "{$others->count()} {$label} : " . $this->names($others));

        $message = "{$others->count()} utilisateur(s) {$label}." . ReservationService::cancelledNote($cancelled);
        if ($skipped > 0) {
            $message .= ' Votre propre compte a été ignoré.';
        }

        return back()->with('success', $message);
    }

    /** Desactive les comptes et libere leurs places a venir. Renvoie le nombre de reservations annulees. */
    private function deactivate(Collection $users): int
    {
        User::whereIn('id', $users->modelKeys())->update(['is_active' => false]);

        return $this->reservations->cancelUpcomingForUsers($users->modelKeys(), ReservationService::DEACTIVATION_REASON);
    }

    /**
     * Envoie a chacun un lien pour choisir son mot de passe : aucun mot de passe n'est genere ni affiche.
     * Les e-mails partent apres l'envoi de la reponse : 500 envois un par un depassaient le delai d'une requete.
     * Le bilan (envoyes, echecs) est inscrit dans le journal d'activite une fois tout envoye.
     */
    private function resetPasswords(Collection $users)
    {
        $active = $users->where('is_active', true)->values();
        app()->terminating(fn () => $this->sendResetLinks($active));

        $message = "{$active->count()} lien(s) de réinitialisation en cours d'envoi. Le bilan sera inscrit dans le journal d'activité.";
        $inactive = $users->count() - $active->count();
        if ($inactive > 0) {
            $message .= " {$inactive} compte(s) inactif(s) ignoré(s).";
        }

        return back()->with('success', $message);
    }

    private function sendResetLinks(Collection $users): void
    {
        ignore_user_abort(true);
        @set_time_limit(0);

        $sent = 0;
        $failed = [];
        foreach ($users as $user) {
            try {
                $status = Password::broker()->sendResetLink(['email' => $user->email]);
            } catch (\Throwable $e) {
                report($e);
                $status = null;
            }
            $status === Password::RESET_LINK_SENT ? $sent++ : $failed[] = $user->email;
        }

        $detail = "{$sent} lien(s) envoyé(s), " . count($failed) . ' échec(s)';
        if ($failed !== []) {
            $detail .= ' (e-mail non configuré ou demande trop rapprochée) : ' . implode(', ', array_slice($failed, 0, 5)) . (count($failed) > 5 ? '...' : '');
        }
        ActivityLogger::log('Réinitialisation de mot de passe groupée', $detail);
    }

    private function export(Collection $users)
    {
        ActivityLogger::log('Export CSV utilisateurs', $users->count() . ' utilisateur(s) sélectionné(s)');

        // BOM : Excel reconnait ainsi l'UTF-8 (accents)
        return response("\xEF\xBB\xBF" . $this->exporter->csv($users), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="utilisateurs-' . now()->format('Ymd') . '.csv"',
        ]);
    }

    private function names(Collection $users): string
    {
        $names = $users->pluck('name')->take(5)->implode(', ');

        return $users->count() > 5 ? $names . '...' : $names;
    }
}
