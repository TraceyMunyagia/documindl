<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * When a user is signed in, every query on a business-owned model is
 * limited to their current business. With no signed-in user (seeders,
 * Tinker, scheduled jobs) nothing is filtered, so those must scope explicitly.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
class BusinessScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId !== null) {
            $builder->where($model->qualifyColumn('business_id'), $businessId);
        }
    }
}
