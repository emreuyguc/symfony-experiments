<?php

namespace App\Serializer\Normalizer\CollectionNormalizer;

use App\Serializer\Normalizer\RelationNormalizer\DoctrineRelationNormalizer;
use App\Serializer\Normalizer\RelationNormalizer\RelationNormalizeMode;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Exclude]
class DoctrineCollectionNormalizer implements NormalizerInterface,NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public const COLLECTION_NORMALIZE_MODE = 'collection_normalize_mode';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly array                  $defaultContext,

        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface    $objectNormalizer,
    )
    {
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => false
        ];
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        $mergedContext = array_merge($this->defaultContext, $context);
        $mode = $this->getCollectionNormalizeMode($mergedContext[self::COLLECTION_NORMALIZE_MODE]) ?? CollectionNormalizeMode::DISABLED;

        return ($data instanceof Collection) && $mode != CollectionNormalizeMode::DISABLED;
    }

    private function getCollectionNormalizeMode(string|CollectionNormalizeMode $mode): ?CollectionNormalizeMode
    {
        return $mode instanceof CollectionNormalizeMode ? $mode : CollectionNormalizeMode::from($mode);
    }

    public function normalize($object, string $format = null, array $context = []): ?array
    {
        $mergedContext = array_merge($this->defaultContext, $context);

        $object = match ($this->getCollectionNormalizeMode($mergedContext[self::COLLECTION_NORMALIZE_MODE])) {
            CollectionNormalizeMode::IGNORED, CollectionNormalizeMode::DISABLED => null,
            CollectionNormalizeMode::ONLY_ID => $this->normalizeOnlyIdentifiers($object, $format, $mergedContext),
            CollectionNormalizeMode::WITHOUT_RELATIONS => $this->normalizeWithoutRelations($object, $format, $mergedContext)
        };

        return $object;
    }


    private function normalizeOnlyIdentifiers(Collection $object, string $format = null, array $context = []): array
    {
        return $object->map(function ($obj) {
            return $this->entityManager->getClassMetadata($obj::class)->getIdentifierValues($obj);
        })->getValues();
    }

    private function normalizeWithoutRelations(Collection $object, string $format = null, array $context = []): array
    {
        $mergedContext = array_merge($this->defaultContext, $context);

        return $object->map(
        /**
         * @throws ExceptionInterface
         */
            function ($obj) use ($mergedContext, $format) {
                return $this->normalizer->normalize($obj, $format, array_merge($mergedContext, [
                    DoctrineRelationNormalizer::RELATION_NORMALIZE_MODE => RelationNormalizeMode::IGNORED,
                    DoctrineCollectionNormalizer::COLLECTION_NORMALIZE_MODE => CollectionNormalizeMode::IGNORED
                ]));
            })->getValues();
    }


}
