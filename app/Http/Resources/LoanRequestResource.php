<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'message' => $this->message,
            'admin_note' => $this->admin_note,
            'created_at' => $this->created_at,
            'reviewed_at' => $this->reviewed_at,
            'book' => new CatalogueBookResource($this->book),
            'checkout' => $this->checkout ? [
                'id' => $this->checkout->id,
                'return_date' => $this->checkout->return_date,
                'renewal_number' => $this->checkout->renewal_number,
            ] : null,
            'user' => $this->when($request->user()?->role === 'admin', fn () => [
                'id' => $this->user->id, 'name' => $this->user->name,
                'email' => $this->user->email, 'phone' => $this->user->phone,
            ]),
        ];
    }
}
