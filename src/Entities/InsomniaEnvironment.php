<?php

namespace Rico\Insomnia\Entities;

use stdClass;

/**
 * Class InsomniaEnvironment
 *
 * @package Rico\Insomnia\Entities
 */
class InsomniaEnvironment extends InsomniaEntity
{

    /**
     * InsomniaRequest constructor.
     *
     * @param string|null $parentId
     * @param string      $name
     * @param string      $description
     */
    public function __construct(?string $parentId, string $name, string $description)
    {
        parent::__construct($parentId, self::INSOMNIA_ENVIRONMENT, $name, $description);

        $this->properties->color = '#' . str_pad(substr(dechex(mt_rand(0, 0xFFFFFF)), 0, 6), 6, '0', STR_PAD_LEFT);
        $this->properties->data = new stdClass;
        $this->properties->dataPropertyOrder = null;
        $this->properties->isPrivate = false;
    }

    /**
     * @param string $name
     * @param string $description
     *
     * @return \Rico\Insomnia\Entities\InsomniaEnvironment
     */
    public function environment(string $name, string $description): InsomniaEnvironment
    {
        return new self($this->properties->_id, $name, $description);
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function setData(string $key, $value): void
    {
        $this->properties->data->$key = $value;
    }
}