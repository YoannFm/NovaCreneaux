<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function index()
    {
        $pages = Page::latest()->paginate(25);
        return view('admin.pages.index', ['pages' => $pages, 'pageTitle' => 'Pages']);
    }

    public function create()
    {
        $roles = Role::orderBy('power', 'desc')->get();
        return view('admin.pages.create', ['roles' => $roles, 'pageTitle' => 'Nouvelle page']);
    }

    public function store(Request $request)
    {
        [$data, $roles] = $this->validated($request);
        DB::transaction(fn () => Page::create($data)->roles()->sync($roles));

        return redirect()->route('admin.pages.index')->with('success', 'Page créée.');
    }

    public function edit(Page $page)
    {
        $roles = Role::orderBy('power', 'desc')->get();
        $page->load('roles');
        return view('admin.pages.edit', ['page' => $page, 'roles' => $roles, 'pageTitle' => 'Modifier la page']);
    }

    public function update(Request $request, Page $page)
    {
        [$data, $roles] = $this->validated($request, $page);
        DB::transaction(function () use ($page, $data, $roles) {
            $page->update($data);
            $page->roles()->sync($roles);
        });

        return redirect()->route('admin.pages.index')->with('success', 'Page mise à jour.');
    }

    public function destroy(Page $page)
    {
        $page->delete();
        return redirect()->route('admin.pages.index')->with('success', 'Page supprimée.');
    }

    /**
     * Champs de la page et roles autorises. L'adresse est normalisee AVANT la validation : l'unicite et le format
     * portent sur l'adresse enregistree ("Règlement" et "reglement" sont la meme, "!!!" n'en donne aucune).
     *
     * @return array{0: array, 1: list<int>}
     */
    private function validated(Request $request, ?Page $page = null): array
    {
        if (is_string($request->input('slug'))) {
            $request->merge(['slug' => Str::slug($request->input('slug'))]);
        }

        $data = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'slug'        => ['required', 'string', 'max:200', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('pages', 'slug')->ignore($page)],
            'content'     => 'required|string',
            'roles'       => 'nullable|array',
            'roles.*'     => 'integer|exists:roles,id',
        ], [
            'slug.required' => "L'adresse doit contenir au moins une lettre ou un chiffre.",
            'slug.unique'   => 'Cette adresse est déjà utilisée par une autre page.',
        ]);

        $roles = array_map('intval', $data['roles'] ?? []);
        unset($data['roles']);
        $data['is_enabled']    = $request->boolean('is_enabled');
        $data['is_restricted'] = $request->boolean('restricted');

        return [$data, $roles];
    }
}
