<?php

namespace App\Http\Requests\TeamResources;

use App\Models\UserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class PublishTeamResourceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Authorization parameters.
        $isLoggedIn = Auth::check();
        $belongsToAuthoringTeam = Auth::user()->isPartOfAuthoringTeam($this->resource->id);
        $isTeamOwner = $this->team->owner_id === Auth::user()->id;
        $repositoryBelongsToUser = UserRepository::where('user_id', Auth::user()->id)
            ->where('id', $this->repository_id)
            ->exists();

        return $isLoggedIn && ($belongsToAuthoringTeam || $isTeamOwner) && $repositoryBelongsToUser;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'repository_id' => 'required|exists:user_repositories,id',
            'collection' => 'nullable|string'
        ];
    }
}
