<?php

namespace App\Serializer\Normalizer\RelationNormalizer;

use App\Serializer\Normalizer\CollectionNormalizer\DoctrineCollectionNormalizer;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Exclude]
class DoctrineRelationNormalizer implements NormalizerInterface
{
    public const RELATION_NORMALIZE_MODE = 'relation_normalize_mode';

    private ClassMetadataFactory $metaFactory;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly array                  $defaultContext,
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface    $objectNormalizer,
        #[Autowire(service: 'serializer.normalizer.collection')]
        private readonly NormalizerInterface    $collectionNormalizer
    )
    {
        $this->metaFactory = $this->entityManager->getMetadataFactory();
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => false
        ];
    }

    /**
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        $mergedContext = array_merge($this->defaultContext, $context);
        $mode = $this->getRelationNormalizeMode($mergedContext[self::RELATION_NORMALIZE_MODE]) ?? RelationNormalizeMode::DISABLED;
        return $this->isEntity($data)
            && $this->haveRelation($data)
            && $mode != RelationNormalizeMode::DISABLED
            && !isset($mergedContext[AbstractNormalizer::ATTRIBUTES])
            && !isset($mergedContext[AbstractNormalizer::IGNORED_ATTRIBUTES]);
        //&& !isset($mergedContext[AbstractNormalizer::GROUPS]);
    }

    /**
     * @throws ExceptionInterface
     * @throws \ReflectionException
     * @throws MappingException
     */
    public function normalize($object, string $format = null, array $context = []): array
    {
        $mergedContext = array_merge($this->defaultContext, $context);

        $meta = $this->metaFactory->getMetadataFor($object::class);
        $relations = $meta->getAssociationNames();

        $normalized = $this->normalizeOnlySelf($object, context: $mergedContext);

        $relationNormalizations = [];
        foreach ($relations as $relationName) {
            $relationEntity = $meta->getFieldValue($object, $relationName);
            if ($relationEntity instanceof Collection) {
                $relationNormalizations[$relationName] = $this->collectionNormalizer->normalize($object, $format, $mergedContext);
            } else {
                $relationNormalizations[$relationName] = match ($this->getRelationNormalizeMode($mergedContext[self::RELATION_NORMALIZE_MODE])) {
                    RelationNormalizeMode::IGNORED => null,
                    RelationNormalizeMode::ONLY_ID => $this->normalizeOnlyIdentifiers($relationEntity, context: $mergedContext),
                    RelationNormalizeMode::ONLY_SELF => $this->normalizeOnlySelf($relationEntity, context: $mergedContext),
                    RelationNormalizeMode::ONLY_SELF_WITH_COLLECTIONS => $this->normalizeWithCollections($relationEntity, context: $mergedContext)
                };
            }

        }


        return array_merge($normalized, $relationNormalizations);
    }

    private function getRelationNormalizeMode(string|RelationNormalizeMode $mode): ?RelationNormalizeMode
    {
        return $mode instanceof RelationNormalizeMode ? $mode : RelationNormalizeMode::from($mode);
    }

    /**
     *
     * @throws MappingException
     */
    private function isEntity(mixed $class): bool
    {
        if (!is_object($class)) {
            return false;
        }

        $class = ClassUtils::getClass($class);

        return !$this->entityManager->getMetadataFactory()->isTransient($class);
    }

    /**
     * @throws \ReflectionException
     * @throws MappingException
     */
    private function haveRelation(mixed $data): bool
    {
        $meta = $this->metaFactory->getMetadataFor($data::class);
        return count($meta->getAssociationNames()) > 0;
    }

    private function normalizeOnlyIdentifiers(mixed $object, ?string $format = null, array $context = []): array
    {
        return $this->entityManager->getClassMetadata($object::class)->getIdentifierValues($object);
    }


    /**
     * @throws ExceptionInterface
     */
    private function normalizeOnlySelf(mixed $object, ?string $format = null, array $context = []): array
    {
        $mergedContext = array_merge($this->defaultContext, $context);

        $meta = $this->entityManager->getClassMetadata($object::class);

        return $this->objectNormalizer->normalize($object, null, array_merge($mergedContext, [
            AbstractNormalizer::ATTRIBUTES => $meta->getFieldNames()
        ]));
    }

    /**
     * @throws ExceptionInterface
     */
    private function normalizeWithCollections(mixed $object, ?string $format = null, array $context = []): array
    {
        $mergedContext = array_merge($this->defaultContext, $context);

        $meta = $this->entityManager->getClassMetadata($object::class);

        $self = $this->normalizeOnlySelf($object, $format, $mergedContext);

        $collectionFields = [];
        foreach ($meta->getAssociationMappings() as $fieldName => $associationMapping) {
            if ($associationMapping->isManyToMany() || $associationMapping->isOneToMany()) {
                $collectionFields[] = $fieldName;
            }
        }

        $collectionNormalizations = [];
        foreach ($collectionFields as $collectionField) {
            $relationEntity = $meta->getFieldValue($object, $collectionField);

            $this->entityManager->initializeObject($relationEntity);

            $collectionNormalizations[$collectionField] = $this->collectionNormalizer->normalize($relationEntity, $format, array_merge($mergedContext, [
                DoctrineRelationNormalizer::RELATION_NORMALIZE_MODE => RelationNormalizeMode::DISABLED,
                DoctrineCollectionNormalizer::COLLECTION_NORMALIZE_MODE => $mergedContext[DoctrineCollectionNormalizer::COLLECTION_NORMALIZE_MODE],
            ]));
        }

        return array_merge($self, $collectionNormalizations);
    }

}
