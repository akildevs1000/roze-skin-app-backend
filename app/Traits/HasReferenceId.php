<?php

namespace App\Traits;

trait HasReferenceId
{
    public function generateReferenceId(string $prefix = "INV"): string
    {
        return $prefix . "-" . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }
}
