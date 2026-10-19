<?php

namespace App\Http\Requests;

class WebListingFilterRequest extends ListingFilterRequest
{
    protected function prepareForValidation(): void
    {
        $this->query->replace(array_filter($this->query->all(), fn ($value) => $value !== '' && $value !== null));
    }
}
