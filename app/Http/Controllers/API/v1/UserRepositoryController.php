<?php

namespace App\Http\Controllers\API\v1;

use App\Models\UserRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\v1\UserRepositoryResource;
use App\Http\Requests\UserRepositories\VerifyUserRepositoryConnectionRequest;
use App\Http\Requests\UserRepositories\ListUserRepositoryRequest;
use App\Http\Requests\UserRepositories\ShowUserRepositoryRequest;
use App\Http\Requests\UserRepositories\CreateUserRepositoryRequest;
use App\Http\Requests\UserRepositories\DeleteUserRepositoryRequest;
use App\Http\Requests\UserRepositories\UpdateUserRepositoryRequest;
use App\Http\Requests\UserRepositories\ListAllUserRepositoriesRequest;

class UserRepositoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(ListUserRepositoryRequest $request)
    {
        $repositories = UserRepository::where('user_id', $request->user()->id)
            ->paginate();

        return UserRepositoryResource::collection($repositories);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateUserRepositoryRequest $request)
    {
        $repository = UserRepository::create([
            'name' => $request->name,
            'type' => $request->type,
            'api_endpoint' => rtrim($request->api_endpoint, '/'),
            'client_secret' => $request->client_secret,
            'metadata' => $request->metadata,
            'user_id' => $request->user()->id,
        ]);

        $repository->verifyConnection();

        return new UserRepositoryResource($repository);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(ShowUserRepositoryRequest $request, UserRepository $repository)
    {
        return new UserRepositoryResource($repository);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(
        UpdateUserRepositoryRequest $request,
        UserRepository $repository
    ) {
        // Filter null and falsy values.
        // TODO: Check for SQLi.
        $data = collect($request->all())->filter()->all();

        // Trim any trailing slashes from the URL.
        $data['api_endpoint'] = rtrim($data['api_endpoint'], '/');

        // Update the user details with the new ones.
        $repository->update($data);

        // Verify the connection.
        $repository->verifyConnection();

        return new UserRepositoryResource($repository);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(
        DeleteUserRepositoryRequest $request,
        UserRepository $repository
    ) {
        $repository->delete();

        return response()->json([], 204);
    }

    /**
     * Return all the user repositories.
     *
     * @return \Illuminate\Http\Response
     */
    public function all(ListAllUserRepositoriesRequest $request)
    {
        $all = UserRepository::where('user_id', $request->user()->id)->get();

        return UserRepositoryResource::collection($all);
    }

    /**
     * Return all the user repositories.
     *
     * @return \Illuminate\Http\Response
     */
    public function verifyConnection(
        VerifyUserRepositoryConnectionRequest $request,
        UserRepository $repository
    ) {
        // Verify the connection.
        $repository->verifyConnection();

        return new UserRepositoryResource($repository);
    }
}
