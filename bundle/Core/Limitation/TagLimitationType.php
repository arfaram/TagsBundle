<?php

namespace Netgen\TagsBundle\Core\Limitation;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Exceptions\NotImplementedException;
use eZ\Publish\API\Repository\Values\User\Limitation as APILimitationValue;
use eZ\Publish\API\Repository\Values\User\UserReference;
use eZ\Publish\API\Repository\Values\ValueObject;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentType;
use eZ\Publish\Core\FieldType\ValidationError;
use eZ\Publish\Core\Limitation\AbstractPersistenceLimitationType;
use eZ\Publish\SPI\Limitation\Type as SPILimitationTypeInterface;
use eZ\Publish\SPI\Persistence\Handler as SPIPersistenceHandler;
use Netgen\TagsBundle\API\Repository\Values\Content\Query\Criterion\TagId;
use Netgen\TagsBundle\API\Repository\Values\Tags\Tag;
use Netgen\TagsBundle\API\Repository\Values\User\Limitation\TagLimitation as APITagLimitation;
use Netgen\TagsBundle\SPI\Persistence\Tags\Handler as SPITagsPersistenceHandler;
use RuntimeException;

class TagLimitationType extends AbstractPersistenceLimitationType implements SPILimitationTypeInterface
{
    /**
     * @var \Netgen\TagsBundle\SPI\Persistence\Tags\Handler
     */
    private $tagsPersistence;

    public function __construct(SPIPersistenceHandler $persistence, SPITagsPersistenceHandler $tagsPersistence)
    {
        parent::__construct($persistence);

        $this->tagsPersistence = $tagsPersistence;
    }

    public function acceptValue(APILimitationValue $limitationValue)
    {
        if (!$limitationValue instanceof APITagLimitation) {
            throw new InvalidArgumentType('$limitationValue', 'TagLimitation', $limitationValue);
        }

        if (!is_array($limitationValue->limitationValues)) {
            throw new InvalidArgumentType('$limitationValue->limitationValues', 'array', $limitationValue->limitationValues);
        }

        foreach ($limitationValue->limitationValues as $key => $value) {
            if (!is_int($value) && !ctype_digit($value)) {
                throw new InvalidArgumentType("\$limitationValue->limitationValues[{$key}]", 'int', $value);
            }
        }
    }

    public function validate(APILimitationValue $limitationValue)
    {
        $validationErrors = [];

        foreach ($limitationValue->limitationValues as $key => $id) {
            try {
                $this->tagsPersistence->loadTagInfo($id);
            } catch (NotFoundException $e) {
                $validationErrors[] = new ValidationError(
                    "limitationValues[%key%] => '%value%' does not exist in the backend",
                    null,
                    [
                        'value' => $id,
                        'key' => $key,
                    ]
                );
            }
        }

        return $validationErrors;
    }

    public function buildValue(array $limitationValues)
    {
        return new APITagLimitation(['limitationValues' => $limitationValues]);
    }

    public function evaluate(APILimitationValue $value, UserReference $currentUser, ValueObject $object, array $targets = null)
    {
        if (!$value instanceof APITagLimitation) {
            throw new InvalidArgumentException('$value', 'Must be of type: TagLimitation');
        }

        if (!$object instanceof Tag) {
            throw new InvalidArgumentException('$object', 'Must be of type: Tag');
        }

        if (count($value->limitationValues ?? []) === 0) {
            return false;
        }

        $limitationValues = array_map(
            static function ($value): int {
                return (int) $value;
            },
            $value->limitationValues
        );

        return in_array($object->id, $limitationValues, true);
    }

    public function getCriterion(APILimitationValue $value, UserReference $currentUser)
    {
        if (count($value->limitationValues ?? []) === 0) {
            // no limitation values
            throw new RuntimeException('$value->limitationValues is empty, it should not have been stored in the first place');
        }

        if (!isset($value->limitationValues[1])) {
            // 1 limitation value: EQ operation
            return new TagId($value->limitationValues[0]);
        }

        // several limitation values: IN operation
        return new TagId($value->limitationValues);
    }

    public function valueSchema()
    {
        throw new NotImplementedException(__METHOD__);
    }
}
