<?php

namespace App\Controller;

use Doctrine\Common\Annotations\AnnotationReader;
use Knp\Bundle\TimeBundle\DateTimeFormatter;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class WebSocketCoreController
{
    /**
     * Serialize $data according to the given serialization $group
     *
     * @param mixed $data The data that need to be serialized
     * @param string $group The serialization group
     * @param DateTimeFormatter $dateTimeFormatter Instance of DateTimeFomatter that will be used to format dates
     * @return string
     */
    protected static function serialize(mixed $data, string $group, DateTimeFormatter $dateTimeFormatter): string
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $dateCallback = function ($innerObject) use ($dateTimeFormatter) {
            return ($innerObject instanceof \DateTime && null !== $innerObject) ? $dateTimeFormatter->formatDiff($innerObject, new \DateTime()) : '';
        };
        
        $defaultContext = [
            AbstractNormalizer::CALLBACKS => [
                'createdAt' => $dateCallback,
                'updatedAt' => $dateCallback,
            ],
        ];

        $normalizer = new ObjectNormalizer($classMetadataFactory, defaultContext: $defaultContext);
        $encoder = new JsonEncoder();
        $serializer = new Serializer([$normalizer], [$encoder]);

        return $serializer->serialize($data, 'json', ['groups' => $group]);
    }
}
