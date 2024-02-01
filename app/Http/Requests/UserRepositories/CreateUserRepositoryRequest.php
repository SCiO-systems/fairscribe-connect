<?php

namespace App\Http\Requests\UserRepositories;

use Auth;
use App\Enums\RepositoryType;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreateUserRepositoryRequest extends FormRequest
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
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'string|required',
            'type' => Rule::in(RepositoryType::getValues()),
            'client_secret' => 'string|required',
            'api_endpoint' => 'url|required',
            'metadata' => 'json|nullable',
        ];
    }
}
