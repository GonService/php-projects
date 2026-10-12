<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /** Defaults mirrored from the migration, so new models serialize them before a refresh. */
    protected $attributes = [
        'done' => false,
    ];

    protected $fillable = [
        'title',
        'description',
        'done',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'done' => 'boolean',
            'due_date' => 'date',
        ];
    }

    /**
     * Filter by completion when the "done" query parameter is present.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeDone(Builder $query, ?bool $done): void
    {
        if ($done !== null) {
            $query->where('done', $done);
        }
    }
}
