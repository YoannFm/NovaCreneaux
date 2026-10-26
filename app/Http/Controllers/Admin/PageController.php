<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $data = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'slug'        => 'required|string|max:200|unique:pages,slug',
            'content'     => 'required|string',
        ]);

        $data['slug']          = Str::slug($data['slug']);
        $data['is_enabled']    = $request->boolean('is_enabled');
        $data['is_restricted'] = $request->boolean('restricted');

        $page = Page::create($data);
        $page->roles()->sync($request->input('roles', []));

        LogHelper::log('created_page', 'page', $page->id, ['title' => $page->title]);
        return redirect()->route('admin.pages.index')->with('success', 'Page creee.');
    }

    public function edit(Page $page)
    {
        $roles = Role::orderBy('power', 'desc')->get();
        $page->load('roles');
        return view('admin.pages.edit', ['page' => $page, 'roles' => $roles, 'pageTitle' => 'Modifier la page']);
    }

    public function update(Request $request, Page $page)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'slug'        => 'required|string|max:200|unique:pages,slug,' . $page->id,
            'content'     => 'required|string',
        ]);

        $data['slug']          = Str::slug($data['slug']);
        $data['is_enabled']    = $request->boolean('is_enabled');
        $data['is_restricted'] = $request->boolean('restricted');

        $page->update($data);
        $page->roles()->sync($request->input('roles', []));

        LogHelper::log('updated_page', 'page', $page->id, ['title' => $page->title]);
        return redirect()->route('admin.pages.index')->with('success', 'Page mise a jour.');
    }

    public function destroy(Page $page)
    {
        LogHelper::log('deleted_page', 'page', $page->id, ['title' => $page->title]);
        $page->delete();
        return redirect()->route('admin.pages.index')->with('success', 'Page supprimee.');
    }
}
