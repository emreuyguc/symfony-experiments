<?php

namespace App\Serializer\Normalizer\CollectionNormalizer;

enum CollectionNormalizeMode: string
{
    case DISABLED = 'DISABLED';
    case ONLY_ID = 'ONLY_ID';
    case WITHOUT_RELATIONS = 'WITHOUT_RELATIONS';
    case IGNORED = 'IGNORED';
}