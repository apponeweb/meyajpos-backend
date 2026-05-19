<?php

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;

class PlatformAwareQuoteStrategy extends DefaultQuoteStrategy
{
    public function getTableName(ClassMetadata $class, AbstractPlatform $platform): string
    {
        $tableName = parent::getTableName($class, $platform);

        if ($tableName === 'user') {
            return $platform->quoteSingleIdentifier($tableName);
        }

        return $tableName;
    }
}
