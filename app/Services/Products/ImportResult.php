<?php

namespace App\Services\Products;

class ImportResult
{
    public int $created = 0;

    public int $updated = 0;

    public int $unchanged = 0;

    /** @var array<int, string> */
    public array $categoriesCreated = [];

    /** @var array<int, string> problems that stopped the import (nothing was saved) */
    public array $errors = [];

    /** @var array<int, string> things worth knowing; the import still happened */
    public array $warnings = [];

    public function failed(): bool
    {
        return $this->errors !== [];
    }

    public function summary(): string
    {
        $parts = ["{$this->created} new", "{$this->updated} updated"];
        if ($this->unchanged) {
            $parts[] = "{$this->unchanged} already up to date";
        }
        if ($this->categoriesCreated) {
            $parts[] = 'new categories: '.implode(', ', array_unique($this->categoriesCreated));
        }

        return implode(' · ', $parts);
    }
}
