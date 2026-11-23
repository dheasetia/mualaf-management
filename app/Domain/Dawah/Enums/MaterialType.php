<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Enums;

enum MaterialType: string
{
    case Book = 'book';
    case Leaflet = 'leaflet';
    case Article = 'article';
    case Video = 'video';
    case Audio = 'audio';
    case Poster = 'poster';
    case Presentation = 'presentation';
    case Qr = 'qr';
    case Translation = 'translation';
    case Module = 'module';

    public function label(): string
    {
        return __('dawah.material_types.'.$this->value);
    }
}
