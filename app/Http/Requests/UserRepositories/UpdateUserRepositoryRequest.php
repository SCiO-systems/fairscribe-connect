<?php

namespace App\Http\Requests\UserRepositories;

use App\Enums\RepositoryType;
use Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRepositoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Validate that the user can access the user object
        // that they want to see (eg. themselves).
        $ownsRepository = Auth::user()->id === $this->repository->user_id;
        return $ownsRepository;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'nullable|string',
            'type' => Rule::in(RepositoryType::getValues()),
            'client_secret' => 'nullable|string',
            'metadata' => 'nullable|json',
            'api_endpoint' => 'nullable|url',
        ];
    }
}
