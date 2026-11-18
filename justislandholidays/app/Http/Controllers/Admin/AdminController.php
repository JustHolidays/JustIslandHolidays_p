<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AdminContext;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class AdminController extends Controller
{
    public const PER_PAGE = 25;

    private ?AdminContext $context = null;

    protected function ctx(): AdminContext
    {
        return $this->context ??= new AdminContext(request());
    }

    /**
     * Applies ?sort=column&dir=asc|desc from an allow-list. Filters and sort live in
     * the URL so a filtered view can be bookmarked or shared.
     *
     * @param  array<string, string|\Closure>  $columns  sort key => column (or closure taking the query and direction)
     */
    protected function sort(Builder $query, Request $request, array $columns, string $default, string $defaultDir = 'desc'): Builder
    {
        $key = $request->query('sort');
        $key = is_string($key) && array_key_exists($key, $columns) ? $key : $default;
        $dir = $request->query('dir') === 'asc' ? 'asc' : ($request->query('dir') === 'desc' ? 'desc' : $defaultDir);

        $column = $columns[$key];
        if ($column instanceof \Closure) {
            $column($query, $dir);
        } else {
            $query->orderBy($column, $dir);
        }

        return $query;
    }

    protected function reason(Request $request, string $field = 'reason'): string
    {
        return $request->validate([$field => ['required', 'string', 'min:3', 'max:500']], [
            "{$field}.required" => 'Please give a reason. It is sent to the person affected.',
        ])[$field];
    }

    /** Escapes a free-text search for LIKE. */
    protected function like(string $text): string
    {
        return '%'.addcslashes($text, '%_\\').'%';
    }
}
