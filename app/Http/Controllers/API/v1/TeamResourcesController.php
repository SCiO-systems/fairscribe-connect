<?php

namespace App\Http\Controllers\API\v1;

use DB;
use Auth;
use Cache;
use Exception;
use App\Models\Team;
use App\Enums\PIIStatus;
use App\Models\Resource;
use App\Enums\ResourceStatus;
use App\Models\UserRepository;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\v1\TeamResourceResource;
use App\Http\Resources\v1\SingleResourceResource;
use App\Services\FairScoring\Facades\FairScoring;
use App\Utilities\Repositories\Clients\DataverseClient;
use App\Utilities\Repositories\Mappers\DataverseMapper;
use App\Http\Requests\TeamResources\ListTeamResourcesRequest;
use App\Http\Requests\TeamResources\CreateTeamResourceRequest;
use App\Http\Requests\TeamResources\DeleteTeamResourceRequest;
use App\Http\Requests\TeamResources\UpdateTeamResourceRequest;
use App\Http\Requests\TeamResources\PublishTeamResourceRequest;
use App\Http\Requests\TeamResources\GetSingleTeamResourceRequest;
use Storage;

class TeamResourcesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(ListTeamResourcesRequest $request, Team $team)
    {
        $userId = Auth::user()->id;
        $resources = $team->resources()->with('repositories');

        // TODO: Refactor this.
        if (!empty($request->status)) {

            $resourceIds = [];

            if ($request->status === ResourceStatus::UNDER_PREPARATION) {
                $resourceIds = DB::table('resource_authors')
                    ->where('user_id', $userId)
                    ->pluck('resource_id');
            }

            if ($request->status === ResourceStatus::UNDER_REVIEW) {
                $resourceIds = DB::table('resource_reviewers')
                    ->where('user_id', $userId)
                    ->pluck('resource_id');
            }

            $resources = $resources->where('status', $request->status);

            if (!empty($resourceIds)) {
                $resources = $resources->whereIn('id', $resourceIds);
            }
        }

        $resources = $resources->paginate();

        return TeamResourceResource::collection($resources);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateTeamResourceRequest $request, Team $team)
    {
        // Get the author.
        $authorId = Auth::user()->id;

        // Team leader is added in both the review as well as the authoring team.
        // The teams are filtered by checking if the members belong to the team.
        $authoringTeam = $team->users()
            ->whereIn('user_id', $request->authoring_team)
            ->pluck('user_id');

        // Add the team leader and author in the authoring team.
        $authoringTeam->push($team->owner_id, $authorId);

        // The team leader should be in the review team as well.
        $reviewTeam = $team->users()
            ->whereIn('user_id', $request->review_team)
            ->pluck('user_id');

        $reviewTeam->push($team->owner_id);

        // The collections that this resource will belong to.
        // The collections are filtered using the team collections.
        $collections = [];
        if (!empty($request->collections)) {
            $collections = $team->collections()->whereIn('id', $request->collections)->pluck('id');
        }

        // Create the resource with proper status.
        $resource = Resource::create([
            'title' => $request->title,
            'team_id' => $team->id,
            'description' => $request->description,
            'type' => $request->type,
            'subtype' => $request->subtype,
            'status' => ResourceStatus::UNDER_PREPARATION,
            'author_id' => $authorId,
            'version' => 1
        ]);

        // Set the collections for a resource.
        if (!empty($request->collections)) {
            $resource->setCollections($collections);
        }

        // Set review team with team owner.
        $resource->setReviewTeam($reviewTeam);

        // Set the authoring team with team owner and author.
        $resource->setAuthoringTeam($authoringTeam);

        return new SingleResourceResource($resource);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(GetSingleTeamResourceRequest $request, Team $team, Resource $resource)
    {
        return new SingleResourceResource($resource);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateTeamResourceRequest $request, Team $team, Resource $resource)
    {
        // The collections that this resource will belong to.
        // The collections are filtered using the team collections.
        $collections = [];
        if (!empty($request->collections)) {
            $collections = $team->collections()->whereIn('id', $request->collections)->pluck('id');
        }

        $resource->setCollections($collections);

        if (!empty($request->metadata_record)) {
            $resource->setOrCreateMetadataRecord($request->metadata_record);
        }

        // Validate the data when the request status is under review.
        if ($request->status === ResourceStatus::UNDER_REVIEW && $resource->type === 'dataset') {

            // The mapper.
            $mapper = new DataverseMapper($resource->getMetadataRecord());

            // Validate the metadata.
            try {
                $mapper->validate();
            } catch (Exception $ex) {
                return response()->json([
                    'errors' => [
                        'error' => [$ex->getMessage()]
                    ]
                ], 422);
            }
        }

        if (!empty($request->status)) {
            try {
                $resource->changeStatus($request->status);
            } catch (Exception $ex) {
                return response()->json(['errors' => [
                    'error' => $ex->getMessage()
                ]], 400);
            }
        }

        // Check if files exist and their PII check status has been updated.
        if ($resource->files()->count() > 0) {
            foreach ($resource->files()->get() as $file) {
                $status = $file->pii_check_status;
                $file->setPIIStatus($status);
            }
        }

        $fairScoreService = FairScoring::for($resource)->getResult();
        $resource->findable_score = $fairScoreService['findable']['score'];
        $resource->accessible_score = $fairScoreService['accessible']['score'];
        $resource->interoperable_score = $fairScoreService['interoperable']['score'];
        $resource->reusable_score = $fairScoreService['reusable']['score'];

        $resource->save();

        return new SingleResourceResource($resource);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(DeleteTeamResourceRequest $request, Team $team, Resource $resource)
    {
        $deletableStatuses = [
            ResourceStatus::DRAFT,
            ResourceStatus::UNDER_PREPARATION,
            ResourceStatus::UNDER_REVIEW
        ];

        if (!in_array($resource->status, $deletableStatuses)) {
            return response()->json([
                'errors' => [
                    'error' => [
                        'The resource is not in a deletable status. Valid deletable statuses are: ' . implode(",", $deletableStatuses)
                    ]
                ]
            ], 422);
        }

        $resource->deleteMetadataRecord();

        if ($resource->delete()) {
            return response()->json([], 204);
        }

        return response()->json(['errors' => [
            'error' => 'The resource could not be deleted!'
        ]], 400);
    }

    /**
     * Calculate FairScoring for the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function calculateFairScore(
        GetSingleTeamResourceRequest $request,
        Team $team,
        Resource $resource
    ) {
        $fairScoreService = FairScoring::for($resource);

        return response()->json($fairScoreService->getResult(), 200);
    }

    /**
     * Get the PII status for each for each of the files.
     */
    public function getPIIStatus(
        GetSingleTeamResourceRequest $request,
        Team $team,
        Resource $resource
    ) {
        $cacheKey = "pii_status_$resource->id";

        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey), 200);
        }

        $files = $resource->files()->get()->map(function ($file) {
            return ["$file->id" => "$file->pii_check_status"];
        });

        Cache::put($cacheKey, $files, env('CACHE_TTL_SECONDS'));

        return response()->json($files, 200);
    }

    /**
     * Publish the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function publish(PublishTeamResourceRequest $request, Team $team, Resource $resource)
    {
        // TODO: Refactor this as a method inside the resource model.
        $piiFailingFilesCount = $resource->files()->where('pii_check_status', PIIStatus::FAILED)
            ->whereNull('pii_terms_accepted_at')
            ->count();

        if ($piiFailingFilesCount > 0) {
            return response()->json([
                'errors' => [
                    'error' => ['The resource cannot be published because it has files that have not passed PII check and do not have any terms accepted.']
                ]
            ], 422);
        }

        // Check if the repository exists.
        $repository = UserRepository::findOrFail($request->repository_id);

        // Check the connection.
        if (!$repository->verifyConnection()) {
            return response()->json([
                'errors' => [
                    'error' => [
                        'The connection with the specified repository cannot be verified. Please try again later.'
                    ]
                ]
            ], 422);
        }

        // Publish the resource to the relevant repository.
        $metadataRecord = $resource->getMetadataRecord();

        // Get the metadata record file IDs.
        $fileIds = [];
        if (!empty($metadataRecord["resource_files"])) {
            foreach ($metadataRecord["resource_files"] as $file) {
                $fileIds[] = $file["id"];
            }
        }

        // Check if the resource has already been published in a repository.
        $published = $resource->repositories()
            ->where('repository_id', $repository->id)
            ->where('collection', $request->collection)
            ->first();

        // The client.
        $client = new DataverseClient($repository->api_endpoint, $repository->client_secret);

        // The mapper.
        $mapper = new DataverseMapper($metadataRecord);

        // Validate the metadata.
        try {
            $mapper->validate();
        } catch (Exception $ex) {
            return response()->json([
                'errors' => [
                    'error' => [$ex->getMessage()]
                ]
            ], 422);
        }

        // Get the schema for the mapper
        $schema = $mapper->getSchema(false);

        $id = null;
        $persistentId = null;

        // Check if the resource has already been published.
        if ($published) {
            // Update the resource.
            try {
                $schema = $mapper->getSchemaForUpdate(false);
                $metadata = json_decode($published->pivot->metadata);
                $persistentId = $metadata->data->persistentId
                    ?? $metadata->data->datasetPersistentId;
                Log::info("Deleting dataset previous files to replace with new ones.");
                $client->deleteDatasetFiles($persistentId);
                $response = $client->updateResource($persistentId, $schema);
                Log::info(
                    "Resource {$resource->id} updated for {$repository->name}.",
                    ['response' => $response]
                );
                $published->pivot->delete();
                $resource->repositories()->attach($repository, [
                    'metadata' => json_encode($response),
                    'collection' => $request->collection,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $id = $response['data']['id'];
                $persistentId = $response['data']['datasetPersistentId'];

                $resource->publish();

                Log::info("Updated resource $persistentId in Dataverse with new metadata.");
            } catch (Exception $ex) {
                // TODO: Fix this.
                $statusCode = (int) $ex->getCode();
                if ($statusCode === 404) {
                    $published->pivot->delete();
                    return response()->json([
                        'errors' => [
                            'error' => [
                                'The resource could not be updated, please try again.'
                            ]
                        ]
                    ], 422);
                }
                if ($statusCode === 400 || $statusCode === 500) {
                    $resource->status = ResourceStatus::UNDER_PREPARATION;
                    $resource->save();
                }
                return response()->json([
                    'errors' => [
                        'error' => [
                            'Failed to import the resource in the remote repository.' .
                                $ex->getMessage()
                        ]
                    ]
                ], 422);
            }
        } else {
            // Create the resource.
            try {
                $response = $client->createResource($schema, $request->collection);
                Log::info(
                    "Resource {$resource->id} published to {$repository->name}.",
                    ['response' => $response]
                );
                $id = $response['data']['id'];
                $persistentId = $response['data']['persistentId'];
            } catch (Exception $ex) {
                $statusCode = (int) $ex->getCode();
                if ($statusCode === 400 || $statusCode === 500) {
                    $resource->status = ResourceStatus::UNDER_PREPARATION;
                    $resource->save();
                }
                $client->deleteResource($id, $request->collection);
                return response()->json([
                    'errors' => [
                        'error' => [
                            'Failed to create the resource in the remote repository.' .
                                $ex->getMessage()
                        ]
                    ]
                ], 422);
            }
        }

        // Get the files of the resource.
        $files = $resource->files()->get();

        if (!empty($files)) {
            // Upload the files for the resource.
            foreach ($files as $file) {
                if (!in_array($file->id, $fileIds)) {
                    continue;
                }

                $contents = Storage::disk('s3')->get($file->path);
                $mimeType = Storage::disk('s3')->mimeType($file->path);
                $size = Storage::disk('s3')->size($file->path);
                $sha256sum = hash('sha1', $contents);
                $filename = $file->filename;

                // Upload the file.
                try {
                    $client->uploadNewFile(
                        $persistentId,
                        $filename,
                        $mimeType,
                        $contents,
                        $size,
                        $sha256sum
                    );
                } catch (Exception $ex) {
                    $client->deleteResource($id, $request->collection);
                    $statusCode = (int) $ex->getCode();
                    if ($statusCode === 400 || $statusCode === 500) {
                        $resource->status = ResourceStatus::UNDER_PREPARATION;
                        $resource->save();
                    }
                    return response()->json([
                        'errors' => [
                            'error' => [
                                'Failed to create new files for resource.' . $ex->getMessage()
                            ]
                        ]
                    ], 422);
                }
            }
        }

        if (!$published) {
            $resource->repositories()->attach($repository, [
                'metadata' => json_encode($response),
                'collection' => $request->collection,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $resource->publish();
        }


        return response()->json([], 204);
    }
}
