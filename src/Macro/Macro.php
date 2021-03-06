<?php

namespace Macro\Macro;

use Cake\Core\InstanceConfigTrait;
use Cake\Datasource\ModelAwareTrait;
use Macro\Error\InvalidContextException;
use Macro\Error\MacroException;

abstract class Macro
{

    use ModelAwareTrait;
    use InstanceConfigTrait;

    protected $_defaultConfig = [
        'context' => null
    ];

    /**
     * The name of this Macro. Macro names are plural, named after the model they use.
     *
     * Set automatically using conventions in Macro::__construct().
     *
     * @var string
     */
    public $name = null;

    public function __construct(MacroRegistry $macroRegistry, array $config = [], $name = null)
    {
        if ($this->name === null && $name === null) {
            list(, $name) = namespaceSplit(get_class($this));
            $name = substr($name, 0, -5);
        }
        if ($name !== null) {
            $this->name = $name;
        }

        $this->config($config);

        $this->modelFactory('Table', ['Cake\ORM\TableRegistry', 'get']);
        $modelClass = ($this->plugin ? $this->plugin . '.' : '') . $this->name;
        $this->_setModelClass($modelClass);
    }


    /**
     * Magic accessor for model autoloading.
     *
     * @param string $name Property name
     * @return bool|object The model instance or false
     */
    public function __get($name)
    {
        list($plugin, $class) = pluginSplit($this->modelClass, true);
        if ($class !== $name) {
            return false;
        }
        return $this->loadModel($plugin . $class);
    }


    /**
     * @return null
     */
    public function run()
    {
    }

    public function context($context = null)
    {
        if ($context) {
            try {
                $this->_validateContext($context);
            }
            catch (InvalidContextException $exception) {
                throw $exception;
            }

            return $this->config('context', $context);
        }

        return $this->config('context');
    }

    /**
     * @param $context
     * @throws InvalidContextException
     * @return bool
     */
    protected function _validateContext($context)
    {
        return true;
    }

}
