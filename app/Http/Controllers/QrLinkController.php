<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\QrLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class QrLinkController extends Controller
{
    /**
     * Public redirect: /qr-{slug} -> whatever target_url is currently configured.
     * Always a 302 (temporary) redirect — never 301 — since the whole point of this
     * feature is that a printed QR code's destination is expected to change over time;
     * a permanent redirect could get cached by browsers/scanners and stick to the old target.
     */
    public function redirect(string $slug)
    {
        $link = QrLink::where('slug', $slug)->where('is_active', true)->first();
        if (!$link) {
            abort(404);
        }

        $link->increment('click_count');

        return redirect($link->resolvedTargetUrl(), 302);
    }

    /**
     * Admin: list all QR links.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            abort(403, 'Unauthorized access.');
        }

        $qrLinks = QrLink::orderBy('created_at', 'desc')->get();
        $events = Event::orderBy('event_date', 'desc')->get();

        return view('admin.manage-qr-links', compact('qrLinks', 'events'));
    }

    /**
     * Admin: create a new QR link.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'label' => 'nullable|string|max:255',
            'slug' => 'required|string|max:100|regex:/^[A-Za-z0-9-]+$/|unique:qr_links,slug',
            'target_url' => 'required|string|max:2048',
            'is_active' => 'nullable|boolean',
        ]);
        $validated['slug'] = Str::slug($validated['slug']);
        $validated['is_active'] = $request->boolean('is_active');

        try {
            QrLink::create($validated);
            return redirect()->back()->with('success', 'QR link created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create QR link: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Admin: update an existing QR link (repoint it to a new target).
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $link = QrLink::findOrFail($id);

        $validated = $request->validate([
            'label' => 'nullable|string|max:255',
            'slug' => 'required|string|max:100|regex:/^[A-Za-z0-9-]+$/|unique:qr_links,slug,' . $link->id,
            'target_url' => 'required|string|max:2048',
            'is_active' => 'nullable|boolean',
        ]);
        $validated['slug'] = Str::slug($validated['slug']);
        $validated['is_active'] = $request->boolean('is_active');

        try {
            $link->update($validated);
            return redirect()->back()->with('success', 'QR link updated successfully. The printed QR code now points to the new target.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update QR link: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Admin: delete a QR link.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        try {
            QrLink::findOrFail($id)->delete();
            return redirect()->back()->with('success', 'QR link deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete QR link: ' . $e->getMessage());
        }
    }
}
