<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Resources\Json\JsonResource;

class ResourceRepositoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'repository_id' => $this->id,
            'metadata' => $this->pivot->metadata,
            'collection' => $this->pivot->collection,
        ];
    }
}
