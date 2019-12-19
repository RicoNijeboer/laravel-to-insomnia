<?php

namespace Rico\Insomnia\Entities;

use Carbon\Carbon;
use JsonSerializable;
use stdClass;

/**
 * Class Insomnia
 *
 * @package Rico\Insomnia
 */
class Insomnia implements JsonSerializable
{

    /**
     * @var \stdClass
     */
    protected $properties;

    /**
     * @var \Illuminate\Support\Collection|InsomniaEntity[]
     */
    protected $resources;

    /**
     * Insomnia constructor.
     */
    public function __construct()
    {
        $this->resources = collect();
        $this->properties = new stdClass;

        $this->properties->_type = 'export';
        $this->properties->__export_format = 4;
        $this->properties->__export_date = Carbon::now()->toIso8601String();
        $this->properties->__export_source = 'rico/laravel-to-insomnia';
    }

    /**
     * @param string $name
     * @param string $description
     *
     * @return \Rico\Insomnia\Entities\InsomniaEntity
     */
    public function workspace(string $name, string $description = ''): InsomniaEntity
    {
        $entity = new InsomniaWorkspace(null, InsomniaEntity::INSOMNIA_WORKSPACE, $name, $description);

        $entity->setWorkspace($this);

        return $entity;
    }

    /**
     * @param \Rico\Insomnia\Entities\InsomniaEntity $param
     */
    public function join(InsomniaEntity $param)
    {
        $this->resources->push($param);
    }

    /**
     * @return \stdClass
     */
    public function jsonSerialize(): stdClass
    {
        $properties = $this->properties;
        $properties->resources = $this->resources->map(function (InsomniaEntity $entity) {
            return $entity->jsonSerialize();
        });

        return $properties;
    }
}