<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Shoe extends Model
{
    use HasUlids, LogsActivity;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'size',
        'price',
        'stock',
        'description',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'category_id',
                'brand_id',
                'name',
                'size',
                'price',
                'stock',
                'description',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('shoe')
            ->setDescriptionForEvent(fn (string $eventName) => "Shoe has been {$eventName}");
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
