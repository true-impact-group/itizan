<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

#[Unguarded]
class Page extends Model
{
    use HasFactory, HasLocalizedAttributes, SoftDeletes;

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function title(): string
    {
        return (string) $this->localized('title');
    }

    /** Body is Markdown; raw HTML is stripped so editors cannot inject scripts. */
    public function renderedBody(): HtmlString
    {
        return new HtmlString(Str::markdown((string) $this->localized('body'), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }
}
