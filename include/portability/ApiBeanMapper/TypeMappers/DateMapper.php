<?php

/* @noinspection PhpIncludeInspection */
require_once 'include/portability/Services/DateTime/DateFormatService.php';
require_once 'include/portability/ApiBeanMapper/TypeMappers/TypeMapperInterface.php';

class DateMapper implements TypeMapperInterface
{

    /**
     * @var DateFormatService
     */
    protected $dateFormatService;

    public function __construct()
    {
        $this->dateFormatService = new DateFormatService();
    }

    /**
     * @inheritDoc
     */
    public static function getType(): string
    {
        return 'date';
    }

    /**
     * @inheritDoc
     */
    public function run(SugarBean $bean, array &$container, string $name, string $alternativeName = ''): void
    {
        $newName = $name;

        if (!empty($alternativeName)) {
            $newName = $alternativeName;
        }

        if (empty($bean->$name)) {
            $container[$newName] = '';

            return;
        }

        $dbDate = $this->dateFormatService->toDBDate($bean->$name);

        if (empty($dbDate)) {
            $container[$newName] = '';

            return;
        }

        $container[$newName] = $dbDate;
    }
}
