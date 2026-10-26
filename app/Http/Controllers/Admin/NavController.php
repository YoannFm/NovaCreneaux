<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NavItem;
use Illuminate\Http\Request;

class NavController extends Controller
{
    public function index()
    {
        return view('admin.nav.index', [
            'items' => NavItem::orderBy('position')->orderBy('id')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.nav.form', [
            'item'      => null,
            'typeOptions' => NavItem::typeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validate($request);
        $data['position'] = NavItem::max('position') + 1;
        NavItem::create($data);

        return redirect()->route('admin.nav.index')->with('success', 'Élément de navigation ajouté.');
    }

    public function edit(NavItem $nav)
    {
        return view('admin.nav.form', [
            'item'        => $nav,
            'typeOptions' => NavItem::typeOptions(),
        ]);
    }

    public function update(Request $request, NavItem $nav)
    {
        $nav->update($this->validate($request));
        return redirect()->route('admin.nav.index')->with('success', 'Élément mis à jour.');
    }

    public function destroy(NavItem $nav)
    {
        $nav->delete();
        return redirect()->route('admin.nav.index')->with('success', 'Élément supprimé.');
    }

    public function reorder(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        foreach ($request->ids as $pos => $id) {
            NavItem::where('id', $id)->update(['position' => $pos]);
        }
        return response()->json(['ok' => true]);
    }

    private function validate(Request $request): array
    {
        $type = $request->input('type', 'external');
        $data = $request->validate([
            'label'      => 'required|string|max:60',
            'type'       => 'required|in:external,home,reservations,my_reservations,profile',
            'url'        => [$type === 'external' ? 'required' : 'nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail) use ($type) {
                if ($type === 'external' && ! NavItem::isSafeUrl($value)) {
                    $fail("L'adresse doit commencer par https://, http://, mailto:, tel: ou / (chemin du site).");
                }
            }],
            'icon'       => 'nullable|string|max:60',
            'target'     => 'required|in:_self,_blank',
            'is_active'  => 'boolean',
            'admin_only' => 'boolean',
        ]);

        $data['icon']       = ($data['icon'] ?? null) ?: 'bi-link-45deg';
        $data['is_active']  = $request->boolean('is_active', true);
        $data['admin_only'] = $request->boolean('admin_only', false);

        return $data;
    }
}
