# Symfony Entity Normalizer
Symfony uygulamamız için iki özel normalizer sınıfı geliştirildi: `DoctrineRelationNormalizer` ve `DoctrineCollectionNormalizer`. Bu normalizerlar, ilişkisel verilerin ve koleksiyonların normalize edilme şeklini özelleştirmek için kullanılabilir.

## Kullanım

Öncelikle normalizer sınıflarını projenize dahil edin. Symfony varsayılan olarak bu sınıfları şu dizin altında oluşturacaktır: `src/Serializer/Normalizer`.

### Default Context Ayarları

İhtiyaçlarınıza göre normalizerlar için modları default context verisine tanımlayabilirsiniz. Bunun için `config/packages/framework.yaml` dosyasına aşağıdaki ayarları ekleyin:

```yaml
serializer:
    default_context:
        skip_null_values: true
        collection_normalize_mode: WITHOUT_RELATIONS
        relation_normalize_mode: ONLY_SELF
```

Bu tanımlamaya göre:

- Bir entity bir ilişkiye sahipse, ilişkinin sadece kendine ait, başka bir entity'e referans olmayan property'leri normalize edilecektir.
- Bir collection objesi geldiğinde, her eleman yukarıdaki ayarda olduğu gibi ilişkileri olmadan normalize edilecektir.
- `null` değerler çıktıya dahil edilmeyecektir.

### Normalizerları Servise Kayıt Etme

Normalizers'ları servise şu şekilde kayıt edebilirsiniz:

`config/services.yaml` dosyasına aşağıdaki tanımları ekleyin:

```yaml
serializer.normalizer.relation:
    class: 'App\Serializer\Normalizer\RelationNormalizer\DoctrineRelationNormalizer'
    tags:
        - { name: serializer.normalizer, priority: -1 }

serializer.normalizer.collection:
    class: 'App\Serializer\Normalizer\CollectionNormalizer\DoctrineCollectionNormalizer'
    tags:
        - { name: serializer.normalizer, priority: -2 }
```

### Normalizer Kullanımı

Normalizers'ları herhangi bir ek tanımlama yapmadan aşağıdaki şekilde kullanabilirsiniz:

```php
$entity = $this->service->read($id);
$normalizedData = $normalizer->normalize($entity);
```

### Modları Kapatma

Ayrıca, `framework.yaml` dosyasındaki ayar modunu `DISABLED` olarak seçerek normalizerları kapatabilirsiniz. Bu durumda sadece istediğiniz yerlerde context tanımlaması ile normalizerları kullanabilirsiniz. Örneğin:

```php
$entity = $this->service->read($id);
$normalizedData = $normalizer->normalize($entity, context: [
    DoctrineRelationNormalizer::RELATION_NORMALIZE_MODE => RelationNormalizeMode::ONLY_SELF
]);
```
---

# Daha Fazla Bilgi

Bu konu hakkında daha fazla bilgi edinmek ve detaylı inceleme için aşağıdaki Medium makalesini ziyaret edebilirsiniz:

[Symfony Entity Normalizasyonu: Entity Relation/Collection Normalizer](https://medium.com/@emreuyguc/symfony-custom-entity-normalizer-3f4477ed3feb)
