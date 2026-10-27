<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogueBookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'description' => $this->description,
            'publisher' => $this->publisher,
            'published_year' => $this->published_year,
            'pages' => $this->pages,
            'is_available' => (bool) $this->is_available,
            'book_img' => $this->book_img,
            'category' => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null,
        ];
    }
}
