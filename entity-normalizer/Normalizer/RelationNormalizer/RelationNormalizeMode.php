<?php

namespace App\Serializer\Normalizer\RelationNormalizer;

enum RelationNormalizeMode: string
{
    case DISABLED = 'DISABLED';
    case ONLY_ID = 'ONLY_ID';
    case ONLY_SELF = 'ONLY_SELF';
    case ONLY_SELF_WITH_COLLECTIONS = 'ONLY_SELF_WIC';
    case IGNORED = 'IGNORED';
}