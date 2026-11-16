<?php

declare(strict_types=1);

namespace App\Enums;

/** Matches the colours the x-ui.badge component knows how to draw. */
enum CategoryColor: string
{
    case Indigo = 'indigo';
    case Emerald = 'emerald';
    case Amber = 'amber';
    case Rose = 'rose';
    case Sky = 'sky';
    case Zinc = 'zinc';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function swatchClass(): string
    {
        return match ($this) {
            self::Indigo => 'bg-indigo-500',
            self::Emerald => 'bg-emerald-500',
            self::Amber => 'bg-amber-500',
            self::Rose => 'bg-rose-500',
            self::Sky => 'bg-sky-500',
            self::Zinc => 'bg-zinc-500',
        };
    }
}
