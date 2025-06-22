<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $organizations = Organization::query()
            ->with('team')
            ->whereHas('team', function ($query) use ($user) {
                $query->whereHas('users', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            })
            ->get();

        return OrganizationResource::collection($organizations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrganizationRequest $request)
    {
        $data = $request->validated();
        $user = Auth::user();

        // Create a new team for the organization
        $team = Team::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'] . ' Team',
            'owner_id' => $user->id,
            'is_active' => true
        ]);

        // Attach the team to the user
        $team->users()->attach($user->id, ['id' => (string) Str::uuid(), 'role' => 'owner']);

        // Add the team_id to the organization data
        $data['team_id'] = $team->id;
        $organization = Organization::create($data);

        return new OrganizationResource($organization->load('team'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization)
    {
        // Use Gate facade for authorization
        if (Gate::denies('view', $organization)) {
            abort(403, 'Unauthorized action.');
        }

        return new OrganizationResource($organization->load('team'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization)
    {
        // Use Gate facade for authorization
        if (Gate::denies('update', $organization)) {
            abort(403, 'Unauthorized action.');
        }

        $data = $request->validated();
        $organization->update($data);

        return new OrganizationResource($organization->load('team'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization)
    {
        // Use Gate facade for authorization
        if (Gate::denies('delete', $organization)) {
            abort(403, 'Unauthorized action.');
        }

        $organization->delete();

        return response()->json(['message' => 'Organization deleted successfully']);
    }
}
