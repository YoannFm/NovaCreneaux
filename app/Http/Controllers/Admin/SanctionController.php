<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sanction;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SanctionController extends Controller
{
    public function index()
    {
        $sanctions = Sanction::with(['user', 'admin'])->latest()->paginate(30);
        return view('admin.sanctions.index', compact('sanctions'));
    }

    /**
     * Avertissement ou suspension. Les erreurs d'une suspension vont dans le sac "suspend" :
     * la fenetre du formulaire se rouvre (liste ou fiche) avec la saisie et le compte vise.
     */
    public function store(Request $request, User $user)
    {
        abort_unless(auth()->user()->canManage($user), 403);
        abort_if($user->is(auth()->user()), 403, 'Vous ne pouvez pas vous sanctionner vous-même.');

        $data = $request->validateWithBag('suspend', [
            'type' => 'required|in:warning,ban',
            'severity' => ['required_if:type,ban', 'nullable', Rule::in(array_keys(Sanction::SEVERITIES))],
            'reason' => 'required|string|max:1000',
            'permanent' => 'nullable|boolean',
            'banned_until' => 'exclude_if:permanent,1|required_if:type,ban|nullable|date|after:today',
        ], [
            'severity.required_if' => 'Choisissez la gravité.',
            'banned_until.required_if' => 'Indiquez la date de fin, ou choisissez « Définitive ».',
        ], [
            'severity' => 'gravité', 'reason' => 'raison', 'banned_until' => 'date de fin',
        ]);

        $isBan = $data['type'] === 'ban';
        if ($isBan && $user->activeBan()) {
            return back()->with('error', "{$user->name} est déjà suspendu : levez d'abord la suspension en cours.");
        }

        $sanction = Sanction::create([
            'user_id' => $user->id,
            'admin_id' => auth()->id(),
            'type' => $data['type'],
            'severity' => $isBan ? $data['severity'] : null,
            'reason' => $data['reason'],
            'banned_until' => $isBan && empty($data['permanent']) ? $data['banned_until'] : null,
        ]);

        if (!$isBan) {
            ActivityLogger::log('Avertissement donné', $user->name);
            return back()->with('success', 'Avertissement donné à ' . $user->name . '.');
        }

        $until = $sanction->isPermanent() ? 'définitivement' : "jusqu'au " . $sanction->banned_until->format('d/m/Y');
        ActivityLogger::log('Utilisateur suspendu', "{$user->name}, {$until}, gravité {$sanction->severityLabel()}");

        return back()->with('success', "{$user->name} est suspendu {$until}.");
    }

    public function destroy(Sanction $sanction)
    {
        abort_unless(auth()->user()->canManage($sanction->user), 403);

        $sanction->delete();
        ActivityLogger::log($sanction->type === 'ban' ? 'Suspension levée' : 'Avertissement retiré', $sanction->user->name);

        return back()->with('success', $sanction->type === 'ban' ? 'Suspension levée.' : 'Sanction levée.');
    }
}
