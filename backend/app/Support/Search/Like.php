<?php

namespace App\Support\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * Case-insensitive substring matching (ARCHITECTURE D15), shared by the catalog and global search.
 * `%`, `_` and `\` in the term are escaped, so they match themselves.
 */
final class Like
{
    public static function pattern(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }

    /**
     * Rows where any of the columns contains the term.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @return Builder<TModel>
     */
    public static function anyColumn(Builder $query, array $columns, string $term): Builder
    {
        $like = self::pattern($term);

        return $query->where(function (Builder $q) use ($columns, $like) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', $like);
            }
        });
    }
}
