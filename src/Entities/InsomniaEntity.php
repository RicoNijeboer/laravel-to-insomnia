<?php

namespace Rico\Insomnia\Entities;

use Carbon\Carbon;
use Exception;
use JsonSerializable;
use stdClass;

/**
 * Class InsomniaEntity
 *
 * @package Rico\Insomnia\Entities
 */
class InsomniaEntity implements JsonSerializable
{

    public const INSOMNIA_REQUEST = 'request';
    public const INSOMNIA_WORKSPACE = 'workspace';
    public const INSOMNIA_FOLDER = 'request_group';
    public const INSOMNIA_ENVIRONMENT = 'environment';

    private const INSOMNIA_PREFIXES = [
        self::INSOMNIA_WORKSPACE   => 'wrk_',
        self::INSOMNIA_FOLDER      => 'fld_',
        self::INSOMNIA_REQUEST     => 'req_',
        self::INSOMNIA_ENVIRONMENT => 'env_',
    ];

    /**
     * @var \stdClass
     */
    protected $properties;

    /**
     * @var \Rico\Insomnia\Entities\Insomnia
     */
    protected $workspace;

    /**
     * InsomniaEntity constructor.
     *
     * @param string|null $parentId
     * @param string      $type
     * @param string      $name
     * @param string      $description
     */
    public function __construct(?string $parentId, string $type, string $name, string $description)
    {
        $this->properties = new stdClass;

        $this->properties->_id = generate_insomnia_id(self::INSOMNIA_PREFIXES[$type]);
        $this->properties->_type = $type;
        $this->properties->parentId = $parentId;
        $this->properties->name = $name;
        $this->properties->description = $description;
        $this->properties->created = Carbon::now()->unix();
        $this->properties->modified = Carbon::now()->unix();

        if ( ! is_null($parentId))
        {
            $this->properties->environment = new stdClass;
            $this->properties->environmentPropertyOrder = null;
            $this->properties->metaSortKey = 0;
        }
    }

    /**
     * @param string $name
     * @param string $description
     *
     * @return \Rico\Insomnia\Entities\InsomniaEntity
     *
     * @throws \Exception
     */
    public function folder(string $name, string $description = ''): InsomniaEntity
    {
        if (in_array($this->properties->_type, [self::INSOMNIA_REQUEST, self::INSOMNIA_ENVIRONMENT]))
        {
            throw new Exception("This type can not accept a child of type 'folder'");
        }

        $entity = new InsomniaEntity($this->properties->_id, self::INSOMNIA_FOLDER, $name, $description);

        $entity->setWorkspace($this->workspace);

        return $entity;
    }

    /**
     * @param string $name
     * @param string $description
     * @param string $url
     * @param string $method
     *
     * @return \Rico\Insomnia\Entities\InsomniaEntity
     *
     * @throws \Exception
     */
    public function request(string $name, string $description, string $url, string $method = InsomniaRequest::METHOD_GET): InsomniaEntity
    {
        if (in_array($this->properties->_type, [self::INSOMNIA_REQUEST, self::INSOMNIA_ENVIRONMENT]))
        {
            throw new Exception("This type can not accept a child of type 'request'");
        }

        $request = new InsomniaRequest($this->properties->_id, $name, $description, $url, $method);

        $request->setWorkspace($this->workspace);

        return $request;
    }

    /**
     * @param \Rico\Insomnia\Entities\Insomnia $workspace
     */
    public function setWorkspace(Insomnia &$workspace)
    {
        $this->workspace = $workspace;

        $workspace->join($this);
    }

    /**
     * @return stdClass
     */
    public function jsonSerialize(): stdClass
    {
        return $this->properties;
    }
}