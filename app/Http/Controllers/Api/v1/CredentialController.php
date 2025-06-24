<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Credential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CredentialController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        return Credential::where('user_id', Auth::id())
            ->orWhere('team_id', Auth::user()->current_team_id)
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'provider' => 'required',
            'type' => 'required|in:oauth2,api_key,basic',
            'name' => 'required',
            'data' => 'required|array',
            'meta' => 'nullable|array'
        ]);

        return Credential::create([
            'user_id' => Auth::id(),
            'provider' => $data['provider'],
            'type' => $data['type'],
            'name' => $data['name'],
            'data' => $data['data'],
            'meta' => $data['meta'] ?? []
        ]);
    }

    public function destroy($id)
    {
        $credential = Credential::findOrFail($id);
        $this->authorize('delete', $credential);
        $credential->delete();
        return response()->noContent();
    }
}
