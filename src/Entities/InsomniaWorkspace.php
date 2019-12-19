<?php


namespace Rico\Insomnia\Entities;

use Exception;

/**
 * Class InsomniaWorkspace
 *
 * @package Rico\Insomnia\Entities
 */
class InsomniaWorkspace extends InsomniaEntity
{

    /**
     * @param string $name
     * @param string $description
     *
     * @return \Rico\Insomnia\Entities\InsomniaEnvironment
     *
     * @throws \Exception
     */
    public function createEnvironment(string $name, string $description = ''): InsomniaEnvironment
    {
        if (in_array($this->properties->_type, [self::INSOMNIA_REQUEST, self::INSOMNIA_ENVIRONMENT]))
        {
            throw new Exception("This type can not accept a child of type 'folder'");
        }

        $entity = new InsomniaEnvironment($this->properties->_id, $name, $description);

        $entity->setWorkspace($this->workspace);

        return $entity;
    }
}