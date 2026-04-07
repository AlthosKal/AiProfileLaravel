<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * @phpstan-return View
     */
    public function index(): View
    {
        return view('client::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    /**
     * @phpstan-return View
     */
    public function create(): View
    {
        return view('client::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        // TODO: Implement store logic
        return redirect()->back();
    }

    /**
     * Show the specified resource.
     */
    /**
     * @phpstan-return View
     */
    public function show(string $id): View
    {
        return view('client::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    /**
     * @phpstan-return View
     */
    public function edit(string $id): View
    {
        return view('client::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        // TODO: Implement update logic
        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        // TODO: Implement destroy logic
        return redirect()->back();
    }
}
